<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/busca.php';
require_once __DIR__ . '/includes/componentes.php';

$q = trim(get_texto('q', 120));
$q = mb_substr($q, 0, 120);
$res = $q !== '' ? provedor_busca()->buscar($q) : ['termos' => [], 'temas' => [], 'propostas' => [], 'total' => 0];

$grupos = [];
foreach ($res['propostas'] as $p) $grupos[$p['categoria_nome']][] = $p;

$pageId    = 'busca';
$pageTitle = $q !== '' ? 'Busca: ' . $q : 'Busca';
$temasSug  = array_slice(categorias_com_contagem(), 0, 14);

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina topo-busca wrap">
    <p class="kicker" data-reveal>Busca por assunto</p>
    <form class="busca-home__form" action="<?= h(link_pagina('busca')) ?>" method="get" role="search">
      <label class="topo-busca__label display display--m" for="q-pag">O que você quer entender sobre o Brasil?</label>
      <div class="campo">
        <span class="campo__prompt mono" aria-hidden="true">›</span>
        <input id="q-pag" name="q" type="search" value="<?= h($q) ?>" autocomplete="off" maxlength="120" placeholder="Segurança pública, universidades, impostos..." data-sugestoes="sugestoes-pag" <?= $q === '' ? 'autofocus' : '' ?>>
        <button class="btn-solid campo__btn" type="submit">Pesquisar propostas <span aria-hidden="true">→</span></button>
      </div>
      <div class="sugestoes" id="sugestoes-pag" role="listbox" aria-label="Sugestões"></div>
    </form>
  </header>

  <section class="resultados wrap" aria-live="polite">
    <?php if ($q === ''): ?>
      <p class="mono resultados__cont">Escolha um tema para começar</p>
      <div class="chips-temas">
        <?php foreach ($temasSug as $i => $t): ?>
          <a href="<?= h(link_tema($t['slug'])) ?>"><span class="mono"><?= pad2($i + 1) ?></span><?= h($t['nome']) ?></a>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <p class="resultados__cont">
        <span class="display display--m"><?= pad2($res['total']) ?></span>
        <span class="mono"><?= plural($res['total'], 'proposta encontrada', 'propostas encontradas') ?> para “<?= h($q) ?>”</span>
      </p>

      <?php if ($res['temas']): ?>
      <div class="resultados__temas">
        <p class="mono resultados__rot">Temas relacionados</p>
        <div class="chips-temas">
          <?php foreach ($res['temas'] as $t): ?>
            <a href="<?= h(link_tema($t['slug'])) ?>"><span class="mono">Tema</span><?= h($t['nome']) ?></a>
            <?php foreach ($t['subs'] as $s): ?>
              <a href="<?= h(link_tema($t['slug'], $s['slug'])) ?>#candidaturas"><span class="mono">Subtema</span><?= h($s['nome']) ?></a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$res['propostas']): ?>
        <div class="vazio">
          <p><strong>Nenhuma proposta publicada corresponde a “<?= h($q) ?>”.</strong></p>
          <p>Tente palavras mais gerais (ex.: “saúde”, “escola”, “emprego”) ou navegue pelos <a class="sublinhado" href="<?= h(link_pagina('temas')) ?>">temas</a>.</p>
        </div>
      <?php endif; ?>

      <?php foreach ($grupos as $cat => $lista): ?>
      <section class="resultado">
        <h2 class="resultado__tit"><?= h($cat) ?> <sup class="mono"><?= pad2(count($lista)) ?></sup></h2>
        <div class="resultado__fichas">
          <?php foreach ($lista as $p) echo render_proposta($p, 'compacta', $res['termos'], true); ?>
        </div>
      </section>
      <?php endforeach; ?>
      <p class="mono resultados__nota">Os resultados são ordenados por correspondência com o texto buscado — não por candidatura.</p>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
