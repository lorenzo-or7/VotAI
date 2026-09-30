/**
 * Painel "Ver trecho original": mostra lado a lado o RESUMO
 * (feito pela plataforma) e o TEXTO ORIGINAL do documento.
 */

export function iniciarFonte() {
  const modal = document.getElementById('modal-fonte');

  // fechar qualquer <dialog> com [data-fechar]
  document.addEventListener('click', (e) => {
    const f = e.target.closest('[data-fechar]');
    if (f) f.closest('dialog')?.close();
  });

  if (!modal) return;
  const campo = (k) => modal.querySelector(`[data-m="${k}"]`);
  let origem = null;

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-fonte]');
    if (!btn) return;
    const tpl = document.getElementById(`fonte-${btn.dataset.fonte}`);
    if (!tpl) return;
    origem = btn;
    const conteudo = tpl.content;
    campo('resumo').textContent = conteudo.querySelector('[data-resumo]')?.textContent || '';
    campo('trecho').innerHTML = conteudo.querySelector('[data-trecho]')?.innerHTML || ''; // já escapado no servidor
    campo('cand').textContent = tpl.dataset.cand || '';
    campo('titulo').textContent = tpl.dataset.titulo || '';
    campo('tipo').textContent = tpl.dataset.tipo || '';
    campo('pagina').textContent = tpl.dataset.pagina || '';
    campo('local').textContent = tpl.dataset.local || 'Não indicada';
    const link = campo('doc');
    const doc = tpl.dataset.doc || '';
    if (/^(https?:\/\/|\/|\.\/|[\w-]+\/)/i.test(doc) && !/^\s*(javascript|data|vbscript):/i.test(doc)) { link.href = doc; link.hidden = false; } else { link.hidden = true; }
    modal.showModal();
    modal.querySelector('.modal-fonte__corpo').scrollTop = 0;
  });

  modal.addEventListener('click', (e) => { if (e.target === modal) modal.close(); });
  modal.addEventListener('close', () => origem?.focus({ preventScroll: true }));
}
