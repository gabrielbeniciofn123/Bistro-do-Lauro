(function () {
  "use strict";
  const mount = document.getElementById("historyMount") || document.getElementById("historyAdminMount");
  if (!mount) return;
  const methodLabels = { cash: "Dinheiro", pix: "PIX", debit_card: "Débito", credit_card: "Crédito", other: "Outro" };
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;

  mount.innerHTML = `<form class="toolbar" id="historyFilters"><label class="field"><span>De</span><input name="from" type="date" value="${today}"></label><label class="field"><span>Até</span><input name="to" type="date" value="${today}"></label><label class="field"><span>Mesa</span><select name="table_id"><option value="">Todas</option></select></label><label class="field"><span>Garçom</span><select name="waiter_id"><option value="">Todos</option></select></label><label class="field"><span>Pagamento</span><select name="payment_method"><option value="">Todos</option>${Object.entries(methodLabels).map(([value,label]) => `<option value="${value}">${label}</option>`).join("")}</select></label><button class="btn btn-primary" type="submit" style="align-self:end">Filtrar</button></form><div class="table-wrap"><table class="data-table"><thead><tr><th>Venda</th><th>Mesa</th><th>Data</th><th>Garçom</th><th>Pagamento</th><th>Total</th><th></th></tr></thead><tbody id="historyBody"></tbody></table></div>`;

  async function loadFilters() {
    const data = await PDV.request("/api/history/filters.php");
    const form = document.getElementById("historyFilters");
    form.elements.table_id.innerHTML += data.tables.map((table) => `<option value="${table.id}">Mesa ${PDV.escapeHtml(table.number)}</option>`).join("");
    form.elements.waiter_id.innerHTML += data.waiters.map((waiter) => `<option value="${waiter.id}">${PDV.escapeHtml(waiter.name)}</option>`).join("");
  }

  async function loadHistory() {
    const form = document.getElementById("historyFilters");
    const params = new URLSearchParams(new FormData(form));
    const data = await PDV.request(`/api/history/list.php?${params}`);
    document.getElementById("historyBody").innerHTML = data.items.length ? data.items.map((item) => `<tr><td><strong>#${item.id}</strong></td><td>Mesa ${PDV.escapeHtml(item.table_number)}<br><small>${PDV.escapeHtml(item.area_name || "")}</small></td><td>${PDV.dateTime(item.closed_at)}</td><td>${PDV.escapeHtml(item.waiter_name)}</td><td>${item.payment_methods.map((method) => PDV.escapeHtml(methodLabels[method] || method)).join(" + ")}</td><td class="money">${PDV.money(item.total)}</td><td><button class="btn btn-secondary btn-sm" data-history-id="${item.id}">Detalhes</button></td></tr>`).join("") : '<tr><td colspan="7"><div class="empty-state"><p>Nenhuma venda encontrada neste período.</p></div></td></tr>';
  }

  async function showDetails(id) {
    try {
      const session = await PDV.request(`/api/history/details.php?session_id=${id}`);
      PDV.openModal({ title: `Venda #${session.id} · Mesa ${PDV.escapeHtml(session.table_number)}`, large: true, body: `<div class="summary-list"><div class="summary-row"><span>Aberta por</span><strong>${PDV.escapeHtml(session.waiter_name)}</strong></div><div class="summary-row"><span>Fechada em</span><strong>${PDV.dateTime(session.closed_at)}</strong></div></div><h3 style="margin-top:1.3rem">Pedidos</h3>${session.orders.map((order) => `<article class="order-item"><div class="summary-row"><strong>Pedido #${order.id}</strong><span>${order.status === "cancelled" ? "Cancelado" : PDV.money(order.total)}</span></div>${order.items.map((item) => `<div class="summary-row ${item.status === "cancelled" ? "muted" : ""}"><span>${item.quantity}× ${PDV.escapeHtml(item.product_name)}${item.status === "cancelled" ? " · cancelado" : ""}</span><span>${PDV.money(item.line_total)}</span></div>`).join("")}</article>`).join("")}<div class="summary-list" style="margin-top:1rem"><div class="summary-row"><span>Subtotal</span><span>${PDV.money(session.subtotal)}</span></div><div class="summary-row"><span>Taxa</span><span>${PDV.money(session.service_fee)}</span></div><div class="summary-row"><span>Desconto</span><span>− ${PDV.money(session.discount)}</span></div><div class="summary-row"><span>Acréscimo</span><span>${PDV.money(session.surcharge)}</span></div><div class="summary-row total"><strong>Total</strong><strong>${PDV.money(session.total)}</strong></div></div>`, footer: `<a class="btn btn-secondary" href="/print/receipt.php?session_id=${session.id}" target="_blank">Imprimir comprovante</a><button class="btn btn-primary" type="button" data-close-modal>Fechar</button>` });
    } catch (error) { PDV.toast(error.message, "error"); }
  }

  document.getElementById("historyFilters").addEventListener("submit", (event) => { event.preventDefault(); loadHistory().catch((error) => PDV.toast(error.message, "error")); });
  document.getElementById("historyBody").addEventListener("click", (event) => { const button = event.target.closest("[data-history-id]"); if (button) showDetails(button.dataset.historyId); });
  loadFilters().then(loadHistory).catch((error) => PDV.toast(error.message, "error", 6000));
})();
