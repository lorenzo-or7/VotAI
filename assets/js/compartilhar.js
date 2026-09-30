/**
 * Compartilhar: todo botão [data-compartilhar data-url data-titulo] abre o painel
 * com o link, "Copiar" e atalhos (WhatsApp, Telegram, X, e-mail).
 * No celular, usa o compartilhamento nativo do aparelho quando existir.
 */

export function iniciarCompartilhar() {
  const modal = document.getElementById('modal-compartilhar');
  if (!modal) return;
  const campo = (k) => modal.querySelector(`[data-c="${k}"]`);
  const status = modal.querySelector('[data-c-status]');
  const nativo = modal.querySelector('[data-c-nativo]');
  let atual = null;
  let origem = null;

  const compartilharNativo = async (dados) => {
    try { await navigator.share({ title: dados.titulo, text: dados.titulo, url: dados.url }); return true; }
    catch (e) { return e && e.name === 'AbortError'; }   // cancelado pelo usuário conta como resolvido
  };

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-compartilhar]');
    if (!btn) return;
    e.preventDefault();
    atual = { url: btn.dataset.url || location.href, titulo: btn.dataset.titulo || document.title };
    origem = btn;

    const toque = window.matchMedia && matchMedia('(pointer: coarse)').matches;
    if (navigator.share && toque && await compartilharNativo(atual)) return;

    const texto = encodeURIComponent(`${atual.titulo} — ${atual.url}`);
    campo('titulo').textContent = atual.titulo;
    campo('url').value = atual.url;
    campo('whatsapp').href = `https://wa.me/?text=${texto}`;
    campo('telegram').href = `https://t.me/share/url?url=${encodeURIComponent(atual.url)}&text=${encodeURIComponent(atual.titulo)}`;
    campo('x').href = `https://twitter.com/intent/tweet?url=${encodeURIComponent(atual.url)}&text=${encodeURIComponent(atual.titulo)}`;
    campo('email').href = `mailto:?subject=${encodeURIComponent(atual.titulo)}&body=${texto}`;
    nativo.hidden = !navigator.share;
    status.textContent = '';
    modal.showModal();
    campo('url').focus();
    campo('url').select();
  });

  modal.querySelector('[data-c-copiar]').addEventListener('click', async () => {
    const input = campo('url');
    let ok = false;
    try { await navigator.clipboard.writeText(input.value); ok = true; }
    catch {
      input.focus(); input.select();
      try { ok = document.execCommand('copy'); } catch { ok = false; }
    }
    status.textContent = ok ? 'Link copiado.' : 'Link selecionado. Use Ctrl+C (ou "Copiar" no celular).';
  });

  nativo.addEventListener('click', () => { if (atual) compartilharNativo(atual); });
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.close(); });
  modal.addEventListener('close', () => origem?.focus({ preventScroll: true }));
}
