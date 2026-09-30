<?php
/**
 * Navegação principal
 *  - Desktop: trilho vertical fixo à direita (rail)
 *  - Tablet/celular: "urna de navegação" — visor compacto + teclado numérico
 */

declare(strict_types=1);

$isHome = ($pageId ?? '') === 'inicio';

$menu = [
    ['id' => 'inicio',        'label' => 'Início',        'href' => $isHome ? '#inicio'        : url('')],
    ['id' => 'temas',         'label' => 'Temas',         'href' => $isHome ? '#temas'         : link_pagina('temas')],
    ['id' => 'candidatos',    'label' => 'Candidatos',    'href' => $isHome ? '#candidatos'    : link_pagina('candidatos')],
    ['id' => 'comparar',      'label' => 'Comparar',      'href' => $isHome ? '#comparar'      : link_pagina('comparar')],
    ['id' => 'como-funciona', 'label' => 'Como funciona', 'curto' => 'Como funciona', 'href' => $isHome ? '#como-funciona' : link_pagina('metodologia')],
    ['id' => 'busca',         'label' => 'Busca',         'href' => link_pagina('busca'), 'busca' => true],
];

$atual = 'Início';
foreach ($menu as $i => $m) if ($m['id'] === ($pageId ?? '')) $atual = $m['label'];

/** Botão de tema: uma pequena urna eletrônica. Escuro = visor desligado; claro = visor aceso. */
function urna_toggle(string $classe = ''): string
{
    return '<button type="button" class="urna-tema js-tema ' . h($classe) . '" aria-pressed="false" aria-label="Ativar modo claro">
  <svg viewBox="0 0 44 36" aria-hidden="true">
    <rect class="urna-tema__corpo" x="1.5" y="4.5" width="41" height="27" rx="3"/>
    <rect class="urna-tema__visor" x="5" y="8" width="19" height="13" rx="1.2"/>
    <rect class="urna-tema__luz" x="5" y="8" width="19" height="13" rx="1.2"/>
    <path class="urna-tema__scan" d="M6 14.5h17"/>
    <g class="urna-tema__teclas">
      <circle cx="29" cy="10" r="1.3"/><circle cx="33.5" cy="10" r="1.3"/><circle cx="38" cy="10" r="1.3"/>
      <circle cx="29" cy="14.5" r="1.3"/><circle cx="33.5" cy="14.5" r="1.3"/><circle cx="38" cy="14.5" r="1.3"/>
      <circle cx="29" cy="19" r="1.3"/><circle cx="33.5" cy="19" r="1.3"/><circle cx="38" cy="19" r="1.3"/>
    </g>
    <rect class="urna-tema__confirma" x="31" y="24" width="8.5" height="3.6" rx="1"/>
    <rect class="urna-tema__corrige" x="23" y="24" width="6" height="3.6" rx="1"/>
    <path class="urna-tema__pe" d="M8 31.5v3M36 31.5v3"/>
  </svg>
  <span class="urna-tema__txt mono" aria-hidden="true"><span class="so-escuro">Modo escuro</span><span class="so-claro">Modo claro</span></span>
</button>';
}
?>
<nav class="rail" aria-label="Navegação principal">
  <a class="rail__marca" href="<?= h(url('')) ?>" aria-label="<?= h(SITE_NAME) ?> — início">
    <svg class="marca-simbolo" viewBox="0 0 40 40" aria-hidden="true">
      <path d="M20 2 38 20 20 38 2 20Z" fill="none" stroke="currentColor" stroke-width="1.5"/>
      <circle cx="20" cy="20" r="8.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
      <path d="M11.8 18.4c5.6-1.9 11-1 16.4 2.6" fill="none" stroke="var(--amarelo)" stroke-width="2"/>
      <path d="m15.5 21.5 3 3 6-7" fill="none" stroke="var(--verde-luz)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="rail__nome"><?= h(mb_strtoupper(SITE_NAME)) ?></span>
  </a>

  <ul class="rail__lista">
    <?php foreach ($menu as $i => $m): $ativo = $m['id'] === ($pageId ?? ''); ?>
    <li>
      <a class="rail__item<?= $ativo ? ' is-ativo' : '' ?>" href="<?= h($m['href']) ?>" data-nav="<?= h($m['id']) ?>"
         <?= !empty($m['busca']) ? 'data-abrir-busca' : '' ?> <?= $ativo ? 'aria-current="page"' : '' ?>>
        <span class="rail__label mono"><?= h($m['label']) ?></span>
        <span class="rail__linha" aria-hidden="true"></span>
        <span class="rail__cod mono" aria-hidden="true"><?= pad2($i + 1) ?></span>
        <span class="rail__dot" aria-hidden="true"></span>
        <span class="rail__nomeitem mono" aria-hidden="true"><?= h($m['curto'] ?? $m['label']) ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="rail__base">
    <?= urna_toggle('urna-tema--rail') ?>
    <p class="rail__coord mono" aria-hidden="true">15°47′S · 47°52′W</p>
  </div>
  <span class="rail__progresso" aria-hidden="true"><span></span></span>
</nav>

<!-- Celular / tablet: visor compacto -->
<div class="dock">
  <button type="button" class="dock__btn" aria-expanded="false" aria-controls="urna-nav" data-dock>
    <span class="dock__visor">
      <span class="dock__marca mono"><?= h(mb_strtoupper(SITE_NAME)) ?></span>
      <span class="dock__atual" data-dock-atual><?= h($atual) ?></span>
    </span>
    <span class="dock__teclado" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
    <span class="visually-hidden">Abrir navegação</span>
  </button>
</div>

<div class="urna-nav" id="urna-nav" role="dialog" aria-modal="true" aria-label="Navegação" hidden>
  <div class="urna-nav__fundo" data-fechar-urna></div>
  <div class="urna-nav__corpo">
    <div class="urna-nav__visor" aria-live="polite">
      <p class="mono urna-nav__cab"><span>Navegação</span><span><?= h(mb_strtoupper(SITE_NAME)) ?> · 2026</span></p>
      <p class="urna-nav__destino" data-visor>Escolha uma seção</p>
      <p class="mono urna-nav__dica">Toque em uma tecla</p>
    </div>
    <ul class="urna-nav__teclas">
      <?php foreach ($menu as $i => $m): $ativo = $m['id'] === ($pageId ?? ''); ?>
      <li><a class="tecla<?= $ativo ? ' is-ativo' : '' ?>" href="<?= h($m['href']) ?>" data-nav="<?= h($m['id']) ?>" data-tecla="<?= h($m['label']) ?>" <?= !empty($m['busca']) ? 'data-abrir-busca' : '' ?>>
        <span class="tecla__n"><?= $i + 1 ?></span><span class="tecla__t mono"><?= h($m['label']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="urna-nav__base">
      <button type="button" class="tecla tecla--corrige" data-fechar-urna><span class="mono">Corrige</span><small>fechar</small></button>
      <?= urna_toggle('tecla tecla--tema') ?>
    </div>
  </div>
</div>
