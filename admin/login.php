<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (admin_usuario()) redirecionar('admin/index.php');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $r = admin_login(post('email', 190), (is_string($_POST['senha'] ?? null) ? $_POST['senha'] : ''));
    if ($r === true) redirecionar('admin/index.php');
    $erro = is_string($r) ? $r : 'E-mail ou senha incorretos.';
}

admin_topo('Entrar');
?>
<section class="adm-login">
  <div class="adm-login__marca">
    <p class="adm-mono">Painel administrativo</p>
    <h1><?= h(mb_strtoupper(SITE_NAME)) ?></h1>
    <p>Acesso restrito à equipe de revisão.</p>
  </div>
  <form method="post" class="adm-form adm-login__form" autocomplete="on">
    <?= csrf_campo() ?>
    <?php if ($erro): ?><p class="adm-flash adm-flash--erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
    <label>E-mail<input type="email" name="email" required autofocus autocomplete="username" value="<?= h(post('email', 190)) ?>"></label>
    <label>Senha<input type="password" name="senha" required autocomplete="current-password"></label>
    <button class="adm-btn adm-btn--pri" type="submit">Entrar</button>
    <a class="adm-link" href="<?= h(url('')) ?>">← Voltar ao site</a>
  </form>
</section>
<?php admin_rodape();
