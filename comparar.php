<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$todosCands = candidatos_lista();
$temas      = categorias_com_contagem();

// Entrada: /comparar/lula-x-zema/saude (par + tema) ou comparar.php?c[]=1&c[]=2&tema=slug
$ids = [];
$par = get_texto('par', 300);
if ($par !== '' && preg_match('/^[a-z0-9-]{3,300}$/', $par)) {
    $porSlug = array_column($todosCands, null, 'slug');
    foreach (explode('-x-', $par) as $s) if (isset($porSlug[$s])) $ids[] = (int) $porSlug[$s]['id'];
} else {
    foreach (array_slice((array) ($_GET['c'] ?? []), 0, 10) as $v) {
        $v = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($v !== false && !in_array($v, $ids, true)) $ids[] = $v;
    }
}
$ids = array_values(array_unique($ids));
$ids = array_slice($ids, 0, 3);
$temaSlug = get_slug('tema');
// "todos": compara todos os temas de uma vez (um bloco por tema)
$todos = $temaSlug === 'todos';
$tema = $todos ? ['id' => 0, 'slug' => 'todos', 'nome' => 'Todos os temas'] : ($temaSlug ? categoria_por_slug($temaSlug) : null);

$selecionados = candidatos_por_ids($ids);
$editar = isset($_GET['editar']);
$pronto = count($selecionados) >= 2 && $tema && !$editar;
$apresentar = get_texto('apresentar', 2) === '1';

// Com URLs amigáveis, a comparação sempre fica no endereço curto (bom para compartilhar)
if ($pronto && urls_amigaveis() && $par === '' && !headers_sent()) {
    header('Location: ' . link_comparar($selecionados, $tema['slug'], $apresentar), true, 301);
    exit;
}

$porCand = [];
$subs = [];      // grupos presentes, na ordem do índice: subtemas (um tema) ou temas (todos)
/** Grupo de uma proposta no filtro: o subtema, ou o tema quando se comparam todos. */
$grupoDe = static fn(array $p): string => $todos ? (string) $p['categoria_slug'] : (string) ($p['subcategoria_slug'] ?? '');
if ($pronto) {
    $filtro = ['candidatos' => array_column($selecionados, 'id')];
    if (!$todos) $filtro['categoria_id'] = (int) $tema['id'];
    $props = propostas_publicadas($filtro);
    foreach ($props as $p) {
        $porCand[(int) $p['candidato_id']][] = $p;
        $sk = $grupoDe($p);
        $subs[$sk] ??= ['slug' => $sk, 'nome' => $todos ? $p['categoria_nome'] : ($p['subcategoria_nome'] ?: 'Outras propostas'), 'total' => 0, 'porCand' => []];
        $subs[$sk]['total']++;
        $subs[$sk]['porCand'][(int) $p['candidato_id']][] = $p;
    }
}

$aspectos = [
    'meta'          => 'Objetivos e metas',
    'prazo'         => 'Prazos',
    'custo'         => 'Custo informado',
    'financiamento' => 'Financiamento informado',
];

