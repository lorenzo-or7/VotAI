<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
exigir_login();

$porStatus = array_fill_keys(array_keys(STATUS_ROTULOS), 0);
foreach (db()->query('SELECT status, COUNT(*) n FROM propostas GROUP BY status') as $r) $porStatus[$r['status']] = (int) $r['n'];

$totais = db()->query("SELECT
    (SELECT COUNT(*) FROM candidatos) cand,
    (SELECT COUNT(*) FROM documentos) docs,
    (SELECT COUNT(*) FROM categorias) cats,
    (SELECT COUNT(*) FROM partidos) parts")->fetch();

$fila = db()->query("SELECT p.id, p.resumo, p.status, p.atualizado_em, c.nome_urna, cat.nome AS cat
    FROM propostas p JOIN candidatos c ON c.id = p.candidato_id JOIN categorias cat ON cat.id = p.categoria_id
    WHERE p.status IN ('pendente','em_revisao','aprovada')
    ORDER BY FIELD(p.status,'em_revisao','pendente','aprovada'), p.atualizado_em DESC LIMIT 10")->fetchAll();

$hist = db()->query("SELECT h.*, u.nome AS usuario FROM propostas_historico h LEFT JOIN usuarios_admin u ON u.id = h.usuario_id ORDER BY h.id DESC LIMIT 8")->fetchAll();

admin_topo('Painel', 'index');
?>
<section class="adm-status">
  <?php foreach (STATUS_ROTULOS as $k => $rot): ?>
  <a class="adm-status__item adm-status__item--<?= h($k) ?>" href="<?= h(url('admin/propostas.php', ['status' => $k])) ?>">
    <span class="adm-mono"><?= h($rot) ?></span>
    <strong><?= pad2($porStatus[$k]) ?></strong>
  </a>
  <?php endforeach; ?>
</section>
<p class="adm-nota">Somente propostas com status <strong>Publicada</strong> aparecem no site público. Fluxo: Pendente → Em revisão → Aprovada → Publicada (ou Rejeitada).</p>

<div class="adm-grade2">
  <section class="adm-caixa">
    <header class="adm-caixa__cab"><h2>Fila de revisão</h2><a class="adm-link" href="<?= h(url('admin/propostas.php', ['status' => 'em_revisao'])) ?>">Ver todas →</a></header>
    <?php if (!$fila): ?><p class="adm-vazio">Nenhuma proposta aguardando revisão.</p><?php else: ?>
    <ul class="adm-lista">
      <?php foreach ($fila as $p): ?>
      <li><a href="<?= h(url('admin/propostas.php', ['editar' => $p['id']])) ?>">
        <span class="adm-mono">Nº <?= str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT) ?> · <?= h($p['nome_urna']) ?> · <?= h($p['cat']) ?></span>
        <span><?= h(mb_strimwidth($p['resumo'], 0, 110, '…')) ?></span>
      </a><?= status_badge($p['status']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="adm-caixa">
    <header class="adm-caixa__cab"><h2>Cadastros</h2></header>
    <dl class="adm-totais">
      <div><dt>Candidatos</dt><dd><?php if (eh_admin()): ?><a href="<?= h(url('admin/candidatos.php')) ?>"><?= pad2((int) $totais['cand']) ?></a><?php else: ?><?= pad2((int) $totais['cand']) ?><?php endif; ?></dd></div>
      <div><dt>Partidos</dt><dd><?php if (eh_admin()): ?><a href="<?= h(url('admin/partidos.php')) ?>"><?= pad2((int) $totais['parts']) ?></a><?php else: ?><?= pad2((int) $totais['parts']) ?><?php endif; ?></dd></div>
      <div><dt>Documentos</dt><dd><?php if (eh_admin()): ?><a href="<?= h(url('admin/documentos.php')) ?>"><?= pad2((int) $totais['docs']) ?></a><?php else: ?><?= pad2((int) $totais['docs']) ?><?php endif; ?></dd></div>
      <div><dt>Categorias</dt><dd><?php if (eh_admin()): ?><a href="<?= h(url('admin/categorias.php')) ?>"><?= pad2((int) $totais['cats']) ?></a><?php else: ?><?= pad2((int) $totais['cats']) ?><?php endif; ?></dd></div>
    </dl>
    <h3 class="adm-sub">Últimas alterações de status</h3>
    <?php if (!$hist): ?><p class="adm-vazio">Sem registros ainda.</p><?php else: ?>
    <ul class="adm-hist">
      <?php foreach ($hist as $h): ?>
      <li><span class="adm-mono"><?= h(date('d/m H:i', strtotime($h['criado_em']))) ?></span>
        Nº <?= str_pad((string) $h['proposta_id'], 4, '0', STR_PAD_LEFT) ?>: <?= h(STATUS_ROTULOS[$h['status_anterior']] ?? '—') ?> → <strong><?= h(STATUS_ROTULOS[$h['status_novo']] ?? $h['status_novo']) ?></strong>
        <small><?= h($h['usuario'] ?? 'sistema') ?></small></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
<?php admin_rodape();
