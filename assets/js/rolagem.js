/**
 * Faixas horizontais (temas / subtemas): no computador ganham setas nas
 * pontas, rolagem com a roda do mouse e "arrastar para o lado".
 * No celular continua o deslize com o dedo, como antes.
 */
export function iniciarRolagem() {
  document.querySelectorAll('.filtros-wrap > .filtros').forEach((trilho) => {
    const caixa = trilho.parentElement;
    caixa.classList.add('rolavel');

    const seta = (lado) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = `rolavel__seta rolavel__seta--${lado}`;
      b.setAttribute('aria-label', lado === 'esq' ? 'Ver itens anteriores' : 'Ver mais itens');
      b.tabIndex = -1;   // pelo teclado os próprios itens já rolam a faixa
      b.innerHTML = `<span aria-hidden="true">${lado === 'esq' ? '←' : '→'}</span>`;
      b.addEventListener('click', () => trilho.scrollBy({ left: (lado === 'esq' ? -1 : 1) * trilho.clientWidth * 0.7, behavior: 'smooth' }));
      caixa.appendChild(b);
      return b;
    };
    const esq = seta('esq');
    const dir = seta('dir');

    const atualizar = () => {
      const max = trilho.scrollWidth - trilho.clientWidth;
      caixa.classList.toggle('tem-antes', trilho.scrollLeft > 4);
      caixa.classList.toggle('tem-depois', trilho.scrollLeft < max - 4);
      esq.hidden = !(trilho.scrollLeft > 4);
      dir.hidden = !(trilho.scrollLeft < max - 4);
    };
    trilho.addEventListener('scroll', atualizar, { passive: true });
    window.addEventListener('resize', atualizar);
    atualizar();

    // roda do mouse (vertical) vira rolagem lateral enquanto houver para onde ir
    trilho.addEventListener('wheel', (e) => {
      if (Math.abs(e.deltaX) > Math.abs(e.deltaY) || e.shiftKey) return;
      const max = trilho.scrollWidth - trilho.clientWidth;
      if (max <= 0) return;
      const indo = e.deltaY > 0 ? trilho.scrollLeft < max - 1 : trilho.scrollLeft > 1;
      if (!indo) return;
      e.preventDefault();
      trilho.scrollLeft += e.deltaY;
    }, { passive: false });

    // arrastar com o mouse
    let x0 = null, s0 = 0, arrastou = false;
    trilho.addEventListener('pointerdown', (e) => {
      if (e.pointerType !== 'mouse' || e.button !== 0) return;
      x0 = e.clientX; s0 = trilho.scrollLeft; arrastou = false;
    });
    window.addEventListener('pointermove', (e) => {
      if (x0 === null) return;
      const dx = e.clientX - x0;
      if (!arrastou && Math.abs(dx) > 6) { arrastou = true; caixa.classList.add('is-arrastando'); }
      if (arrastou) trilho.scrollLeft = s0 - dx;
    });
    window.addEventListener('pointerup', () => {
      if (x0 === null) return;
      x0 = null;
      caixa.classList.remove('is-arrastando');
    });
    // depois de arrastar, o clique que termina o gesto não abre o link
    trilho.addEventListener('click', (e) => {
      if (arrastou) { e.preventDefault(); e.stopPropagation(); arrastou = false; }
    }, true);
    trilho.addEventListener('dragstart', (e) => e.preventDefault());

    // item ativo sempre visível
    const ativo = trilho.querySelector('.is-ativo');
    if (ativo && ativo.offsetLeft + ativo.offsetWidth > trilho.clientWidth) {
      trilho.scrollLeft = ativo.offsetLeft - trilho.clientWidth / 3;
    }
    trilho.addEventListener('click', (e) => {
      const f = e.target.closest('.filtro');
      if (f) setTimeout(() => f.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' }), 0);
    });
  });
}
