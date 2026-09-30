<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
exigir_admin();

$pdo = db();
$FOTO_TIPOS = ['image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = post('acao', 20);
    $id = post_int('id');

    if ($acao === 'ativo' && $id) {
        $pdo->prepare('UPDATE candidatos SET ativo = 1 - ativo WHERE id = :id')->execute(['id' => $id]);
        flash('Visibilidade do candidato alterada.');
        redirecionar('admin/candidatos.php');
    }

    if ($acao === 'salvar') {
        $d = [
            'eleicao_id'   => post_int('eleicao_id'),
            'partido_id'   => post_int('partido_id'),
            'nome'         => post('nome', 180),
            'nome_urna'    => post('nome_urna', 120),
            'slug'         => gerar_slug(post('slug', 140) ?: post('nome_urna', 120)),
            'numero'       => preg_replace('/\D/', '', post('numero', 8)) ?: null,
            'cargo'        => post('cargo', 120),
            'uf'           => ($uf = strtoupper(post('uf', 2))) && preg_match('/^[A-Z]{2}$/', $uf) ? $uf : null,
            'foto'         => post_opcional('foto', 255),
            'foto_fonte'   => post_opcional('foto_fonte', 255),
            'demonstracao' => isset($_POST['demonstracao']) ? 1 : 0,
            'ativo'        => isset($_POST['ativo']) ? 1 : 0,
        ];
        $erros = [];
        if (!$d['eleicao_id']) $erros[] = 'eleição';
        if ($d['nome'] === '') $erros[] = 'nome completo';
        if ($d['nome_urna'] === '') $erros[] = 'nome de urna';
        if ($d['cargo'] === '') $erros[] = 'cargo';
        $st = $pdo->prepare('SELECT COUNT(*) FROM candidatos WHERE slug = :s AND id <> :id');
        $st->execute(['s' => $d['slug'], 'id' => (int) $id]);
        if ($st->fetchColumn()) $erros[] = 'endereço (slug) já utilizado';

        try {
            $novaFoto = salvar_upload('foto_arquivo', 'assets/images/candidatos', $d['slug'], $FOTO_TIPOS, 3 * 1048576);
            if ($novaFoto) $d['foto'] = $novaFoto;
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        }
        if ($d['foto'] && (str_contains($d['foto'], '..') || !preg_match('#^assets/images/candidatos/[\w.\-]+$#', $d['foto']))) {
            $erros[] = 'caminho da foto deve ficar em assets/images/candidatos/';
        }

        if ($erros) {
            flash('Verifique: ' . rtrim(implode('; ', $erros), '.') . '.', 'erro');
            redirecionar('admin/candidatos.php', $id ? ['editar' => $id] : ['novo' => 1]);
        }
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($d)));
            $pdo->prepare("UPDATE candidatos SET $sets WHERE id = :id")->execute($d + ['id' => $id]);
        } else {
            $cols = implode(', ', array_keys($d));
            $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($d)));
            $pdo->prepare("INSERT INTO candidatos ($cols) VALUES ($vals)")->execute($d);
            $id = (int) $pdo->lastInsertId();
        }
        flash('Candidato salvo.');
        redirecionar('admin/candidatos.php', ['editar' => $id]);
    }
}

$eleicoes = $pdo->query('SELECT id, nome FROM eleicoes ORDER BY ano DESC')->fetchAll();
$partidos = $pdo->query('SELECT id, nome, sigla FROM partidos ORDER BY nome')->fetchAll();
$editarId = get_int('editar');

