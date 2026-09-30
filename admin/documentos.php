<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
exigir_admin();

$pdo = db();
$TIPOS_DOC = ['plano_governo' => 'Plano de Governo', 'programa_partidario' => 'Programa partidário', 'documento_complementar' => 'Documento complementar', 'outro' => 'Outro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = post('acao', 20);
    $id = post_int('id');

    if ($acao === 'excluir' && $id) {
        $n = $pdo->prepare('SELECT COUNT(*) FROM fontes WHERE documento_id = :id');
        $n->execute(['id' => $id]);
        if ($n->fetchColumn()) {
            flash('Este documento é fonte de propostas e não pode ser excluído.', 'erro');
        } else {
            $st = $pdo->prepare('SELECT arquivo FROM documentos WHERE id = :id');
            $st->execute(['id' => $id]);
            $arq = $st->fetchColumn();
            $pdo->prepare('DELETE FROM documentos WHERE id = :id')->execute(['id' => $id]);
            if ($arq && str_starts_with($arq, 'uploads/documentos/') && is_file(ROOT_PATH . '/' . $arq)) @unlink(ROOT_PATH . '/' . $arq);
            flash('Documento excluído.');
        }
        redirecionar('admin/documentos.php');
    }

    if ($acao === 'salvar') {
        $candId = post_int('candidato_id');
        $d = [
            'candidato_id'    => $candId,
            'titulo'          => post('titulo', 220),
            'tipo'            => array_key_exists(post('tipo', 40), $TIPOS_DOC) ? post('tipo', 40) : 'plano_governo',
            'url_origem'      => filter_var(post('url_origem', 500), FILTER_VALIDATE_URL) ?: null,
            'total_paginas'   => post_int('total_paginas'),
            'data_publicacao' => preg_match('/^\d{4}-\d{2}-\d{2}$/', post('data_publicacao', 10)) ? post('data_publicacao', 10) : null,
            'demonstracao'    => isset($_POST['demonstracao']) ? 1 : 0,
        ];
        if ($d['url_origem'] && !preg_match('#^https?://#i', $d['url_origem'])) $d['url_origem'] = null;
        $erros = [];
        if (!$candId) $erros[] = 'candidato';
        if ($d['titulo'] === '') $erros[] = 'título';
        $st = $pdo->prepare('SELECT eleicao_id FROM candidatos WHERE id = :id');
        $st->execute(['id' => (int) $candId]);
        $d['eleicao_id'] = $st->fetchColumn() ?: null;
        if (!$d['eleicao_id']) $erros[] = 'candidato inválido';

        $arquivo = null;
        try {
            $arquivo = salvar_upload('arquivo', 'uploads/documentos', $d['titulo'] ?: 'documento', ['application/pdf' => 'pdf'], 25 * 1048576);
        } catch (RuntimeException $e) { $erros[] = $e->getMessage(); }

        if ($erros) {
            flash('Verifique: ' . rtrim(implode('; ', $erros), '.') . '.', 'erro');
            redirecionar('admin/documentos.php', $id ? ['editar' => $id] : ['novo' => 1]);
        }
        if ($arquivo) {
            $d['arquivo'] = $arquivo;
            $d['hash_sha256'] = hash_file('sha256', ROOT_PATH . '/' . $arquivo);
        }
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($d)));
            $pdo->prepare("UPDATE documentos SET $sets WHERE id = :id")->execute($d + ['id' => $id]);
        } else {
            $cols = implode(', ', array_keys($d));
            $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($d)));
            $pdo->prepare("INSERT INTO documentos ($cols) VALUES ($vals)")->execute($d);
        }
        flash('Documento salvo.');
        redirecionar('admin/documentos.php');
    }
}

$candidatos = $pdo->query('SELECT id, nome_urna FROM candidatos ORDER BY nome_urna')->fetchAll();
$editarId = get_int('editar');

