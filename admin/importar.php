<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tse.php';
exigir_login();

if (!eh_admin()) { flash('Somente administradores podem importar dados.', 'erro'); redirecionar('admin/index.php'); }

@set_time_limit(300);
$resultado = null;
$erro = null;

/** Arquivo enviado válido? Retorna o caminho temporário ou null. */
function arquivo_enviado(string $campo, array $mimes, int $maxMb): ?string
{
    $f = $_FILES[$campo] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('O arquivo "' . $f['name'] . '" passou do limite de envio do PHP. Aumente upload_max_filesize e post_max_size no php.ini (ex.: 100M) e reinicie o Apache.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Falha no envio de "' . $f['name'] . '".');
    if ($f['size'] > $maxMb * 1048576) throw new RuntimeException('"' . $f['name'] . "\" é maior que $maxMb MB.");
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!in_array($mime, $mimes, true)) throw new RuntimeException('Tipo inesperado em "' . $f['name'] . "\" ($mime).");
    return $f['tmp_name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $imp = new ImportadorTSE();
    $dirs = [];
    try {
        $novidades = garantir_schema();
        if ($novidades) $imp->log[] = 'Banco atualizado: ' . implode(', ', $novidades) . '.';

        // Arquivos de propostas (.json) — etapa de extração
        if (!empty($_FILES['json']['name'][0])) {
            foreach ($_FILES['json']['tmp_name'] as $k => $tmp) {
                if ($_FILES['json']['error'][$k] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) throw new RuntimeException('Falha no envio de ' . $_FILES['json']['name'][$k] . '.');
                if ($_FILES['json']['size'][$k] > 20 * 1048576) throw new RuntimeException($_FILES['json']['name'][$k] . ' é grande demais.');
                foreach (importar_propostas_json((string) file_get_contents($tmp)) as $l) $imp->log[] = $l;
            }
            $resultado = $imp;
        } else {

        $csv = arquivo_enviado('csv', ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], 60);
        $fotos = arquivo_enviado('fotos', ['application/zip', 'application/x-zip-compressed'], 100);
        $props = arquivo_enviado('propostas', ['application/zip', 'application/x-zip-compressed'], 200);

        if ($csv) {
            $imp->importarCandidaturas($imp->lerCsv($csv), isset($_POST['remover_demo']));
        } elseif (!(int) db()->query('SELECT COUNT(*) FROM candidatos WHERE sq_candidato IS NOT NULL')->fetchColumn()) {
            throw new RuntimeException('Envie primeiro o CSV de candidaturas. Fotos e PDFs são ligados às candidaturas já importadas.');
        }
        if ($fotos) { $dirs[] = $d = $imp->extrairZip($fotos); $imp->importarFotos($d); }
        if ($props) { $dirs[] = $d = $imp->extrairZip($props); $imp->importarPropostas($d); }
        if (!$csv && !$fotos && !$props) throw new RuntimeException('Nenhum arquivo enviado.');
        $resultado = $imp;
        }
    } catch (Throwable $e) {
        $erro = $e instanceof RuntimeException ? $e->getMessage() : 'Erro inesperado durante a importação. Os detalhes foram registrados no log do servidor.';
        error_log('[votai import] ' . $e);
    } finally {
        foreach ($dirs as $d) ImportadorTSE::limparDir($d);
    }
}

$temDemo = (int) db()->query('SELECT COUNT(*) FROM candidatos WHERE demonstracao = 1')->fetchColumn();
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');

admin_topo('Importar do TSE', 'importar');
?>
<?php if ($erro): ?><p class="adm-flash adm-flash--erro" role="alert"><?= h($erro) ?></p><?php endif; ?>

