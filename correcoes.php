<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';
require_once __DIR__ . '/includes/correcoes.php';

$erros = [];
$v = ['tipo' => '', 'proposta' => '', 'pagina' => '', 'descricao' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) $v[$k] = is_string($_POST[$k] ?? null) ? mb_substr(trim($_POST[$k]), 0, $k === 'descricao' ? 3000 : 190) : '';
    try {
        [$ok, $res] = correcoes_registrar($_POST);
    } catch (Throwable $e) {
        error_log('[votai correcoes] ' . $e->getMessage());
        [$ok, $res] = [false, ['Não foi possível registrar agora. Tente novamente em alguns minutos.']];
    }
    if ($ok) {
        header('Location: ' . link_pagina('correcoes', ['enviado' => $res]), true, 303);
        exit;
    }
    $erros = $res;
} else {
    $n = get_int('n');
    if ($n) $v['proposta'] = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    $pg = get_int('pagina');
    if ($pg) $v['pagina'] = (string) $pg;
}

$enviado = get_int('enviado');
$propRef = null;
if (!$enviado && $v['proposta'] !== '' && ctype_digit(ltrim($v['proposta'], '0') ?: '0')) {
    $propRef = proposta_publicada((int) $v['proposta']);
}

$pageId        = 'como-funciona';
$pageTitle     = 'Canal de correções';
$pageDesc      = 'Encontrou algo diferente do documento oficial? Informe aqui. Toda correção é revisada e registrada.';
$pageCanonical = link_pagina('correcoes');

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina wrap">
    <p class="kicker" data-reveal>Transparência · Correções</p>
    <h1 class="display display--xl topo-pagina__titulo">
      <span class="mascara"><span>Encontrou</span></span>
      <span class="mascara"><span class="outline">um erro?</span></span>
    </h1>
    <p class="lead" data-reveal>Se algo no site estiver diferente do documento oficial, conte para a gente. Cada relato é conferido com o PDF original e, se for o caso, a proposta é corrigida e a mudança aparece em <a href="<?= h(link_pagina('atualizacoes')) ?>">Últimas atualizações</a>.</p>
  </header>

  <section class="correcao wrap">
    <?php if ($enviado): ?>
    <div class="correcao__ok" role="status">
      <p class="mono">Relato recebido · protocolo Nº <?= str_pad((string) $enviado, 5, '0', STR_PAD_LEFT) ?></p>
      <h2>Obrigado. Vamos conferir com o documento original.</h2>
      <p>Se a informação estiver mesmo diferente do documento, a correção será feita e registrada com data. Se você deixou um e-mail, poderemos responder por lá.</p>
      <div class="correcao__acoes">
        <a class="btn-solid" href="<?= h(link_pagina('correcoes')) ?>">Enviar outro relato</a>
        <a class="btn-line" href="<?= h(url('')) ?>">Voltar ao início</a>
      </div>
    </div>
    <?php else: ?>
    <div class="correcao__grade">
      <form class="correcao__form" method="post" action="<?= h(link_pagina('correcoes')) ?>" novalidate>
        <?php if ($erros): ?>
        <div class="correcao__erros" role="alert">
          <p class="mono">Confira antes de enviar</p>
          <ul><?php foreach ($erros as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <?php if ($propRef): ?>
        <div class="correcao__ref">
          <p class="mono">Proposta Nº <?= str_pad((string) $propRef['id'], 4, '0', STR_PAD_LEFT) ?> · <?= h($propRef['candidato_nome']) ?> · <?= h($propRef['categoria_nome']) ?></p>
          <p><?= h($propRef['acao']) ?></p>
        </div>
        <?php endif; ?>

        <fieldset class="correcao__tipos">
          <legend class="mono">1 · Qual é o problema?</legend>
          <?php foreach (CORRECAO_TIPOS as $k => $rot): ?>
          <label class="correcao__tipo">
            <input type="radio" name="tipo" value="<?= h($k) ?>" <?= $v['tipo'] === $k ? 'checked' : '' ?> required>
            <span><?= h($rot) ?></span>
          </label>
          <?php endforeach; ?>
        </fieldset>

        <div class="correcao__linha">
          <label class="correcao__campo">
            <span class="mono">2 · Nº da proposta <small>(se houver)</small></span>
            <input type="text" name="proposta" inputmode="numeric" maxlength="8" placeholder="Ex.: 0042" value="<?= h($v['proposta']) ?>">
          </label>
          <label class="correcao__campo">
            <span class="mono">Página do documento <small>(se souber)</small></span>
            <input type="text" name="pagina" maxlength="40" placeholder="Ex.: 34" value="<?= h($v['pagina']) ?>">
          </label>
        </div>

        <label class="correcao__campo">
          <span class="mono">3 · O que está diferente do documento?</span>
          <textarea name="descricao" rows="6" maxlength="3000" required placeholder="Ex.: O resumo diz que a meta é até 2028, mas na página 34 o plano fala em 2030."><?= h($v['descricao']) ?></textarea>
        </label>

        <label class="correcao__campo">
          <span class="mono">4 · Seu e-mail <small>(opcional, só se quiser resposta)</small></span>
          <input type="email" name="email" maxlength="190" autocomplete="email" value="<?= h($v['email']) ?>">
        </label>

        <!-- armadilha para robôs: pessoas não veem este campo -->
        <label class="correcao__armadilha" aria-hidden="true">Site<input type="text" name="site" tabindex="-1" autocomplete="off"></label>
        <input type="hidden" name="carimbo" value="<?= h(correcoes_carimbo()) ?>">

        <button class="btn-solid" type="submit">Enviar relato <span aria-hidden="true">→</span></button>
        <p class="correcao__nota">Ao enviar, você concorda com o uso descrito na <a href="<?= h(link_pagina('privacidade')) ?>">política de privacidade</a>. Não guardamos seu IP; o e-mail só é usado para responder a este relato.</p>
      </form>

      <aside class="correcao__lado">
        <h2 class="mono">Como funciona</h2>
        <ol>
          <li><strong>Você envia</strong> o relato, de preferência com o Nº da proposta (aparece em cada ficha) e a página do documento.</li>
          <li><strong>Conferimos</strong> com o PDF oficial registrado na Justiça Eleitoral.</li>
          <li><strong>Se estiver errado</strong>, a proposta é corrigida, passa de novo pela revisão e a mudança é registrada com data.</li>
        </ol>
        <p>O canal serve para erros de conteúdo. Opiniões sobre candidaturas ou propostas não são publicadas nem respondidas: o <?= h(SITE_NAME) ?> não avalia quem é melhor.</p>
      </aside>
    </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
