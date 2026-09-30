<?php
/**
 * Componentes de interface reutilizados nas páginas públicas.
 * Todas as candidaturas usam exatamente a MESMA estrutura visual.
 */

declare(strict_types=1);

require_once __DIR__ . '/repositorio.php';

/**
 * Ficha de proposta.
 * $modo: 'completa' (todos os campos visíveis) | 'compacta' (detalhes recolhidos)
 */
function render_proposta(array $p, string $modo = 'completa', array $termos = [], bool $mostrarCandidato = false, bool $comLink = true): string
{
    $id      = (int) $p['id'];
    $link    = link_proposta($p);
    $resumo  = h($p['resumo']);
    if ($termos) $resumo = destacar($resumo, $termos);
    $doc     = link_documento($p);
    $pagina  = $p['pagina'] ? 'Página ' . (int) $p['pagina'] : 'Página não indicada';
    $docTit  = $p['documento_titulo'] ?: 'Documento não vinculado';
    $tipo    = tipo_documento($p['documento_tipo'] ?? null);

    ob_start(); ?>
<article class="ficha ficha--<?= h($modo) ?>" id="proposta-<?= $id ?>"
         data-sub="<?= h((string) ($p['subcategoria_slug'] ?? '')) ?>" data-cat="<?= h($p['categoria_slug']) ?>" data-quant="<?= tem_quantificacao($p) ? '1' : '0' ?>">
  <span class="ficha__crop" aria-hidden="true"></span>
  <header class="ficha__topo">
    <p class="ficha__trilha mono">
      <span><?= h($p['categoria_nome']) ?></span>
      <?php if (!empty($p['subcategoria_nome'])): ?><span class="ficha__sep" aria-hidden="true">/</span><span><?= h($p['subcategoria_nome']) ?></span><?php endif; ?>
    </p>
    <p class="ficha__num mono"><a href="<?= h($link) ?>" aria-label="Registro <?= $id ?> · abrir a página da proposta">Nº <?= str_pad((string) $id, 4, '0', STR_PAD_LEFT) ?></a></p>
  </header>

  <?php if ($mostrarCandidato): ?>
  <p class="ficha__cand mono"><a href="<?= h(link_candidato($p['candidato_slug'])) ?>"><?= h($p['candidato_numero']) ?> · <?= h($p['candidato_nome']) ?></a> <span>· <?= h($p['partido_sigla'] ?? '') ?></span></p>
  <?php endif; ?>

  <p class="ficha__rotulo mono">Resumo da proposta</p>
  <p class="ficha__resumo"><?= $resumo ?></p>

  <?php $dl = '<dl class="ficha__dados">'
      . '<div><dt>Ação proposta</dt><dd>' . campo($p['acao']) . '</dd></div>'
      . '<div><dt>Meta</dt><dd>' . campo($p['meta']) . '</dd></div>'
      . '<div><dt>Prazo</dt><dd>' . campo($p['prazo']) . '</dd></div>'
      . '<div><dt>Custo informado</dt><dd>' . campo($p['custo']) . '</dd></div>'
      . '<div><dt>Fonte de financiamento</dt><dd>' . campo($p['financiamento']) . '</dd></div>'
      . '</dl>'; ?>
  <?php if ($modo === 'compacta'): ?>
    <details class="ficha__mais"><summary><span>Ver detalhes</span><span aria-hidden="true" class="ficha__mais-ico">+</span></summary><?= $dl ?></details>
  <?php else: ?>
    <?= $dl ?>
  <?php endif; ?>

  <footer class="fonte">
    <?= render_selos($p) ?>
    <div class="fonte__meta">
      <span class="fonte__doc"><?= h($tipo) ?></span>
      <span class="fonte__pag mono"><?= h($pagina) ?></span>
    </div>
    <div class="fonte__acoes">
      <?php if (!empty($p['trecho_original'])): ?>
      <button type="button" class="fonte__btn" data-fonte="<?= $id ?>">Ver trecho original</button>
      <?php endif; ?>
      <?php if ($doc): ?>
      <a class="fonte__btn fonte__btn--doc" href="<?= h($doc) ?>" target="_blank" rel="noopener">Abrir documento <span aria-hidden="true">↗</span></a>
      <?php endif; ?>
      <?php if ($comLink): ?>
      <a class="fonte__btn fonte__btn--doc" href="<?= h($link) ?>">Página da proposta</a>
      <?= botao_compartilhar($link, 'Proposta de ' . $p['candidato_nome'] . ': ' . $p['acao'], 'fonte__btn fonte__btn--doc') ?>
      <?php endif; ?>
    </div>
    <?= fonte_template($p) ?>
  </footer>
</article>
<?php
    return (string) ob_get_clean();
}

/**
 * "Coluna de cédula" de uma candidatura — idêntica para todas.
 */
