<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$slug = get_slug('c');
$c = $slug ? candidato_por_slug($slug) : null;
if (!$c) nao_encontrado('Candidatura não encontrada.');

$temaInicial = get_slug('tema');
$mapa  = mapa_propostas_candidato((int) $c['id']);
$props = propostas_publicadas(['candidato_id' => (int) $c['id']]);
$docs  = documentos_do_candidato((int) $c['id']);
$partidosCand = partidos_da_candidatura((int) $c['id']);

$porCat = [];
foreach ($props as $p) $porCat[$p['categoria_slug']][] = $p;

$pageId    = 'candidatos';
$pageTitle = $c['nome_urna'];
$pageDesc  = 'Propostas de ' . $c['nome_urna'] . ' organizadas por tema, com fonte oficial.';
$pageCanonical = link_candidato($c['slug']);
$pageImagem    = url('og.php', ['tipo' => 'candidato', 'c' => $c['slug']]);

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="perfil wrap">
    <p class="perfil__eco" aria-hidden="true"><?= h($c['numero']) ?></p>
    <div class="perfil__foto" data-reveal>
      <?= foto_candidato($c, 'perfil__img', '(max-width: 800px) 70vw, 30vw', true) ?>
      <p class="perfil__legenda mono"><?= !empty($c['foto_fonte']) ? h($c['foto_fonte']) : 'Foto padronizada · 4:5' ?></p>
    </div>
    <div class="perfil__info">
      <p class="trilha mono" data-reveal><a href="<?= h(link_pagina('candidatos')) ?>">Candidaturas</a> <span>/</span> <?= h($c['nome_urna']) ?></p>
      <h1 class="display display--xl perfil__nome">
        <?php foreach (preg_split('/\s+/u', mb_strtoupper($c['nome_urna'])) as $k => $w): ?>
        <span class="mascara"><span style="--i:<?= $k ?>" class="<?= $k % 2 ? 'outline' : '' ?>"><?= h($w) ?></span></span>
        <?php endforeach; ?>
      </h1>
      <dl class="perfil__ficha" data-reveal>
        <div><dt class="mono">Número</dt><dd><?= digitos((string) $c['numero']) ?></dd></div>
        <div><dt class="mono">Partido</dt><dd><?= logo_partido($c) ?> <?= h($c['partido_nome'] ?? 'Não informado') ?></dd></div>
        <?php if (!empty($c['vice_nome_urna'])): ?>
        <div><dt class="mono">Vice</dt><dd class="perfil__vice">
          <?= foto_candidato(['nome_urna' => $c['vice_nome_urna'], 'foto' => $c['vice_foto'] ?? null], 'perfil__vice-foto', '48px') ?>
          <span><?= h($c['vice_nome_urna']) ?><small class="mono"><?= h((string) $c['vice_partido_sigla']) ?></small></span></dd></div>
        <?php endif; ?>
        <?php if (!empty($c['tipo_agremiacao'])): ?>
        <div><dt class="mono"><?= !empty($c['coligacao_nome']) ? 'Coligação' : 'Candidatura' ?></dt><dd><?= h($c['coligacao_nome'] ?: $c['tipo_agremiacao']) ?></dd></div>
        <?php endif; ?>
        <div><dt class="mono">Cargo</dt><dd><?= h($c['cargo']) ?><?= $c['uf'] ? ' · ' . h($c['uf']) : '' ?></dd></div>
        <div><dt class="mono">Eleição</dt><dd><?= h($c['eleicao_nome']) ?></dd></div>
        <div><dt class="mono">Nome completo</dt><dd><?= h($c['nome']) ?></dd></div>
      </dl>
      <?php if (count($partidosCand) > 1): ?>
      <div class="partidos-cand" data-reveal>
        <p class="mono partidos-cand__tit">Partidos da candidatura <span><?= pad2(count($partidosCand)) ?></span></p>
        <ul class="partidos-cand__lista">
          <?php foreach ($partidosCand as $pc): ?>
          <li class="partido-chip<?= $pc['papel'] === 'titular' ? ' partido-chip--titular' : '' ?>" title="<?= h($pc['nome'] . ($pc['federacao'] ? ' · ' . $pc['federacao'] : '')) ?>">
            <?= logo_partido(['partido_sigla' => $pc['sigla'], 'partido_nome' => $pc['nome'], 'partido_logo' => $pc['logo']]) ?>
            <span class="partido-chip__nome"><?= h($pc['nome']) ?><?php if ($pc['federacao']): ?><small class="mono"><?= h($pc['federacao']) ?></small><?php endif; ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!empty($c['coligacao_composicao'])): ?><p class="mono partidos-cand__fonte">Composição registrada no TSE: <?= h($c['coligacao_composicao']) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="perfil__acoes" data-reveal><?= botao_compartilhar(link_candidato($c['slug']), 'Propostas de ' . $c['nome_urna'] . ' por tema — ' . SITE_NAME) ?></div>
      <?php if ((int) $c['demonstracao'] === 1) echo aviso_demo('perfil__demo'); ?>
    </div>
  </header>

  <section class="docs wrap" aria-labelledby="docs-tit">
    <h2 class="mono docs__tit" id="docs-tit">Documentos analisados</h2>
    <?php if (!$docs): ?>
      <p class="vazio">Nenhum documento cadastrado para esta candidatura.</p>
    <?php else: ?>
    <ul class="docs__lista">
      <?php foreach ($docs as $d):
        $link = arquivo_existe($d['arquivo']) ? url($d['arquivo']) : (preg_match('#^https?://#i', (string) $d['url_origem']) ? $d['url_origem'] : null); ?>
      <li class="doc">
        <span class="doc__icone" aria-hidden="true"><span></span></span>
        <span class="doc__txt"><strong><?= h($d['titulo']) ?></strong>
          <span class="mono"><?= h(tipo_documento($d['tipo'])) ?> · <?= $d['total_paginas'] ? (int) $d['total_paginas'] . ' páginas' : 'páginas não informadas' ?><?= $d['data_publicacao'] ? ' · ' . h(date('d/m/Y', strtotime($d['data_publicacao']))) : '' ?></span></span>
        <?php if ($link): ?><a class="fonte__btn" href="<?= h($link) ?>" target="_blank" rel="noopener">Abrir documento <span aria-hidden="true">↗</span></a><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="mapa-sec wrap" id="propostas" data-filtro-cat data-cat-inicial="<?= h((string) $temaInicial) ?>">
    <header class="mapa-sec__cab">
      <p class="kicker">Mapa das propostas</p>
      <h2 class="display display--l"><span class="mascara"><span><?= pad2(count($props)) ?> propostas</span></span><span class="mascara"><span class="outline">em <?= count($porCat) ?> temas</span></span></h2>
      <p class="mapa-sec__nota">Selecione um tema para filtrar. Cada traço representa uma proposta identificada no documento.</p>
    </header>

    <ul class="mapa" role="list">
      <li><button type="button" class="mapa__item is-ativo" data-cat=""><span class="mapa__nome">Todos os temas</span><span class="mapa__n mono"><?= pad2(count($props)) ?></span></button></li>
      <?php foreach ($mapa as $m): $n = (int) $m['total']; ?>
      <li>
        <button type="button" class="mapa__item<?= $n === 0 ? ' is-zero' : '' ?>" data-cat="<?= h($m['slug']) ?>" <?= $n === 0 ? 'disabled' : '' ?>>
          <span class="mapa__nome"><?= h($m['nome']) ?></span>
          <span class="mapa__traco" aria-hidden="true"></span>
          <span class="tracos" aria-hidden="true"><?php for ($k = 0; $k < $n; $k++): ?><i style="--i:<?= $k ?>"></i><?php endfor; ?></span>
          <span class="mapa__n mono"><?= pad2($n) ?></span>
        </button>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="grupos">
      <?php foreach ($mapa as $i => $m): if (empty($porCat[$m['slug']])) continue; ?>
      <section class="grupo" data-grupo="<?= h($m['slug']) ?>">
        <h3 class="grupo__tit"><span class="mono"><?= pad2($i + 1) ?></span> <?= h($m['nome']) ?> <sup class="mono"><?= pad2(count($porCat[$m['slug']])) ?></sup>
          <a class="grupo__link mono" href="<?= h(link_tema($m['slug'])) ?>">Ver tema com todas as candidaturas →</a></h3>
        <?php $subsG = [];
          foreach ($porCat[$m['slug']] as $p) if ($p['subcategoria_slug']) { $subsG[$p['subcategoria_slug']] ??= ['nome' => $p['subcategoria_nome'], 'n' => 0]; $subsG[$p['subcategoria_slug']]['n']++; }
          if (count($subsG) > 1): ?>
        <div class="chips chips--grupo" data-grupo-subs role="group" aria-label="Filtrar <?= h($m['nome']) ?> por subtema">
          <button type="button" class="chip is-ativo" data-sub="" aria-pressed="true">Todos <sup><?= pad2(count($porCat[$m['slug']])) ?></sup></button>
          <?php foreach ($subsG as $sk => $sv): ?><button type="button" class="chip" data-sub="<?= h($sk) ?>" aria-pressed="false"><?= h($sv['nome']) ?> <sup><?= pad2($sv['n']) ?></sup></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="grupo__fichas">
          <?php foreach ($porCat[$m['slug']] as $p) echo render_proposta($p, 'completa'); ?>
        </div>
      </section>
      <?php endforeach; ?>
      <?php if (!$props): ?><p class="vazio">Nenhuma proposta publicada para esta candidatura até o momento.</p><?php endif; ?>
    </div>

    <p class="mais-link"><a class="btn-line" href="<?= h(link_pagina('comparar', ['c' => [(int) $c['id']]])) ?>">Comparar com outras candidaturas <span aria-hidden="true">→</span></a></p>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
