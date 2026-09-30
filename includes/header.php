<?php
/**
 * Cabeçalho HTML. Variáveis opcionais antes do require:
 *   $pageTitle  — título da página
 *   $pageDesc   — meta description
 *   $pageId     — item ativo do menu (inicio|temas|candidatos|comparar|como-funciona|busca)
 *   $bodyClass  — classe extra no <body>
 *   $pageCanonical — endereço oficial (usado no link compartilhado)
 *   $pageImagem    — imagem da prévia do link (og:image)
 */

declare(strict_types=1);

require_once __DIR__ . '/componentes.php';

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — ' . SITE_NAME : SITE_NAME . ' — O que eles propõem para o Brasil?';
$pageDesc  = $pageDesc  ?? SITE_DESCRIPTION;
$pageId    = $pageId    ?? '';
$bodyClass = $bodyClass ?? '';
$pageCanonical = $pageCanonical ?? null;               // endereço curto/oficial da página
$pageImagem    = $pageImagem ?? url('og.php');         // imagem da prévia do link (WhatsApp, redes)
$pageTipo      = $pageTipo ?? 'website';

// Script inline único (tema claro/escuro antes da pintura). O hash dele entra na política de segurança (CSP).
$scriptTema = "try{var t=localStorage.getItem('votai-tema');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t;}catch(e){}document.documentElement.classList.add('js');";
enviar_cabecalhos_seguranca([$scriptTema]);
?>
<!doctype html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($pageTitle) ?></title>
<meta name="description" content="<?= h($pageDesc) ?>">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="#060807">
<?php if ($pageCanonical): ?><link rel="canonical" href="<?= h(url_absoluta($pageCanonical)) ?>">
<meta property="og:url" content="<?= h(url_absoluta($pageCanonical)) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h($pageDesc) ?>">
<meta property="og:type" content="<?= h($pageTipo) ?>">
<meta property="og:image" content="<?= h(url_absoluta($pageImagem)) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= h($pageTitle) ?>">
<meta name="twitter:card" content="summary_large_image">
<script><?= $scriptTema ?></script>
<link rel="preload" href="<?= h(url('assets/fonts/archivo-var.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= h(url('assets/fonts/ibm-plex-mono-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="icon" href="<?= h(url('assets/images/interface/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= h(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= h(asset('css/componentes.css')) ?>">
<link rel="stylesheet" href="<?= h(asset('css/paginas.css')) ?>">
<script type="module" src="<?= h(asset('js/main.js')) ?>"></script>
</head>
<body class="<?= h(trim('p-' . ($pageId ?: 'geral') . ' ' . $bodyClass)) ?>" data-page="<?= h($pageId) ?>" data-base="<?= h(BASE_URL) ?>">
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<div class="grao" aria-hidden="true"></div>
<?php require __DIR__ . '/menu.php'; ?>
<?= demo_ativo() ? '<div class="faixa-demo mono" role="note"><span class="aviso-demo__dot" aria-hidden="true"></span>Demonstração · dados fictícios</div>' : '' ?>