<?php if ($resultado): ?>
<section class="adm-caixa">
  <h2 class="adm-sub">Importação concluída</h2>
  <ul class="adm-hist"><?php foreach ($resultado->log as $l): ?><li>✓ <?= h($l) ?></li><?php endforeach; ?></ul>
  <?php if ($resultado->avisos): ?>
  <h3 class="adm-sub" style="margin-top:18px">Pontos para conferir</h3>
  <ul class="adm-hist"><?php foreach ($resultado->avisos as $a): ?><li class="adm-alerta">! <?= h($a) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <div class="adm-botoes"><a class="adm-btn adm-btn--pri" href="<?= h(url('admin/candidatos.php')) ?>">Ver candidaturas</a><a class="adm-btn" href="<?= h(url('candidatos.php')) ?>" target="_blank">Ver no site ↗</a></div>
</section>
<?php endif; ?>

<div class="adm-grade2">
  <section class="adm-caixa">
    <h2 class="adm-sub">Arquivos do Portal de Dados Abertos</h2>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <?= csrf_campo() ?>
      <label>1. Candidaturas — <strong>consulta_cand_2026_BR.csv</strong> (ou _BRASIL.csv)
        <input type="file" name="csv" accept=".csv,text/csv"></label>
      <label>2. Fotos — <strong>foto_cand2026_BR_div.zip</strong> (opcional)
        <input type="file" name="fotos" accept=".zip,application/zip"></label>
      <label>3. Propostas de governo — <strong>proposta_governo_2026_BR.zip</strong> (opcional)
        <input type="file" name="propostas" accept=".zip,application/zip"></label>
      <?php if ($temDemo): ?>
      <label class="adm-check"><input type="checkbox" name="remover_demo" checked> Remover os <?= $temDemo ?> candidatos fictícios de demonstração (e suas propostas)</label>
      <?php endif; ?>
      <p class="adm-nota">Limite de envio do seu PHP: <?= h($uploadMax) ?> por arquivo, <?= h($postMax) ?> no total.</p>
      <div class="adm-botoes"><button class="adm-btn adm-btn--pri" type="submit" data-confirmar="Importar os arquivos do TSE agora?">Importar</button></div>
    </form>
  </section>

  <section class="adm-caixa">
    <h2 class="adm-sub">Propostas extraídas (.json)</h2>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <?= csrf_campo() ?>
      <label>Arquivo(s) gerado(s) na extração — ex.: <strong>280002552484-clariana-barao.json</strong>
        <input type="file" name="json[]" accept=".json,application/json" multiple required></label>
      <p class="adm-nota">Todas as propostas entram como <strong>Pendente</strong>. Nada aparece no site até você revisar e publicar em Propostas. Importar o mesmo arquivo de novo não duplica.</p>
      <div class="adm-botoes"><button class="adm-btn adm-btn--pri" type="submit">Importar propostas</button></div>
    </form>
  </section>
</div>

<div class="adm-grade2">
  <section class="adm-caixa">
    <h2 class="adm-sub">Como funciona</h2>
    <ul class="adm-hist">
      <li>Nesta fase são importadas só as candidaturas à <strong>Presidência</strong> e seus vices (SG_UF = BR).</li>
      <li>A ligação entre CSV, fotos e PDFs é o <strong>SQ_CANDIDATO</strong> do TSE.</li>
      <li>Pode importar de novo quando o TSE atualizar: os dados são atualizados, nada é duplicado. Candidaturas ocultadas por você continuam ocultas.</li>
      <li>As fotos são copiadas sem nenhuma alteração. O recorte 4:5 é feito pelo site, igual para todos.</li>
      <li>Os partidos da coligação/federação são lidos do próprio CSV. Logos são ligados automaticamente se existir um arquivo com a sigla em <code>assets/images/partidos/</code> (ex.: <code>pt.webp</code>).</li>
      <li>O CSV de 2026 não informa a situação do registro (campo "#NE"). Se uma candidatura for indeferida ou substituída, oculte-a em Candidatos.</li>
    </ul>
    <p class="adm-nota">Fonte: <a class="adm-link" href="https://dadosabertos.tse.jus.br/dataset/candidatos-2026" target="_blank" rel="noopener">dadosabertos.tse.jus.br/dataset/candidatos-2026</a></p>
  </section>
</div>
<?php admin_rodape();