function render_candidato_slot(array $c, string $extra = ''): string
{
    $total = (int) ($c['total_propostas'] ?? 0);
    ob_start(); ?>
<article class="slot">
  <a class="slot__link" href="<?= h(link_candidato($c['slug'])) ?>">
    <p class="slot__num mono"><span>Número</span><?= digitos((string) $c['numero']) ?></p>
    <?= foto_candidato($c, 'slot__foto') ?>
    <h3 class="slot__nome"><?= h($c['nome_urna']) ?></h3>
    <p class="slot__partido"><?= logo_partido($c) ?><span><?= h($c['partido_nome'] ?? 'Sem partido') ?></span></p>
    <?php if (!empty($c['tipo_agremiacao'])): ?>
    <p class="slot__agrem mono"><?= !empty($c['coligacao_nome']) ? 'Coligação · ' . h($c['coligacao_nome']) : h($c['tipo_agremiacao']) ?></p>
    <?php endif; ?>
    <p class="slot__dado mono"><strong><?= pad2($total) ?></strong> <?= plural($total, 'proposta identificada', 'propostas identificadas') ?></p>
    <?= $extra ?>
    <span class="slot__ir mono">Ver propostas <span aria-hidden="true">→</span></span>
  </a>
</article>
<?php
    return (string) ob_get_clean();
}

/** Template oculto com o trecho original (lido pelo painel de fonte). */
function fonte_template(array $p): string
{
    if (empty($p['trecho_original'])) return '';
    $pagina = $p['pagina'] ? 'Página ' . (int) $p['pagina'] : 'Página não indicada';
    return '<template id="fonte-' . (int) $p['id'] . '" data-titulo="' . h($p['documento_titulo'] ?: 'Documento não vinculado') . '"'
        . ' data-tipo="' . h(tipo_documento($p['documento_tipo'] ?? null)) . '" data-pagina="' . h($pagina) . '"'
        . ' data-local="' . h((string) ($p['localizacao'] ?? '')) . '" data-doc="' . h((string) link_documento($p)) . '"'
        . ' data-cand="' . h($p['candidato_numero'] . ' · ' . $p['candidato_nome']) . '">'
        . '<div data-resumo>' . h($p['resumo']) . '</div><div data-trecho>' . nl2br(h($p['trecho_original'])) . '</div></template>';
}


/**
 * Selos de verificação. Cada um leva à parte da metodologia que explica o que ele significa.
 */
function render_selos(array $p, string $classe = ''): string
{
    $m = link_pagina('metodologia');
    $rev = !empty($p['revisado_em']) ? date('d/m/Y', strtotime((string) $p['revisado_em'])) : null;
    return '<ul class="selos ' . h($classe) . '" aria-label="Verificação desta proposta">'
        . '<li><a class="selo selo--fonte" href="' . h($m) . '#fonte-oficial" title="Extraída de documento oficial registrado pela candidatura. Saiba como.">Fonte oficial</a></li>'
        . '<li><a class="selo selo--revisado" href="' . h($m) . '#revisao" title="Conferida por uma pessoa com o texto original antes de ir ao ar. Saiba como.">Revisado' . ($rev ? '<span class="selo__data"> · ' . h($rev) . '</span>' : '') . '</a></li>'
        . '<li><a class="selo selo--ia" href="' . h($m) . '#ia" title="O resumo foi redigido com auxílio de inteligência artificial e conferido por uma pessoa. Saiba como.">Resumo assistido por IA</a></li>'
        . '</ul>';
}

/** Botão que abre o painel de compartilhamento (link, WhatsApp, copiar). */
function botao_compartilhar(string $link, string $titulo, string $classe = 'btn-line', string $rotulo = 'Compartilhar'): string
{
    return '<button type="button" class="' . h($classe) . '" data-compartilhar data-url="' . h(url_absoluta($link)) . '" data-titulo="' . h($titulo) . '">'
        . '<svg class="ico-comp" viewBox="0 0 16 16" aria-hidden="true"><circle cx="12.5" cy="3.5" r="2"/><circle cx="3.5" cy="8" r="2"/><circle cx="12.5" cy="12.5" r="2"/><path d="M5.3 7.1l5.4-2.7M5.3 8.9l5.4 2.7"/></svg>'
        . '<span>' . h($rotulo) . '</span></button>';
}

/** Chips de meta, prazo e custo (só o que está escrito no documento). */
function chips_quantificacao(array $p): string
{
    $out = '';
    foreach (['meta' => 'Meta', 'prazo' => 'Prazo', 'custo' => 'Custo'] as $k => $rot) {
        $v = trim((string) ($p[$k] ?? ''));
        if ($v !== '') $out .= '<li class="quant quant--' . $k . '"><span class="mono">' . $rot . '</span> ' . h($v) . '</li>';
    }
    return $out ? '<ul class="quants">' . $out . '</ul>' : '';
}

/** Número exibido em "casas", como no visor da urna. */
function digitos(string $numero): string
{
    $numero = trim($numero);
    if ($numero === '') return '<span class="digitos" aria-label="Número não informado"><span>–</span><span>–</span></span>';
    $out = '<span class="digitos" aria-label="Número ' . h($numero) . '">';
    foreach (mb_str_split($numero) as $d) $out .= '<span aria-hidden="true">' . h($d) . '</span>';
    return $out . '</span>';
}

/** Aviso de dados fictícios. */
function aviso_demo(string $classe = ''): string
{
    if (!demo_ativo()) return '';
    return '<p class="aviso-demo mono ' . h($classe) . '"><span class="aviso-demo__dot" aria-hidden="true"></span>Dados fictícios de demonstração</p>';
}
