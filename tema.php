<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$slug = get_slug('t');
$tema = $slug ? categoria_por_slug($slug) : null;
if (!$tema) nao_encontrado('Este tema não existe ou foi desativado.');

$subAtual = get_slug('sub');
$subs     = array_values(array_filter(subcategorias_com_contagem((int) $tema['id']), static fn($s) => (int) $s['total'] > 0));
$cands    = candidatos_lista();
$props    = propostas_publicadas(['categoria_id' => (int) $tema['id']]);

// Agrupa propostas por candidatura
$porCand = [];
foreach ($props as $p) $porCand[(int) $p['candidato_id']][] = $p;
$comPropostas = count($porCand);

// posição do tema no índice
$todos = categorias_com_contagem();
$pos = 1;
foreach ($todos as $i => $t) if ($t['id'] == $tema['id']) $pos = $i + 1;

$pageId    = 'temas';
$pageTitle = $tema['nome'];
$pageDesc  = 'O que cada candidatura propõe sobre ' . $tema['nome'] . ', com fonte oficial.';
$subNome   = null;
foreach ($subs as $s) if ($s['slug'] === $subAtual) $subNome = $s['nome'];
if (!$subNome) $subAtual = null;
if ($subNome) { $pageTitle = $subNome . ' · ' . $tema['nome']; $pageDesc = 'O que cada candidatura propõe sobre ' . $subNome . ' (' . $tema['nome'] . '), com fonte oficial.'; }
$pageCanonical = link_tema($tema['slug'], $subAtual);
$pageImagem    = url('og.php', ['tipo' => 'tema', 't' => $tema['slug']]);
$nomeLongo = mb_strlen($tema['nome']) > 11;
$VISIVEIS  = 3;

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo" data-tema-pagina>
  <header class="tema-topo wrap">
    <div class="tema-topo__glifo" aria-hidden="true"><?= glifo_tema($tema['slug']) ?></div>
    <p class="trilha mono" data-reveal><a href="<?= h(link_pagina('temas')) ?>">Temas</a> <span>/</span> <?= pad2($pos) ?> <span>/</span> <?= pad2(count($todos)) ?></p>
    <h1 class="display <?= $nomeLongo ? 'display--xl estreito' : 'display--xxl' ?> tema-topo__titulo">
      <?php foreach (preg_split('/\s+/u', mb_strtoupper($tema['nome'])) as $k => $palavra): ?>
        <span class="mascara"><span style="--i:<?= $k ?>" class="<?= $k % 2 ? 'outline' : '' ?>"><?= h($palavra) ?></span></span>
      <?php endforeach; ?>
    </h1>
    <div class="tema-topo__base">
      <p class="lead" data-reveal><?= h($tema['descricao']) ?></p>
      <dl class="tema-topo__dados mono" data-reveal>
        <div><dt>Propostas</dt><dd><?= pad2(count($props)) ?></dd></div>
        <div><dt>Candidaturas com propostas</dt><dd><?= $comPropostas ?>/<?= count($cands) ?></dd></div>
        <div><dt>Subtemas</dt><dd><?= pad2(count($subs)) ?></dd></div>
      </dl>
      <div class="tema-topo__acoes" data-reveal>
        <a class="btn-solid" href="<?= h(link_pagina('comparar', ['tema' => $tema['slug']])) ?>">Comparar este tema <span aria-hidden="true">→</span></a>
        <?= botao_compartilhar(link_tema($tema['slug'], $subAtual), 'O que cada candidatura propõe sobre ' . ($subNome ?: $tema['nome']) . ' — ' . SITE_NAME) ?>
      </div>
    </div>
  </header>

  <nav class="filtros-wrap" aria-label="Filtrar por subtema">
    <div class="filtros wrap" data-filtros="sub">
      <a class="filtro<?= !$subAtual ? ' is-ativo' : '' ?>" href="<?= h(link_tema($tema['slug'])) ?>#candidaturas" data-filtro="">Todos <sup><?= pad2(count($props)) ?></sup></a>
      <?php foreach ($subs as $s): ?>
      <a class="filtro<?= $subAtual === $s['slug'] ? ' is-ativo' : '' ?>" href="<?= h(link_tema($tema['slug'], $s['slug'])) ?>#candidaturas"
         data-filtro="<?= h($s['slug']) ?>"><?= h($s['nome']) ?> <sup><?= pad2((int) $s['total']) ?></sup></a>
      <?php endforeach; ?>
    </div>
  </nav>

  <section class="blocos wrap" id="candidaturas" data-filtro-alvo data-sub-inicial="<?= h((string) $subAtual) ?>">
    <p class="blocos__nota mono">Candidaturas em ordem alfabética · <?= $VISIVEIS ?> propostas exibidas por candidatura (todas, quando um subtema está selecionado)</p>

    <?php foreach ($cands as $c): $lista = $porCand[(int) $c['id']] ?? []; $n = count($lista); ?>
    <article class="bloco" data-bloco data-visiveis="<?= $VISIVEIS ?>">
      <aside class="bloco__id">
        <div class="bloco__id-cola">
          <?= foto_candidato($c, 'bloco__foto', '(max-width: 700px) 30vw, 180px') ?>
          <div class="bloco__id-txt">
            <p class="bloco__num mono"><?= digitos((string) $c['numero']) ?></p>
            <h2 class="bloco__nome"><a href="<?= h(link_candidato($c['slug'])) ?>"><?= h($c['nome_urna']) ?></a></h2>
            <p class="bloco__partido"><?= logo_partido($c) ?> <span><?= h($c['partido_nome'] ?? '') ?></span></p>
            <p class="bloco__conta mono"><strong data-conta><?= pad2($n) ?></strong> <?= plural($n, 'proposta identificada', 'propostas identificadas') ?> neste tema</p>
          </div>
        </div>
      </aside>

      <div class="bloco__lista">
        <?php if (!$lista): ?>
          <p class="vazio">Nenhuma proposta sobre <strong><?= h($tema['nome']) ?></strong> foi identificada no documento analisado desta candidatura.</p>
        <?php else: ?>
          <?php foreach ($lista as $k => $p): ?>
            <?= render_proposta($p, 'compacta') ?>
          <?php endforeach; ?>
          <p class="vazio vazio--filtro" data-vazio-filtro hidden>Nenhuma proposta desta candidatura neste subtema.</p>
          <div class="bloco__acoes">
            <button type="button" class="btn-line" data-ver-todas hidden>Ver todas <span data-ver-n></span> <span aria-hidden="true">↓</span></button>
            <a class="btn-line" href="<?= h(link_candidato($c['slug'], $tema['slug'])) ?>#propostas">Página da candidatura <span aria-hidden="true">→</span></a>
          </div>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