$nomes = $pronto ? implode(' × ', array_column($selecionados, 'nome_urna')) : '';
$pageId    = 'comparar';
$pageTitle = $pronto ? $nomes . ' · ' . $tema['nome'] : 'Comparar';
$pageDesc  = $pronto ? 'O que ' . $nomes . ($todos ? ' propõem em todos os temas' : ' propõem sobre ' . $tema['nome']) . ', lado a lado, com a fonte oficial de cada proposta.' : null;
if ($pronto) {
    $pageCanonical = link_comparar($selecionados, $tema['slug']);
    $pageImagem = url('og.php', ['tipo' => 'comparar', 'c' => array_map('intval', array_column($selecionados, 'id')), 'tema' => $tema['slug']]);
}

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina topo-pagina--comp wrap">
    <p class="kicker" data-reveal>Comparador</p>
    <h1 class="display <?= $pronto ? 'display--l' : 'display--xxl' ?> topo-pagina__titulo">
      <span class="mascara"><span>Compare</span></span>
      <span class="mascara"><span class="outline largo">antes de decidir.</span></span>
    </h1>
  </header>

  <?php if (!$pronto): ?>
  <section class="wrap comp-vazio">
    <form class="seletor seletor--pagina" action="<?= h(link_pagina('comparar')) ?>" method="get" data-comparar>
      <fieldset>
        <legend class="mono"><span class="seletor__passo">1</span> Escolha de 2 a 3 candidaturas</legend>
        <div class="marcadores">
          <?php foreach ($todosCands as $c): ?>
          <label class="marcador">
            <input type="checkbox" name="c[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $ids, true) ? 'checked' : '' ?>>
            <span class="marcador__caixa" aria-hidden="true"></span>
            <span class="marcador__num mono"><?= h($c['numero']) ?></span>
            <span class="marcador__nome"><?= h($c['nome_urna']) ?></span>
            <span class="marcador__partido mono"><?= h($c['partido_sigla'] ?? '') ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset>
        <legend class="mono"><span class="seletor__passo">2</span> Escolha um tema</legend>
        <div class="select">
          <select name="tema" required aria-label="Tema">
            <option value="">Selecione um tema</option>
            <option value="todos" <?= $temaSlug === 'todos' ? 'selected' : '' ?>>Todos os temas</option>
            <?php foreach ($temas as $t): ?><option value="<?= h($t['slug']) ?>" <?= $temaSlug === $t['slug'] ? 'selected' : '' ?>><?= h($t['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </fieldset>
      <p class="seletor__aviso mono" data-aviso role="status" aria-live="polite"><?= $_GET ? 'Selecione de 2 a 3 candidaturas e um tema.' : '' ?></p>
      <button class="btn-solid" type="submit">Montar comparação <span aria-hidden="true">→</span></button>
      <p class="seletor__nota">Sem notas, sem ranking, sem vencedor. As informações aparecem lado a lado, como estão nos documentos.</p>
    </form>
  </section>

  <?php else: $n = count($selecionados); $totalQuant = 0; ?>
  <section class="comp" style="--n: <?= $n ?>" aria-label="Comparação de propostas sobre <?= h($tema['nome']) ?>" data-comp>
    <div class="comp__barra wrap">
      <p class="comp__tema"><span class="mono">Tema</span> <strong><?= h($tema['nome']) ?></strong></p>
      <div class="comp__acoes">
        <button type="button" class="btn-solid btn-solid--pequeno" data-apres-abrir>
          <svg class="ico-apres" viewBox="0 0 20 16" aria-hidden="true"><rect x="1" y="1" width="18" height="11" rx="1"/><path d="M10 12v3M6 15h8"/></svg>
          Modo apresentação
        </button>
        <?= botao_compartilhar(link_comparar($selecionados, $tema['slug']), $nomes . ' · ' . $tema['nome'] . ' — comparação no ' . SITE_NAME) ?>
        <a class="btn-line" href="<?= h(link_pagina('comparar', ['c' => $ids, 'tema' => $tema['slug'], 'editar' => '1'])) ?>">Alterar seleção</a>
      </div>
    </div>

    <nav class="filtros-wrap" aria-label="Trocar tema">
      <div class="filtros wrap">
        <a class="filtro filtro--todos<?= $todos ? ' is-ativo' : '' ?>" href="<?= h(link_comparar($selecionados, 'todos')) ?>">Todos os temas</a>
        <?php foreach ($temas as $t): ?>
        <a class="filtro<?= $t['slug'] === $tema['slug'] ? ' is-ativo' : '' ?>" href="<?= h(link_comparar($selecionados, $t['slug'])) ?>"><?= h($t['nome']) ?></a>
        <?php endforeach; ?>
      </div>
    </nav>

    <?php if ($subs): ?>
    <div class="comp__ferramentas wrap">
      <div class="comp__subs" role="group" aria-label="<?= $todos ? 'Filtrar por tema' : 'Filtrar por subtema' ?>">
        <p class="mono comp__ferr-rot"><?= $todos ? 'Tema' : 'Subtema' ?></p>
        <div class="chips" data-comp-subs>
          <button type="button" class="chip is-ativo" data-sub="" aria-pressed="true">Todos <sup><?= pad2(array_sum(array_column($subs, 'total'))) ?></sup></button>
          <?php foreach ($subs as $s): ?>
          <button type="button" class="chip" data-sub="<?= h($s['slug']) ?>" aria-pressed="false"><?= h($s['nome']) ?> <sup><?= pad2($s['total']) ?></sup></button>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="alternador" data-so-quant aria-pressed="false">
        <span class="alternador__trilho" aria-hidden="true"><span></span></span>
        <span>Só propostas com meta, prazo ou custo escritos</span>
      </button>
    </div>
    <?php endif; ?>

    <div class="comp__tabela wrap">
      <!-- Cabeçalho das colunas -->
      <div class="comp__linha comp__linha--cab">
        <div class="comp__rot mono">Candidatura</div>
        <?php foreach ($selecionados as $k => $c): $lista = $porCand[(int) $c['id']] ?? []; $qtd = count($lista); $q = count(array_filter($lista, 'tem_quantificacao')); ?>
        <div class="comp__cel comp__quem" data-cand="<?= (int) $c['id'] ?>">
          <span class="comp__letra mono" aria-hidden="true"><?= chr(65 + $k) ?></span>
          <?= foto_candidato($c, 'comp__foto', '80px') ?>
          <div>
            <p class="comp__nome"><a href="<?= h(link_candidato($c['slug'], $todos ? null : $tema['slug'])) ?>"><?= h($c['nome_urna']) ?></a></p>
            <p class="comp__part mono"><?= h($c['numero']) ?> · <?= h($c['partido_sigla'] ?? '') ?></p>
            <p class="comp__qtd mono"><span data-comp-conta><?= pad2($qtd) ?></span> <?= plural($qtd, 'proposta', 'propostas') ?> · <?= pad2($q) ?> com meta/prazo</p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Propostas: uma linha (um tema) ou uma linha por tema (todos) -->
      <?php
        $linhas = $todos
            ? array_map(static fn($g) => ['slug' => $g['slug'], 'rot' => $g['nome'], 'porCand' => $g['porCand']], $subs)
            : [['slug' => '', 'rot' => 'Propostas', 'porCand' => $porCand]];
        foreach ($linhas as $linha): ?>
      <div class="comp__linha<?= $todos ? ' comp__linha--tema' : '' ?>"<?= $todos ? ' data-grupo-tema="' . h($linha['slug']) . '"' : '' ?>>
        <h2 class="comp__rot mono"><?= h($linha['rot']) ?><?php if ($todos): ?><a class="comp__rot-link" href="<?= h(link_comparar($selecionados, $linha['slug'])) ?>">Só este tema →</a><?php endif; ?></h2>
        <?php foreach ($selecionados as $k => $c): $lista = $linha['porCand'][(int) $c['id']] ?? []; ?>
        <div class="comp__cel" data-cand="<?= (int) $c['id'] ?>">
          <p class="comp__cel-quem mono"><?= chr(65 + $k) ?> · <?= h($c['nome_urna']) ?></p>
          <?php if (!$lista): ?>
            <p class="nao-informado">Nenhuma proposta sobre este tema identificada no documento analisado.</p>
          <?php else: ?>
          <ol class="comp__itens">
            <?php foreach ($lista as $p): ?>
            <li class="comp__item" data-sub="<?= h($grupoDe($p)) ?>" data-quant="<?= tem_quantificacao($p) ? '1' : '0' ?>">
              <span class="comp__sub mono"><?= h($p['subcategoria_nome'] ?? 'Geral') ?></span>
              <p><?= h($p['acao']) ?></p>
              <?= chips_quantificacao($p) ?>
              <span class="comp__fonte">
                <button type="button" class="fonte__btn" data-fonte="<?= (int) $p['id'] ?>">Trecho original · p. <?= (int) $p['pagina'] ?></button>
                <a class="fonte__btn fonte__btn--doc" href="<?= h(link_proposta($p)) ?>">Nº <?= str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT) ?></a>
              </span>
              <?= fonte_template($p) ?>
            </li>
            <?php endforeach; ?>
          </ol>
          <p class="nao-informado" data-comp-vazio hidden>Nenhuma proposta desta candidatura com este filtro.</p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>

      <!-- Aspectos -->
      <?php foreach ($aspectos as $campo => $rotulo): ?>
      <div class="comp__linha">
        <h2 class="comp__rot mono"><?= h($rotulo) ?></h2>
        <?php foreach ($selecionados as $k => $c):
          $lista = array_values(array_filter($porCand[(int) $c['id']] ?? [], static fn($p) => trim((string) $p[$campo]) !== '')); ?>
        <div class="comp__cel" data-cand="<?= (int) $c['id'] ?>">
          <p class="comp__cel-quem mono"><?= chr(65 + $k) ?> · <?= h($c['nome_urna']) ?></p>
          <?php if (!$lista): ?>
            <p class="nao-informado"><?= NAO_INFORMADO ?></p>
          <?php else: ?>
          <ul class="comp__valores">
            <?php foreach ($lista as $p): ?>
            <li data-sub="<?= h($grupoDe($p)) ?>"><strong><?= h($p[$campo]) ?></strong><span class="mono"><a href="<?= h(link_proposta($p)) ?>">Nº <?= str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT) ?></a> · <?= h($todos ? $p['categoria_nome'] . ' / ' . ($p['subcategoria_nome'] ?? '') : ($p['subcategoria_nome'] ?? '')) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>

      <!-- Fontes -->
      <div class="comp__linha">
        <h2 class="comp__rot mono">Fontes</h2>
        <?php foreach ($selecionados as $k => $c):
          $lista = $porCand[(int) $c['id']] ?? [];
          $docsC = [];
          foreach ($lista as $p) {
              if (!$p['documento_id']) continue;
              $docsC[$p['documento_id']] ??= ['p' => $p, 'paginas' => []];
              if ($p['pagina']) $docsC[$p['documento_id']]['paginas'][(int) $p['pagina']] = true;
          } ?>
        <div class="comp__cel" data-cand="<?= (int) $c['id'] ?>">
          <p class="comp__cel-quem mono"><?= chr(65 + $k) ?> · <?= h($c['nome_urna']) ?></p>
          <?php if (!$docsC): ?>
            <p class="nao-informado">Sem fontes para este tema.</p>
          <?php else: foreach ($docsC as $d): $pags = array_keys($d['paginas']); sort($pags); $link = link_documento(array_merge($d['p'], ['pagina' => null])); ?>
            <div class="comp__doc">
              <span class="fonte__selo mono">Fonte oficial</span>
              <p><?= h($d['p']['documento_titulo']) ?></p>
              <p class="mono comp__pags"><?= count($pags) === 1 ? 'Página' : 'Páginas' ?> <?= h(implode(', ', $pags)) ?></p>
              <?php if ($link): ?><a class="fonte__btn fonte__btn--doc" href="<?= h($link) ?>" target="_blank" rel="noopener">Abrir documento ↗</a><?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="wrap comp__rodape">
      <p class="comp__nota mono">A ordem das colunas segue a sua seleção. Nenhuma coluna tem destaque sobre outra. Campos vazios indicam que a informação não aparece no documento.</p>
      <?= render_selos([], 'selos--nota') ?>
    </div>
  </section>

  <?php require __DIR__ . '/includes/apresentacao.php'; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
