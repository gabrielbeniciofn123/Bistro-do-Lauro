(function () {
  "use strict";

  const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const toastRegion = document.getElementById("toastRegion");
  const modalRoot = document.getElementById("globalModal");
  const modalBody = document.getElementById("globalModalBody");
  const modalTitle = document.getElementById("globalModalTitle");
  const modalFooter = document.getElementById("globalModalFooter");

  async function request(url, options = {}) {
    const settings = { credentials: "same-origin", ...options };
    settings.headers = { Accept: "application/json", ...(options.headers || {}) };
    if (settings.method && settings.method !== "GET") {
      settings.headers["X-CSRF-Token"] = token;
    }
    if (settings.body && !(settings.body instanceof FormData) && typeof settings.body !== "string") {
      settings.headers["Content-Type"] = "application/json";
      settings.body = JSON.stringify(settings.body);
    }

    let response;
    try {
      response = await fetch(url, settings);
    } catch (error) {
      setConnection(false);
      throw new Error("Sem conexão com o servidor. Seus dados não foram descartados.");
    }
    setConnection(true);
    const payload = await response.json().catch(() => null);
    if (response.status === 401) {
      window.location.href = "/login.php";
      throw new Error("Sessão encerrada.");
    }
    if (!response.ok || !payload?.success) {
      throw new Error(payload?.message || "Não foi possível concluir a operação.");
    }
    return payload.data;
  }

  function toast(message, type = "success", timeout = 3500) {
    if (!toastRegion) return;
    const item = document.createElement("div");
    item.className = `toast ${type}`;
    item.textContent = message;
    toastRegion.appendChild(item);
    window.setTimeout(() => item.remove(), timeout);
  }

  function openModal({ title, body, footer = "", large = false }) {
    if (!modalRoot) return;
    modalTitle.textContent = title;
    modalBody.innerHTML = body;
    modalFooter.innerHTML = footer;
    modalRoot.querySelector(".modal")?.classList.toggle("modal-lg", large);
    modalRoot.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    window.setTimeout(() => modalRoot.querySelector("input, select, textarea, button")?.focus(), 0);
  }

  function closeModal() {
    if (!modalRoot) return;
    modalRoot.classList.add("hidden");
    document.body.style.overflow = "";
  }

  function confirmAction(message, confirmLabel = "Confirmar") {
    return new Promise((resolve) => {
      openModal({
        title: "Confirme a ação",
        body: `<p>${escapeHtml(message)}</p>`,
        footer: `<button class="btn btn-secondary" type="button" data-confirm="no">Voltar</button><button class="btn btn-danger" type="button" data-confirm="yes">${escapeHtml(confirmLabel)}</button>`,
      });
      modalFooter.onclick = (event) => {
        const choice = event.target.closest("[data-confirm]")?.dataset.confirm;
        if (!choice) return;
        closeModal();
        resolve(choice === "yes");
      };
    });
  }

  function money(value) {
    return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(value || 0));
  }

  function dateTime(value) {
    if (!value) return "—";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[char]));
  }

  function setLoading(element, state) {
    if (!element) return;
    element.disabled = state;
    element.classList.toggle("loading", state);
    if (state) element.dataset.originalText = element.textContent;
    else if (element.dataset.originalText) element.textContent = element.dataset.originalText;
  }

  function setConnection(online, statusText = null) {
    const status = document.getElementById("connectionStatus");
    if (!status) return;
    status.classList.toggle("offline", !online);
    const label = status.querySelector("span");
    if (label) label.textContent = statusText || (online ? "Conectado" : "Sem conexão");
  }

  function startPolling(task, options = {}) {
    const interval = Math.max(500, Number(options.interval) || 2000);
    const errorLabel = options.errorLabel || "Sincronização pausada";
    const errorMessage = options.errorMessage || "A atualização automática foi interrompida. Tentando reconectar…";
    let timer = null;
    let running = null;
    let stopped = false;
    let failures = 0;

    const schedule = () => {
      if (stopped) return;
      window.clearTimeout(timer);
      timer = window.setTimeout(run, interval);
    };

    async function run() {
      if (stopped) return;
      if (running) return running;
      window.clearTimeout(timer);
      running = (async () => {
        try {
          await task();
          if (failures > 0) toast("Atualização automática restabelecida.", "success", 4500);
          failures = 0;
          setConnection(true);
        } catch (error) {
          failures += 1;
          setConnection(false, errorLabel);
          if (failures === 1 || failures % 10 === 0) {
            toast(errorMessage, "error", 6000);
          }
        } finally {
          running = null;
          schedule();
        }
      })();
      return running;
    }

    if (options.immediate === false) schedule();
    else run();

    return {
      runNow() {
        window.clearTimeout(timer);
        return run();
      },
      stop() {
        stopped = true;
        window.clearTimeout(timer);
      },
    };
  }

  modalRoot?.addEventListener("click", (event) => {
    if (event.target === modalRoot || event.target.closest("[data-close-modal]")) closeModal();
  });
  document.addEventListener("keydown", (event) => { if (event.key === "Escape") closeModal(); });

  const sidebar = document.getElementById("sidebar");
  const scrim = document.getElementById("sidebarScrim");
  const menuButton = document.getElementById("mobileMenuButton");
  const toggleMenu = (open) => {
    sidebar?.classList.toggle("open", open);
    scrim?.classList.toggle("hidden", !open);
    menuButton?.setAttribute("aria-expanded", String(open));
  };
  menuButton?.addEventListener("click", () => toggleMenu(!sidebar?.classList.contains("open")));
  scrim?.addEventListener("click", () => toggleMenu(false));
  window.addEventListener("online", () => setConnection(true));
  window.addEventListener("offline", () => setConnection(false));
  setConnection(navigator.onLine);

  window.PDV = { request, toast, openModal, closeModal, confirmAction, money, dateTime, escapeHtml, setLoading, setConnection, startPolling, csrfToken: token };
})();
