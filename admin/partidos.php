<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
exigir_admin();

$pdo = db();
// SVG não é aceito no envio: pode carregar código executável. Use PNG ou WebP.
$LOGO_TIPOS = ['image/png' => 'png', 'image/webp' => 'webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $id = post_int('id');
    $d = [
        'nome'   => post('nome', 160),
        'sigla'  => mb_strtoupper(post('sigla', 20)),
        'numero' => preg_replace('/\D/', '', post('numero', 5)) ?: null,
        'logo'   => post_opcional('logo', 255),
    ];
    $erros = [];
    if ($d['nome'] === '' || $d['sigla'] === '') $erros[] = 'nome e sigla são obrigatórios';
    $st = $pdo->prepare('SELECT COUNT(*) FROM partidos WHERE sigla = :s AND id <> :id');
    $st->execute(['s' => $d['sigla'], 'id' => (int) $id]);
    if ($st->fetchColumn()) $erros[] = 'sigla já cadastrada';
    try {
        $novo = salvar_upload('logo_arquivo', 'assets/images/partidos', $d['sigla'] ?: 'partido', $LOGO_TIPOS, 1048576);
        if ($novo) $d['logo'] = $novo;
    } catch (RuntimeException $e) { $erros[] = $e->getMessage(); }
    if ($d['logo'] && !preg_match('#^assets/images/partidos/[\w.\-]+$#', $d['logo'])) $erros[] = 'o logo deve ficar em assets/images/partidos/';

    if ($erros) {
        flash('Verifique: ' . rtrim(implode('; ', $erros), '.') . '.', 'erro');
    } elseif ($id) {
        $pdo->prepare('UPDATE partidos SET nome = :nome, sigla = :sigla, numero = :numero, logo = :logo WHERE id = :id')->execute($d + ['id' => $id]);
        flash('Partido atualizado.');
    } else {
        $pdo->prepare('INSERT INTO partidos (nome, sigla, numero, logo) VALUES (:nome, :sigla, :numero, :logo)')->execute($d);
        flash('Partido cadastrado.');
    }
    redirecionar('admin/partidos.php');
}

$editar = null;
if ($eid = get_int('editar')) {
    $st = $pdo->prepare('SELECT * FROM partidos WHERE id = :id');
    $st->execute(['id' => $eid]);
    $editar = $st->fetch() ?: null;
}
$lista = $pdo->query('SELECT pa.*, (SELECT COUNT(*) FROM candidatos c WHERE c.partido_id = pa.id) AS cands FROM partidos pa ORDER BY pa.nome')->fetchAll();

admin_topo('Partidos', 'partidos');
?>
<div class="adm-grade2">
  <section class="adm-caixa">
    <h2 class="adm-sub">Partidos cadastrados</h2>
    <table class="adm-tabela">
      <thead><tr><th>Logo</th><th>Sigla</th><th>Nome</th><th>Nº</th><th>Candidatos</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lista as $p): ?>
      <tr>
        <td><?= logo_partido(['partido_sigla' => $p['sigla'], 'partido_nome' => $p['nome'], 'partido_logo' => $p['logo']]) ?></td>
        <td class="adm-mono"><?= h($p['sigla']) ?></td><td><?= h($p['nome']) ?></td><td class="adm-mono"><?= h((string) $p['numero']) ?></td>
        <td class="adm-mono"><?= (int) $p['cands'] ?></td>
        <td><a class="adm-chip" href="<?= h(url('admin/partidos.php', ['editar' => $p['id']])) ?>">Editar</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <section class="adm-caixa">
    <h2 class="adm-sub"><?= $editar ? 'Editar ' . h($editar['sigla']) : 'Novo partido' ?></h2>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
      <label>Nome *<input name="nome" required maxlength="160" value="<?= h($editar['nome'] ?? '') ?>"></label>
      <label>Sigla *<input name="sigla" required maxlength="20" value="<?= h($editar['sigla'] ?? '') ?>"></label>
      <label>Número<input name="numero" maxlength="5" inputmode="numeric" value="<?= h((string) ($editar['numero'] ?? '')) ?>"></label>
      <label>Logotipo oficial (PNG ou WebP, até 1 MB)<input type="file" name="logo_arquivo" accept="image/png,image/webp"></label>
      <label>ou caminho<input name="logo" maxlength="255" value="<?= h((string) ($editar['logo'] ?? '')) ?>" placeholder="assets/images/partidos/partido-01.webp"></label>
      <p class="adm-nota">O logo é exibido pequeno, em área neutra, sem deformação. Sem logo, o site mostra a sigla.</p>
      <div class="adm-botoes"><button class="adm-btn adm-btn--pri" type="submit">Salvar</button><?php if ($editar): ?><a class="adm-btn" href="<?= h(url('admin/partidos.php')) ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </section>
</div>
<?php admin_rodape();
