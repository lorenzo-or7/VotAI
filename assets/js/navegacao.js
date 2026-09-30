/**
 * Navegação: trilho vertical (desktop) e "urna de navegação" (celular).
 * - progresso de rolagem no trilho
 * - item ativo conforme a seção visível (na home)
 * - teclado de urna com visor
 */

export function iniciarNavegacao() {
  const barra = document.querySelector('.rail__progresso span');
  const dockAtual = document.querySelector('[data-dock-atual]');

  // Progresso de leitura
  let pendente = false;
  const atualizarProgresso = () => {
    pendente = false;
    const max = document.documentElement.scrollHeight - innerHeight;
    barra?.style.setProperty('--progresso', max > 0 ? (scrollY / max).toFixed(4) : '0');
  };
  addEventListener('scroll', () => { if (!pendente) { pendente = true; requestAnimationFrame(atualizarProgresso); } }, { passive: true });
  atualizarProgresso();

  // Seção ativa (home)
  const secoes = [...document.querySelectorAll('[data-secao]')];
  if (secoes.length && document.body.dataset.page === 'inicio') {
    const marcar = (id) => {
      document.querySelectorAll('[data-nav]').forEach((a) => {
        const ativo = a.dataset.nav === id;
        a.classList.toggle('is-ativo', ativo);
        ativo ? a.setAttribute('aria-current', 'true') : a.removeAttribute('aria-current');
        if (ativo && dockAtual && a.classList.contains('rail__item')) dockAtual.textContent = a.querySelector('.rail__label')?.textContent || '';
      });
    };
    const io = new IntersectionObserver((entradas) => {
      entradas.forEach((e) => { if (e.isIntersecting) marcar(e.target.dataset.secao); });
    }, { rootMargin: '-45% 0px -50% 0px' });
    secoes.forEach((s) => io.observe(s));
  }

  // Urna de navegação (celular/tablet)
  const painel = document.getElementById('urna-nav');
  const botao = document.querySelector('[data-dock]');
  const visor = painel?.querySelector('[data-visor]');
  if (!painel || !botao) return;

  const abrir = () => {
    painel.hidden = false;
    botao.setAttribute('aria-expanded', 'true');
    painel.querySelectorAll('.tecla').forEach((t, i) => t.style.setProperty('--i', i));
    visor.textContent = 'Escolha uma seção';
    document.body.style.overflow = 'hidden';
    painel.querySelector('.tecla')?.focus({ preventScroll: true });
  };
  const fechar = () => {
    painel.hidden = true;
    botao.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    botao.focus({ preventScroll: true });
  };

  botao.addEventListener('click', abrir);
  painel.addEventListener('click', (e) => {
    if (e.target.closest('[data-fechar-urna]')) { fechar(); return; }
    const tecla = e.target.closest('a.tecla');
    if (!tecla) return;
    e.preventDefault();
    const n = tecla.querySelector('.tecla__n')?.textContent || '';
    visor.textContent = `${n.padStart(2, '0')} — ${tecla.dataset.tecla}`;
    tecla.classList.add('is-pressionada');
    const reduzido = matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(() => {
      tecla.classList.remove('is-pressionada');
      if (tecla.hasAttribute('data-abrir-busca')) {
        fechar();
        document.dispatchEvent(new CustomEvent('votai:abrir-busca'));
        return;
      }
      const href = tecla.getAttribute('href');
      if (href.startsWith('#')) {
        fechar();
        document.querySelector(href)?.scrollIntoView({ behavior: reduzido ? 'auto' : 'smooth' });
      } else {
        location.href = href;
      }
    }, reduzido ? 0 : 260);
  });
  painel.querySelectorAll('a.tecla').forEach((t) => {
    t.addEventListener('pointerenter', () => { visor.textContent = t.dataset.tecla; });
  });
  document.addEventListener('keydown', (e) => {
    if (painel.hidden) return;
    if (e.key === 'Escape') fechar();
    const n = parseInt(e.key, 10);
    if (n >= 1 && n <= 9) painel.querySelectorAll('a.tecla')[n - 1]?.click();
    if (e.key === 'Tab') { // mantém o foco dentro do painel
      const foco = [...painel.querySelectorAll('a, button')];
      const i = foco.indexOf(document.activeElement);
      if (e.shiftKey && i <= 0) { e.preventDefault(); foco.at(-1).focus(); }
      else if (!e.shiftKey && i === foco.length - 1) { e.preventDefault(); foco[0].focus(); }
    }
  });
}
