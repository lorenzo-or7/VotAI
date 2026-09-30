/** Tema claro/escuro — botão "urna". Preferência salva em localStorage. */

const CHAVE = 'votai-tema';

function aplicar(tema, animar) {
  const raiz = document.documentElement;
  raiz.dataset.theme = tema;
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', tema === 'light' ? '#f4f3eb' : '#060807');
  document.querySelectorAll('.js-tema').forEach((b) => {
    const claro = tema === 'light';
    b.setAttribute('aria-pressed', String(claro));
    b.setAttribute('aria-label', claro ? 'Ativar modo escuro' : 'Ativar modo claro');
    if (animar) {
      b.classList.remove('is-trocando');
      void b.offsetWidth;
      b.classList.add('is-trocando');
      setTimeout(() => b.classList.remove('is-trocando'), 500);
    }
  });
  document.dispatchEvent(new CustomEvent('votai:tema', { detail: tema }));
}

export function iniciarTema() {
  aplicar(document.documentElement.dataset.theme === 'light' ? 'light' : 'dark', false);
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-tema');
    if (!btn) return;
    const novo = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    try { localStorage.setItem(CHAVE, novo); } catch (_) { /* navegação privada */ }
    aplicar(novo, true);
  });
}
