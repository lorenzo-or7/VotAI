<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
exigir_login();

$pdo = db();

// ============================================================ AÇÕES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = post('acao', 20);

    // ---------- Mudança de status
    if ($acao === 'status') {
        $id   = post_int('id');
        $novo = post('novo', 20);
        $obs  = post_opcional('observacao', 2000);
        $st = $pdo->prepare('SELECT status FROM propostas WHERE id = :id');
        $st->execute(['id' => $id]);
        $atual = $st->fetchColumn();

        if (!$atual || !isset(STATUS_ROTULOS[$novo])) {
            flash('Proposta ou status inválido.', 'erro');
        } elseif (!in_array($novo, STATUS_TRANSICOES[$atual], true)) {
            flash('Transição não permitida: ' . STATUS_ROTULOS[$atual] . ' → ' . STATUS_ROTULOS[$novo] . '.', 'erro');
        } elseif ($novo === 'publicada' && !eh_admin()) {
            flash('Somente administradores podem publicar.', 'erro');
        } elseif ($novo === 'publicada' && !$pdo->query('SELECT COUNT(*) FROM fontes WHERE proposta_id = ' . (int) $id)->fetchColumn()) {
            flash('Não é possível publicar uma proposta sem fonte (documento + trecho original).', 'erro');
        } else {
            $pdo->prepare('UPDATE propostas SET status = :s, revisado_por = :u, revisado_em = NOW(), observacao_revisao = COALESCE(:o, observacao_revisao) WHERE id = :id')
                ->execute(['s' => $novo, 'u' => admin_usuario()['id'], 'o' => $obs, 'id' => $id]);
            registrar_status((int) $id, $atual, $novo, $obs);
            flash('Status alterado para “' . STATUS_ROTULOS[$novo] . '”.');
        }
        $volta = post('volta', 300);
        header('Location: ' . ($volta && str_starts_with($volta, url('admin/')) ? $volta : url('admin/propostas.php', ['editar' => $id])));
        exit;
    }

    // ---------- Ação em lote
    if ($acao === 'lote') {
        $novo = post('novo', 20);
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $ok = 0; $pulou = 0;
        if (isset(STATUS_ROTULOS[$novo]) && $ids && !($novo === 'publicada' && !eh_admin())) {
            $sel = $pdo->prepare('SELECT status, (SELECT COUNT(*) FROM fontes f WHERE f.proposta_id = p.id) nf FROM propostas p WHERE id = :id');
            $upd = $pdo->prepare('UPDATE propostas SET status = :s, revisado_por = :u, revisado_em = NOW() WHERE id = :id');
            foreach ($ids as $id) {
                $sel->execute(['id' => $id]);
                $r = $sel->fetch();
                if (!$r || !in_array($novo, STATUS_TRANSICOES[$r['status']], true) || ($novo === 'publicada' && !$r['nf'])) { $pulou++; continue; }
                $upd->execute(['s' => $novo, 'u' => admin_usuario()['id'], 'id' => $id]);
                registrar_status($id, $r['status'], $novo, 'Ação em lote.');
                $ok++;
            }
        }
        flash("$ok " . plural($ok, 'proposta alterada', 'propostas alteradas') . ' para “' . (STATUS_ROTULOS[$novo] ?? '?') . '”' . ($pulou ? " · $pulou ignorada(s) por não permitirem essa transição" : '') . '.', $ok ? 'ok' : 'erro');
        $volta = post('volta', 300);
        header('Location: ' . ($volta && str_starts_with($volta, url('admin/')) ? $volta : url('admin/propostas.php')));
        exit;
    }

    // ---------- Salvar (criar/editar)
    if ($acao === 'salvar') {
        $id = post_int('id');
        $dados = [
            'candidato_id'    => post_int('candidato_id'),
            'categoria_id'    => post_int('categoria_id'),
            'subcategoria_id' => post_int('subcategoria_id'),
            'resumo'          => post('resumo', 3000),
            'acao'            => post('acao_proposta', 500),
            'meta'            => post_opcional('meta'),
            'prazo'           => post_opcional('prazo', 255),
            'custo'           => post_opcional('custo', 255),
            'financiamento'   => post_opcional('financiamento'),
        ];
        $fonte = [
            'documento_id'    => post_int('documento_id'),
            'pagina'          => post_int('pagina'),
            'localizacao'     => post_opcional('localizacao', 255),
            'trecho_original' => post('trecho_original', 20000),
        ];

        $erros = [];
        if (!$dados['candidato_id']) $erros[] = 'candidato';
        if (!$dados['categoria_id']) $erros[] = 'categoria';
        if ($dados['resumo'] === '') $erros[] = 'resumo';
        if ($dados['acao'] === '') $erros[] = 'ação proposta';
        if (!$fonte['documento_id']) $erros[] = 'documento de origem';
        if ($fonte['trecho_original'] === '') $erros[] = 'trecho original';

        // subcategoria precisa pertencer à categoria; documento ao candidato
        if ($dados['subcategoria_id']) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM subcategorias WHERE id = :s AND categoria_id = :c');
            $st->execute(['s' => $dados['subcategoria_id'], 'c' => $dados['categoria_id']]);
            if (!$st->fetchColumn()) $dados['subcategoria_id'] = null;
        }
        if ($fonte['documento_id']) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM documentos WHERE id = :d AND candidato_id = :c');
            $st->execute(['d' => $fonte['documento_id'], 'c' => $dados['candidato_id']]);
            if (!$st->fetchColumn()) $erros[] = 'documento (deve pertencer ao candidato escolhido)';
        }

        if ($erros) {
            $_SESSION['form_proposta'] = $_POST;
            flash('Preencha corretamente: ' . implode(', ', $erros) . '.', 'erro');
            redirecionar('admin/propostas.php', $id ? ['editar' => $id] : ['novo' => 1]);
        }

        $pdo->beginTransaction();
        try {
            if ($id) {
                $statusAtual = $pdo->query('SELECT status FROM propostas WHERE id = ' . (int) $id)->fetchColumn();
                $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($dados)));
                $pdo->prepare("UPDATE propostas SET $sets WHERE id = :id")->execute($dados + ['id' => $id]);
                // Conteúdo publicado alterado volta para revisão
                if ($statusAtual === 'publicada' || $statusAtual === 'aprovada') {
                    $pdo->prepare("UPDATE propostas SET status = 'em_revisao' WHERE id = :id")->execute(['id' => $id]);
                    registrar_status($id, $statusAtual, 'em_revisao', 'Conteúdo editado — requer nova revisão.');
                }
                $fid = $pdo->query('SELECT MIN(id) FROM fontes WHERE proposta_id = ' . (int) $id)->fetchColumn();
                if ($fid) {
                    $pdo->prepare('UPDATE fontes SET documento_id = :documento_id, pagina = :pagina, localizacao = :localizacao, trecho_original = :trecho_original WHERE id = :fid')
                        ->execute($fonte + ['fid' => $fid]);
                } else {
                    $pdo->prepare('INSERT INTO fontes (proposta_id, documento_id, pagina, localizacao, trecho_original) VALUES (:pid, :documento_id, :pagina, :localizacao, :trecho_original)')
                        ->execute($fonte + ['pid' => $id]);
                }
            } else {
                $cols = implode(', ', array_keys($dados));
                $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($dados)));
                $pdo->prepare("INSERT INTO propostas ($cols, status) VALUES ($vals, 'pendente')")->execute($dados);
                $id = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO fontes (proposta_id, documento_id, pagina, localizacao, trecho_original) VALUES (:pid, :documento_id, :pagina, :localizacao, :trecho_original)')
                    ->execute($fonte + ['pid' => $id]);
                registrar_status($id, null, 'pendente', 'Cadastro manual.');
            }
            $pdo->commit();
            unset($_SESSION['form_proposta']);
            flash('Proposta salva.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[votai] ' . $e->getMessage());
            flash('Erro ao salvar a proposta.', 'erro');
        }
        redirecionar('admin/propostas.php', ['editar' => $id]);
    }
}

