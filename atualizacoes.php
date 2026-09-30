<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$grupos = atualizacoes_recentes(60);

// Reúne os dados das propostas citadas (só as que estão publicadas hoje ganham link)
$porDia = [];
$idsLink = [];
foreach ($grupos as $g) {
    $porDia[$g['dia']][] = $g;
    $idsLink = array_merge($idsLink, array_slice(array_keys(array_filter($g['corrigidas'], static fn($st) => $st === STATUS_PUBLICO)), 0, 6));
}
// uma consulta só para todas as propostas que ganham link
$dadosProp = [];
if ($idsLink) foreach (propostas_publicadas(['ids' => array_values(array_unique($idsLink))]) as $pp) $dadosProp[(int) $pp['id']] = $pp;

$meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$dataExtenso = static function (string $dia) use ($meses): string {
    $t = strtotime($dia);
    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
};

$pageId        = 'como-funciona';
$pageTitle     = 'Últimas atualizações';
$pageDesc      = 'Propostas publicadas, corrigidas ou retiradas recentemente no ' . SITE_NAME . ', por candidatura.';
$pageCanonical = link_pagina('atualizacoes');

require __DIR__ . '/includes/header.php';

/** Lista curta de propostas de um grupo (com link quando ainda publicadas). */
$listar = static function (array $ids, int $max = 6) use ($dadosProp): string {
    $out = '';
    $mostrados = 0;
    foreach ($ids as $pid => $statusAtual) {
        if ($mostrados >= $max) break;
        if ($statusAtual !== STATUS_PUBLICO) continue;
        $p = $dadosProp[(int) $pid] ?? null;
        if (!$p) continue;
        $out .= '<li><a href="' . h(link_proposta($p)) . '">' . h($p['acao']) . '</a> <span class="mono">' . h($p['categoria_nome']) . '</span></li>';
        $mostrados++;
    }
    return $out ? '<ul class="atual__props">' . $out . '</ul>' : '';
};
?>
<main class="page" id="conteudo">
  <header class="topo-pagina wrap">
    <p class="kicker" data-reveal>Transparência · Histórico</p>
    <h1 class="display display--xl topo-pagina__titulo titulo-cedilha">
      <span class="mascara"><span>Últimas</span></span>
      <span class="mascara"><span class="outline">atualizações</span></span>
    </h1>
    <p class="lead" data-reveal>O que entrou, mudou ou saiu do site, por dia e por candidatura. Esta lista é gerada automaticamente a partir do histórico de revisão das propostas.</p>
  </header>

  <section class="atual wrap" aria-label="Atualizações por dia">
    <?php if (!$porDia): ?>
      <p class="vazio">Nenhuma atualização registrada ainda.</p>
    <?php endif; ?>

    <?php foreach ($porDia as $dia => $lista): ?>
    <article class="atual__dia" data-reveal>
      <h2 class="atual__data"><time datetime="<?= h($dia) ?>"><?= h($dataExtenso($dia)) ?></time></h2>
      <ul class="atual__lista">
        <?php foreach ($lista as $g): $c = $g['candidato']; ?>
        <li class="atual__item">
          <a class="atual__cand" href="<?= h(link_candidato($c['slug'])) ?>"><span class="mono"><?= h($c['numero']) ?></span> <?= h($c['nome_urna']) ?></a>
          <div class="atual__corpo">
            <?php if ($g['novas']): $n = count($g['novas']); ?>
              <p class="atual__tipo atual__tipo--nova"><span class="mono">Publicadas</span> <?= $n ?> <?= plural($n, 'proposta nova', 'propostas novas') ?></p>
            <?php endif; ?>
            <?php if ($g['corrigidas']): $n = count($g['corrigidas']); ?>
              <p class="atual__tipo atual__tipo--corrigida"><span class="mono">Corrigidas</span> <?= $n ?> <?= plural($n, 'proposta revisada e republicada', 'propostas revisadas e republicadas') ?></p>
              <?= $listar($g['corrigidas']) ?>
            <?php endif; ?>
            <?php if ($g['retiradas']): $n = count($g['retiradas']); ?>
              <p class="atual__tipo atual__tipo--retirada"><span class="mono">Retiradas</span> <?= $n ?> <?= plural($n, 'proposta saiu do site para revisão', 'propostas saíram do site para revisão') ?></p>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </article>
    <?php endforeach; ?>

    <p class="atual__nota mono">Correções são registradas com data. Para apontar uma diferença entre o site e o documento, use o <a href="<?= h(link_pagina('correcoes')) ?>">canal de correções</a>.</p>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
