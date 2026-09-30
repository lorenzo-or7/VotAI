/**
 * Filtros sem recarregar a página:
 *  - página de tema: subtemas + "ver todas"
 *  - página da candidatura: mapa de propostas por tema
 *  - comparador: limite de 2 a 3 candidaturas
 */

const pad2 = (n) => String(n).padStart(2, '0');

function atualizarUrl(chave, valor) {
  const u = new URL(location.href);
  valor ? u.searchParams.set(chave, valor) : u.searchParams.delete(chave);
  history.replaceState(null, '', u.pathname + u.search);
}

function filtroTema() {
  const barra = document.querySelector('[data-filtros="sub"]');
  const alvo = document.querySelector('[data-filtro-alvo]');
  if (!barra || !alvo) return;
  const blocos = [...alvo.querySelectorAll('[data-bloco]')];
  const expandidos = new Set();

  const aplicar = (sub) => {
    barra.querySelectorAll('[data-filtro]').forEach((a) => a.classList.toggle('is-ativo', a.dataset.filtro === sub));
    blocos.forEach((bloco, bi) => {
      const fichas = [...bloco.querySelectorAll('.ficha')];
      if (!fichas.length) return;
      const lim = parseInt(bloco.dataset.visiveis, 10) || 3;
      const combinam = fichas.filter((f) => !sub || f.dataset.sub === sub);
      fichas.forEach((f) => f.classList.add('is-oculta'));
      combinam.forEach((f, i) => { if (expandidos.has(bi) || sub || i < lim) f.classList.remove('is-oculta'); });
      const conta = bloco.querySelector('[data-conta]');
      if (conta) conta.textContent = pad2(combinam.length);
      const vazio = bloco.querySelector('[data-vazio-filtro]');
      if (vazio) vazio.hidden = combinam.length > 0;
      const ver = bloco.querySelector('[data-ver-todas]');
      if (ver) {
        ver.hidden = expandidos.has(bi) || !!sub || combinam.length <= lim;
        ver.querySelector('[data-ver-n]').textContent = `(${pad2(combinam.length)})`;
        ver.onclick = () => { expandidos.add(bi); aplicar(sub); };
      }
    });
  };

  barra.addEventListener('click', (e) => {
    const a = e.target.closest('[data-filtro]');
    if (!a || a.getAttribute('aria-disabled') === 'true') { if (a) e.preventDefault(); return; }
    e.preventDefault();
    aplicar(a.dataset.filtro);
    try { history.replaceState(null, '', a.getAttribute('href').split('#')[0]); } catch { /* ignore */ }
    const comp = document.querySelector('.tema-topo [data-compartilhar]');
    if (comp) comp.dataset.url = new URL(a.getAttribute('href').split('#')[0], location.href).href;
    a.scrollIntoView({ inline: 'nearest', block: 'nearest' });
  });
  aplicar(alvo.dataset.subInicial || '');
}

function filtroCandidato() {
  const sec = document.querySelector('[data-filtro-cat]');
  if (!sec) return;
  const botoes = [...sec.querySelectorAll('.mapa__item')];
  const grupos = [...sec.querySelectorAll('[data-grupo]')];
  const aplicar = (cat, rolar) => {
    botoes.forEach((b) => b.classList.toggle('is-ativo', b.dataset.cat === cat));
    botoes.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.cat === cat)));
    grupos.forEach((g) => g.classList.toggle('is-oculto', !!cat && g.dataset.grupo !== cat));
    if (rolar) {
      const g = sec.querySelector('.grupos');
      if (g.getBoundingClientRect().top > innerHeight * 0.6) g.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };
  sec.addEventListener('click', (e) => {
    const b = e.target.closest('.mapa__item');
    if (!b || b.disabled) return;
    aplicar(b.dataset.cat, true);
    atualizarUrl('tema', b.dataset.cat);
  });
  const inicial = sec.dataset.catInicial;
  if (inicial && grupos.some((g) => g.dataset.grupo === inicial)) aplicar(inicial, false);

  // subtemas dentro de cada tema
  sec.querySelectorAll('[data-grupo-subs]').forEach((barra) => {
    const grupo = barra.closest('[data-grupo]');
    barra.addEventListener('click', (e) => {
      const b = e.target.closest('[data-sub]');
      if (!b) return;
      barra.querySelectorAll('[data-sub]').forEach((x) => { x.classList.toggle('is-ativo', x === b); x.setAttribute('aria-pressed', String(x === b)); });
      grupo.querySelectorAll('.ficha').forEach((f) => { f.classList.toggle('is-oculta', !!b.dataset.sub && f.dataset.sub !== b.dataset.sub); });
    });
  });
}

function seletorComparar() {
  document.querySelectorAll('[data-comparar]').forEach((form) => {
    const caixas = [...form.querySelectorAll('input[type="checkbox"]')];
    const aviso = form.querySelector('[data-aviso]');
    const atualizar = () => {
      const n = caixas.filter((c) => c.checked).length;
      caixas.forEach((c) => {
        const bloquear = n >= 3 && !c.checked;
        c.disabled = bloquear;
        c.closest('.marcador')?.classList.toggle('is-bloqueado', bloquear);
      });
      if (aviso && n >= 3) aviso.textContent = 'Máximo de 3 candidaturas selecionadas.';
      else if (aviso && aviso.dataset.auto) aviso.textContent = '';
    };
    caixas.forEach((c) => c.addEventListener('change', () => { if (aviso) aviso.dataset.auto = '1'; atualizar(); }));
    form.addEventListener('submit', (e) => {
      const n = caixas.filter((c) => c.checked).length;
      const tema = form.querySelector('select[name="tema"]')?.value;
      let msg = '';
      if (n < 2) msg = 'Selecione pelo menos 2 candidaturas.';
      else if (!tema) msg = 'Escolha um tema para comparar.';
      if (msg) { e.preventDefault(); aviso.textContent = msg; aviso.dataset.auto = ''; }
    });
    atualizar();
  });
}

export function iniciarFiltros() {
  filtroTema();
  filtroCandidato();
  seletorComparar();
}
