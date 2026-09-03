(function () {
  "use strict";
  const app = document.getElementById("counterApp");
  if (!app || app.dataset.view === "history") return;
  const userId = app.dataset.userId;
  const soundStorageKey = `pdv_counter_${userId}_sound_enabled`;
  const state = { orders: [], tables: [], lastEventId: 0, knownNew: new Set(), initialized: false, soundEnabled: localStorage.getItem(soundStorageKey) === "1", settings: null };
  const statusLabels = { new: "Novo", accepted: "Aceito", preparing: "Em preparo", ready: "Pronto", delivered: "Entregue", cancelled: "Cancelado" };
  const tableLabels = { available: "Disponível", occupied: "Ocupada", waiting_order: "Aguardando pedido", bill_requested: "Conta solicitada" };
  const methodLabels = { cash: "Dinheiro", pix: "PIX", debit_card: "Cartão de débito", credit_card: "Cartão de crédito", other: "Outro" };

  function orderCard(order) {
    const next = order.status === "new" ? ["accepted", "Aceitar pedido", "btn-warning"] : order.status === "accepted" ? ["preparing", "Enviar para preparo", "btn-primary"] : order.status === "preparing" ? ["ready", "Marcar como pronto", "btn-success"] : ["delivered", "Marcar entregue", "btn-success"];
    return `<article class="order-card ${order.status}"><div class="order-head"><div><h4>Mesa ${PDV.escapeHtml(order.table_number)} · #${order.id}</h4><span class="order-meta">${PDV.escapeHtml(order.area_name || "")} · ${elapsed(order.created_at)}</span></div><span class="badge ${order.status === "new" ? "badge-warning" : order.status === "ready" ? "badge-success" : "badge-info"}">${statusLabels[order.status]}</span></div><span class="order-meta">Garçom: ${PDV.escapeHtml(order.waiter_name)}</span><div class="order-items">${order.items.filter((item) => item.status !== "cancelled").map((item) => `<div class="order-item"><strong>${item.quantity}× ${PDV.escapeHtml(item.product_name)}</strong>${item.modifiers?.length ? `<small>${item.modifiers.map((modifier) => PDV.escapeHtml(modifier.modifier_name)).join(", ")}</small>` : ""}${item.notes ? `<span class="order-note">${PDV.escapeHtml(item.notes)}</span>` : ""}<button class="btn btn-ghost btn-sm text-danger" data-cancel-item="${item.id}">Cancelar item</button></div>`).join("")}</div><div class="summary-row"><span>Total</span><strong>${PDV.money(order.total)}</strong></div><div class="order-actions"><button class="btn ${next[2]}" data-order-status="${next[0]}" data-order-id="${order.id}">${next[1]}</button><a class="btn btn-secondary btn-sm" href="/print/order.php?order_id=${order.id}" target="_blank">Imprimir cozinha</a><button class="btn btn-ghost btn-sm text-danger" data-cancel-order="${order.id}">Cancelar pedido</button></div></article>`;
  }

  function elapsed(value) {
    const seconds = Math.max(0, Math.floor((Date.now() - new Date(value.replace(" ", "T")).getTime()) / 1000));
    if (seconds < 60) return "Agora";
    const minutes = Math.floor(seconds / 60);
    return `há ${minutes} min`;
  }

  function renderOrders() {
    const groups = {
      new: state.orders.filter((order) => order.status === "new"),
      preparing: state.orders.filter((order) => ["accepted", "preparing"].includes(order.status)),
      ready: state.orders.filter((order) => order.status === "ready"),
    };
    document.getElementById("newOrders").innerHTML = groups.new.map(orderCard).join("") || empty("Nenhum pedido novo");
    document.getElementById("preparingOrders").innerHTML = groups.preparing.map(orderCard).join("") || empty("Nenhum pedido em preparo");
    document.getElementById("readyOrders").innerHTML = groups.ready.map(orderCard).join("") || empty("Nenhum pedido pronto");
    document.getElementById("newCount").textContent = groups.new.length;
    document.getElementById("preparingCount").textContent = groups.preparing.length;
    document.getElementById("readyCount").textContent = groups.ready.length;
    document.querySelectorAll("[data-new-order-count]").forEach((badge) => { badge.textContent = groups.new.length; badge.classList.toggle("hidden", groups.new.length === 0); });
  }
  function empty(text) { return `<div class="empty-state" style="padding:1.5rem .5rem"><p>${text}</p></div>`; }

  async function pollOrders(force = false) {
    try {
      const data = await PDV.request(`/api/orders/poll.php?since_event_id=${force ? 0 : state.lastEventId}`);
      state.lastEventId = data.last_event_id;
      if (!data.changed) return;
      const incomingNew = data.orders.filter((order) => order.status === "new");
      const unseen = incomingNew.filter((order) => !state.knownNew.has(order.id));
      if (state.initialized && unseen.length && state.soundEnabled) playAlert();
      incomingNew.forEach((order) => state.knownNew.add(order.id));
      state.initialized = true;
      state.orders = data.orders;
      renderOrders();
    } catch (error) { if (force) PDV.toast(error.message, "error"); }
  }

  async function changeStatus(orderId, status) {
    try { await PDV.request("/api/orders/status.php", { method: "POST", body: { order_id: orderId, status } }); await pollOrders(true); }
    catch (error) { PDV.toast(error.message, "error"); }
  }

  async function cancelOrder(orderId) {
    PDV.openModal({ title: `Cancelar pedido #${orderId}`, body: '<form id="cancelOrderForm"><label class="field"><span>Motivo do cancelamento</span><textarea name="reason" required maxlength="500" placeholder="Informe o motivo"></textarea></label></form>', footer: '<button class="btn btn-secondary" type="button" data-close-modal>Voltar</button><button class="btn btn-danger" id="confirmCancelOrder" type="button">Cancelar pedido</button>' });
    document.getElementById("confirmCancelOrder").onclick = async () => {
      const reason = document.getElementById("cancelOrderForm").elements.reason.value.trim();
      if (!reason) return PDV.toast("Informe o motivo.", "error");
      try { await PDV.request("/api/orders/cancel.php", { method: "POST", body: { order_id: orderId, reason } }); PDV.closeModal(); PDV.toast("Pedido cancelado."); await pollOrders(true); }
      catch (error) { PDV.toast(error.message, "error"); }
    };
  }

  async function cancelItem(itemId) {
    PDV.openModal({ title: "Cancelar item", body: '<form id="cancelItemForm"><label class="field"><span>Motivo do cancelamento</span><textarea name="reason" required maxlength="500"></textarea></label></form>', footer: '<button class="btn btn-secondary" type="button" data-close-modal>Voltar</button><button class="btn btn-danger" id="confirmCancelItem" type="button">Cancelar item</button>' });
    document.getElementById("confirmCancelItem").onclick = async () => {
      const reason = document.getElementById("cancelItemForm").elements.reason.value.trim();
      if (!reason) return PDV.toast("Informe o motivo.", "error");
      try { await PDV.request("/api/orders/cancel-item.php", { method: "POST", body: { item_id: itemId, reason } }); PDV.closeModal(); PDV.toast("Item cancelado."); await pollOrders(true); }
      catch (error) { PDV.toast(error.message, "error"); }
    };
  }

  function playAlert() {
    try {
      const context = new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = context.createOscillator(); const gain = context.createGain();
      oscillator.type = "sine"; oscillator.frequency.setValueAtTime(720, context.currentTime); oscillator.frequency.setValueAtTime(880, context.currentTime + .12);
      gain.gain.setValueAtTime(.0001, context.currentTime); gain.gain.exponentialRampToValueAtTime(.16, context.currentTime + .02); gain.gain.exponentialRampToValueAtTime(.0001, context.currentTime + .35);
      oscillator.connect(gain); gain.connect(context.destination); oscillator.start(); oscillator.stop(context.currentTime + .36);
    } catch { /* preferência visual continua ativa */ }
  }

  function configureSoundButton() {
    const button = document.getElementById("enableSound");
    if (!button) return;
    button.textContent = state.soundEnabled ? "Som dos pedidos ativo" : "Ativar som dos pedidos";
    button.classList.toggle("btn-success", state.soundEnabled);
    button.onclick = () => { state.soundEnabled = !state.soundEnabled; localStorage.setItem(soundStorageKey, state.soundEnabled ? "1" : "0"); button.textContent = state.soundEnabled ? "Som dos pedidos ativo" : "Ativar som dos pedidos"; button.classList.toggle("btn-success", state.soundEnabled); if (state.soundEnabled) playAlert(); };
  }

  async function loadTables() {
    const [tableData, sessionData] = await Promise.all([PDV.request("/api/tables/list.php"), PDV.request("/api/session.php")]);
    state.tables = tableData.tables; state.settings = sessionData.settings; renderTables();
  }
  function renderTables() {
    document.getElementById("counterTablesGrid").innerHTML = state.tables.map((table) => `<button class="table-card ${table.status}" data-counter-table="${table.id}"><span class="table-number">Mesa ${PDV.escapeHtml(table.number)}</span><span class="table-area">${PDV.escapeHtml(table.area_name || "Sem salão")}</span>${table.waiter_name ? `<small>${PDV.escapeHtml(table.waiter_name)} · ${table.order_count} pedido(s)</small>` : ""}<span class="badge ${table.status === "available" ? "badge-success" : table.status === "bill_requested" ? "badge-info" : "badge-warning"}">${PDV.escapeHtml(tableLabels[table.status] || table.status)}</span></button>`).join("");
  }

  async function openTable(tableId) {
    const table = state.tables.find((item) => item.id === tableId);
    if (table.status === "available") {
      PDV.openModal({ title: `Mesa ${table.number}`, body: '<div class="empty-state"><span class="status-icon">✓</span><h3>Mesa disponível</h3><p>A mesa ainda não possui atendimento aberto.</p></div>', footer: '<button class="btn btn-secondary" data-close-modal>Fechar</button>' });
      return;
    }
    try {
      const session = await PDV.request(`/api/tables/details.php?table_id=${tableId}`);
      const body = `<div class="summary-list"><div class="summary-row"><span>Aberta às</span><strong>${PDV.dateTime(session.opened_at)}</strong></div><div class="summary-row"><span>Garçom</span><strong>${PDV.escapeHtml(session.waiter_name)}</strong></div></div><h3 style="margin-top:1.2rem">Pedidos</h3>${session.orders.map((order) => `<article class="order-item"><div class="summary-row"><strong>Pedido #${order.id}</strong><span>${PDV.money(order.total)}</span></div>${order.items.map((item) => `<div class="summary-row"><span>${item.quantity}× ${PDV.escapeHtml(item.product_name)}</span><span>${PDV.money(item.line_total)}</span></div>${item.notes ? `<span class="order-note">${PDV.escapeHtml(item.notes)}</span>` : ""}`).join("")}</article>`).join("") || '<p>Nenhum pedido enviado.</p>'}<div class="summary-row total"><strong>Subtotal</strong><strong>${PDV.money(session.subtotal)}</strong></div>`;
      PDV.openModal({ title: `Mesa ${session.table_number} · ${PDV.escapeHtml(session.area_name || "")}`, body, large: true, footer: `<a class="btn btn-secondary" href="/print/account.php?session_id=${session.id}" target="_blank">Imprimir conta</a><button class="btn btn-success" id="closeTableButton" type="button">Finalizar mesa</button>` });
      document.getElementById("closeTableButton").onclick = () => openPayment(session);
    } catch (error) { PDV.toast(error.message, "error"); }
  }

  function openPayment(session) {
    const servicePercent = state.settings.service_fee_enabled ? Number(state.settings.service_fee_percent) : 0;
    const paymentStorageKey = `pdv_counter_${userId}_payment_key_${session.id}`;
    const paymentKey = sessionStorage.getItem(paymentStorageKey) || (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
    sessionStorage.setItem(paymentStorageKey, paymentKey);
    const body = `<form id="paymentForm" class="form-stack"><div class="summary-list"><div class="summary-row"><span>Subtotal</span><strong id="paymentSubtotal" data-value="${session.subtotal}">${PDV.money(session.subtotal)}</strong></div><div class="summary-row"><span>Taxa de serviço</span><strong id="paymentService">${PDV.money(Number(session.subtotal) * servicePercent / 100)}</strong></div><div class="summary-row total"><span>Total</span><strong id="paymentTotal"></strong></div></div><label class="check-field"><input name="apply_service_fee" type="checkbox" ${state.settings.service_fee_enabled ? "checked" : ""}><span>Aplicar taxa de serviço de ${servicePercent}%</span></label><div class="form-grid"><label class="field"><span>Desconto</span><input name="discount" inputmode="decimal" value="0.00"></label><label class="field"><span>Acréscimo</span><input name="surcharge" inputmode="decimal" value="0.00"></label></div><div><div class="panel-header" style="padding-left:0;padding-right:0"><h3>Formas de pagamento</h3><button class="btn btn-secondary btn-sm" id="addPayment" type="button">Dividir pagamento</button></div><div class="form-stack" id="paymentRows"></div></div><p id="paymentDifference" class="muted"></p></form>`;
    PDV.openModal({ title: `Finalizar Mesa ${session.table_number}`, body, footer: '<button class="btn btn-secondary" type="button" data-close-modal>Voltar</button><button class="btn btn-success" id="confirmCloseTable" type="button">Confirmar pagamento e fechar</button>', large: true });
    addPaymentRow("pix", (Number(session.subtotal) * (1 + servicePercent / 100)).toFixed(2));
    document.getElementById("addPayment").onclick = () => addPaymentRow("credit_card", "0.00");
    document.getElementById("paymentForm").addEventListener("input", recalculatePayment);
    document.getElementById("paymentRows").addEventListener("click", (event) => { const remove = event.target.closest("[data-remove-payment]"); if (remove) { remove.closest(".payment-row").remove(); recalculatePayment(); } });
    document.getElementById("confirmCloseTable").onclick = () => closeTable(session.id, paymentKey, paymentStorageKey);
    recalculatePayment();
  }

  function addPaymentRow(method, amount) {
    const row = document.createElement("div"); row.className = "form-grid payment-row";
    row.innerHTML = `<label class="field"><span>Forma</span><select name="method">${Object.entries(methodLabels).map(([value,label]) => `<option value="${value}" ${value === method ? "selected" : ""}>${label}</option>`).join("")}</select></label><label class="field"><span>Valor</span><input name="amount" inputmode="decimal" value="${amount}"></label><label class="field"><span>Referência (opcional)</span><input name="reference" maxlength="120"></label><button class="btn btn-ghost text-danger" type="button" data-remove-payment style="align-self:end">Remover</button>`;
    document.getElementById("paymentRows").appendChild(row);
  }

  function paymentValues() {
    const form = document.getElementById("paymentForm");
    const subtotal = Number(document.getElementById("paymentSubtotal").dataset.value);
    const service = form.elements.apply_service_fee.checked ? subtotal * Number(state.settings.service_fee_percent) / 100 : 0;
    const discount = Number(String(form.elements.discount.value).replace(",", ".")) || 0;
    const surcharge = Number(String(form.elements.surcharge.value).replace(",", ".")) || 0;
    return { subtotal, service, discount, surcharge, total: Math.max(0, subtotal + service + surcharge - discount) };
  }
  function recalculatePayment() {
    const values = paymentValues();
    document.getElementById("paymentService").textContent = PDV.money(values.service);
    document.getElementById("paymentTotal").textContent = PDV.money(values.total);
    const paid = [...document.querySelectorAll('.payment-row input[name="amount"]')].reduce((sum, input) => sum + (Number(input.value.replace(",", ".")) || 0), 0);
    const difference = values.total - paid;
    document.getElementById("paymentDifference").textContent = Math.abs(difference) < .005 ? "Pagamentos conferidos." : `${difference > 0 ? "Falta" : "Excede"}: ${PDV.money(Math.abs(difference))}`;
    document.getElementById("paymentDifference").className = Math.abs(difference) < .005 ? "text-success" : "text-danger";
  }

  async function closeTable(sessionId, paymentKey, paymentStorageKey) {
    const form = document.getElementById("paymentForm");
    const values = paymentValues();
    const payments = [...document.querySelectorAll(".payment-row")]
      .map((row) => ({ method: row.querySelector('[name="method"]').value, amount: row.querySelector('[name="amount"]').value, reference: row.querySelector('[name="reference"]').value }))
      .filter((payment) => (Number(String(payment.amount).replace(",", ".")) || 0) > 0);
    const applyServiceFee = form.elements.apply_service_fee.checked;
    if (!await PDV.confirmAction("Após a confirmação, a mesa será liberada e a venda permanecerá no histórico.", "Fechar mesa")) return openTable(state.tables.find((table) => table.session_id === sessionId)?.id);
    try {
      await PDV.request("/api/payments/close.php", { method: "POST", body: { table_session_id: sessionId, idempotency_key: paymentKey, apply_service_fee: applyServiceFee, discount: values.discount.toFixed(2), surcharge: values.surcharge.toFixed(2), payments } });
      sessionStorage.removeItem(paymentStorageKey);
      PDV.closeModal(); PDV.toast("Pagamento registrado e mesa liberada."); await loadTables();
    } catch (error) { PDV.toast(error.message, "error", 6000); }
  }

  if (app.dataset.view === "orders") {
    configureSoundButton(); pollOrders(true); setInterval(() => pollOrders(false), 2000);
    document.getElementById("refreshOrders").onclick = () => pollOrders(true);
    document.querySelector(".board").addEventListener("click", (event) => { const status = event.target.closest("[data-order-status]"); if (status) changeStatus(Number(status.dataset.orderId), status.dataset.orderStatus); const cancel = event.target.closest("[data-cancel-order]"); if (cancel) cancelOrder(Number(cancel.dataset.cancelOrder)); const item = event.target.closest("[data-cancel-item]"); if (item) cancelItem(Number(item.dataset.cancelItem)); });
  } else {
    loadTables().catch((error) => PDV.toast(error.message, "error", 6000));
    document.getElementById("refreshCounterTables").onclick = () => loadTables();
    document.getElementById("counterTablesGrid").addEventListener("click", (event) => { const table = event.target.closest("[data-counter-table]"); if (table) openTable(Number(table.dataset.counterTable)); });
    setInterval(() => loadTables().catch(() => {}), 5000);
  }
})();
