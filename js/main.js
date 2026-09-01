/**
 * BISTRÔ SÃO LAURO — Interatividade do site
 * Depende de config.js e data.js (carregados antes deste arquivo).
 */
(function () {
  "use strict";

  /* ---- Ícones SVG inline ---- */
  const ICONES = {
    utensils: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 2v7M9.5 2v7M12 2v7M9.5 9v13"/><path d="M16.5 2c-1.5 0-2.5 2-2.5 4.5S15 11 16.5 11 19 8.5 19 6.5 18 2 16.5 2z"/><path d="M16.5 11v11"/></svg>',
    meat:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4a6 6 0 0 1 6 6c0 3-2 5-4 7l-3 3a2.5 2.5 0 1 1-3-3l3-3c2-2 4-4 4-7a3 3 0 0 0-3-3z"/><circle cx="6" cy="18" r="2"/></svg>',
    fish:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12c3-4 8-6 12-6 3 0 5 2 6 6-1 4-3 6-6 6-4 0-9-2-12-6z"/><path d="M15 9v6"/><circle cx="7" cy="11" r="0.6" fill="currentColor" stroke="none"/></svg>',
    pot:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11h16v4a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5v-4z"/><path d="M2 11h20"/><path d="M8 11V8a1.5 1.5 0 0 1 3 0M13 11V8a1.5 1.5 0 0 1 3 0"/><path d="M12 3v3"/></svg>',
    mug:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h11v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M5 8V5h11v3"/></svg>',
    bottle:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2h4v3l2 3v12a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V8l2-3V2z"/><path d="M8.5 12h7"/></svg>',
    juice:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8h10l-1 11a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2L7 8z"/><path d="M9 8l1-5h4l1 5"/><path d="M16 4l3-2"/></svg>',
    martini:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16l-8 9-8-9z"/><path d="M12 13v7"/><path d="M8 20h8"/></svg>',
    spirit:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10l-1 16a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2L7 3z"/><path d="M7.5 10h9"/></svg>',
    glass:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l-1 7a5 5 0 0 1-10 0L6 3z"/><path d="M12 14v7"/><path d="M8 21h8"/></svg>'
  };

  const formatarPreco = (v) =>
    "R$ " + v.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const escHtml = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  /* ============ NAVEGAÇÃO ============ */
  const nav = document.getElementById("nav");
  const botaoHamburguer = document.getElementById("botaoHamburguer");
  const menuMobile = document.getElementById("menuMobile");

  window.addEventListener("scroll", () => {
    nav.classList.toggle("nav--rolado", window.scrollY > 40);
  }, { passive: true });

  function alternarMenu(abrir) {
    const deveAbrir = abrir ?? !menuMobile.classList.contains("aberto");
    menuMobile.classList.toggle("aberto", deveAbrir);
    botaoHamburguer.setAttribute("aria-expanded", String(deveAbrir));
    document.body.style.overflow = deveAbrir ? "hidden" : "";
  }

  botaoHamburguer.addEventListener("click", () => alternarMenu());
  menuMobile.querySelectorAll("a").forEach((link) =>
    link.addEventListener("click", () => alternarMenu(false))
  );
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && menuMobile.classList.contains("aberto")) alternarMenu(false);
  });

  /* ============ REVELAR AO ROLAR ============ */
  const observadorReveal = new IntersectionObserver((entradas) => {
    entradas.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add("visivel");
        observadorReveal.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll(".revelar").forEach((el) => observadorReveal.observe(el));

  /* ============ CATEGORIAS DO CARDÁPIO ============ */
  const containerCategorias = document.getElementById("categoriasCardapio");
  const gradeCardapio = document.getElementById("gradeCardapio");

  function montarCategorias() {
    if (!containerCategorias) return;
    MENU_DATA.forEach((cat, i) => {
      const btn = document.createElement("button");
      btn.className = "categoria-botao" + (i === 0 ? " ativo" : "");
      btn.textContent = cat.nome;
      btn.dataset.alvo = cat.id;
      containerCategorias.appendChild(btn);
    });

    containerCategorias.addEventListener("click", (e) => {
      const btn = e.target.closest(".categoria-botao");
      if (!btn) return;
      const secao = document.getElementById(btn.dataset.alvo);
      if (secao) secao.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  /* ============ RENDERIZAR CARDÁPIO ============ */
  function criarCartao(item) {
    const selo = item.selo
      ? `<span class="prato-selo">${escHtml(item.selo)}</span>` : "";
    const busca = escHtml((item.nome + " " + item.descricao).toLowerCase());
    return `
      <article class="prato-cartao revelar" data-busca="${busca}">
        ${selo}
        <div class="prato-cabecalho">
          <span class="prato-nome">${escHtml(item.nome)}</span>
          <span class="prato-linha-pontilhada"></span>
          <span class="prato-preco">${formatarPreco(item.preco)}</span>
        </div>
        <p class="prato-descricao">${escHtml(item.descricao)}</p>
        <span class="prato-peso">${escHtml(item.peso)}</span>
      </article>
    `;
  }

  function renderizarCardapio() {
    if (!gradeCardapio) return;
    gradeCardapio.innerHTML = MENU_DATA.map((cat) => `
      <section class="secao-categoria" id="${escHtml(cat.id)}" data-categoria>
        <div class="categoria-cabecalho">
          <span class="categoria-icone">${ICONES[cat.icone] || ""}</span>
          <h3 class="categoria-titulo">${escHtml(cat.nome)}</h3>
          <p class="categoria-subtitulo">${escHtml(cat.subtitulo)}</p>
        </div>
        <div class="pratos-grade">
          ${cat.itens.map(criarCartao).join("")}
        </div>
      </section>
    `).join("");

    /* Revelar os novos cartões */
    gradeCardapio.querySelectorAll(".revelar").forEach((el) => observadorReveal.observe(el));
  }

  montarCategorias();
  renderizarCardapio();

  /* ============ SCROLLSPY ============ */
  const setAtivo = (id) => {
    document.querySelectorAll(".categoria-botao[data-alvo]").forEach((b) =>
      b.classList.toggle("ativo", b.dataset.alvo === id)
    );
  };

  const secoes = document.querySelectorAll("[data-categoria]");
  if (secoes.length && "IntersectionObserver" in window) {
    const observadorSpy = new IntersectionObserver((entradas) => {
      entradas.forEach((e) => {
        if (e.isIntersecting) setAtivo(e.target.id);
      });
    }, { rootMargin: "-20% 0px -60% 0px", threshold: 0 });

    secoes.forEach((s) => observadorSpy.observe(s));
  }

  /* ============ BUSCA ============ */
  const inputBusca = document.getElementById("buscaCardapio");
  const semResultado = document.getElementById("semResultado");

  if (inputBusca) {
    inputBusca.addEventListener("input", () => {
      const q = inputBusca.value.trim().toLowerCase();
      const secoesCat = Array.from(document.querySelectorAll("[data-categoria]"));
      let totalVisiveis = 0;

      secoesCat.forEach((secao) => {
        const itens = Array.from(secao.querySelectorAll(".prato-cartao"));
        let visiveis = 0;
        itens.forEach((item) => {
          const match = !q || item.dataset.busca.includes(q);
          item.hidden = !match;
          if (match) visiveis++;
        });
        secao.hidden = visiveis === 0;
        totalVisiveis += visiveis;
      });

      if (semResultado) semResultado.hidden = totalVisiveis > 0;
    });
  }

  /* ============ HORÁRIOS ============ */
  const gradeHorarios = document.getElementById("gradeHorarios");
  const listaRodape = document.getElementById("listaHorariosRodape");

  if (gradeHorarios) {
    BISTRO_CONFIG.horarios.forEach((h) => {
      const cartao = document.createElement("div");
      cartao.className = "horario-cartao";
      cartao.innerHTML = `<p class="dias">${h.dias}</p><p class="horario">${h.horario}</p>`;
      gradeHorarios.appendChild(cartao);
    });

    if (BISTRO_CONFIG.diasFechado) {
      const fechado = document.createElement("div");
      fechado.className = "horario-cartao";
      fechado.innerHTML = `<p class="dias" style="opacity:0.55">Segunda a Quarta</p><p class="horario" style="color:var(--cor-carvao);opacity:0.5">Fechado</p>`;
      gradeHorarios.appendChild(fechado);
    }
  }

  if (listaRodape) {
    BISTRO_CONFIG.horarios.forEach((h) => {
      const li = document.createElement("li");
      li.textContent = `${h.dias}: ${h.horario}`;
      listaRodape.appendChild(li);
    });
  }

  /* ============ ANO NO RODAPÉ ============ */
  const anoEl = document.getElementById("anoAtual");
  if (anoEl) anoEl.textContent = new Date().getFullYear();

  /* ============ CONTATO WHATSAPP (atualiza os links a partir do config) ============ */
  const waBase = `https://wa.me/${BISTRO_CONFIG.whatsappNumero}`;
  document.querySelectorAll('a[href*="wa.me"]').forEach((a) => {
    a.href = waBase;
  });

})();
