<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
exigir_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = post('acao', 20);

    if ($acao === 'categoria') {
        $id = post_int('id');
        $d = [
            'nome'           => post('nome', 120),
            'slug'           => gerar_slug(post('slug', 80) ?: post('nome', 120)),
            'descricao'      => post_opcional('descricao', 2000),
            'palavras_chave' => post_opcional('palavras_chave', 2000),
            'ordem'          => (int) ($_POST['ordem'] ?? 0),
            'ativo'          => isset($_POST['ativo']) ? 1 : 0,
        ];
        $st = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE slug = :s AND id <> :id');
        $st->execute(['s' => $d['slug'], 'id' => (int) $id]);
        if ($d['nome'] === '') flash('Informe o nome da categoria.', 'erro');
        elseif ($st->fetchColumn()) flash('Já existe uma categoria com esse endereço (slug).', 'erro');
        elseif ($id) { $pdo->prepare('UPDATE categorias SET nome=:nome, slug=:slug, descricao=:descricao, palavras_chave=:palavras_chave, ordem=:ordem, ativo=:ativo WHERE id=:id')->execute($d + ['id' => $id]); flash('Categoria atualizada.'); }
        else { $pdo->prepare('INSERT INTO categorias (nome, slug, descricao, palavras_chave, ordem, ativo) VALUES (:nome,:slug,:descricao,:palavras_chave,:ordem,:ativo)')->execute($d); $id = (int) $pdo->lastInsertId(); flash('Categoria criada.'); }
        redirecionar('admin/categorias.php', $id ? ['editar' => $id] : []);
    }

    if ($acao === 'sub') {
        $catId = post_int('categoria_id');
        $subId = post_int('sub_id');
        $nome = post('nome', 120);
        if ($catId && $nome !== '') {
            $slug = gerar_slug($nome);
            try {
                if ($subId) {
                    $pdo->prepare('UPDATE subcategorias SET nome = :n, slug = :s, ordem = :o WHERE id = :id AND categoria_id = :c')
                        ->execute(['n' => $nome, 's' => $slug, 'o' => (int) ($_POST['ordem'] ?? 0), 'id' => $subId, 'c' => $catId]);
                } else {
                    $pdo->prepare('INSERT INTO subcategorias (categoria_id, nome, slug, ordem) VALUES (:c, :n, :s, :o)')
                        ->execute(['c' => $catId, 'n' => $nome, 's' => $slug, 'o' => (int) ($_POST['ordem'] ?? 0)]);
                }
                flash('Subcategoria salva.');
            } catch (PDOException) {
                flash('Já existe uma subcategoria com esse nome nesta categoria.', 'erro');
            }
        }
        redirecionar('admin/categorias.php', ['editar' => $catId]);
    }

    if ($acao === 'sub_excluir') {
        $catId = post_int('categoria_id');
        $pdo->prepare('DELETE FROM subcategorias WHERE id = :id AND categoria_id = :c')->execute(['id' => post_int('sub_id'), 'c' => $catId]);
        flash('Subcategoria removida. Propostas vinculadas ficaram sem subcategoria.');
        redirecionar('admin/categorias.php', ['editar' => $catId]);
    }
}

