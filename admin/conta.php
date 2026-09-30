<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
$u = exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $atual = (is_string($_POST['atual'] ?? null) ? $_POST['atual'] : '');
    $nova  = (is_string($_POST['nova'] ?? null) ? $_POST['nova'] : '');
    $conf  = (is_string($_POST['confirmacao'] ?? null) ? $_POST['confirmacao'] : '');
    $st = db()->prepare('SELECT senha_hash FROM usuarios_admin WHERE id = :id');
    $st->execute(['id' => $u['id']]);
    if (!password_verify($atual, (string) $st->fetchColumn())) flash('Senha atual incorreta.', 'erro');
    elseif (mb_strlen($nova) < 10) flash('A nova senha precisa ter pelo menos 10 caracteres.', 'erro');
    elseif ($nova !== $conf) flash('A confirmação não confere.', 'erro');
    elseif (hash_equals($atual, $nova) || strcasecmp($nova, SENHA_PADRAO) === 0) flash('Escolha uma senha diferente da atual e da senha padrão de instalação.', 'erro');
    else {
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        db()->prepare('UPDATE usuarios_admin SET senha_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $u['id']]);
        session_regenerate_id(true);
        $_SESSION['marca'] = marca_senha($hash);   // outras sessões abertas com a senha antiga caem
        unset($_SESSION['trocar_senha']);
        flash('Senha alterada. Outras sessões abertas foram encerradas.');
    }
    redirecionar('admin/conta.php');
}

admin_topo('Minha conta', 'conta');
?>
<section class="adm-caixa adm-estreito">
  <p class="adm-nota"><?= h($u['nome']) ?> · <?= h($u['email']) ?></p>
  <?php if (!empty($_SESSION['trocar_senha'])): ?>
  <p class="adm-alerta adm-alerta--erro">Você entrou com a senha padrão de instalação. Crie uma senha nova para liberar o painel.</p>
  <?php endif; ?>
  <h2 class="adm-sub">Alterar senha</h2>
  <form method="post" class="adm-form">
    <?= csrf_campo() ?>
    <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
    <label>Nova senha (mín. 10 caracteres)<input type="password" name="nova" required minlength="10" autocomplete="new-password"></label>
    <label>Confirmar nova senha<input type="password" name="confirmacao" required minlength="10" autocomplete="new-password"></label>
    <div class="adm-botoes"><button class="adm-btn adm-btn--pri" type="submit">Alterar senha</button></div>
  </form>
</section>
<?php admin_rodape();
