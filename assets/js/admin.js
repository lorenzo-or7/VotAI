/* Painel administrativo — pequenas interações (sem bibliotecas) */
(function () {
  // Modo claro / escuro (mesma preferência do site público)
  var raiz = document.documentElement;
  var botoesTema = document.querySelectorAll('[data-adm-tema]');
  var pintarTema = function () {
    var claro = raiz.dataset.theme === 'light';
    botoesTema.forEach(function (b) {
      b.setAttribute('aria-pressed', String(claro));
      var t = b.querySelector('[data-adm-tema-txt]');
      if (t) t.textContent = claro ? 'Modo escuro' : 'Modo claro';
      b.setAttribute('aria-label', claro ? 'Ativar modo escuro' : 'Ativar modo claro');
    });
  };
  botoesTema.forEach(function (b) {
    b.addEventListener('click', function () {
      raiz.dataset.theme = raiz.dataset.theme === 'light' ? 'dark' : 'light';
      try { localStorage.setItem('votai-tema', raiz.dataset.theme); } catch (e) {}
      pintarTema();
    });
  });
  pintarTema();

  // Confirmação em ações sensíveis
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirmar]');
    if (b && !window.confirm(b.getAttribute('data-confirmar'))) e.preventDefault();
  });

  // Selecionar todas (ações em lote)
  var todas = document.querySelector('[data-marcar-todas]');
  if (todas) todas.addEventListener('change', function () {
    document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = todas.checked; });
  });

  // Slug automático
  var origem = document.querySelector('[data-slug-origem]');
  var destino = document.querySelector('[data-slug-destino]');
  if (origem && destino) {
    var manual = destino.value !== '';
    destino.addEventListener('input', function () { manual = true; });
    origem.addEventListener('input', function () {
      if (manual) return;
      destino.value = origem.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  }

  // Formulário de proposta: subcategorias da categoria e documentos do candidato
  var form = document.querySelector('[data-form-proposta]');
  if (form) {
    var cat = form.querySelector('[data-categoria]'), sub = form.querySelector('[data-subcategoria]');
    var cand = form.querySelector('[data-candidato]'), doc = form.querySelector('[data-documento]');
    var filtrar = function (select, attr, valor) {
      Array.prototype.forEach.call(select.options, function (o) {
        if (!o.value) return;
        var ok = o.getAttribute(attr) === valor;
        o.hidden = !ok; o.disabled = !ok;
        if (!ok && o.selected) select.value = '';
      });
    };
    var atualizar = function () { filtrar(sub, 'data-cat', cat.value); filtrar(doc, 'data-cand', cand.value); };
    cat.addEventListener('change', atualizar);
    cand.addEventListener('change', atualizar);
    atualizar();
  }

  // Imagem ausente → fallback de iniciais
  document.querySelectorAll('img[data-foto]').forEach(function (img) {
    var q = function () { var f = img.closest('.foto'); img.remove(); if (f) f.querySelector('.foto__fallback') && (f.querySelector('.foto__fallback').style.display = 'grid'); };
    if (img.complete && img.naturalWidth === 0) q(); else img.addEventListener('error', q);
  });
})();
