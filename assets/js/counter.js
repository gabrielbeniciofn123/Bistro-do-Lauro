(function () {
  "use strict";
  const app = document.getElementById("counterApp");
  if (!app || app.dataset.view === "history") return;
  const userId = app.dataset.userId;
  const soundStorageKey = `pdv_counter_${userId}_sound_enabled`;
  const state = { orders: [], tables: [], lastEventId: 0, knownNew: new Set(), initialized: false, soundEnabled: localStorage.getItem(soundStorageKey) === "1", settings: null };
  let tableLoadPromise = null;
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

  function loadTables() {
    if (tableLoadPromise) return tableLoadPromise;
    tableLoadPromise = (async () => {
      const [tableData, sessionData] = await Promise.all([
        PDV.request("/api/tables/list.php"),
        state.settings ? Promise.resolve(null) : PDV.request("/api/session.php"),
      ]);
      state.tables = tableData.tables;
      if (sessionData) state.settings = sessionData.settings;
      renderTables();
    })().finally(() => { tableLoadPromise = null; });
    return tableLoadPromise;
  }

  function tableBadgeClass(status) {
    if (status === "available") return "badge-success";
    if (status === "bill_requested") return "badge-info";
    return "badge-warning";
  }

  function tableCard(table) {
    const available = table.status === "available";
    const label = tableLabels[table.status] || table.status;
    const details = available
      ? '<p class="table-card-empty">Livre para um novo atendimento</p>'
      : `<div class="table-card-metrics"><span><small>Pedidos</small><strong>${Number(table.order_count)}</strong></span><span><small>Subtotal</small><strong>${PDV.money(table.subtotal)}</strong></span></div><div class="table-card-service"><span>${PDV.escapeHtml(table.waiter_name || "Garçom não informado")}</span><small>Aberta ${PDV.dateTime(table.opened_at)}</small></div><span class="table-card-action">Ver pedidos e pagamento →</span>`;
    return `<button class="table-card table-overview-card ${table.status}" type="button" data-counter-table="${table.id}" aria-label="Mesa ${PDV.escapeHtml(table.number)}, ${PDV.escapeHtml(label)}"><span class="table-card-heading"><span class="table-number">Mesa ${PDV.escapeHtml(table.number)}</span><span class="badge ${tableBadgeClass(table.status)}">${PDV.escapeHtml(label)}</span></span>${table.name ? `<strong class="table-name">${PDV.escapeHtml(table.name)}</strong>` : ""}${details}</button>`;
  }

  function renderTables() {
    const mount = document.getElementById("counterTablesGrid");
    const groups = new Map();
    state.tables.forEach((table) => {
      const area = table.area_name || "Sem salão";
      if (!groups.has(area)) groups.set(area, []);
      groups.get(area).push(table);
    });
    mount.innerHTML = [...groups.entries()].map(([area, tables]) => {
      const active = tables.filter((table) => table.status !== "available").length;
      return `<section class="area-group"><header class="area-group-header"><div><span class="eyebrow">Salão</span><h3>${PDV.escapeHtml(area)}</h3></div><span>${tables.length} mesa(s) · ${active} em atendimento</span></header><div class="tables-grid">${tables.map(tableCard).join("")}</div></section>`;
    }).join("") || '<div class="empty-state"><h3>Nenhuma mesa ativa</h3><p>Cadastre mesas e salões na administração.</p></div>';
  }

  function orderDetails(order) {
    const badgeClass = order.status === "cancelled" ? "badge-neutral" : order.status === "ready" || order.status === "delivered" ? "badge-success" : order.status === "new" ? "badge-warning" : "badge-info";
    const items = order.items.map((item) => {
      const cancelled = item.status === "cancelled";
      const modifiers = item.modifiers?.length ? `<small class="table-order-modifiers">${item.modifiers.map((modifier) => PDV.escapeHtml(modifier.modifier_name)).join(", ")}</small>` : "";
      return `<div class="table-order-item ${cancelled ? "cancelled" : ""}"><div><strong>${item.quantity}× ${PDV.escapeHtml(item.product_name)}</strong>${modifiers}${item.notes ? `<span class="order-note">Obs.: ${PDV.escapeHtml(item.notes)}</span>` : ""}</div><span>${PDV.money(item.line_total)}</span></div>`;
    }).join("");
    return `<article class="table-order-card ${order.status}"><header><div><strong>Pedido #${order.id}</strong><small>${PDV.dateTime(order.created_at)} · ${Number(order.item_count)} item(ns)</small></div><span class="badge ${badgeClass}">${PDV.escapeHtml(statusLabels[order.status] || order.status)}</span></header><div class="table-order-items">${items}</div><footer><span>${PDV.escapeHtml(order.waiter_name)}</span><strong>${PDV.money(order.total)}</strong></footer></article>`;
  }

  async function openTable(tableId) {
    const table = state.tables.find((item) => item.id === tableId);
    if (table.status === "available") {
      PDV.openModal({ title: `Mesa ${table.number}`, body: '<div class="empty-state"><span class="status-icon">✓</span><h3>Mesa disponível</h3><p>A mesa ainda não possui atendimento aberto.</p></div>', footer: '<button class="btn btn-secondary" data-close-modal>Fechar</button>' });
      return;
    }
    try {
      const session = await PDV.request(`/api/tables/details.php?table_id=${tableId}`);
      const paymentNotice = session.can_finalize_payment
        ? '<div class="alert alert-success"><strong>Pronta para pagamento.</strong> Todos os pedidos desta mesa foram concluídos.</div>'
        : `<div class="alert alert-warning"><strong>Pagamento ainda indisponível.</strong> Existem ${Number(session.pending_order_count)} pedido(s) em produção ou aguardando entrega.</div>`;
      const body = `<section class="table-session-summary"><div><span class="eyebrow">Atendimento</span><h3>${PDV.escapeHtml(session.waiter_name)}</h3><p>Aberta em ${PDV.dateTime(session.opened_at)}</p></div><div class="table-session-total"><small>${Number(session.order_count)} pedido(s)</small><strong>${PDV.money(session.subtotal)}</strong><span>Subtotal da mesa</span></div></section>${paymentNotice}<div class="table-orders-heading"><h3>Pedidos da mesa</h3><span>Do mais antigo ao mais recente</span></div><div class="table-orders-list">${session.orders.map(orderDetails).join("") || '<div class="empty-state"><p>Nenhum pedido enviado.</p></div>'}</div>`;
      const disabled = session.can_finalize_payment ? "" : " disabled";
      PDV.openModal({ title: `Mesa ${session.table_number} · ${PDV.escapeHtml(session.area_name || "Sem salão")}`, body, large: true, footer: `<a class="btn btn-secondary" href="/print/account.php?session_id=${session.id}" target="_blank">Imprimir conta</a><button class="btn btn-success" id="closeTableButton" type="button"${disabled}>Finalizar pagamento</button>` });
      if (session.can_finalize_payment) document.getElementById("closeTableButton").onclick = () => openPayment(session);
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
    setInterval(() => loadTables().catch(() => {}), 2000);
  }
})();