if ($editarId || isset($_GET['novo'])) {
    $doc = ['id' => null, 'candidato_id' => get_int('candidato'), 'titulo' => '', 'tipo' => 'plano_governo', 'url_origem' => '', 'total_paginas' => '', 'data_publicacao' => '', 'arquivo' => '', 'hash_sha256' => '', 'demonstracao' => 0];
    if ($editarId) {
        $st = $pdo->prepare('SELECT * FROM documentos WHERE id = :id');
        $st->execute(['id' => $editarId]);
        $doc = $st->fetch() ?: null;
        if (!$doc) { flash('Documento não encontrado.', 'erro'); redirecionar('admin/documentos.php'); }
    }
    admin_topo($editarId ? 'Editar documento' : 'Adicionar documento', 'documentos');
    ?>
    <p><a class="adm-link" href="<?= h(url('admin/documentos.php')) ?>">← Voltar</a></p>
    <form method="post" enctype="multipart/form-data" class="adm-form adm-form--grade">
      <?= csrf_campo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
      <fieldset>
        <legend>Documento oficial</legend>
        <label>Candidato *<select name="candidato_id" required><option value="">Selecione</option>
          <?php foreach ($candidatos as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $doc['candidato_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nome_urna']) ?></option><?php endforeach; ?></select></label>
        <label>Tipo<select name="tipo"><?php foreach ($TIPOS_DOC as $k => $v): ?><option value="<?= h($k) ?>" <?= $doc['tipo'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
        <label class="adm-col2">Título *<input name="titulo" required maxlength="220" value="<?= h($doc['titulo']) ?>"></label>
        <label class="adm-col2">Link da fonte oficial (ex.: página do TSE)<input type="url" name="url_origem" maxlength="500" value="<?= h((string) $doc['url_origem']) ?>" placeholder="https://"></label>
        <label>Total de páginas<input type="number" name="total_paginas" min="1" value="<?= h((string) $doc['total_paginas']) ?>"></label>
        <label>Data de publicação<input type="date" name="data_publicacao" value="<?= h((string) $doc['data_publicacao']) ?>"></label>
        <label class="adm-col2">Arquivo PDF (até 25 MB)<?= $doc['arquivo'] ? ' <small>atual: ' . h($doc['arquivo']) . '</small>' : '' ?><input type="file" name="arquivo" accept="application/pdf"></label>
        <?php if ($doc['hash_sha256']): ?><p class="adm-nota adm-col2 adm-mono">SHA-256: <?= h($doc['hash_sha256']) ?></p><?php endif; ?>
        <label class="adm-check adm-col2"><input type="checkbox" name="demonstracao" <?= $doc['demonstracao'] ? 'checked' : '' ?>> Documento fictício (demonstração)</label>
      </fieldset>
      <div class="adm-botoes"><button class="adm-btn adm-btn--pri" type="submit">Salvar</button></div>
    </form>
    <?php
    admin_rodape();
    exit;
}

$lista = $pdo->query('SELECT d.*, c.nome_urna, (SELECT COUNT(*) FROM fontes f WHERE f.documento_id = d.id) AS nfontes
    FROM documentos d JOIN candidatos c ON c.id = d.candidato_id ORDER BY c.nome_urna, d.titulo')->fetchAll();

admin_topo('Documentos', 'documentos');
?>
<div class="adm-barra"><p class="adm-nota">Documentos oficiais usados como fonte das propostas.</p><a class="adm-btn adm-btn--pri" href="<?= h(url('admin/documentos.php', ['novo' => 1])) ?>">+ Adicionar documento</a></div>
<div class="adm-tabela-wrap">
<table class="adm-tabela">
  <thead><tr><th>Título</th><th>Candidato</th><th>Tipo</th><th>Páginas</th><th>Propostas vinculadas</th><th>Arquivo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($lista as $d): ?>
  <tr>
    <td><a href="<?= h(url('admin/documentos.php', ['editar' => $d['id']])) ?>"><?= h($d['titulo']) ?></a><?= $d['demonstracao'] ? ' <span class="badge">Demo</span>' : '' ?></td>
    <td><?= h($d['nome_urna']) ?></td>
    <td><?= h($TIPOS_DOC[$d['tipo']] ?? $d['tipo']) ?></td>
    <td class="adm-mono"><?= h((string) $d['total_paginas']) ?></td>
    <td class="adm-mono"><?= (int) $d['nfontes'] ?></td>
    <td><?php if (arquivo_existe($d['arquivo'])): ?><a class="adm-link" href="<?= h(url($d['arquivo'])) ?>" target="_blank" rel="noopener">PDF ↗</a><?php elseif ($d['url_origem']): ?><a class="adm-link" href="<?= h($d['url_origem']) ?>" target="_blank" rel="noopener">Link ↗</a><?php else: ?><span class="adm-alerta">sem arquivo</span><?php endif; ?></td>
    <td class="adm-acoes">
      <a class="adm-chip" href="<?= h(url('admin/documentos.php', ['editar' => $d['id']])) ?>">Editar</a>
      <?php if (!(int) $d['nfontes']): ?>
      <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button class="adm-chip adm-chip--rejeitada" type="submit" data-confirmar="Excluir este documento?">Excluir</button></form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="7" class="adm-vazio">Nenhum documento cadastrado.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php admin_rodape();
