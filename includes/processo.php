<?php
/** Etapas do processo (usado na home e na metodologia). */
declare(strict_types=1);

$etapas = [
    ['A', '01', 'Fonte oficial',  'Documento',     'Partimos apenas de documentos oficiais registrados pelas candidaturas, como o plano de governo entregue à Justiça Eleitoral.', 'PDF · registro oficial'],
    ['B', '02', 'Processamento',  'Extração',      'O texto é extraído do documento e dividido em trechos, preservando o número da página de cada um.', 'Texto + página'],
    ['C', '03', 'Organização',    'Classificação', 'Os trechos que contêm propostas são classificados por tema e subtema. Campos como meta, prazo e custo só são preenchidos se estiverem escritos.', '14 temas'],
    ['D', '04', 'Revisão',        'Revisão',       'Uma pessoa confere cada resumo com o trecho original. O que não está no documento aparece como “Não informado no documento.”', 'Revisão humana'],
    ['E', '05', 'Publicação',     'Publicação',    'Só propostas aprovadas na revisão são publicadas — sempre com acesso ao trecho original e ao documento completo.', 'Com fonte'],
];
?>
<ol class="processo wrap">
  <span class="processo__linha" aria-hidden="true"><span></span></span>
  <?php foreach ($etapas as $k => [$letra, $num, $titulo, $fase, $texto, $tag]): ?>
  <li class="etapa" data-reveal style="--i: <?= $k ?>">
    <p class="etapa__cod" aria-hidden="true"><span class="etapa__letra"><?= $letra ?></span><span class="etapa__num mono">-<?= $num ?></span></p>
    <p class="etapa__fase mono"><?= h($fase) ?></p>
    <h3 class="etapa__titulo"><?= h($titulo) ?></h3>
    <p class="etapa__texto"><?= h($texto) ?></p>
    <p class="etapa__tag mono"><?= h($tag) ?></p>
    <?php if ($k < count($etapas) - 1): ?><span class="etapa__seta mono" aria-hidden="true">↓</span><?php endif; ?>
  </li>
  <?php endforeach; ?>
</ol>
