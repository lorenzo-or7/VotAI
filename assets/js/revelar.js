/**
 * Revelações na rolagem (máscaras de título, fade), contadores
 * e o número-eco do índice de temas.
 */

const reduzido = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

function contar(el) {
  const alvo = parseInt(el.dataset.contar, 10) || 0;
  if (reduzido()) { el.textContent = String(alvo).padStart(2, '0'); return; }
  const t0 = performance.now(), dur = 1300;
  const passo = (t) => {
    const p = Math.min(1, (t - t0) / dur);
    const v = Math.round(alvo * (1 - Math.pow(1 - p, 3)));
    el.textContent = String(v).padStart(2, '0');
    if (p < 1) requestAnimationFrame(passo);
  };
  requestAnimationFrame(passo);
}

export function iniciarRevelar() {
  const els = document.querySelectorAll('[data-reveal], .mascara, .tracos, [data-contar]');
  if (!('IntersectionObserver' in window)) { els.forEach((e) => e.classList.add('is-in')); return; }

  const io = new IntersectionObserver((entradas) => {
    entradas.forEach((e) => {
      if (!e.isIntersecting) return;
      e.target.classList.add('is-in');
      if (e.target.dataset.contar !== undefined) contar(e.target);
      io.unobserve(e.target);
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
  els.forEach((e) => io.observe(e));

  // Título do hero entra imediatamente
  requestAnimationFrame(() => document.querySelectorAll('[data-reveal-now] .mascara').forEach((m) => m.classList.add('is-in')));

  // Número-eco do índice de temas
  const eco = document.querySelector('[data-eco]');
  if (eco) {
    document.querySelectorAll('[data-eco-num]').forEach((a) => {
      const trocar = () => {
        if (eco.textContent === a.dataset.ecoNum) return;
        eco.style.opacity = '0';
        setTimeout(() => { eco.textContent = a.dataset.ecoNum; eco.style.opacity = '1'; }, 160);
      };
      a.addEventListener('pointerenter', trocar);
      a.addEventListener('focus', trocar);
    });
  }
}
