<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function admin_topo(string $titulo, string $ativo = ''): void
{
    $u = admin_usuario();
    $itens = [
        'index'      => ['Painel', 'admin/index.php'],
        'propostas'  => ['Propostas', 'admin/propostas.php'],
        'correcoes'  => ['Correções', 'admin/correcoes.php'],
        'candidatos' => ['Candidatos', 'admin/candidatos.php'],
        'partidos'   => ['Partidos', 'admin/partidos.php'],
        'documentos' => ['Documentos', 'admin/documentos.php'],
        'categorias' => ['Categorias', 'admin/categorias.php'],
        'importar'   => ['Importar TSE', 'admin/importar.php'],
        'conta'      => ['Minha conta', 'admin/conta.php'],
    ];
    if (($u['papel'] ?? '') !== 'admin') {
        unset($itens['candidatos'], $itens['partidos'], $itens['documentos'], $itens['categorias'], $itens['importar']);
    }
    $f = flash();
    $novasCorr = 0;
    if ($u) {
        require_once dirname(__DIR__, 2) . '/includes/correcoes.php';
        $novasCorr = correcoes_contar_novas();
    }
    ?>
<!doctype html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($titulo) ?> · Admin <?= h(SITE_NAME) ?></title>
<script><?= ADMIN_SCRIPT_TEMA ?></script>
<link rel="icon" href="<?= h(url('assets/images/interface/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= h(asset('css/admin.css')) ?>">
<script src="<?= h(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="adm">
<?php if ($u): ?>
<aside class="adm-lateral">
  <a class="adm-marca" href="<?= h(url('admin/index.php')) ?>">
    <svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 2 38 20 20 38 2 20Z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="20" cy="20" r="8.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m15.5 21.5 3 3 6-7" fill="none" stroke="#22d470" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span><?= h(SITE_NAME) ?><small>Painel de revisão</small></span>
  </a>
  <nav class="adm-nav" aria-label="Administração">
    <?php foreach ($itens as $k => [$rot, $href]): ?>
      <a href="<?= h(url($href)) ?>" class="<?= $k === $ativo ? 'is-ativo' : '' ?>"><?= h($rot) ?><?php if ($k === 'correcoes' && $novasCorr): ?> <span class="adm-contador" title="Relatos novos"><?= (int) $novasCorr ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="adm-lateral__base">
    <button type="button" class="adm-tema" data-adm-tema aria-pressed="false">
      <svg class="adm-tema__ico adm-tema__ico--sol" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3.6"/><path d="M10 1.5v2.2M10 16.3v2.2M1.5 10h2.2M16.3 10h2.2M4 4l1.6 1.6M14.4 14.4 16 16M4 16l1.6-1.6M14.4 5.6 16 4"/></svg>
      <svg class="adm-tema__ico adm-tema__ico--lua" viewBox="0 0 20 20" aria-hidden="true"><path d="M15.8 12.6A6.8 6.8 0 0 1 7.4 4.2a6.8 6.8 0 1 0 8.4 8.4Z"/></svg>
      <span data-adm-tema-txt>Modo claro</span>
    </button>
    <a href="<?= h(url('')) ?>" target="_blank" rel="noopener">Ver site ↗</a>
    <p><?= h($u['nome']) ?><small><?= h($u['papel'] === 'admin' ? 'Administrador' : 'Revisor') ?></small></p>
    <form method="post" action="<?= h(url('admin/logout.php')) ?>"><?= csrf_campo() ?><button type="submit">Sair</button></form>
  </div>
</aside>
<?php else: ?>
<div class="adm-tema-solto"><button type="button" class="adm-tema" data-adm-tema aria-pressed="false">
      <svg class="adm-tema__ico adm-tema__ico--sol" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3.6"/><path d="M10 1.5v2.2M10 16.3v2.2M1.5 10h2.2M16.3 10h2.2M4 4l1.6 1.6M14.4 14.4 16 16M4 16l1.6-1.6M14.4 5.6 16 4"/></svg>
      <svg class="adm-tema__ico adm-tema__ico--lua" viewBox="0 0 20 20" aria-hidden="true"><path d="M15.8 12.6A6.8 6.8 0 0 1 7.4 4.2a6.8 6.8 0 1 0 8.4 8.4Z"/></svg>
      <span data-adm-tema-txt>Modo claro</span>
    </button></div>
<?php endif; ?>
<main class="adm-conteudo">
  <?php if ($u): ?><header class="adm-cab"><h1><?= h($titulo) ?></h1></header><?php endif; ?>
  <?php if ($f): ?><p class="adm-flash adm-flash--<?= h($f['tipo']) ?>" role="status"><?= h($f['msg']) ?></p><?php endif; ?>
<?php
}

function admin_rodape(): void
{
    echo "</main>\n</body>\n</html>";
}
