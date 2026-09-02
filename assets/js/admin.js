(function () {
  "use strict";
  const page = document.getElementById("adminPage");
  if (!page) return;
  const view = page.dataset.view;
  const state = { areas: [], tables: [], categories: [], products: [], users: [], modifier_groups: [], modifiers: [] };
  const labels = { admin: "Administrador", counter: "Balcão / Caixa", waiter: "Garçom", kitchen: "Cozinha" };
  const tableStatus = { available: "Disponível", occupied: "Ocupada", waiting_order: "Aguardando pedido", bill_requested: "Conta solicitada", inactive: "Inativa" };

  async function loadDashboard() {
    const data = await PDV.request("/api/admin/dashboard.php");
    document.getElementById("statSales").textContent = PDV.money(data.sales_today);
    document.getElementById("statOrders").textContent = data.orders_today;
    document.getElementById("statTicket").textContent = PDV.money(data.average_ticket);
    document.getElementById("statOpenTabs").textContent = data.open_tabs;
    document.getElementById("statAvailable").textContent = data.available_tables;
    document.getElementById("statPreparing").textContent = data.preparing_orders;
    document.getElementById("statReady").textContent = data.ready_orders;
    document.getElementById("topProductsBody").innerHTML = data.top_products.length
      ? data.top_products.map((item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong></td><td>${Number(item.quantity)}</td><td class="money">${PDV.money(item.total)}</td></tr>`).join("")
      : '<tr><td colspan="3"><div class="empty-state"><p>Nenhuma venda registrada hoje.</p></div></td></tr>';
  }

  async function loadResource(resource) {
    const data = await PDV.request(`/api/admin/resources.php?resource=${encodeURIComponent(resource)}&action=list`);
    state[resource] = data.items;
    renderResource(resource);
  }

  function statusBadge(active, custom = null) {
    if (custom) return `<span class="badge ${custom.className}">${PDV.escapeHtml(custom.label)}</span>`;
    return `<span class="badge ${active ? "badge-success" : "badge-neutral"}">${active ? "Ativo" : "Inativo"}</span>`;
  }

  function actionButtons(resource, id) {
    return `<div class="row-actions"><button class="btn btn-secondary btn-sm" data-edit-resource="${resource}" data-id="${id}">Editar</button><button class="btn btn-ghost btn-sm text-danger" data-deactivate-resource="${resource}" data-id="${id}">Desativar</button></div>`;
  }

  function renderResource(resource, query = "") {
    const body = document.querySelector(`[data-resource-body="${resource}"]`);
    if (!body) return;
    const items = state[resource].filter((item) => JSON.stringify(item).toLowerCase().includes(query.toLowerCase()));
    const row = {
      areas: (item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong></td><td>${item.sort_order}</td><td>${statusBadge(item.active)}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
      tables: (item) => `<tr><td><strong>Mesa ${PDV.escapeHtml(item.number)}</strong></td><td>${PDV.escapeHtml(item.name || "—")}</td><td>${PDV.escapeHtml(item.area_name || "Sem salão")}</td><td>${statusBadge(item.active, { label: tableStatus[item.status] || item.status, className: item.status === "available" ? "badge-success" : item.status === "inactive" ? "badge-neutral" : "badge-warning" })}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
      categories: (item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong></td><td>${PDV.escapeHtml(item.icon || "—")}</td><td>${item.sort_order}</td><td>${statusBadge(item.active)}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
      products: (item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong><br><small>${PDV.escapeHtml(item.description || "")}</small></td><td>${PDV.escapeHtml(item.category_name)}</td><td class="money">${PDV.money(item.price)}</td><td>${statusBadge(item.active && item.available, { label: item.active && item.available ? "Disponível" : "Indisponível", className: item.active && item.available ? "badge-success" : "badge-neutral" })}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
      users: (item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong><br><small>@${PDV.escapeHtml(item.login)}</small></td><td>${PDV.escapeHtml(labels[item.role] || item.role)}</td><td>${statusBadge(item.active)}</td><td>${PDV.dateTime(item.last_login_at)}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
      modifier_groups: (item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong></td><td>${item.min_choices} a ${item.max_choices}${item.required ? " · obrigatório" : ""}</td><td>${item.modifier_count}</td><td>${statusBadge(item.active)}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
      modifiers: (item) => `<tr><td><strong>${PDV.escapeHtml(item.name)}</strong></td><td>${PDV.escapeHtml(item.group_name)}</td><td class="money">${PDV.money(item.price_delta)}</td><td>${statusBadge(item.active)}</td><td>${actionButtons(resource, item.id)}</td></tr>`,
    }[resource];
    body.innerHTML = items.length ? items.map(row).join("") : `<tr><td colspan="6"><div class="empty-state"><p>Nenhum registro encontrado.</p></div></td></tr>`;
    if (["products", "categories", "users"].includes(resource)) renderHead(resource);
  }

  function renderHead(resource) {
    const head = document.getElementById("resourceHead");
    if (!head) return;
    const headers = {
      products: ["Produto", "Categoria", "Preço", "Situação", ""],
      categories: ["Categoria", "Ícone", "Ordem", "Status", ""],
      users: ["Usuário", "Função", "Status", "Último acesso", ""],
    }[resource];
    head.innerHTML = headers.map((label) => `<th>${label}</th>`).join("");
  }

  function commonFields(item) {
    return `<label class="check-field"><input name="active" type="checkbox" value="1" ${item?.active !== false ? "checked" : ""}><span>Cadastro ativo</span></label>`;
  }

  function formFor(resource, item = {}) {
    const id = item.id ? `<input type="hidden" name="id" value="${item.id}">` : "";
    const forms = {
      areas: `${id}<div class="form-grid"><label class="field full"><span>Nome do salão/setor</span><input name="name" required maxlength="100" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Ordem</span><input name="sort_order" type="number" min="0" value="${item.sort_order ?? 0}"></label>${commonFields(item)}</div>`,
      tables: `${id}<div class="form-grid"><label class="field"><span>Número</span><input name="number" required maxlength="20" value="${PDV.escapeHtml(item.number || "")}"></label><label class="field"><span>Nome opcional</span><input name="name" maxlength="100" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Salão</span><select name="area_id"><option value="">Sem salão</option>${state.areas.map((area) => `<option value="${area.id}" ${Number(item.area_id) === Number(area.id) ? "selected" : ""}>${PDV.escapeHtml(area.name)}</option>`).join("")}</select></label><label class="field"><span>Status</span><select name="status">${Object.entries(tableStatus).map(([value, label]) => `<option value="${value}" ${item.status === value ? "selected" : ""}>${label}</option>`).join("")}</select></label>${commonFields(item)}</div>`,
      categories: `${id}<div class="form-grid"><label class="field full"><span>Nome</span><input name="name" required maxlength="100" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Ícone opcional</span><input name="icon" maxlength="80" value="${PDV.escapeHtml(item.icon || "")}"></label><label class="field"><span>Ordem</span><input name="sort_order" type="number" min="0" value="${item.sort_order ?? 0}"></label>${commonFields(item)}</div>`,
      products: `${id}<input type="hidden" name="existing_image" value="${PDV.escapeHtml(item.image_path || "")}"><div class="form-grid"><label class="field"><span>Nome</span><input name="name" required maxlength="150" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Categoria</span><select name="category_id" required><option value="">Selecione</option>${state.categories.map((category) => `<option value="${category.id}" ${Number(item.category_id) === Number(category.id) ? "selected" : ""}>${PDV.escapeHtml(category.name)}</option>`).join("")}</select></label><label class="field full"><span>Descrição</span><textarea name="description" maxlength="5000">${PDV.escapeHtml(item.description || "")}</textarea></label><label class="field"><span>Preço</span><input name="price" inputmode="decimal" required value="${item.price ?? ""}"></label><label class="field"><span>Ordem</span><input name="sort_order" type="number" min="0" value="${item.sort_order ?? 0}"></label><label class="field full"><span>Imagem (JPG, PNG ou WebP, até 5 MB)</span><input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label><label class="check-field"><input name="available" type="checkbox" value="1" ${item.available !== false ? "checked" : ""}><span>Disponível para venda</span></label><label class="check-field"><input name="featured" type="checkbox" value="1" ${item.featured ? "checked" : ""}><span>Produto em destaque</span></label>${commonFields(item)}<div class="full"><span class="field-label">Grupos de complementos</span><div class="form-grid" style="margin-top:.65rem">${state.modifier_groups.map((group) => `<label class="check-field"><input type="checkbox" name="modifier_group_ids[]" value="${group.id}"><span>${PDV.escapeHtml(group.name)}</span></label>`).join("") || "<p>Cadastre grupos de complementos para relacioná-los.</p>"}</div></div></div>`,
      users: `${id}<div class="form-grid"><label class="field"><span>Nome</span><input name="name" required maxlength="120" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Login</span><input name="login" required maxlength="80" value="${PDV.escapeHtml(item.login || "")}"></label><label class="field"><span>E-mail</span><input name="email" type="email" maxlength="190" value="${PDV.escapeHtml(item.email || "")}"></label><label class="field"><span>Função</span><select name="role" required>${Object.entries(labels).map(([value, label]) => `<option value="${value}" ${item.role === value ? "selected" : ""}>${label}</option>`).join("")}</select></label><label class="field full"><span>${item.id ? "Nova senha (deixe vazio para manter)" : "Senha"}</span><input name="password" type="password" minlength="8" ${item.id ? "" : "required"}></label>${commonFields(item)}</div>`,
      modifier_groups: `${id}<div class="form-grid"><label class="field full"><span>Nome do grupo</span><input name="name" required maxlength="120" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Mínimo de escolhas</span><input name="min_choices" type="number" min="0" max="99" value="${item.min_choices ?? 0}"></label><label class="field"><span>Máximo de escolhas</span><input name="max_choices" type="number" min="1" max="99" value="${item.max_choices ?? 1}"></label><label class="field"><span>Ordem</span><input name="sort_order" type="number" min="0" value="${item.sort_order ?? 0}"></label><label class="check-field"><input name="required" type="checkbox" value="1" ${item.required ? "checked" : ""}><span>Escolha obrigatória</span></label>${commonFields(item)}</div>`,
      modifiers: `${id}<div class="form-grid"><label class="field"><span>Grupo</span><select name="modifier_group_id" required><option value="">Selecione</option>${state.modifier_groups.map((group) => `<option value="${group.id}" ${Number(item.modifier_group_id) === Number(group.id) ? "selected" : ""}>${PDV.escapeHtml(group.name)}</option>`).join("")}</select></label><label class="field"><span>Nome da opção</span><input name="name" required maxlength="120" value="${PDV.escapeHtml(item.name || "")}"></label><label class="field"><span>Acréscimo</span><input name="price_delta" inputmode="decimal" required value="${item.price_delta ?? "0.00"}"></label><label class="field"><span>Ordem</span><input name="sort_order" type="number" min="0" value="${item.sort_order ?? 0}"></label>${commonFields(item)}</div>`,
    };
    return `<form id="resourceForm" class="form-stack" enctype="multipart/form-data">${forms[resource]}</form>`;
  }

  async function openResourceForm(resource, id = null) {
    const item = id ? state[resource].find((row) => Number(row.id) === Number(id)) : {};
    PDV.openModal({ title: `${id ? "Editar" : "Adicionar"} ${resource === "users" ? "usuário" : resource === "tables" ? "mesa" : resource === "products" ? "produto" : "cadastro"}`, body: formFor(resource, item), footer: '<button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button><button class="btn btn-primary" id="saveResourceButton" type="button">Salvar</button>', large: resource === "products" });
    if (resource === "products" && id) {
      const data = await PDV.request(`/api/admin/resources.php?resource=products&action=list&product_id=${id}`);
      data.modifier_group_ids.forEach((groupId) => document.querySelector(`#resourceForm input[name="modifier_group_ids[]"][value="${groupId}"]`)?.setAttribute("checked", "checked"));
    }
    document.getElementById("saveResourceButton").onclick = () => saveResource(resource);
  }

  async function saveResource(resource) {
    const form = document.getElementById("resourceForm");
    if (!form.reportValidity()) return;
    const button = document.getElementById("saveResourceButton");
    PDV.setLoading(button, true);
    const formData = new FormData(form);
    formData.set("resource", resource);
    formData.set("action", "save");
    ["active", "available", "featured", "required"].forEach((name) => { if (form.elements[name] && !form.elements[name].checked) formData.set(name, "0"); });
    if (resource === "products") formData.set("modifier_group_ids", JSON.stringify(formData.getAll("modifier_group_ids[]")));
    try {
      const data = await PDV.request(`/api/admin/resources.php?resource=${resource}&action=save`, { method: "POST", body: formData });
      state[resource] = data.items;
      PDV.closeModal();
      renderResource(resource);
      if (resource === "areas") await loadResource("tables");
      if (resource === "modifier_groups") await loadResource("modifiers");
      PDV.toast("Cadastro salvo com sucesso.");
    } catch (error) {
      PDV.toast(error.message, "error");
      PDV.setLoading(button, false);
    }
  }

  async function deactivate(resource, id) {
    if (!await PDV.confirmAction("O cadastro ficará inativo e continuará preservado no histórico.", "Desativar")) return;
    try {
      const data = await PDV.request(`/api/admin/resources.php?resource=${resource}&action=deactivate`, { method: "POST", body: { id } });
      state[resource] = data.items;
      renderResource(resource);
      PDV.toast("Cadastro desativado.");
    } catch (error) { PDV.toast(error.message, "error"); }
  }

  async function loadSettings() {
    const data = await PDV.request("/api/admin/settings.php");
    const form = document.getElementById("settingsForm");
    Object.entries(data).forEach(([key, value]) => {
      const field = form.elements[key];
      if (!field) return;
      if (field.type === "checkbox") field.checked = Boolean(value);
      else field.value = value ?? "";
    });
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const values = Object.fromEntries(new FormData(form));
      values.service_fee_enabled = form.elements.service_fee_enabled.checked;
      values.restaurant_open = form.elements.restaurant_open.checked;
      PDV.setLoading(button, true);
      try { await PDV.request("/api/admin/settings.php", { method: "POST", body: values }); PDV.toast("Configurações salvas."); }
      catch (error) { PDV.toast(error.message, "error"); }
      finally { PDV.setLoading(button, false); }
    });
  }

  document.addEventListener("click", (event) => {
    const add = event.target.closest("[data-add-resource]");
    if (add) openResourceForm(add.dataset.addResource);
    const edit = event.target.closest("[data-edit-resource]");
    if (edit) openResourceForm(edit.dataset.editResource, edit.dataset.id);
    const remove = event.target.closest("[data-deactivate-resource]");
    if (remove) deactivate(remove.dataset.deactivateResource, Number(remove.dataset.id));
  });
  document.querySelectorAll("[data-resource-search]").forEach((input) => input.addEventListener("input", () => renderResource(input.dataset.resourceSearch, input.value)));

  (async () => {
    try {
      if (view === "dashboard") return loadDashboard();
      if (view === "settings") return loadSettings();
      if (view === "tables") { await loadResource("areas"); await loadResource("tables"); return; }
      if (view === "modifiers") { await loadResource("modifier_groups"); await loadResource("modifiers"); return; }
      if (view === "products") { await Promise.all([loadResource("categories"), loadResource("modifier_groups")]); await loadResource("products"); return; }
      if (["categories", "users"].includes(view)) return loadResource(view);
    } catch (error) { PDV.toast(error.message, "error", 6000); }
  })();
})();
