<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$pageId    = 'candidatos';
$pageTitle = 'Candidaturas';
$cands     = candidatos_lista();
$eleicao   = eleicao_ativa();
$nTemas    = count(categorias_com_contagem());

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina wrap">
    <p class="kicker" data-reveal><?= h($eleicao['nome'] ?? 'Eleições') ?> · <?= h($eleicao['cargo_principal'] ?? '') ?></p>
    <h1 class="display display--xxl topo-pagina__titulo">
      <span class="mascara"><span class="outline largo">Todas as</span></span>
      <span class="mascara"><span class="estreito">candidaturas</span></span>
    </h1>
    <div class="topo-pagina__lado">
      <p class="lead" data-reveal><?= count($cands) ?> candidaturas cadastradas. Clique em uma para ver o mapa de propostas por tema e os documentos analisados.</p>
      <?= aviso_demo() ?>
    </div>
  </header>

  <section class="wrap cands-pagina">
    <p class="blocos__nota mono">Ordem alfabética · mesma estrutura para todas as candidaturas</p>
    <div class="cedula cedula--grande" style="--cols: <?= min(4, max(1, count($cands))) ?>" data-reveal>
      <?php foreach ($cands as $c):
        $extra = '<p class="slot__dado mono"><strong>' . pad2((int) $c['total_temas']) . '</strong> de ' . pad2($nTemas) . ' temas com propostas</p>';
        echo render_candidato_slot($c, $extra);
      endforeach; ?>
    </div>
    <?php if (!$cands): ?><p class="vazio">Nenhuma candidatura cadastrada ainda.</p><?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
