<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/correcoes.php';
exigir_login();
correcoes_tabela();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $id = post_int('id');
    $acao = post('acao', 20);
    $volta = ['status' => post('filtro', 20)];

    if ($id && $acao === 'salvar') {
        $status = post('status', 20);
        if (!isset(CORRECAO_STATUS[$status])) $status = 'nova';
        $pdo->prepare('UPDATE correcoes SET status = :s, nota_interna = :n WHERE id = :id')
            ->execute(['s' => $status, 'n' => post_opcional('nota_interna', 3000), 'id' => $id]);
        flash('Relato Nº ' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . ' atualizado.');
    }
    // LGPD: excluir o relato (e o e-mail) a pedido da pessoa — só administradores
    if ($id && $acao === 'excluir') {
        if (!eh_admin()) flash('Somente administradores podem excluir relatos.', 'erro');
        else { $pdo->prepare('DELETE FROM correcoes WHERE id = :id')->execute(['id' => $id]); flash('Relato excluído.'); }
    }
    redirecionar('admin/correcoes.php', array_filter($volta));
}

$filtro = get_texto('status', 20);
if (!isset(CORRECAO_STATUS[$filtro])) $filtro = '';

$contagem = array_fill_keys(array_keys(CORRECAO_STATUS), 0);
foreach ($pdo->query('SELECT status, COUNT(*) n FROM correcoes GROUP BY status') as $r) $contagem[$r['status']] = (int) $r['n'];

$sql = 'SELECT co.*, p.acao, c.nome_urna, cat.nome AS categoria
        FROM correcoes co
        LEFT JOIN propostas p ON p.id = co.proposta_id
        LEFT JOIN candidatos c ON c.id = p.candidato_id
        LEFT JOIN categorias cat ON cat.id = p.categoria_id';
$params = [];
if ($filtro) { $sql .= ' WHERE co.status = :s'; $params['s'] = $filtro; }
$sql .= " ORDER BY FIELD(co.status, 'nova', 'em_analise', 'corrigida', 'descartada'), co.id DESC LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($params);
$lista = $st->fetchAll();

admin_topo('Correções', 'correcoes');
?>
<section class="adm-status">
  <a class="adm-status__item<?= $filtro === '' ? ' is-ativo' : '' ?>" href="<?= h(url('admin/correcoes.php')) ?>"><span class="adm-mono">Todas</span><strong><?= pad2(array_sum($contagem)) ?></strong></a>
  <?php foreach (CORRECAO_STATUS as $k => $rot): ?>
  <a class="adm-status__item<?= $filtro === $k ? ' is-ativo' : '' ?>" href="<?= h(url('admin/correcoes.php', ['status' => $k])) ?>"><span class="adm-mono"><?= h($rot) ?></span><strong><?= pad2($contagem[$k]) ?></strong></a>
  <?php endforeach; ?>
</section>
<p class="adm-nota">Relatos enviados pelo público em <a class="adm-link" href="<?= h(link_pagina('correcoes')) ?>" target="_blank" rel="noopener">Encontrou um erro?</a>. Confira com o PDF; se houver erro, edite a proposta (ela volta para revisão) e marque o relato como <strong>Corrigida</strong>.</p>

<?php if (!$lista): ?>
  <p class="adm-vazio">Nenhum relato <?= $filtro ? 'com este status' : 'recebido ainda' ?>.</p>
<?php endif; ?>

<?php foreach ($lista as $r): $num = str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT); ?>
<article class="adm-caixa adm-correcao adm-correcao--<?= h($r['status']) ?>">
  <header class="adm-caixa__cab">
    <h2>Nº <?= $num ?> · <?= h(CORRECAO_TIPOS[$r['tipo']] ?? $r['tipo']) ?></h2>
    <span class="adm-mono"><?= h(date('d/m/Y H:i', strtotime((string) $r['criado_em']))) ?></span>
  </header>
  <?php if ($r['proposta_id']): ?>
  <p class="adm-mono">
    Proposta Nº <?= str_pad((string) $r['proposta_id'], 4, '0', STR_PAD_LEFT) ?>
    <?php if ($r['nome_urna']): ?> · <?= h($r['nome_urna']) ?> · <?= h((string) $r['categoria']) ?><?php endif; ?>
    <?php if ($r['pagina']): ?> · página informada: <?= h($r['pagina']) ?><?php endif; ?>
    · <a class="adm-link" href="<?= h(url('admin/propostas.php', ['editar' => (int) $r['proposta_id']])) ?>">editar proposta</a>
  </p>
  <?php if ($r['acao']): ?><p class="adm-nota">“<?= h($r['acao']) ?>”</p><?php endif; ?>
  <?php elseif ($r['pagina']): ?>
  <p class="adm-mono">Página informada: <?= h($r['pagina']) ?></p>
  <?php endif; ?>
  <p class="adm-correcao__texto"><?= nl2br(h($r['descricao'])) ?></p>
  <?php if ($r['email']): ?><p class="adm-mono">Resposta para: <a class="adm-link" href="mailto:<?= h($r['email']) ?>?subject=<?= rawurlencode('Votai — relato Nº ' . $num) ?>"><?= h($r['email']) ?></a></p><?php endif; ?>

  <form method="post" class="adm-correcao__form">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
    <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
    <label>Status
      <select name="status">
        <?php foreach (CORRECAO_STATUS as $k => $rot): ?><option value="<?= h($k) ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= h($rot) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Nota interna (não aparece no site)
      <input name="nota_interna" maxlength="3000" value="<?= h((string) $r['nota_interna']) ?>">
    </label>
    <div class="adm-botoes">
      <button class="adm-btn adm-btn--pri" type="submit" name="acao" value="salvar">Salvar</button>
      <?php if (eh_admin()): ?><button class="adm-btn adm-btn--rejeitada" type="submit" name="acao" value="excluir" data-confirmar="Excluir este relato definitivamente? (use para pedidos de exclusão de dados)">Excluir</button><?php endif; ?>
    </div>
  </form>
</article>
<?php endforeach; ?>
<?php admin_rodape();
