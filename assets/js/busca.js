/**
 * Busca: overlay rápido, sugestões enquanto digita e
 * placeholder rotativo no campo principal.
 * Dados vêm de api/sugestoes.php (MySQL). Sem serviços externos.
 */

const base = document.body.dataset.base || '';
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function sugestoes(input) {
  const lista = document.getElementById(input.dataset.sugestoes);
  if (!lista) return;
  let timer = 0, ctrl = null, foco = -1;

  const limpar = () => { lista.innerHTML = ''; foco = -1; input.removeAttribute('aria-activedescendant'); };
  const itens = () => [...lista.querySelectorAll('.sugestao')];
  const focar = (i) => {
    const el = itens();
    el.forEach((x) => x.classList.remove('is-foco'));
    if (!el.length) return;
    foco = (i + el.length) % el.length;
    el[foco].classList.add('is-foco');
    input.setAttribute('aria-activedescendant', el[foco].id);
  };

  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-controls', lista.id);

  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) { limpar(); return; }
    timer = setTimeout(async () => {
      ctrl?.abort();
      ctrl = new AbortController();
      try {
        const r = await fetch(`${base}/api/sugestoes.php?q=${encodeURIComponent(q)}`, { signal: ctrl.signal });
        const d = await r.json();
        const todos = [...d.temas, ...d.propostas];
        lista.innerHTML = todos.map((s, i) => `
          <a class="sugestao" role="option" id="${lista.id}-${i}" href="${esc(s.url)}">
            <span class="sugestao__tipo">${esc(s.tipo)}</span>
            <span class="sugestao__txt">${esc(s.nome)}<small>${esc(s.info || '')}</small></span>
            <span class="sugestao__seta" aria-hidden="true">→</span>
          </a>`).join('') || `<p class="sugestao"><span class="sugestao__tipo">Busca</span><span class="sugestao__txt">Pressione Enter para buscar “${esc(q)}”</span></p>`;
        foco = -1;
      } catch (_) { /* requisição cancelada ou offline */ }
    }, 180);
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); focar(foco + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); focar(foco - 1); }
    else if (e.key === 'Enter' && foco >= 0) { e.preventDefault(); itens()[foco]?.click(); }
    else if (e.key === 'Escape' && lista.innerHTML) { e.stopPropagation(); limpar(); }
  });
  input.addEventListener('blur', () => setTimeout(limpar, 200));
}

function cicloPlaceholder(input) {
  let lista;
  try { lista = JSON.parse(input.dataset.ciclo); } catch (_) { return; }
  if (!lista?.length) return;
  const reduzido = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let i = 0, c = 0, apagando = false;
  const tick = () => {
    if (document.activeElement === input || input.value) { setTimeout(tick, 1200); return; }
    const alvo = lista[i];
    if (reduzido) { input.placeholder = alvo; i = (i + 1) % lista.length; setTimeout(tick, 2800); return; }
    if (!apagando) {
      c++;
      input.placeholder = alvo.slice(0, c) + '▍';
      if (c >= alvo.length) { apagando = true; setTimeout(tick, 1800); return; }
      setTimeout(tick, 55);
    } else {
      c--;
      input.placeholder = alvo.slice(0, c) + '▍';
      if (c <= 0) { apagando = false; i = (i + 1) % lista.length; }
      setTimeout(tick, 28);
    }
  };
  setTimeout(tick, 1600);
}

export function iniciarBusca() {
  document.querySelectorAll('input[data-sugestoes]').forEach(sugestoes);
  document.querySelectorAll('input[data-ciclo]').forEach(cicloPlaceholder);

  const overlay = document.getElementById('busca-overlay');
  if (!overlay) return;
  const abrir = () => {
    if (overlay.open) return;
    overlay.showModal();
    setTimeout(() => overlay.querySelector('input')?.focus(), 30);
  };
  document.addEventListener('votai:abrir-busca', abrir);
  document.addEventListener('click', (e) => {
    const a = e.target.closest('.rail__item[data-abrir-busca]');
    if (a) { e.preventDefault(); abrir(); }
  });
  document.addEventListener('keydown', (e) => {
    const alvo = e.target;
    const digitando = alvo.closest?.('input, textarea, select, [contenteditable]');
    if (e.key === '/' && !digitando) { e.preventDefault(); abrir(); }
  });
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.close(); });
}
