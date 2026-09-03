(function () {
  "use strict";
  const app = document.getElementById("waiterApp");
  if (!app) return;
  const userId = app.dataset.userId;
  const state = { tables: [], catalog: [], session: null, cart: [], category: "all", productSearch: "", lastEventId: 0, pendingKey: null };
  let tableLoadPromise = null;
  const tableLabels = { available: "Disponível", occupied: "Ocupada", waiting_order: "Aguardando pedido", bill_requested: "Conta solicitada" };
  const statusLabels = { new: "Novo", accepted: "Aceito", preparing: "Em preparo", ready: "Pronto", delivered: "Entregue", cancelled: "Cancelado" };

  function draftKey() {
    if (!state.session) return null;
    const reference = state.session.id ? `session_${state.session.id}` : `table_${state.session.table_id}`;
    return `pdv_waiter_${userId}_cart_${reference}`;
  }
  function saveDraft() {
    if (!draftKey()) return;
    localStorage.setItem(draftKey(), JSON.stringify({ cart: state.cart, pendingKey: state.pendingKey, savedAt: Date.now() }));
  }
  function loadDraft() {
    const raw = draftKey() ? localStorage.getItem(draftKey()) : null;
    try { const data = raw ? JSON.parse(raw) : {}; state.cart = Array.isArray(data.cart) ? data.cart : []; state.pendingKey = data.pendingKey || null; }
    catch { state.cart = []; state.pendingKey = null; }
  }
  function clearDraft() { if (draftKey()) localStorage.removeItem(draftKey()); state.cart = []; state.pendingKey = null; }

  function loadTables() {
    if (tableLoadPromise) return tableLoadPromise;
    tableLoadPromise = (async () => {
      const data = await PDV.request("/api/tables/list.php");
      state.tables = data.tables;
      const areas = [...new Set(state.tables.map((table) => table.area_name).filter(Boolean))];
      const areaFilter = document.getElementById("areaFilter");
      const selectedArea = areaFilter.value;
      areaFilter.innerHTML = '<option value="">Todos os salões</option>' + areas.map((area) => `<option>${PDV.escapeHtml(area)}</option>`).join("");
      if (areas.includes(selectedArea)) areaFilter.value = selectedArea;
      renderTables();
    })().finally(() => { tableLoadPromise = null; });
    return tableLoadPromise;
  }

  function renderTables() {
    const search = document.getElementById("tableSearch").value.toLowerCase();
    const area = document.getElementById("areaFilter").value;
    const tables = state.tables.filter((table) => (!area || table.area_name === area) && `${table.number} ${table.name || ""} ${table.area_name || ""}`.toLowerCase().includes(search));
    document.getElementById("waiterTablesGrid").innerHTML = tables.length ? tables.map((table) => {
      const badgeClass = table.status === "available" ? "badge-success" : table.status === "bill_requested" ? "badge-info" : "badge-warning";
      return `<button class="table-card ${table.status}" type="button" data-table-id="${table.id}"><span class="table-number">Mesa ${PDV.escapeHtml(table.number)}</span><span class="table-area">${PDV.escapeHtml(table.area_name || "Sem salão")}</span>${table.waiter_name ? `<small>Garçom: ${PDV.escapeHtml(table.waiter_name)}</small>` : ""}<span class="badge ${badgeClass}">${PDV.escapeHtml(tableLabels[table.status] || table.status)}</span></button>`;
    }).join("") : '<div class="empty-state"><span class="status-icon">⌕</span><h3>Nenhuma mesa encontrada</h3><p>Revise os filtros ou cadastre mesas no painel administrativo.</p></div>';
  }

  async function selectTable(tableId) {
    const table = state.tables.find((item) => item.id === tableId);
    if (!table) return;
    try {
      let session;
      if (table.status === "available") {
        session = {
          id: null,
          table_id: table.id,
          table_number: table.number,
          area_name: table.area_name,
          waiter_name: "",
          opened_at: null,
          status: "draft",
        };
      } else {
        session = await PDV.request(`/api/tables/details.php?table_id=${table.id}`);
      }
      await openMenu(session);
    } catch (error) { PDV.toast(error.message, "error"); await loadTables(); }
  }

  async function openMenu(session) {
    state.session = session;
    loadDraft();
    if (!state.catalog.length) {
      const catalog = await PDV.request("/api/products/list.php");
      state.catalog = catalog.categories;
    }
    document.getElementById("tablesView").classList.add("hidden");
    document.getElementById("ordersView").classList.add("hidden");
    document.getElementById("menuView").classList.remove("hidden");
    document.getElementById("selectedTable").textContent = `Mesa ${session.table_number}`;
    document.getElementById("selectedArea").textContent = session.area_name || "Atendimento";
    document.getElementById("selectedSessionMeta").textContent = session.id
      ? `Aberta por ${session.waiter_name} às ${new Date(session.opened_at.replace(" ", "T")).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`
      : "A mesa ficará ocupada somente quando o primeiro pedido for enviado.";
    document.getElementById("requestBillButton").disabled = !session.id || session.status === "payment_pending";
    state.category = "all";
    state.productSearch = "";
    document.getElementById("productSearch").value = "";
    renderCategories(); renderProducts(); updateCartBar();
  }

  function renderCategories() {
    document.getElementById("waiterCategories").innerHTML = `<button class="category-tab ${state.category === "all" ? "active" : ""}" data-category="all">Todos</button>` + state.catalog.map((category) => `<button class="category-tab ${Number(state.category) === category.id ? "active" : ""}" data-category="${category.id}">${PDV.escapeHtml(category.name)}</button>`).join("");
  }

  function allProducts() {
    return state.catalog.flatMap((category) => category.products.map((product) => ({ ...product, category_name: category.name })));
  }

  function searchable(value) {
    return String(value || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  }

  function renderProducts() {
    const query = searchable(state.productSearch);
    const products = allProducts().filter((product) => {
      const matchesCategory = state.category === "all" || product.category_id === Number(state.category);
      const matchesSearch = !query || searchable(`${product.name} ${product.description || ""} ${product.category_name}`).includes(query);
      return matchesCategory && matchesSearch;
    });
    document.getElementById("visibleProductCount").textContent = `${products.length} ${products.length === 1 ? "produto" : "produtos"}`;
    const emptyMessage = query
      ? '<div class="empty-state menu-empty"><h3>Nenhum produto encontrado</h3><p>Tente outro nome ou limpe a busca.</p><button class="btn btn-secondary" type="button" data-clear-product-search>Limpar busca</button></div>'
      : '<div class="empty-state menu-empty"><p>Nenhum produto disponível nesta categoria.</p></div>';
    document.getElementById("waiterProducts").innerHTML = products.length ? products.map((product) => `<article class="product-card">${product.image_path ? `<img class="product-image" src="${PDV.escapeHtml(product.image_path)}" alt="">` : `<div class="product-placeholder">${PDV.escapeHtml(product.name.slice(0, 2).toUpperCase())}</div>`}<div class="product-body"><span class="eyebrow">${PDV.escapeHtml(product.category_name)}</span><h3>${PDV.escapeHtml(product.name)}</h3><p>${PDV.escapeHtml(product.description || "")}</p><div class="product-footer"><strong class="money">${PDV.money(product.price)}</strong><button class="btn btn-primary btn-sm" type="button" data-add-product="${product.id}" aria-label="Adicionar ${PDV.escapeHtml(product.name)} ao pedido">Adicionar</button></div></div></article>`).join("") : emptyMessage;
  }

  function productForm(product, existing = null) {
    const selected = new Set(existing?.modifier_ids || []);
    return `<form id="addProductForm" class="form-stack"><input type="hidden" name="product_id" value="${product.id}"><div><span class="eyebrow">${PDV.money(product.price)}</span><h3 style="margin:.25rem 0">${PDV.escapeHtml(product.name)}</h3><p>${PDV.escapeHtml(product.description || "")}</p></div><div><span class="field-label">Quantidade</span><div class="quantity-control" style="margin-top:.45rem"><button type="button" data-quantity="minus">−</button><span id="productQuantity">${existing?.quantity || 1}</span><button type="button" data-quantity="plus">+</button></div></div>${product.modifier_groups.map((group) => `<fieldset style="border:1px solid var(--line);border-radius:12px;padding:1rem"><legend><strong>${PDV.escapeHtml(group.name)}</strong> <small>${group.required ? "Obrigatório" : "Opcional"} · ${group.min_choices} a ${group.max_choices}</small></legend><div class="form-stack">${group.options.map((option) => `<label class="check-field"><input type="checkbox" name="modifier_ids" value="${option.id}" data-group-id="${group.id}" data-group-min="${group.required ? Math.max(1, group.min_choices) : group.min_choices}" data-group-max="${group.max_choices}" ${selected.has(option.id) ? "checked" : ""}><span>${PDV.escapeHtml(option.name)}${Number(option.price_delta) ? ` · +${PDV.money(option.price_delta)}` : ""}</span></label>`).join("")}</div></fieldset>`).join("")}<label class="field"><span>Observação</span><textarea name="notes" maxlength="500" placeholder="Ex.: sem cebola">${PDV.escapeHtml(existing?.notes || "")}</textarea></label></form>`;
  }

  function openProduct(productId, editIndex = null) {
    const product = allProducts().find((item) => item.id === Number(productId));
    if (!product) return;
    const existing = editIndex === null ? null : state.cart[editIndex];
    PDV.openModal({ title: existing ? "Editar item" : "Adicionar ao pedido", body: productForm(product, existing), footer: `<button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button><button class="btn btn-primary" id="confirmProduct" type="button">${existing ? "Salvar alterações" : "Adicionar"}</button>` });
    const quantity = document.getElementById("productQuantity");
    const productFormElement = document.getElementById("addProductForm");
    productFormElement.onclick = (event) => {
      const action = event.target.closest("[data-quantity]")?.dataset.quantity;
      if (!action) return;
      const current = Number(quantity.textContent);
      quantity.textContent = action === "plus" ? Math.min(99, current + 1) : Math.max(1, current - 1);
    };
    productFormElement.addEventListener("change", (event) => {
      const option = event.target.closest('input[name="modifier_ids"]');
      if (!option?.checked) return;
      const selectedInGroup = [...productFormElement.querySelectorAll(`input[name="modifier_ids"][data-group-id="${option.dataset.groupId}"]:checked`)];
      if (selectedInGroup.length > Number(option.dataset.groupMax)) {
        option.checked = false;
        PDV.toast(`Escolha no máximo ${option.dataset.groupMax} opção(ões) neste complemento.`, "error", 4500);
      }
    });
    document.getElementById("confirmProduct").onclick = () => {
      const form = document.getElementById("addProductForm");
      const selected = [...form.querySelectorAll('input[name="modifier_ids"]:checked')];
      for (const group of product.modifier_groups) {
        const count = selected.filter((input) => Number(input.dataset.groupId) === group.id).length;
        const min = group.required ? Math.max(1, group.min_choices) : group.min_choices;
        if (count < min || count > group.max_choices) { PDV.toast(`Revise as escolhas de ${group.name}.`, "error"); return; }
      }
      const item = { product_id: product.id, name: product.name, unit_price: product.price, quantity: Number(quantity.textContent), notes: form.elements.notes.value.trim(), modifier_ids: selected.map((input) => Number(input.value)), modifiers: selected.map((input) => ({ id: Number(input.value), name: input.parentElement.textContent.trim(), price: product.modifier_groups.flatMap((g) => g.options).find((o) => o.id === Number(input.value))?.price_delta || 0 })) };
      if (editIndex === null) state.cart.push(item); else state.cart[editIndex] = item;
      state.pendingKey = null; saveDraft(); updateCartBar(); PDV.closeModal(); PDV.toast("Item adicionado ao pedido.");
    };
  }

  function cartTotal() {
    return state.cart.reduce((total, item) => total + (Number(item.unit_price) + item.modifiers.reduce((sum, modifier) => sum + Number(modifier.price), 0)) * item.quantity, 0);
  }
  function updateCartBar() {
    const bar = document.getElementById("cartBar");
    const count = state.cart.reduce((sum, item) => sum + item.quantity, 0);
    bar.classList.toggle("hidden", count === 0);
    document.getElementById("cartItemCount").textContent = `${count} ${count === 1 ? "item" : "itens"}`;
    document.getElementById("cartTotal").textContent = PDV.money(cartTotal());
  }

  function openCart() {
    const body = state.cart.length ? `<div class="summary-list">${state.cart.map((item, index) => `<div class="order-item"><div class="summary-row"><strong>${item.quantity}× ${PDV.escapeHtml(item.name)}</strong><span>${PDV.money((Number(item.unit_price) + item.modifiers.reduce((sum, mod) => sum + Number(mod.price), 0)) * item.quantity)}</span></div>${item.modifiers.length ? `<small>${item.modifiers.map((mod) => PDV.escapeHtml(mod.name)).join(", ")}</small>` : ""}${item.notes ? `<span class="order-note">${PDV.escapeHtml(item.notes)}</span>` : ""}<div style="display:flex;gap:.35rem;margin-top:.45rem"><button class="btn btn-secondary btn-sm" data-edit-cart="${index}">Editar</button><button class="btn btn-ghost btn-sm text-danger" data-remove-cart="${index}">Remover</button></div></div>`).join("")}<div class="summary-row total"><strong>Total</strong><strong>${PDV.money(cartTotal())}</strong></div></div>` : '<div class="empty-state"><p>Seu pedido está vazio.</p></div>';
    PDV.openModal({ title: `Pedido · Mesa ${state.session.table_number}`, body, footer: '<button class="btn btn-secondary" type="button" data-close-modal>Continuar adicionando</button><button class="btn btn-success" id="sendOrder" type="button">Enviar pedido</button>' });
    document.getElementById("sendOrder").disabled = !state.cart.length;
    document.getElementById("globalModalBody").onclick = (event) => {
      const edit = event.target.closest("[data-edit-cart]");
      if (edit) { PDV.closeModal(); openProduct(state.cart[Number(edit.dataset.editCart)].product_id, Number(edit.dataset.editCart)); }
      const remove = event.target.closest("[data-remove-cart]");
      if (remove) { state.cart.splice(Number(remove.dataset.removeCart), 1); state.pendingKey = null; saveDraft(); updateCartBar(); openCart(); }
    };
    document.getElementById("sendOrder").onclick = sendOrder;
  }

  async function sendOrder() {
    if (!state.cart.length) return;
    if (!await PDV.confirmAction(`Enviar ${state.cart.reduce((sum, item) => sum + item.quantity, 0)} itens para a Mesa ${state.session.table_number}?`, "Enviar pedido")) { openCart(); return; }
    state.pendingKey ||= crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
    saveDraft();
    PDV.openModal({ title: "Enviando pedido", body: '<div class="empty-state sending-state"><span class="sending-spinner" aria-hidden="true"></span><h3>Aguarde um instante</h3><p>Estamos enviando os itens para o balcão e a cozinha.</p></div>' });
    try {
      const tableReference = state.session.id
        ? { table_session_id: state.session.id }
        : { table_id: state.session.table_id };
      const order = await PDV.request("/api/orders/create.php", { method: "POST", body: { ...tableReference, idempotency_key: state.pendingKey, items: state.cart.map(({ product_id, quantity, notes, modifier_ids }) => ({ product_id, quantity, notes, modifier_ids })) } });
      clearDraft(); updateCartBar(); PDV.closeModal();
      state.session = {
        ...state.session,
        id: Number(order.table_session_id),
        status: "open",
        waiter_name: order.waiter_name,
        opened_at: state.session.opened_at || order.created_at,
      };
      PDV.openModal({ title: "Pedido enviado", body: `<div class="success-panel"><span class="status-icon success">✓</span><h3>Pedido #${order.id}</h3><p>O balcão e a cozinha já podem visualizar este pedido.</p></div>`, footer: '<button class="btn btn-primary" type="button" data-close-modal>Continuar</button>' });
      await loadTables();
    } catch (error) { PDV.toast(error.message, "error", 6000); openCart(); }
  }

  async function requestBill() {
    if (!state.session?.id) {
      PDV.toast("Envie o primeiro pedido antes de solicitar a conta.", "error", 5000);
      return;
    }
    if (state.cart.length) {
      PDV.toast("Envie ou remova os itens do carrinho antes de solicitar a conta.", "error", 5000);
      return;
    }
    if (!await PDV.confirmAction(`Solicitar a conta da Mesa ${state.session.table_number}?`, "Solicitar conta")) return;
    try { state.session = await PDV.request("/api/tables/request-bill.php", { method: "POST", body: { table_id: state.session.table_id } }); document.getElementById("requestBillButton").disabled = true; PDV.toast("Conta solicitada ao balcão."); }
    catch (error) { PDV.toast(error.message, "error"); }
  }

  async function pollOrders() {
    const data = await PDV.request(`/api/orders/poll.php?since_event_id=${state.lastEventId}`);
    state.lastEventId = Math.max(state.lastEventId, data.last_event_id);
    if (data.changed) document.getElementById("waiterOrdersList").innerHTML = data.orders.length ? data.orders.map((order) => `<article class="order-card ${order.status}"><div class="order-head"><div><h4>Mesa ${PDV.escapeHtml(order.table_number)} · #${order.id}</h4><span class="order-meta">${PDV.dateTime(order.created_at)}</span></div><span class="badge badge-info">${statusLabels[order.status]}</span></div><div class="order-items">${order.items.filter((item) => item.status !== "cancelled").map((item) => `<div class="order-item"><strong>${item.quantity}× ${PDV.escapeHtml(item.product_name)}</strong>${item.notes ? `<span class="order-note">${PDV.escapeHtml(item.notes)}</span>` : ""}</div>`).join("")}</div></article>`).join("") : '<div class="empty-state"><p>Nenhum pedido em acompanhamento.</p></div>';
  }

  document.getElementById("waiterTablesGrid").addEventListener("click", (event) => { const card = event.target.closest("[data-table-id]"); if (card) selectTable(Number(card.dataset.tableId)); });
  document.getElementById("waiterCategories").addEventListener("click", (event) => { const tab = event.target.closest("[data-category]"); if (!tab) return; state.category = tab.dataset.category === "all" ? "all" : Number(tab.dataset.category); renderCategories(); renderProducts(); });
  document.getElementById("waiterProducts").addEventListener("click", (event) => {
    const add = event.target.closest("[data-add-product]");
    if (add) openProduct(add.dataset.addProduct);
    if (event.target.closest("[data-clear-product-search]")) {
      state.productSearch = "";
      document.getElementById("productSearch").value = "";
      renderProducts();
      document.getElementById("productSearch").focus();
    }
  });
  document.getElementById("productSearch").addEventListener("input", (event) => { state.productSearch = event.target.value; renderProducts(); });
  document.getElementById("tableSearch").addEventListener("input", renderTables);
  document.getElementById("areaFilter").addEventListener("change", renderTables);
  document.getElementById("refreshTables").addEventListener("click", () => loadTables().catch((error) => PDV.toast(error.message, "error")));
  document.getElementById("backToTables").addEventListener("click", () => { document.getElementById("menuView").classList.add("hidden"); document.getElementById("tablesView").classList.remove("hidden"); loadTables(); });
  document.getElementById("openCart").addEventListener("click", openCart);
  document.getElementById("requestBillButton").addEventListener("click", requestBill);

  if (app.dataset.initialView === "orders") {
    PDV.startPolling(pollOrders, { errorMessage: "Não foi possível atualizar seus pedidos. Uma nova tentativa será feita automaticamente." });
  } else {
    let requestedTablePending = true;
    PDV.startPolling(async () => {
      await loadTables();
      if (requestedTablePending) {
        requestedTablePending = false;
        const requestedTable = Number(new URLSearchParams(window.location.search).get("table_id"));
        if (requestedTable) await selectTable(requestedTable);
      }
    }, { errorMessage: "Não foi possível atualizar as mesas. Uma nova tentativa será feita automaticamente." });
  }
})();
