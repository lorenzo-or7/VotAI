<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$pageId    = 'inicio';
$pageTitle = '';
$stats     = estatisticas();
$temas     = categorias_com_contagem();
$cands     = candidatos_lista();
$eleicao   = eleicao_ativa();
$doDia     = tema_do_dia();
$atualiz   = array_slice(atualizacoes_recentes(8), 0, 3);

$pageCanonical = url_absoluta(url(''));
$pageImagem    = url('og.php', ['tipo' => 'site']);

$cores = ['var(--verde-luz)', 'var(--amarelo)', 'var(--indigo-luz)'];
$exemplos = ['Segurança pública', 'universidades', 'impostos', 'fila do SUS', 'desmatamento', 'creches', 'conta de luz', 'emprego para jovens'];

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">

  <!-- =========================== HERO =========================== -->
  <section class="hero" id="inicio" data-secao="inicio">
    <div class="hero__halo" aria-hidden="true"></div>
    <svg class="hero__geo" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <path class="hero__losango" d="M600 40 1160 400 600 760 40 400Z"/>
      <path class="hero__losango hero__losango--2" d="M600 110 1050 400 600 690 150 400Z"/>
      <circle class="hero__circulo" cx="600" cy="400" r="210"/>
      <path class="hero__faixa" d="M395 360c140-60 300-40 415 60"/>
    </svg>
    <canvas class="hero__canvas" data-brasil aria-hidden="true"></canvas>

    <div class="hero__meta">
      <p class="kicker">Eleições • Brasil • <?= h((string) ($eleicao['ano'] ?? 2026)) ?></p>
      <p class="hero__coord mono" aria-hidden="true">
        <span>S 14°14′06″ · O 51°55′31″</span>
        <span>27 UF · 5.570 municípios</span>
      </p>
    </div>

    <h1 class="hero__titulo display" data-reveal-now>
      <span class="mascara hero__l1"><span class="outline largo" style="--i:0">O que eles</span></span>
      <span class="mascara hero__l2"><span class="estreito" style="--i:1">propõem</span></span>
      <span class="mascara hero__l3"><span style="--i:2">para o Brasil<em class="hero__q">?</em></span></span>
    </h1>

    <div class="hero__base">
      <p class="hero__frase" data-reveal style="--i:4">Escolha um problema.<br><span>Veja o que cada candidato propõe.</span></p>
      <p class="hero__texto" data-reveal style="--i:5">Propostas organizadas por tema a partir de documentos oficiais para você comparar e tirar suas próprias conclusões.</p>
      <a class="hero__rolar mono" href="#busca" data-reveal style="--i:6"><span>Começar</span><span class="hero__rolar-linha" aria-hidden="true"></span></a>
    </div>

    <div class="fita" aria-hidden="true">
      <div class="fita__trilho">
        <?php for ($k = 0; $k < 2; $k++): ?>
        <span class="fita__grupo">
          <?php foreach ($temas as $i => $t): ?><span><?= pad2($i + 1) ?> <?= h(mb_strtoupper($t['nome'])) ?></span><i>◆</i><?php endforeach; ?>
        </span>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <!-- =========================== BUSCA =========================== -->
  <section class="busca-home secao" id="busca" data-secao="inicio">
    <div class="busca-home__anel" aria-hidden="true">
      <svg viewBox="0 0 600 600">
        <defs><path id="arco-busca" d="M300 300m-250 0a250 250 0 1 1 500 0a250 250 0 1 1-500 0"/></defs>
        <circle cx="300" cy="300" r="286" class="anel__fino"/>
        <circle cx="300" cy="300" r="250" class="anel__faixa"/>
        <circle cx="300" cy="300" r="214" class="anel__fino"/>
        <g>
          <text><textPath href="#arco-busca" startOffset="0">O QUE VOCÊ QUER ENTENDER SOBRE O BRASIL? ◆ PROPOSTAS POR TEMA ◆ FONTE OFICIAL ◆ </textPath></text>
        </g>
        <path class="anel__arco" d="M110 340c120-70 280-60 380 20"/>
      </svg>
    </div>

    <div class="busca-home__conteudo wrap">
      <p class="kicker" data-reveal>Busca por assunto</p>
      <h2 class="display display--l busca-home__titulo">
        <span class="mascara"><span>O que você quer</span></span>
        <span class="mascara"><span>entender <span class="outline">sobre</span></span></span>
        <span class="mascara"><span><span class="marca-texto">o Brasil?</span></span></span>
      </h2>

      <form class="busca-home__form" action="<?= h(link_pagina('busca')) ?>" method="get" role="search" data-reveal>
        <label class="visually-hidden" for="q-home">Buscar propostas por assunto</label>
        <div class="campo">
          <span class="campo__prompt mono" aria-hidden="true">›</span>
          <input id="q-home" name="q" type="search" autocomplete="off" maxlength="120"
                 placeholder="Segurança pública, universidades, impostos..."
                 data-ciclo='<?= h(json_encode($exemplos, JSON_UNESCAPED_UNICODE)) ?>'
                 data-sugestoes="sugestoes-home">
          <button class="btn-solid campo__btn" type="submit">Pesquisar propostas <span aria-hidden="true">→</span></button>
        </div>
        <div class="sugestoes" id="sugestoes-home" role="listbox" aria-label="Sugestões"></div>
      </form>

      <p class="busca-home__tente mono" data-reveal>
        <span>Tente:</span>
        <?php foreach (['fila do SUS', 'creche', 'imposto de renda', 'desmatamento', 'polícia'] as $ex): ?>
          <a href="<?= h(link_pagina('busca', ['q' => $ex])) ?>"><?= h($ex) ?></a>
        <?php endforeach; ?>
      </p>
    </div>
  </section>


  <!-- =========================== TEMA DO DIA =========================== -->
  <?php if ($doDia): $td = $doDia['tema']; ?>
  <section class="dia secao" id="tema-do-dia" data-secao="inicio" aria-labelledby="dia-titulo">
    <div class="wrap">
      <header class="dia__topo">
        <div>
          <p class="kicker" data-reveal>Tema do dia · <?= h($doDia['data']->format('d/m')) ?></p>
          <h2 class="display display--m dia__titulo" id="dia-titulo">
            <span class="mascara"><span><?= h($td['nome']) ?></span></span>
          </h2>
          <p class="dia__sub" data-reveal>Uma proposta de cada candidatura sobre o mesmo tema, lado a lado.</p>
        </div>
        <div class="dia__acoes" data-reveal>
          <a class="btn-solid" href="<?= h(link_tema($td['slug'])) ?>">Ver tudo sobre <?= h($td['nome']) ?> <span aria-hidden="true">→</span></a>
          <?= botao_compartilhar(link_tema($td['slug']), 'Tema do dia no ' . SITE_NAME . ': ' . $td['nome']) ?>
        </div>
      </header>

      <ol class="dia__trilho" data-reveal>
        <?php foreach ($doDia['itens'] as $it): $c = $it['candidato']; $p = $it['proposta']; ?>
        <li class="dia__card<?= $p ? '' : ' dia__card--vazio' ?>">
          <a class="dia__quem" href="<?= h(link_candidato($c['slug'], $td['slug'])) ?>">
            <?= foto_candidato($c, 'dia__foto', '64px') ?>
            <span>
              <strong><?= h($c['nome_urna']) ?></strong>
              <span class="mono"><?= h($c['numero']) ?> · <?= h($c['partido_sigla'] ?? '') ?></span>
            </span>
          </a>
          <?php if ($p): ?>
            <?php if ($p['subcategoria_nome']): ?><p class="dia__sub-tema mono"><?= h($p['subcategoria_nome']) ?></p><?php endif; ?>
            <p class="dia__acao"><a href="<?= h(link_proposta($p)) ?>"><?= h($p['acao']) ?></a></p>
            <p class="dia__resumo"><?= h($p['resumo']) ?></p>
            <div class="dia__rodape">
              <?php if (!empty($p['trecho_original'])): ?><button type="button" class="fonte__btn" data-fonte="<?= (int) $p['id'] ?>">Trecho original</button><?php endif; ?>
              <span class="mono"><?= pad2($it['total']) ?> <?= plural($it['total'], 'proposta', 'propostas') ?> no tema</span>
            </div>
            <?= fonte_template($p) ?>
          <?php else: ?>
            <p class="dia__vazio">Sem proposta sobre este tema no documento analisado.</p>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>

      <p class="dia__nota mono">
        Escolha automática: o tema, a proposta exibida e a ordem das candidaturas mudam todo dia por rodízio. Nenhuma escolha editorial.
        <a href="<?= h(link_pagina('metodologia')) ?>#tema-do-dia">Como funciona</a>
      </p>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($atualiz): ?>
  <aside class="atual-home wrap" aria-labelledby="atual-home-tit">
    <p class="mono atual-home__tit" id="atual-home-tit">Últimas atualizações</p>
    <ul>
      <?php foreach ($atualiz as $g): $n = count($g['novas']) + count($g['corrigidas']); ?>
      <li>
        <time class="mono" datetime="<?= h($g['dia']) ?>"><?= h(date('d/m', strtotime($g['dia']))) ?></time>
        <a href="<?= h(link_candidato($g['candidato']['slug'])) ?>"><?= h($g['candidato']['nome_urna']) ?></a>
        <span><?php
          $partes = [];
          if ($g['novas']) $partes[] = count($g['novas']) . ' ' . plural(count($g['novas']), 'proposta publicada', 'propostas publicadas');
          if ($g['corrigidas']) $partes[] = count($g['corrigidas']) . ' ' . plural(count($g['corrigidas']), 'corrigida', 'corrigidas');
          if ($g['retiradas']) $partes[] = count($g['retiradas']) . ' ' . plural(count($g['retiradas']), 'retirada', 'retiradas');
          echo h(implode(' · ', $partes));
        ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <a class="mono atual-home__mais" href="<?= h(link_pagina('atualizacoes')) ?>">Ver todas <span aria-hidden="true">→</span></a>
  </aside>
  <?php endif; ?>

  <div class="divisor" data-coord="S-01 · MANIFESTO"></div>

  <!-- =========================== MANIFESTO =========================== -->
  <section class="manifesto secao wrap" data-secao="inicio">
    <h2 class="display display--xl manifesto__titulo">
      <span class="mascara"><span style="--i:0">O Brasil</span></span>
      <span class="mascara"><span class="outline" style="--i:1">tem problemas.</span></span>
      <span class="mascara manifesto__recuo"><span style="--i:2">Eles têm</span></span>
      <span class="mascara manifesto__recuo"><span class="manifesto__destaque" style="--i:3">propostas?</span></span>
    </h2>

    <div class="numeros">
      <dl class="numeros__lista">
        <div data-reveal style="--i:0"><dt class="mono">Candidaturas</dt><dd data-contar="<?= $stats['candidatos'] ?>"><?= pad2($stats['candidatos']) ?></dd></div>
        <div data-reveal style="--i:1"><dt class="mono">Propostas publicadas</dt><dd data-contar="<?= $stats['propostas'] ?>"><?= pad2($stats['propostas']) ?></dd></div>
        <div data-reveal style="--i:2"><dt class="mono">Temas</dt><dd data-contar="<?= $stats['temas'] ?>"><?= pad2($stats['temas']) ?></dd></div>
        <div data-reveal style="--i:3"><dt class="mono">Documentos-fonte</dt><dd data-contar="<?= $stats['documentos'] ?>"><?= pad2($stats['documentos']) ?></dd></div>
      </dl>
      <p class="numeros__nota mono">Contagens informativas. A quantidade de propostas não indica qualidade nem viabilidade.</p>
    </div>
  </section>

  <!-- =========================== TEMAS =========================== -->
  <section class="temas-home secao" id="temas" data-secao="temas">
    <header class="cab-secao wrap">
      <p class="kicker" data-reveal>Escolha um problema</p>
      <h2 class="display display--xl">
        <span class="mascara"><span>O que você quer</span></span>
        <span class="mascara"><span><span class="outline">melhorar</span> no Brasil?</span></span>
      </h2>
      <p class="cab-secao__nota" data-reveal><?= count($temas) ?> temas. Selecione um para ver, lado a lado, o que cada candidatura escreveu sobre ele.</p>
    </header>

    <div class="indice-wrap">
      <div class="indice__eco" aria-hidden="true" data-eco>01</div>
      <ol class="indice">
        <?php foreach ($temas as $i => $t): ?>
        <li class="indice__item" style="--cor: <?= $cores[$i % 3] ?>; --i: <?= $i ?>" data-reveal>
          <a class="indice__link" href="<?= h(link_tema($t['slug'])) ?>" data-eco-num="<?= pad2($i + 1) ?>">
            <span class="indice__faixa" aria-hidden="true"></span>
            <span class="indice__num mono"><?= pad2($i + 1) ?></span>
            <span class="indice__nome"><?= h($t['nome']) ?></span>
            <span class="indice__meta mono">
              <span><?= pad2((int) $t['total_propostas']) ?> <?= plural((int) $t['total_propostas'], 'proposta', 'propostas') ?></span>
              <span><?= (int) $t['total_candidatos'] ?> de <?= count($cands) ?> candidaturas</span>
            </span>
            <span class="indice__glifo"><?= glifo_tema($t['slug']) ?></span>
            <span class="indice__seta" aria-hidden="true">→</span>
          </a>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <p class="wrap mais-link"><a class="btn-line" href="<?= h(link_pagina('temas')) ?>">Índice completo de temas <span aria-hidden="true">→</span></a></p>
  </section>

  <div class="divisor" data-coord="S-02 · CANDIDATURAS"></div>

  <!-- =========================== CANDIDATURAS =========================== -->
  <section class="cands-home secao" id="candidatos" data-secao="candidatos">
    <header class="cab-secao cab-secao--lado wrap">
      <div>
        <p class="kicker" data-reveal>Candidaturas · <?= h($eleicao['cargo_principal'] ?? '') ?></p>
        <h2 class="display display--xl">
          <span class="mascara"><span>Quem está</span></span>
          <span class="mascara"><span class="outline">na cédula</span></span>
        </h2>
      </div>
      <p class="cab-secao__nota" data-reveal>Ordem alfabética. Todas as candidaturas aparecem com a mesma estrutura, o mesmo tamanho de foto e as mesmas informações.</p>
    </header>
    <div class="wrap">
      <div class="cedula" style="--cols: <?= min(4, max(1, count($cands))) ?>" data-reveal>
        <?php foreach ($cands as $c) echo render_candidato_slot($c); ?>
      </div>
      <p class="mais-link"><a class="btn-line" href="<?= h(link_pagina('candidatos')) ?>">Todas as candidaturas <span aria-hidden="true">→</span></a></p>
    </div>
  </section>

  <!-- =========================== COMPARAR =========================== -->
  <section class="comparar-home secao" id="comparar" data-secao="comparar">
    <div class="colunas-fantasma" aria-hidden="true"><span>A</span><span>B</span><span>C</span></div>
    <div class="wrap comparar-home__grade">
      <h2 class="display display--xl comparar-home__titulo">
        <span class="mascara"><span>Compare</span></span>
        <span class="mascara"><span class="outline">antes de</span></span>
        <span class="mascara"><span>decidir.</span></span>
      </h2>

      <form class="seletor" action="<?= h(link_pagina('comparar')) ?>" method="get" data-comparar data-reveal>
        <fieldset>
          <legend class="mono"><span class="seletor__passo">1</span> Escolha de 2 a 3 candidaturas</legend>
          <div class="marcadores">
            <?php foreach ($cands as $c): ?>
            <label class="marcador">
              <input type="checkbox" name="c[]" value="<?= (int) $c['id'] ?>">
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
              <option value="todos">Todos os temas</option>
              <?php foreach ($temas as $t): ?><option value="<?= h($t['slug']) ?>"><?= h($t['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </fieldset>
        <p class="seletor__aviso mono" data-aviso role="status" aria-live="polite"></p>
        <button class="btn-solid" type="submit">Montar comparação <span aria-hidden="true">→</span></button>
        <p class="seletor__nota">Sem notas, sem ranking, sem vencedor. As informações aparecem lado a lado, como estão nos documentos.</p>
      </form>
    </div>
  </section>

  <div class="divisor" data-coord="S-03 · PROCESSO"></div>

  <!-- =========================== COMO FUNCIONA =========================== -->
  <section class="processo-sec secao" id="como-funciona" data-secao="como-funciona">
    <header class="cab-secao cab-secao--lado wrap">
      <div>
        <p class="kicker" data-reveal>Como funciona</p>
        <h2 class="display display--xl">
          <span class="mascara"><span>Do documento</span></span>
          <span class="mascara"><span class="outline">à informação</span></span>
        </h2>
      </div>
      <p class="cab-secao__nota" data-reveal>Cada resumo pode ser conferido com o trecho exato do documento oficial, com número de página.</p>
    </header>
    <?php require __DIR__ . '/includes/processo.php'; ?>
    <p class="wrap mais-link"><a class="btn-line" href="<?= h(link_pagina('metodologia')) ?>">Como analisamos as propostas <span aria-hidden="true">→</span></a></p>
  </section>

</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