$cats = $pdo->query("SELECT cat.*, (SELECT COUNT(*) FROM subcategorias s WHERE s.categoria_id = cat.id) nsub,
    (SELECT COUNT(*) FROM propostas p WHERE p.categoria_id = cat.id) nprop FROM categorias cat ORDER BY cat.ordem, cat.nome")->fetchAll();

$editar = null; $subs = [];
if ($eid = get_int('editar')) {
    $st = $pdo->prepare('SELECT * FROM categorias WHERE id = :id');
    $st->execute(['id' => $eid]);
    $editar = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT s.*, (SELECT COUNT(*) FROM propostas p WHERE p.subcategoria_id = s.id) nprop FROM subcategorias s WHERE categoria_id = :id ORDER BY ordem, nome');
    $st->execute(['id' => $eid]);
    $subs = $st->fetchAll();
}

admin_topo('Categorias', 'categorias');
?>
<div class="adm-grade2">
  <section class="adm-caixa">
    <header class="adm-caixa__cab"><h2>Temas</h2><a class="adm-link" href="<?= h(url('admin/categorias.php')) ?>">+ Nova categoria</a></header>
    <table class="adm-tabela">
      <thead><tr><th>Ordem</th><th>Nome</th><th>Subcat.</th><th>Propostas</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
      <tr class="<?= $editar && (int) $editar['id'] === (int) $c['id'] ? 'is-sel' : '' ?>">
        <td class="adm-mono"><?= pad2((int) $c['ordem']) ?></td>
        <td><a href="<?= h(url('admin/categorias.php', ['editar' => $c['id']])) ?>"><?= h($c['nome']) ?></a></td>
        <td class="adm-mono"><?= (int) $c['nsub'] ?></td><td class="adm-mono"><?= (int) $c['nprop'] ?></td>
        <td><?= $c['ativo'] ? '<span class="badge badge--publicada">Ativa</span>' : '<span class="badge badge--rejeitada">Inativa</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <div>
    <section class="adm-caixa">
      <h2 class="adm-sub"><?= $editar ? 'Editar: ' . h($editar['nome']) : 'Nova categoria' ?></h2>
      <form method="post" class="adm-form">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="categoria"><input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <label>Nome *<input name="nome" required maxlength="120" value="<?= h($editar['nome'] ?? '') ?>"></label>
        <label>Endereço (slug)<input name="slug" maxlength="80" value="<?= h($editar['slug'] ?? '') ?>" placeholder="gerado automaticamente"></label>
        <label>Descrição (neutra)<textarea name="descricao" rows="3"><?= h((string) ($editar['descricao'] ?? '')) ?></textarea></label>
        <label>Palavras-chave para a busca <small>separadas por vírgula (ex.: sus, hospital, remédio)</small><textarea name="palavras_chave" rows="2"><?= h((string) ($editar['palavras_chave'] ?? '')) ?></textarea></label>
        <label>Ordem de exibição<input type="number" name="ordem" value="<?= (int) ($editar['ordem'] ?? count($cats) + 1) ?>"></label>
        <label class="adm-check"><input type="checkbox" name="ativo" <?= !$editar || $editar['ativo'] ? 'checked' : '' ?>> Ativa no site</label>
        <div class="adm-botoes"><button class="adm-btn adm-btn--pri" type="submit">Salvar categoria</button></div>
      </form>
    </section>

    <?php if ($editar): ?>
    <section class="adm-caixa">
      <h2 class="adm-sub">Subcategorias de <?= h($editar['nome']) ?></h2>
      <ul class="adm-subs">
        <?php foreach ($subs as $s): ?>
        <li>
          <form method="post" class="adm-inline">
            <?= csrf_campo() ?><input type="hidden" name="acao" value="sub"><input type="hidden" name="categoria_id" value="<?= (int) $editar['id'] ?>"><input type="hidden" name="sub_id" value="<?= (int) $s['id'] ?>">
            <input type="number" name="ordem" value="<?= (int) $s['ordem'] ?>" aria-label="Ordem" class="adm-ordem">
            <input name="nome" value="<?= h($s['nome']) ?>" maxlength="120" aria-label="Nome">
            <span class="adm-mono"><?= (int) $s['nprop'] ?> prop.</span>
            <button class="adm-chip" type="submit">Salvar</button>
          </form>
          <form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="sub_excluir"><input type="hidden" name="categoria_id" value="<?= (int) $editar['id'] ?>"><input type="hidden" name="sub_id" value="<?= (int) $s['id'] ?>">
            <button class="adm-chip adm-chip--rejeitada" type="submit" data-confirmar="Remover esta subcategoria?">Remover</button></form>
        </li>
        <?php endforeach; ?>
      </ul>
      <form method="post" class="adm-inline adm-inline--novo">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="sub"><input type="hidden" name="categoria_id" value="<?= (int) $editar['id'] ?>">
        <input type="number" name="ordem" value="<?= count($subs) + 1 ?>" aria-label="Ordem" class="adm-ordem">
        <input name="nome" required maxlength="120" placeholder="Nova subcategoria">
        <button class="adm-btn" type="submit">Adicionar</button>
      </form>
    </section>
    <?php endif; ?>
  </div>
</div>
<?php admin_rodape();
