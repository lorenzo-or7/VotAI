<?php
/**
 * MODO APRESENTAÇÃO do comparador.
 * Tela cheia, sem menu, texto grande. Um slide por subtema, com as candidaturas lado a lado.
 * Variáveis esperadas (vindas de comparar.php): $selecionados, $tema, $subs, $porCand, $apresentar
 */

declare(strict_types=1);

$MAX_POR_CEL = 4;
$linkComp = url_absoluta(link_comparar($selecionados, $tema['slug']));
$linkCurto = preg_replace('#^https?://#', '', $linkComp);
$hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');
$totalSlides = 2 + count($subs) + 1;
$slideN = 0;
$num = static function () use (&$slideN, $totalSlides): string {
    $slideN++;
    return pad2($slideN) . ' / ' . pad2($totalSlides);
};
?>
<div class="apres" id="apresentacao" tabindex="-1" role="dialog" aria-modal="true" aria-label="Modo apresentação: <?= h($tema['nome']) ?>" hidden
     data-apres <?= $apresentar ? 'data-apres-auto' : '' ?> style="--n: <?= count($selecionados) ?>">
  <div class="apres__palco" data-apres-palco>

    <!-- Capa -->
    <section class="apres__slide apres__slide--capa" data-slide>
      <p class="apres__kicker mono"><span><?= h(SITE_NAME) ?> · Comparação</span><span><?= $num() ?></span></p>
      <h2 class="apres__tema"><?= h(mb_strtoupper($tema['nome'])) ?></h2>
      <ul class="apres__quem">
        <?php foreach ($selecionados as $k => $c): $qtd = count($porCand[(int) $c['id']] ?? []); ?>
        <li data-cand="<?= (int) $c['id'] ?>">
          <span class="apres__letra mono"><?= chr(65 + $k) ?></span>
          <?= foto_candidato($c, 'apres__foto', '160px') ?>
          <strong><?= h($c['nome_urna']) ?></strong>
          <span class="mono"><?= h($c['numero']) ?> · <?= h($c['partido_sigla'] ?? '') ?> · <?= pad2($qtd) ?> <?= plural($qtd, 'proposta', 'propostas') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="apres__rodape mono">Fonte: planos de governo registrados na Justiça Eleitoral · consulta em <?= h($hoje) ?> · sem notas nem ranking</p>
    </section>

    <!-- Um slide por subtema -->
    <?php foreach ($subs as $s): ?>
    <section class="apres__slide" data-slide data-slide-sub>
      <p class="apres__kicker mono"><span><?= h($tema['nome']) ?> · <?= !empty($todos) ? 'Tema' : 'Subtema' ?></span><span><?= $num() ?></span></p>
      <h2 class="apres__tit"><?= h($s['nome']) ?></h2>
      <div class="apres__cols">
        <?php foreach ($selecionados as $k => $c): $lista = $s['porCand'][(int) $c['id']] ?? []; ?>
        <div class="apres__col" data-cand="<?= (int) $c['id'] ?>">
          <p class="apres__col-quem mono"><span class="apres__letra"><?= chr(65 + $k) ?></span> <?= h($c['nome_urna']) ?></p>
          <?php if (!$lista): ?>
            <p class="apres__vazio">Sem proposta neste subtema no documento analisado.</p>
          <?php else: ?>
          <ul class="apres__itens">
            <?php foreach (array_slice($lista, 0, $MAX_POR_CEL) as $p): ?>
            <li><?= h($p['acao']) ?> <span class="mono">p. <?= (int) $p['pagina'] ?></span></li>
            <?php endforeach; ?>
          </ul>
          <?php if (count($lista) > $MAX_POR_CEL): ?><p class="apres__mais mono">+ <?= count($lista) - $MAX_POR_CEL ?> no site</p><?php endif; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <!-- Metas e prazos -->
    <section class="apres__slide" data-slide>
      <p class="apres__kicker mono"><span><?= h($tema['nome']) ?> · Números escritos nos documentos</span><span><?= $num() ?></span></p>
      <h2 class="apres__tit">Metas, prazos e custos</h2>
      <div class="apres__cols">
        <?php foreach ($selecionados as $k => $c): $lista = array_values(array_filter($porCand[(int) $c['id']] ?? [], 'tem_quantificacao')); ?>
        <div class="apres__col" data-cand="<?= (int) $c['id'] ?>">
          <p class="apres__col-quem mono"><span class="apres__letra"><?= chr(65 + $k) ?></span> <?= h($c['nome_urna']) ?></p>
          <?php if (!$lista): ?>
            <p class="apres__vazio">Nenhuma meta, prazo ou custo escrito no documento para este tema.</p>
          <?php else: ?>
          <ul class="apres__itens apres__itens--quant">
            <?php foreach (array_slice($lista, 0, $MAX_POR_CEL) as $p): ?>
            <li><?= chips_quantificacao($p) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php if (count($lista) > $MAX_POR_CEL): ?><p class="apres__mais mono">+ <?= count($lista) - $MAX_POR_CEL ?> no site</p><?php endif; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Encerramento -->
    <section class="apres__slide apres__slide--fim" data-slide>
      <p class="apres__kicker mono"><span><?= h(SITE_NAME) ?></span><span><?= $num() ?></span></p>
      <h2 class="apres__tit">Confira cada proposta<br>no documento original.</h2>
      <p class="apres__link"><?= h($linkCurto) ?></p>
      <p class="apres__rodape mono">Cada proposta tem o trecho literal e a página do plano de governo. O <?= h(SITE_NAME) ?> não recomenda candidaturas.</p>
    </section>
  </div>

  <div class="apres__controles">
    <button type="button" class="apres__btn" data-apres-ant aria-label="Slide anterior">←</button>
    <span class="apres__pos mono" data-apres-pos aria-live="polite"></span>
    <button type="button" class="apres__btn" data-apres-prox aria-label="Próximo slide">→</button>
    <button type="button" class="apres__btn apres__btn--txt mono" data-apres-tela>Tela cheia</button>
    <button type="button" class="apres__btn apres__btn--txt mono" data-apres-sair>Sair · Esc</button>
  </div>
  <span class="apres__progresso" aria-hidden="true"><span data-apres-barra></span></span>
</div>
