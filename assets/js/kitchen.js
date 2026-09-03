(function () {
  "use strict";
  const app = document.getElementById("kitchenApp");
  if (!app) return;
  const state = { orders: [], lastEventId: 0 };

  function elapsed(value) {
    const seconds = Math.max(0, Math.floor((Date.now() - new Date(value.replace(" ", "T")).getTime()) / 1000));
    const minutes = Math.floor(seconds / 60).toString().padStart(2, "0");
    return `${minutes}:${(seconds % 60).toString().padStart(2, "0")}`;
  }

  function render() {
    document.getElementById("kitchenGrid").innerHTML = state.orders.length ? state.orders.map((order) => `<article class="kds-card ${order.status}"><div class="order-head"><div><span class="eyebrow">Mesa ${PDV.escapeHtml(order.table_number)}</span><h3 style="margin:.25rem 0;color:white">Pedido #${order.id}</h3></div><span class="kds-timer" data-created-at="${order.created_at}">${elapsed(order.created_at)}</span></div><span class="order-meta">Garçom: ${PDV.escapeHtml(order.waiter_name)}</span><div class="order-items">${order.items.filter((item) => item.status !== "cancelled").map((item) => `<div class="order-item"><strong>${item.quantity}× ${PDV.escapeHtml(item.product_name)}</strong>${item.modifiers?.length ? `<small>${item.modifiers.map((modifier) => PDV.escapeHtml(modifier.modifier_name)).join(", ")}</small>` : ""}${item.notes ? `<span class="order-note">${PDV.escapeHtml(item.notes)}</span>` : ""}</div>`).join("")}</div><button class="btn ${order.status === "preparing" ? "btn-success" : "btn-primary"} btn-lg btn-block" data-kitchen-status="${order.status === "preparing" ? "ready" : "preparing"}" data-order-id="${order.id}">${order.status === "preparing" ? "PEDIDO PRONTO" : "INICIAR PREPARO"}</button></article>`).join("") : '<div class="empty-state" style="color:white"><span class="status-icon">✓</span><h3>Nenhum pedido aguardando</h3><p>Os novos pedidos aparecerão aqui automaticamente.</p></div>';
  }

  async function poll(force = false) {
    const data = await PDV.request(`/api/orders/poll.php?since_event_id=${force ? 0 : state.lastEventId}`);
    state.lastEventId = Math.max(state.lastEventId, data.last_event_id);
    if (data.changed) { state.orders = data.orders; render(); }
  }

  async function changeStatus(orderId, status, button) {
    PDV.setLoading(button, true);
    try { await PDV.request("/api/orders/status.php", { method: "POST", body: { order_id: orderId, status } }); await poll(true); }
    catch (error) { PDV.toast(error.message, "error"); PDV.setLoading(button, false); }
  }

  document.getElementById("kitchenGrid").addEventListener("click", (event) => { const button = event.target.closest("[data-kitchen-status]"); if (button) changeStatus(Number(button.dataset.orderId), button.dataset.kitchenStatus, button); });
  const orderPolling = PDV.startPolling(() => poll(false), { errorMessage: "Não foi possível atualizar a cozinha. Uma nova tentativa será feita automaticamente." });
  document.getElementById("refreshKitchen").onclick = () => { state.lastEventId = 0; orderPolling.runNow(); };
  setInterval(() => document.querySelectorAll("[data-created-at]").forEach((timer) => { timer.textContent = elapsed(timer.dataset.createdAt); }), 1000);
})();
