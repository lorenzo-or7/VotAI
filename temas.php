<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$pageId    = 'temas';
$pageTitle = 'Temas';
$temas     = categorias_com_contagem();
$nCands    = count(candidatos_lista());

// Subtemas de todos os temas em uma consulta
$subs = [];
foreach (db()->query('SELECT categoria_id, nome FROM subcategorias ORDER BY ordem, nome') as $s) {
    $subs[(int) $s['categoria_id']][] = $s['nome'];
}

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina wrap">
    <p class="kicker" data-reveal>Índice · <?= count($temas) ?> temas</p>
    <h1 class="display display--xxl topo-pagina__titulo">
      <span class="mascara"><span>Os problemas</span></span>
      <span class="mascara"><span class="outline largo">do Brasil</span></span>
    </h1>
    <p class="lead" data-reveal>Cada tema reúne as propostas encontradas nos documentos oficiais de todas as candidaturas. Os temas seguem uma ordem fixa, igual para todos.</p>
  </header>

  <section class="catalogo wrap">
    <?php foreach ($temas as $i => $t): ?>
    <a class="catalogo__item" href="<?= h(link_tema($t['slug'])) ?>" data-reveal>
      <span class="catalogo__num" aria-hidden="true"><?= pad2($i + 1) ?></span>
      <span class="catalogo__corpo">
        <span class="catalogo__nome"><?= h($t['nome']) ?></span>
        <span class="catalogo__desc"><?= h($t['descricao']) ?></span>
        <?php if (!empty($subs[(int) $t['id']])): ?>
        <span class="catalogo__subs mono"><?= h(implode(' · ', $subs[(int) $t['id']])) ?></span>
        <?php endif; ?>
      </span>
      <span class="catalogo__dados mono">
        <span><strong><?= pad2((int) $t['total_propostas']) ?></strong> propostas</span>
        <span><strong><?= (int) $t['total_candidatos'] ?>/<?= $nCands ?></strong> candidaturas</span>
      </span>
      <span class="catalogo__glifo"><?= glifo_tema($t['slug']) ?></span>
    </a>
    <?php endforeach; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