// ============================================================ DADOS AUXILIARES
$candidatos = $pdo->query('SELECT id, nome_urna FROM candidatos ORDER BY nome_urna')->fetchAll();
$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY ordem, nome')->fetchAll();
$subcats    = $pdo->query('SELECT id, categoria_id, nome FROM subcategorias ORDER BY ordem, nome')->fetchAll();
$documentos = $pdo->query('SELECT id, candidato_id, titulo, total_paginas FROM documentos ORDER BY titulo')->fetchAll();

$editarId = get_int('editar');
$novo = isset($_GET['novo']);

// ============================================================ FORMULÁRIO
if ($editarId || $novo) {
    $p = ['id' => null, 'candidato_id' => get_int('candidato'), 'categoria_id' => null, 'subcategoria_id' => null, 'resumo' => '', 'acao' => '',
          'meta' => '', 'prazo' => '', 'custo' => '', 'financiamento' => '', 'status' => 'pendente', 'observacao_revisao' => '',
          'documento_id' => null, 'pagina' => null, 'localizacao' => '', 'trecho_original' => ''];
    $hist = [];
    if ($editarId) {
        $st = $pdo->prepare('SELECT p.*, f.documento_id, f.pagina, f.localizacao, f.trecho_original
            FROM propostas p LEFT JOIN fontes f ON f.id = (SELECT MIN(id) FROM fontes WHERE proposta_id = p.id) WHERE p.id = :id');
        $st->execute(['id' => $editarId]);
        $p = $st->fetch() ?: null;
        if (!$p) { flash('Proposta não encontrada.', 'erro'); redirecionar('admin/propostas.php'); }
        $st = $pdo->prepare('SELECT h.*, u.nome AS usuario FROM propostas_historico h LEFT JOIN usuarios_admin u ON u.id = h.usuario_id WHERE proposta_id = :id ORDER BY h.id DESC');
        $st->execute(['id' => $editarId]);
        $hist = $st->fetchAll();
    }
    // repõe dados após erro de validação
    if (!empty($_SESSION['form_proposta'])) {
        $f = $_SESSION['form_proposta'];
        foreach (['candidato_id','categoria_id','subcategoria_id','resumo','meta','prazo','custo','financiamento','documento_id','pagina','localizacao','trecho_original'] as $k) $p[$k] = $f[$k] ?? $p[$k];
        $p['acao'] = $f['acao_proposta'] ?? $p['acao'];
        unset($_SESSION['form_proposta']);
    }

    admin_topo($editarId ? 'Proposta Nº ' . str_pad((string) $editarId, 4, '0', STR_PAD_LEFT) : 'Nova proposta', 'propostas');
    ?>
    <p><a class="adm-link" href="<?= h(url('admin/propostas.php')) ?>">← Voltar à lista</a></p>

    <?php if ($editarId): ?>
    <section class="adm-caixa adm-revisao">
      <div>
        <p class="adm-mono">Status atual</p>
        <p class="adm-revisao__status"><?= status_badge($p['status']) ?></p>
        <?php if ($p['status'] === 'publicada'): ?><p class="adm-nota">Visível no site. Editar o conteúdo devolve a proposta para “Em revisão”.</p><?php endif; ?>
      </div>
      <form method="post" class="adm-revisao__acoes">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="status"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <label class="adm-revisao__obs">Observação da revisão (opcional)<input type="text" name="observacao" maxlength="2000" placeholder="Ex.: resumo conferido com a página 47"></label>
        <div class="adm-botoes">
          <?php foreach (STATUS_TRANSICOES[$p['status']] as $novoSt):
            $rot = ['em_revisao' => 'Enviar para revisão', 'aprovada' => $p['status'] === 'publicada' ? 'Despublicar' : 'Aprovar', 'rejeitada' => 'Rejeitar', 'publicada' => 'Publicar', 'pendente' => 'Voltar para pendente'][$novoSt];
            if ($novoSt === 'publicada' && !eh_admin()) continue; ?>
            <button type="submit" name="novo" value="<?= h($novoSt) ?>" class="adm-btn adm-btn--<?= h($novoSt) ?>" <?= in_array($novoSt, ['rejeitada', 'aprovada'], true) && $p['status'] === 'publicada' ? 'data-confirmar="Remover esta proposta do site público?"' : '' ?>><?= h($rot) ?></button>
          <?php endforeach; ?>
        </div>
      </form>
    </section>
    <?php endif; ?>

    <form method="post" class="adm-form adm-form--grade" data-form-proposta>
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">

      <fieldset>
        <legend>Classificação</legend>
        <label>Candidato *
          <select name="candidato_id" required data-candidato>
            <option value="">Selecione</option>
            <?php foreach ($candidatos as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $p['candidato_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nome_urna']) ?></option><?php endforeach; ?>
          </select></label>
        <label>Categoria *
          <select name="categoria_id" required data-categoria>
            <option value="">Selecione</option>
            <?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $p['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option><?php endforeach; ?>
          </select></label>
        <label>Subcategoria
          <select name="subcategoria_id" data-subcategoria>
            <option value="">Nenhuma</option>
            <?php foreach ($subcats as $s): ?><option value="<?= (int) $s['id'] ?>" data-cat="<?= (int) $s['categoria_id'] ?>" <?= (int) $p['subcategoria_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['nome']) ?></option><?php endforeach; ?>
          </select></label>
      </fieldset>

      <fieldset>
        <legend>Conteúdo <small>— deixe em branco o que não aparece no documento</small></legend>
        <label class="adm-col2">Resumo da proposta * <small>Texto da plataforma, neutro. Ex.: “O plano propõe…”</small>
          <textarea name="resumo" rows="3" required maxlength="3000"><?= h($p['resumo']) ?></textarea></label>
        <label class="adm-col2">Ação proposta *<input type="text" name="acao_proposta" required maxlength="500" value="<?= h($p['acao']) ?>"></label>
        <label>Meta<input type="text" name="meta" maxlength="500" value="<?= h((string) $p['meta']) ?>" placeholder="Não informado no documento"></label>
        <label>Prazo<input type="text" name="prazo" maxlength="255" value="<?= h((string) $p['prazo']) ?>" placeholder="Não informado no documento"></label>
        <label>Custo informado<input type="text" name="custo" maxlength="255" value="<?= h((string) $p['custo']) ?>" placeholder="Não informado no documento"></label>
        <label>Fonte de financiamento<input type="text" name="financiamento" maxlength="500" value="<?= h((string) $p['financiamento']) ?>" placeholder="Não informado no documento"></label>
      </fieldset>

      <fieldset>
        <legend>Fonte oficial *</legend>
        <label>Documento de origem *
          <select name="documento_id" required data-documento>
            <option value="">Selecione</option>
            <?php foreach ($documentos as $d): ?><option value="<?= (int) $d['id'] ?>" data-cand="<?= (int) $d['candidato_id'] ?>" <?= (int) $p['documento_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['titulo']) ?></option><?php endforeach; ?>
          </select></label>
        <label>Página<input type="number" name="pagina" min="1" max="9999" value="<?= h((string) $p['pagina']) ?>"></label>
        <label class="adm-col2">Seção / localização<input type="text" name="localizacao" maxlength="255" value="<?= h((string) $p['localizacao']) ?>" placeholder="Ex.: Eixo 3 — Saúde"></label>
        <label class="adm-col2">Trecho original * <small>Cole o texto literal do documento, sem alterações.</small>
          <textarea name="trecho_original" rows="7" required><?= h((string) $p['trecho_original']) ?></textarea></label>
      </fieldset>

      <div class="adm-botoes">
        <button class="adm-btn adm-btn--pri" type="submit"><?= $editarId ? 'Salvar alterações' : 'Cadastrar como pendente' ?></button>
      </div>
    </form>

    <?php if ($hist): ?>
    <section class="adm-caixa">
      <h2 class="adm-sub">Histórico</h2>
      <ul class="adm-hist">
        <?php foreach ($hist as $h): ?>
        <li><span class="adm-mono"><?= h(date('d/m/Y H:i', strtotime($h['criado_em']))) ?></span>
          <?= h(STATUS_ROTULOS[$h['status_anterior']] ?? '—') ?> → <strong><?= h(STATUS_ROTULOS[$h['status_novo']] ?? $h['status_novo']) ?></strong>
          <small><?= h($h['usuario'] ?? 'sistema') ?><?= $h['observacao'] ? ' · ' . h($h['observacao']) : '' ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif;
    admin_rodape();
    exit;
}

// ============================================================ LISTA
$fStatus = get_texto('status', 20);
if (!isset(STATUS_ROTULOS[$fStatus])) $fStatus = '';
$fCand   = get_int('candidato');
$fCat    = get_int('categoria');
$fQ      = trim(get_texto('q', 100));
$pagina  = max(1, get_int('p') ?? 1);
$porPag  = 30;

$where = ['1=1'];
$params = [];
if ($fStatus) { $where[] = 'p.status = :st'; $params['st'] = $fStatus; }
if ($fCand)   { $where[] = 'p.candidato_id = :cand'; $params['cand'] = $fCand; }
if ($fCat)    { $where[] = 'p.categoria_id = :cat'; $params['cat'] = $fCat; }
if ($fQ !== '') { $where[] = '(p.resumo LIKE :q1 OR p.acao LIKE :q2)'; $params['q1'] = $params['q2'] = '%' . addcslashes($fQ, '%_\\') . '%'; }
$w = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM propostas p WHERE $w");
$st->execute($params);
$total = (int) $st->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPag));

$st = $pdo->prepare("SELECT p.id, p.resumo, p.status, p.atualizado_em, c.nome_urna, cat.nome AS cat, s.nome AS sub,
        (SELECT COUNT(*) FROM fontes f WHERE f.proposta_id = p.id) AS nfontes
    FROM propostas p JOIN candidatos c ON c.id = p.candidato_id JOIN categorias cat ON cat.id = p.categoria_id
    LEFT JOIN subcategorias s ON s.id = p.subcategoria_id
    WHERE $w ORDER BY p.atualizado_em DESC, p.id DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue('lim', $porPag, PDO::PARAM_INT);
$st->bindValue('off', ($pagina - 1) * $porPag, PDO::PARAM_INT);
$st->execute();
$lista = $st->fetchAll();

$contagem = array_fill_keys(array_keys(STATUS_ROTULOS), 0);
foreach ($pdo->query('SELECT status, COUNT(*) n FROM propostas GROUP BY status') as $r) $contagem[$r['status']] = (int) $r['n'];

$filtrosBase = ['candidato' => $fCand, 'categoria' => $fCat, 'q' => $fQ];
$volta = url('admin/propostas.php', $filtrosBase + ['status' => $fStatus, 'p' => $pagina > 1 ? $pagina : null]);

admin_topo('Propostas', 'propostas');
?>
<div class="adm-barra">
  <nav class="adm-abas" aria-label="Filtrar por status">
    <a href="<?= h(url('admin/propostas.php', $filtrosBase)) ?>" class="<?= !$fStatus ? 'is-ativo' : '' ?>">Todas <span><?= array_sum($contagem) ?></span></a>
    <?php foreach (STATUS_ROTULOS as $k => $rot): ?>
    <a href="<?= h(url('admin/propostas.php', $filtrosBase + ['status' => $k])) ?>" class="<?= $fStatus === $k ? 'is-ativo' : '' ?>"><?= h($rot) ?> <span><?= $contagem[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <a class="adm-btn adm-btn--pri" href="<?= h(url('admin/propostas.php', ['novo' => 1])) ?>">+ Nova proposta</a>
</div>

<form class="adm-filtros" method="get">
  <?php if ($fStatus): ?><input type="hidden" name="status" value="<?= h($fStatus) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= h($fQ) ?>" placeholder="Buscar no resumo ou na ação">
  <select name="candidato"><option value="">Todos os candidatos</option><?php foreach ($candidatos as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $fCand === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nome_urna']) ?></option><?php endforeach; ?></select>
  <select name="categoria"><option value="">Todas as categorias</option><?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $fCat === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option><?php endforeach; ?></select>
  <button class="adm-btn" type="submit">Filtrar</button>
</form>

<p class="adm-nota"><?= $total ?> <?= plural($total, 'proposta', 'propostas') ?> · página <?= $pagina ?> de <?= $paginas ?></p>

<form id="lote" method="post" class="adm-lote">
  <?= csrf_campo() ?><input type="hidden" name="acao" value="lote"><input type="hidden" name="volta" value="<?= h($volta) ?>">
  <label class="adm-check"><input type="checkbox" data-marcar-todas> Selecionar todas desta página</label>
  <select name="novo" aria-label="Novo status">
    <option value="em_revisao">Enviar para revisão</option>
    <option value="aprovada">Aprovar</option>
    <?php if (eh_admin()): ?><option value="publicada">Publicar (somente aprovadas)</option><?php endif; ?>
    <option value="rejeitada">Rejeitar</option>
  </select>
  <button class="adm-btn" type="submit" data-confirmar="Aplicar às propostas selecionadas?">Aplicar às selecionadas</button>
</form>

<div class="adm-tabela-wrap">
<table class="adm-tabela">
  <thead><tr><th></th><th>Nº</th><th>Resumo</th><th>Candidato</th><th>Categoria</th><th>Fonte</th><th>Status</th><th>Ações rápidas</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $p): ?>
  <tr>
    <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" form="lote" aria-label="Selecionar proposta <?= (int) $p['id'] ?>"></td>
    <td class="adm-mono"><a href="<?= h(url('admin/propostas.php', ['editar' => $p['id']])) ?>"><?= str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT) ?></a></td>
    <td><a href="<?= h(url('admin/propostas.php', ['editar' => $p['id']])) ?>"><?= h(mb_strimwidth($p['resumo'], 0, 120, '…')) ?></a></td>
    <td><?= h($p['nome_urna']) ?></td>
    <td><?= h($p['cat']) ?><?= $p['sub'] ? '<small>' . h($p['sub']) . '</small>' : '' ?></td>
    <td><?= (int) $p['nfontes'] ? '✓' : '<span class="adm-alerta">sem fonte</span>' ?></td>
    <td><?= status_badge($p['status']) ?></td>
    <td>
      <form method="post" class="adm-rapido">
        <?= csrf_campo() ?><input type="hidden" name="acao" value="status"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="volta" value="<?= h($volta) ?>">
        <?php foreach (STATUS_TRANSICOES[$p['status']] as $n): if ($n === 'publicada' && !eh_admin()) continue; ?>
          <button type="submit" name="novo" value="<?= h($n) ?>" class="adm-chip adm-chip--<?= h($n) ?>" title="<?= h(STATUS_ROTULOS[$n]) ?>"><?= h(['em_revisao' => 'Revisar', 'aprovada' => $p['status'] === 'publicada' ? 'Despublicar' : 'Aprovar', 'rejeitada' => 'Rejeitar', 'publicada' => 'Publicar', 'pendente' => 'Pendente'][$n]) ?></button>
        <?php endforeach; ?>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="8" class="adm-vazio">Nenhuma proposta encontrada.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?php if ($paginas > 1): ?>
<nav class="adm-paginacao">
  <?php for ($i = 1; $i <= $paginas; $i++): ?>
    <a href="<?= h(url('admin/propostas.php', $filtrosBase + ['status' => $fStatus, 'p' => $i])) ?>" class="<?= $i === $pagina ? 'is-ativo' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</nav>
<?php endif;
admin_rodape();
