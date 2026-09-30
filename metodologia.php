<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$pageId    = 'como-funciona';
$pageTitle = 'Como analisamos as propostas';
$pageDesc  = 'Metodologia e princípios de transparência do ' . SITE_NAME . '.';

$principios = [
    ['Tudo vem de documentos oficiais.', 'As propostas são extraídas exclusivamente de documentos registrados pelas candidaturas, como o plano de governo entregue à Justiça Eleitoral. Não usamos entrevistas, redes sociais, notícias ou declarações de terceiros.'],
    ['Organizamos por tema, não por opinião.', 'Cada proposta é classificada em um dos temas (Saúde, Educação, Economia...) e em um subtema. A mesma lista de temas vale para todas as candidaturas.'],
    ['Nada é inferido.', 'Meta, prazo, custo e fonte de financiamento só são preenchidos quando aparecem escritos no documento. Quando não aparecem, a plataforma mostra: “Não informado no documento.”'],
    ['Você acessa o original.', 'Toda proposta mostra o documento de origem, o número da página e o trecho exato usado para produzir o resumo, com link para abrir o documento completo.'],
    ['Resumo e texto original ficam separados.', 'O resumo é escrito pela plataforma, com auxílio de inteligência artificial, para facilitar a leitura, e nunca é apresentado como fala da candidatura. O texto original aparece à parte, identificado, sem alterações.'],
    ['Revisão humana antes de publicar.', 'Cada proposta passa pelos status Pendente → Em revisão → Aprovada (ou Rejeitada) → Publicada. Somente propostas publicadas aparecem no site.'],
    ['Neutralidade também no visual.', 'Candidaturas aparecem em ordem alfabética, com a mesma estrutura, o mesmo tamanho de foto e o mesmo estilo. Nenhuma recebe cor, selo, brilho ou posição de destaque.'],
];
$ancoras = [0 => 'fonte-oficial', 5 => 'revisao'];
$status = [
    ['Pendente', 'Extraída do documento, aguardando revisão.'],
    ['Em revisão', 'Resumo sendo conferido com o texto original.'],
    ['Aprovada', 'Conferida e pronta para publicação.'],
    ['Rejeitada', 'Não corresponde ao documento ou não é uma proposta.'],
    ['Publicada', 'Visível para o público, com fonte.'],
];

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina wrap">
    <p class="kicker" data-reveal>Transparência · Metodologia</p>
    <h1 class="display display--xxl topo-pagina__titulo">
      <span class="mascara"><span>Como</span></span>
      <span class="mascara"><span class="outline largo">analisamos</span></span>
      <span class="mascara"><span class="estreito">as propostas</span></span>
    </h1>
    <p class="lead" data-reveal>O <?= h(SITE_NAME) ?> organiza informações; não avalia candidaturas. Esta página explica, passo a passo, de onde vem cada dado e o que fazemos — e não fazemos — com ele.</p>
  </header>

  <section class="principios wrap" aria-label="Princípios">
    <?php foreach ($principios as $i => [$tit, $txt]): ?>
    <article class="principio"<?= isset($ancoras[$i]) ? ' id="' . $ancoras[$i] . '"' : '' ?> data-reveal>
      <p class="principio__num" aria-hidden="true"><?= pad2($i + 1) ?></p>
      <div>
        <h2 class="principio__tit"><?= h($tit) ?></h2>
        <p class="principio__txt"><?= h($txt) ?></p>
        <?php if ($i === 4): ?>
        <div class="demo-separacao" aria-hidden="true">
          <div class="demo-separacao__resumo"><p class="rotulo mono"><span class="rotulo__marca rotulo__marca--plataforma"></span>Resumo da proposta</p><p>Escrito pela plataforma</p></div>
          <div class="demo-separacao__original papel"><p class="rotulo mono"><span class="rotulo__marca rotulo__marca--doc"></span>Texto original</p><p>Copiado do documento, página indicada</p></div>
        </div>
        <?php elseif ($i === 5): ?>
        <ol class="status-trilha">
          <?php foreach ($status as $k => [$s, $d]): ?>
          <li class="status-trilha__item<?= $s === 'Publicada' ? ' is-publico' : '' ?><?= $s === 'Rejeitada' ? ' is-rejeitada' : '' ?>"><span class="mono"><?= h($s) ?></span><small><?= h($d) ?></small></li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </section>

  <section class="secao processo-sec" aria-labelledby="proc-tit">
    <header class="cab-secao wrap">
      <p class="kicker">Processo</p>
      <h2 class="display display--xl" id="proc-tit"><span class="mascara"><span>Documento</span></span><span class="mascara"><span class="outline">↓ publicação</span></span></h2>
    </header>
    <?php require __DIR__ . '/includes/processo.php'; ?>
  </section>

  <section class="nao-fazemos wrap" id="nao-fazemos">
    <h2 class="display display--l"><span class="mascara"><span>O que a plataforma</span></span><span class="mascara"><span class="outline">não faz</span></span></h2>
    <ul class="nao-fazemos__lista">
      <?php foreach ([
          'Não recomenda candidaturas nem sugere em quem votar.',
          'Não atribui notas, estrelas, porcentagens ou rankings.',
          'Não aponta “melhor proposta” nem “vencedor” em comparações.',
          'Não avalia viabilidade, qualidade ou mérito das propostas.',
          'Não completa informações ausentes nos documentos.',
          'Não publica propostas sem fonte verificável.',
          'Não escreve biografias ou descrições opinativas.',
      ] as $k => $item): ?>
      <li data-reveal style="--i: <?= $k ?>"><span class="nao-fazemos__x" aria-hidden="true">✕</span><?= h($item) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="sobre-ia wrap" id="ia">
    <div class="sobre-ia__cab">
      <p class="kicker">Resumo assistido por IA</p>
      <h2 class="display display--m">Tecnologia para organizar.<br><span class="outline">Pessoas para conferir.</span></h2>
    </div>
    <div class="sobre-ia__txt">
      <p>Os resumos das propostas são redigidos com auxílio de inteligência artificial. A ferramenta recebe apenas o trecho do plano de governo: não pesquisa na internet, não usa notícias e não completa o que o documento não diz.</p>
      <p>Na extração, uma checagem automática confirma que o trecho citado existe, palavra por palavra, no documento oficial e na página indicada. Trecho que não é encontrado não segue adiante.</p>
      <p>Antes de ir ao ar, uma pessoa compara o resumo com o texto original e aprova, corrige ou rejeita. É isso que o selo <strong>Revisado</strong> indica, com a data da revisão.</p>
      <p>A inteligência artificial não decide o que é publicado, não classifica candidaturas e não participa da busca do site, que funciona só sobre os dados já revisados.</p>
    </div>
  </section>

  <section class="rodizio wrap" id="tema-do-dia">
    <p class="kicker">Tema do dia e atualizações</p>
    <h2 class="display display--m">Escolhas automáticas, iguais para todos.</h2>
    <div class="sobre-ia__txt">
      <p>O “Tema do dia” da página inicial é escolhido por rodízio: cada dia avança um tema, na ordem fixa da lista. Para cada candidatura, a proposta exibida também avança uma posição por dia, e a ordem dos cartões começa a cada dia por uma candidatura diferente, seguindo a ordem alfabética. Ninguém escolhe manualmente o que aparece.</p>
      <p>A página <a href="<?= h(link_pagina('atualizacoes')) ?>">Últimas atualizações</a> é gerada a partir do histórico de revisão: mostra quando propostas foram publicadas, corrigidas ou retiradas do site.</p>
    </div>
  </section>

  <section class="correcoes wrap" id="correcoes">
    <p class="kicker">Correções</p>
    <h2 class="display display--m">Encontrou algo diferente do documento?</h2>
    <p class="lead">Cada proposta tem um número de registro (ex.: Nº 0042). Envie esse número e a página do documento pelo canal de correções. Todo relato é conferido com o PDF oficial; se houver erro, a proposta é corrigida, revisada de novo e a mudança aparece em <a href="<?= h(link_pagina('atualizacoes')) ?>">Últimas atualizações</a>.</p>
    <p class="correcoes__acao"><a class="btn-solid" href="<?= h(link_pagina('correcoes')) ?>">Abrir o canal de correções <span aria-hidden="true">→</span></a></p>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
