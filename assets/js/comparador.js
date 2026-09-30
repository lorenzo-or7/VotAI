/**
 * Comparador:
 *  - filtro por subtema e "só propostas com meta, prazo ou custo"
 *  - MODO APRESENTAÇÃO: tela cheia, sem menu, um slide por subtema
 *    Teclas: → / espaço / PageDown avançam · ← / PageUp voltam · Home / End · F tela cheia · Esc sai
 */

const pad2 = (n) => String(n).padStart(2, '0');

function filtrosComparacao(comp) {
  const chips = comp.querySelector('[data-comp-subs]');
  const quant = comp.querySelector('[data-so-quant]');
  let sub = '';
  let soQuant = false;

  const aplicar = () => {
    comp.querySelectorAll('.comp__cel[data-cand]').forEach((cel) => {
      const itens = [...cel.querySelectorAll('.comp__item')];
      if (itens.length) {
        let visiveis = 0;
        itens.forEach((it) => {
          const ok = (!sub || it.dataset.sub === sub) && (!soQuant || it.dataset.quant === '1');
          it.hidden = !ok;
          if (ok) visiveis++;
        });
        const vazio = cel.querySelector('[data-comp-vazio]');
        if (vazio) vazio.hidden = visiveis > 0;
      }
      const ul = cel.querySelector('.comp__valores');
      if (ul) {
        const lis = [...ul.querySelectorAll('li[data-sub]')];
        lis.forEach((li) => { li.hidden = !!sub && li.dataset.sub !== sub; });
        let aviso = cel.querySelector('[data-comp-vazio-v]');
        if (!aviso) {
          aviso = document.createElement('p');
          aviso.className = 'nao-informado';
          aviso.dataset.compVazioV = '';
          aviso.textContent = 'Não informado no documento para este subtema.';
          ul.after(aviso);
        }
        aviso.hidden = lis.some((li) => !li.hidden);
      }
    });
    comp.querySelectorAll('.comp__quem[data-cand]').forEach((q) => {
      const n = comp.querySelectorAll(`.comp__cel[data-cand="${q.dataset.cand}"] .comp__item:not([hidden])`).length;
      const el = q.querySelector('[data-comp-conta]');
      if (el) el.textContent = pad2(n);
    });
    // comparando todos os temas: esconde as linhas dos outros temas
    comp.querySelectorAll('[data-grupo-tema]').forEach((linha) => { linha.hidden = !!sub && linha.dataset.grupoTema !== sub; });
    comp.classList.toggle('comp--quant', soQuant);
  };

  chips?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-sub]');
    if (!b) return;
    sub = b.dataset.sub;
    chips.querySelectorAll('[data-sub]').forEach((x) => {
      x.classList.toggle('is-ativo', x === b);
      x.setAttribute('aria-pressed', String(x === b));
    });
    aplicar();
  });
  quant?.addEventListener('click', () => {
    soQuant = !soQuant;
    quant.setAttribute('aria-pressed', String(soQuant));
    aplicar();
  });
}

function apresentacao(apres) {
  // o painel vira filho direto do <body> para poder "desligar" o resto da página enquanto aberto
  document.body.appendChild(apres);
  const slides = [...apres.querySelectorAll('[data-slide]')];
  const pos = apres.querySelector('[data-apres-pos]');
  const barra = apres.querySelector('[data-apres-barra]');
  const btnTela = apres.querySelector('[data-apres-tela]');
  let i = 0;
  let anterior = null;
  let aberto = false;

  const ir = (n) => {
    i = Math.max(0, Math.min(slides.length - 1, n));
    slides.forEach((s, k) => {
      s.classList.toggle('is-atual', k === i);
      s.inert = k !== i;
    });
    pos.textContent = `${pad2(i + 1)} / ${pad2(slides.length)}`;
    barra.style.transform = `scaleX(${(i + 1) / slides.length})`;
    try { history.replaceState(null, '', `${location.pathname}${location.search}#slide-${i + 1}`); } catch { /* ignore */ }
  };

  const outros = () => [...document.body.children].filter((el) => el !== apres && el.tagName !== 'SCRIPT' && el.tagName !== 'DIALOG');

  const abrir = (inicio = 0) => {
    if (aberto) return;
    aberto = true;
    anterior = document.activeElement;
    outros().forEach((el) => { el.inert = true; });
    apres.hidden = false;
    document.documentElement.classList.add('em-apresentacao');
    ir(inicio);
    apres.focus({ preventScroll: true });
  };

  const fechar = () => {
    if (!aberto) return;
    aberto = false;
    if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
    apres.hidden = true;
    outros().forEach((el) => { el.inert = false; });
    document.documentElement.classList.remove('em-apresentacao');
    try { history.replaceState(null, '', location.pathname.replace(/\/apresentar\/?$/, '') + location.search.replace(/([?&])apresentar=1&?/, '$1').replace(/[?&]$/, '')); } catch { /* ignore */ }
    anterior?.focus?.({ preventScroll: true });
  };

  const telaCheia = () => {
    if (document.fullscreenElement) { document.exitFullscreen?.().catch(() => {}); return; }
    apres.requestFullscreen?.().catch(() => {});
  };
  document.addEventListener('fullscreenchange', () => {
    btnTela.textContent = document.fullscreenElement ? 'Sair da tela cheia' : 'Tela cheia';
  });
  if (!apres.requestFullscreen) btnTela.hidden = true;

  document.querySelectorAll('[data-apres-abrir]').forEach((b) => b.addEventListener('click', () => { abrir(0); telaCheia(); }));
  apres.querySelector('[data-apres-ant]').addEventListener('click', () => ir(i - 1));
  apres.querySelector('[data-apres-prox]').addEventListener('click', () => ir(i + 1));
  apres.querySelector('[data-apres-sair]').addEventListener('click', fechar);
  btnTela.addEventListener('click', telaCheia);

  apres.addEventListener('keydown', (e) => {
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    const k = e.key;
    if (['ArrowRight', 'PageDown', ' ', 'Enter'].includes(k) && !e.target.closest('button, a')) { e.preventDefault(); ir(i + 1); }
    else if (['ArrowRight', 'PageDown'].includes(k)) { e.preventDefault(); ir(i + 1); }
    else if (['ArrowLeft', 'PageUp'].includes(k)) { e.preventDefault(); ir(i - 1); }
    else if (k === 'Home') { e.preventDefault(); ir(0); }
    else if (k === 'End') { e.preventDefault(); ir(slides.length - 1); }
    else if (k === 'f' || k === 'F') { e.preventDefault(); telaCheia(); }
    else if (k === 'Escape') { e.preventDefault(); fechar(); }
  });

  // toque: deslizar para os lados
  let x0 = null;
  apres.querySelector('[data-apres-palco]').addEventListener('pointerdown', (e) => { x0 = e.clientX; });
  apres.querySelector('[data-apres-palco]').addEventListener('pointerup', (e) => {
    if (x0 === null) return;
    const dx = e.clientX - x0;
    x0 = null;
    if (Math.abs(dx) > 60) ir(i + (dx < 0 ? 1 : -1));
  });

  // abrir direto pelo link ".../apresentar" ou "#slide-3"
  const m = location.hash.match(/^#slide-(\d+)$/);
  if (apres.hasAttribute('data-apres-auto') || m) abrir(m ? parseInt(m[1], 10) - 1 : 0);
}

export function iniciarComparador() {
  const comp = document.querySelector('[data-comp]');
  if (comp) filtrosComparacao(comp);
  const apres = document.querySelector('[data-apres]');
  if (apres) apresentacao(apres);
}