if ($editarId || isset($_GET['novo'])) {
    $c = ['id' => null, 'eleicao_id' => $eleicoes[0]['id'] ?? null, 'partido_id' => null, 'nome' => '', 'nome_urna' => '', 'slug' => '', 'numero' => '',
          'cargo' => 'Presidente da República', 'uf' => '', 'foto' => '', 'foto_fonte' => '', 'demonstracao' => 0, 'ativo' => 1];
    if ($editarId) {
        $st = $pdo->prepare('SELECT * FROM candidatos WHERE id = :id');
        $st->execute(['id' => $editarId]);
        $c = $st->fetch() ?: null;
        if (!$c) { flash('Candidato não encontrado.', 'erro'); redirecionar('admin/candidatos.php'); }
    }
    admin_topo($editarId ? 'Editar candidato' : 'Cadastrar candidato', 'candidatos');
    ?>
    <p><a class="adm-link" href="<?= h(url('admin/candidatos.php')) ?>">← Voltar</a></p>
    <form method="post" enctype="multipart/form-data" class="adm-form adm-form--grade">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <fieldset>
        <legend>Identificação</legend>
        <label class="adm-col2">Nome completo *<input name="nome" required maxlength="180" value="<?= h($c['nome']) ?>"></label>
        <label>Nome de urna *<input name="nome_urna" required maxlength="120" value="<?= h($c['nome_urna']) ?>" data-slug-origem></label>
        <label>Endereço (slug)<input name="slug" maxlength="140" value="<?= h($c['slug']) ?>" data-slug-destino placeholder="gerado automaticamente"></label>
        <label>Número<input name="numero" inputmode="numeric" maxlength="8" value="<?= h((string) $c['numero']) ?>"></label>
        <label>Partido
          <select name="partido_id"><option value="">Sem partido</option>
            <?php foreach ($partidos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $c['partido_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['sigla'] . ' — ' . $p['nome']) ?></option><?php endforeach; ?>
          </select></label>
        <label>Cargo *<input name="cargo" required maxlength="120" value="<?= h($c['cargo']) ?>"></label>
        <label>UF <small>(vazio para cargos nacionais)</small><input name="uf" maxlength="2" value="<?= h((string) $c['uf']) ?>"></label>
        <label class="adm-col2">Eleição *
          <select name="eleicao_id" required><?php foreach ($eleicoes as $e): ?><option value="<?= (int) $e['id'] ?>" <?= (int) $c['eleicao_id'] === (int) $e['id'] ? 'selected' : '' ?>><?= h($e['nome']) ?></option><?php endforeach; ?></select></label>
      </fieldset>
      <fieldset>
        <legend>Foto padronizada <small>— 4:5, 800×1000 px, WebP de preferência, fonte oficial</small></legend>
        <div class="adm-foto-previa"><?= foto_candidato($c + ['nome_urna' => $c['nome_urna'] ?: '?'], 'adm-foto') ?></div>
        <label>Enviar arquivo (WebP, JPG ou PNG, até 3 MB)<input type="file" name="foto_arquivo" accept="image/webp,image/jpeg,image/png"></label>
        <label>ou caminho do arquivo<input name="foto" maxlength="255" value="<?= h((string) $c['foto']) ?>" placeholder="assets/images/candidatos/candidato-01.webp"></label>
        <label class="adm-col2">Origem da foto<input name="foto_fonte" maxlength="255" value="<?= h((string) $c['foto_fonte']) ?>" placeholder="Ex.: DivulgaCandContas / TSE"></label>
        <p class="adm-nota adm-col2">Permitido apenas recortar, redimensionar, centralizar e otimizar. Não aplicar filtros ou retoques diferentes entre candidatos.</p>
      </fieldset>
      <fieldset>
        <legend>Situação</legend>
        <label class="adm-check"><input type="checkbox" name="ativo" <?= $c['ativo'] ? 'checked' : '' ?>> Visível no site</label>
        <label class="adm-check"><input type="checkbox" name="demonstracao" <?= $c['demonstracao'] ? 'checked' : '' ?>> Registro fictício (demonstração)</label>
      </fieldset>
      <div class="adm-botoes">
        <button class="adm-btn adm-btn--pri" type="submit">Salvar</button>
        <?php if ($editarId): ?><a class="adm-btn" href="<?= h(url('admin/propostas.php', ['novo' => 1, 'candidato' => $editarId])) ?>">+ Proposta deste candidato</a>
        <a class="adm-btn" href="<?= h(url('admin/documentos.php', ['novo' => 1, 'candidato' => $editarId])) ?>">+ Documento</a><?php endif; ?>
      </div>
    </form>
    <?php
    admin_rodape();
    exit;
}

$lista = $pdo->query("SELECT c.*, pa.sigla, e.nome AS eleicao,
      (SELECT COUNT(*) FROM propostas p WHERE p.candidato_id = c.id) AS total,
      (SELECT COUNT(*) FROM propostas p WHERE p.candidato_id = c.id AND p.status = 'publicada') AS publicadas,
      (SELECT COUNT(*) FROM documentos d WHERE d.candidato_id = c.id) AS docs
    FROM candidatos c LEFT JOIN partidos pa ON pa.id = c.partido_id JOIN eleicoes e ON e.id = c.eleicao_id
    ORDER BY c.nome_urna")->fetchAll();

admin_topo('Candidatos', 'candidatos');
?>
<div class="adm-barra"><span></span><a class="adm-btn adm-btn--pri" href="<?= h(url('admin/candidatos.php', ['novo' => 1])) ?>">+ Cadastrar candidato</a></div>
<div class="adm-tabela-wrap">
<table class="adm-tabela">
  <thead><tr><th>Foto</th><th>Nome de urna</th><th>Nº</th><th>Partido</th><th>Cargo</th><th>Propostas</th><th>Docs</th><th>Situação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($lista as $c): ?>
  <tr>
    <td class="adm-td-foto"><?= foto_candidato($c, 'adm-mini') ?></td>
    <td><a href="<?= h(url('admin/candidatos.php', ['editar' => $c['id']])) ?>"><strong><?= h($c['nome_urna']) ?></strong></a><small><?= h($c['nome']) ?></small></td>
    <td class="adm-mono"><?= h((string) $c['numero']) ?></td>
    <td><?= h((string) $c['sigla']) ?></td>
    <td><?= h($c['cargo']) ?><?= $c['uf'] ? ' · ' . h($c['uf']) : '' ?></td>
    <td class="adm-mono"><?= (int) $c['publicadas'] ?>/<?= (int) $c['total'] ?> <small>publicadas</small></td>
    <td class="adm-mono"><?= (int) $c['docs'] ?></td>
    <td><?= $c['ativo'] ? '<span class="badge badge--publicada">Visível</span>' : '<span class="badge badge--rejeitada">Oculto</span>' ?><?= $c['demonstracao'] ? ' <span class="badge">Demo</span>' : '' ?></td>
    <td class="adm-acoes">
      <a class="adm-chip" href="<?= h(url('admin/candidatos.php', ['editar' => $c['id']])) ?>">Editar</a>
      <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="ativo"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="adm-chip" type="submit"><?= $c['ativo'] ? 'Ocultar' : 'Exibir' ?></button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php admin_rodape();
