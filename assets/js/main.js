/**
 * VOTAI — ponto de entrada (ES modules nativos, sem bibliotecas).
 * Cada módulo só age se encontrar seus elementos na página.
 */
import { iniciarTema } from './tema.js';
import { iniciarNavegacao } from './navegacao.js';
import { iniciarBusca } from './busca.js';
import { iniciarFonte } from './fonte.js';
import { iniciarFiltros } from './filtros.js';
import { iniciarRevelar } from './revelar.js';
import { iniciarCompartilhar } from './compartilhar.js';
import { iniciarComparador } from './comparador.js';
import { iniciarRolagem } from './rolagem.js';

// Foto/logo ausente → fallback neutro (sem ícone de imagem quebrada)
function fallbackImagens() {
  const quebrar = (img) => {
    if (img.matches('[data-foto]')) img.closest('.foto')?.classList.add('is-quebrada');
    if (img.matches('[data-logo]')) img.remove();
  };
  document.querySelectorAll('img[data-foto], img[data-logo]').forEach((img) => {
    if (img.complete && img.naturalWidth === 0) quebrar(img);
    else img.addEventListener('error', () => quebrar(img), { once: true });
  });
}

// Modo leve: aparelhos com poucos núcleos/memória ou economia de dados ativada
(function detectarModoLeve() {
  const n = navigator.hardwareConcurrency || 8;
  const mem = navigator.deviceMemory || 8;
  const economia = navigator.connection && navigator.connection.saveData;
  if (n <= 4 || mem <= 4 || economia) document.documentElement.classList.add('leve');
})();

function seguro(fn) { try { fn(); } catch (e) { console.error('[votai]', e); } }

seguro(fallbackImagens);
seguro(iniciarTema);
seguro(iniciarNavegacao);
seguro(iniciarBusca);
seguro(iniciarFonte);
seguro(iniciarFiltros);
seguro(iniciarRevelar);
seguro(iniciarCompartilhar);
seguro(iniciarComparador);
seguro(iniciarRolagem);

// Hero pseudo-3D carregado só onde existe
const canvas = document.querySelector('[data-brasil]');
if (canvas) import('./hero.js').then((m) => m.iniciarHero(canvas)).catch((e) => console.error('[votai]', e));
