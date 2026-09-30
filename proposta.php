<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$id = get_int('id');
$p  = $id ? proposta_publicada($id) : null;
if (!$p) nao_encontrado('Esta proposta não existe ou não está publicada.');

$c = candidato_por_slug($p['candidato_slug']);
if (!$c) nao_encontrado('Candidatura não encontrada.');

// Outras candidaturas com propostas no mesmo subtema (ordem alfabética, só contagem)
$vizinhas = [];
if ($p['subcategoria_id']) {
    foreach (propostas_publicadas(['subcategoria_id' => (int) $p['subcategoria_id']]) as $o) {
        $vizinhas[$o['candidato_nome']] ??= ['slug' => $o['candidato_slug'], 'nome' => $o['candidato_nome'], 'n' => 0];
        $vizinhas[$o['candidato_nome']]['n']++;
    }
    ksort($vizinhas, SORT_LOCALE_STRING);
}

$numero  = str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT);
$doc     = link_documento($p);
$pagina  = $p['pagina'] ? 'Página ' . (int) $p['pagina'] : 'Página não indicada';
$link    = link_proposta($p);
$titShare = 'Proposta de ' . $p['candidato_nome'] . ': ' . $p['acao'];

$pageId        = 'temas';
$pageTitle     = $p['acao'] . ' · ' . $p['candidato_nome'];
$pageDesc      = mb_strimwidth($p['resumo'], 0, 190, '…');
$pageCanonical = $link;
$pageImagem    = url('og.php', ['tipo' => 'proposta', 'id' => (int) $p['id']]);
$pageTipo      = 'article';

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <article class="prop wrap" id="proposta-<?= (int) $p['id'] ?>">
    <p class="trilha mono" data-reveal>
      <a href="<?= h(link_pagina('temas')) ?>">Temas</a> <span>/</span>
      <a href="<?= h(link_tema($p['categoria_slug'])) ?>"><?= h($p['categoria_nome']) ?></a>
      <?php if ($p['subcategoria_slug']): ?><span>/</span> <a href="<?= h(link_tema($p['categoria_slug'], $p['subcategoria_slug'])) ?>"><?= h($p['subcategoria_nome']) ?></a><?php endif; ?>
    </p>

    <header class="prop__topo">
      <a class="prop__quem" href="<?= h(link_candidato($c['slug'], $p['categoria_slug'])) ?>">
        <?= foto_candidato($c, 'prop__foto', '96px', true) ?>
        <span>
          <span class="prop__nome"><?= h($c['nome_urna']) ?></span>
          <span class="prop__part"><?= logo_partido($c) ?> <span class="mono"><?= h($c['numero']) ?> · <?= h($c['partido_sigla'] ?? '') ?></span></span>
        </span>
      </a>
      <p class="prop__num mono">Proposta Nº <?= $numero ?></p>
    </header>

    <h1 class="prop__acao"><?= h($p['acao']) ?></h1>

    <div class="prop__compartilhar">
      <?= botao_compartilhar($link, $titShare, 'btn-solid btn-solid--pequeno') ?>
      <a class="btn-line" href="https://wa.me/?text=<?= h(rawurlencode($titShare . ' — ' . url_absoluta($link))) ?>" target="_blank" rel="noopener">Enviar no WhatsApp</a>
    </div>

    <div class="prop__grade">
      <section class="prop__resumo" aria-labelledby="prop-resumo">
        <p class="ficha__rotulo mono" id="prop-resumo">Resumo da proposta</p>
        <p class="prop__resumo-txt"><?= h($p['resumo']) ?></p>
        <p class="prop__nota">Texto elaborado pela plataforma para facilitar a leitura. Não é uma declaração da candidatura.</p>
        <?= render_selos($p, 'selos--grande') ?>

        <dl class="ficha__dados prop__dados">
          <div><dt>Ação proposta</dt><dd><?= campo($p['acao']) ?></dd></div>
          <div><dt>Meta</dt><dd><?= campo($p['meta']) ?></dd></div>
          <div><dt>Prazo</dt><dd><?= campo($p['prazo']) ?></dd></div>
          <div><dt>Custo informado</dt><dd><?= campo($p['custo']) ?></dd></div>
          <div><dt>Fonte de financiamento</dt><dd><?= campo($p['financiamento']) ?></dd></div>
        </dl>
      </section>

      <section class="prop__original" aria-labelledby="prop-original">
        <div class="papel">
          <p class="rotulo mono" id="prop-original"><span class="rotulo__marca rotulo__marca--doc"></span>Texto original</p>
          <p class="modal-fonte__nota">Transcrição literal do documento, sem alterações.</p>
          <blockquote class="papel__trecho"><?= $p['trecho_original'] ? nl2br(h($p['trecho_original'])) : '<span class="nao-informado">Trecho não cadastrado.</span>' ?></blockquote>
          <dl class="papel__ref mono">
            <div><dt>Documento</dt><dd><?= h($p['documento_titulo'] ?: 'Documento não vinculado') ?></dd></div>
            <div><dt>Tipo</dt><dd><?= h(tipo_documento($p['documento_tipo'] ?? null)) ?></dd></div>
            <div><dt>Localização</dt><dd><?= h($pagina) ?></dd></div>
            <div><dt>Seção</dt><dd><?= h($p['localizacao'] ?: 'Não indicada') ?></dd></div>
          </dl>
          <?php if ($doc): ?><a class="btn-line btn-line--papel" href="<?= h($doc) ?>" target="_blank" rel="noopener">Abrir documento nesta página <span aria-hidden="true">↗</span></a><?php endif; ?>
        </div>
      </section>
    </div>

    <nav class="prop__mais" aria-label="Continuar explorando">
      <a class="prop__mais-link" href="<?= h(link_tema($p['categoria_slug'], $p['subcategoria_slug'] ?: null)) ?>">
        <span class="mono">Mesmo assunto</span><strong><?= h($p['subcategoria_nome'] ?: $p['categoria_nome']) ?> em todas as candidaturas</strong><span aria-hidden="true">→</span></a>
      <a class="prop__mais-link" href="<?= h(link_candidato($c['slug'], $p['categoria_slug'])) ?>#propostas">
        <span class="mono">Mesma candidatura</span><strong>Outras propostas de <?= h($c['nome_urna']) ?> sobre <?= h($p['categoria_nome']) ?></strong><span aria-hidden="true">→</span></a>
      <a class="prop__mais-link" href="<?= h(link_pagina('comparar', ['c' => [(int) $c['id']], 'tema' => $p['categoria_slug'], 'editar' => '1'])) ?>">
        <span class="mono">Comparar</span><strong><?= h($p['categoria_nome']) ?> lado a lado com outras candidaturas</strong><span aria-hidden="true">→</span></a>
    </nav>

    <?php if (count($vizinhas) > 1): ?>
    <section class="prop__vizinhas" aria-labelledby="prop-viz">
      <p class="mono prop__viz-tit" id="prop-viz">Candidaturas com propostas em “<?= h($p['subcategoria_nome']) ?>” · ordem alfabética</p>
      <ul>
        <?php foreach ($vizinhas as $v): ?>
        <li><a href="<?= h(link_tema($p['categoria_slug'], $p['subcategoria_slug'])) ?>#candidaturas"><?= h($v['nome']) ?> <sup class="mono"><?= pad2($v['n']) ?></sup></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <p class="prop__correcao mono">Encontrou algo diferente do documento? <a href="<?= h(link_pagina('correcoes', ['n' => (int) $p['id']] + ($p['pagina'] ? ['pagina' => (int) $p['pagina']] : []))) ?>">Informe pelo canal de correções</a> (o Nº <?= $numero ?> já vai preenchido).</p>
  </article>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
