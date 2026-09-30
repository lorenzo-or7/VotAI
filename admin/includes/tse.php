<?php
/**
 * IMPORTADOR DO TSE
 * ---------------------------------------------------------------
 * Lê os arquivos oficiais do Portal de Dados Abertos do TSE:
 *   - consulta_cand_2026_BR.csv (ou _BRASIL.csv)   → candidaturas, vices, partidos, coligações
 *   - foto_cand2026_BR_div.zip                     → fotos (FBR{SQ_CANDIDATO}_div.jpg)
 *   - proposta_governo_2026_BR.zip                 → PDFs (2026BR{SQ_CANDIDATO}_01.pdf)
 *
 * A chave de ligação entre os arquivos é o SQ_CANDIDATO (sequencial do TSE).
 * Pode ser executado várias vezes: registros existentes são atualizados.
 * As fotos são copiadas SEM qualquer alteração (o recorte 4:5 é feito
 * por CSS, igual para todos).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Cria colunas/tabelas novas se ainda não existirem (bancos criados antes desta versão). */
function garantir_schema(): array
{
    $pdo = db();
    $feito = [];
    $colunas = static function (string $tabela) use ($pdo): array {
        $st = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
        $st->execute(['t' => $tabela]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    };

    $novas = [
        'sq_candidato'         => 'BIGINT UNSIGNED NULL',
        'tipo_agremiacao'      => 'VARCHAR(40) NULL',
        'coligacao_nome'       => 'VARCHAR(200) NULL',
        'coligacao_composicao' => 'TEXT NULL',
        'federacao_nome'       => 'VARCHAR(200) NULL',
        'vice_sq'              => 'BIGINT UNSIGNED NULL',
        'vice_nome'            => 'VARCHAR(180) NULL',
        'vice_nome_urna'       => 'VARCHAR(120) NULL',
        'vice_partido_sigla'   => 'VARCHAR(20) NULL',
        'vice_foto'            => 'VARCHAR(255) NULL',
        'atualizado_tse'       => 'DATETIME NULL',
    ];
    $existentes = $colunas('candidatos');
    foreach ($novas as $col => $def) {
        if (!in_array($col, $existentes, true)) {
            $pdo->exec("ALTER TABLE candidatos ADD COLUMN $col $def");
            $feito[] = "coluna candidatos.$col";
        }
    }
    $idx = $pdo->query("SHOW INDEX FROM candidatos WHERE Key_name = 'uq_candidatos_sq'")->fetchAll();
    if (!$idx) {
        $pdo->exec('ALTER TABLE candidatos ADD UNIQUE KEY uq_candidatos_sq (sq_candidato)');
        $feito[] = 'índice candidatos.sq_candidato';
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS candidatura_partidos (
        candidato_id INT UNSIGNED NOT NULL,
        partido_id   INT UNSIGNED NOT NULL,
        papel        ENUM('titular','coligacao') NOT NULL DEFAULT 'coligacao',
        federacao    VARCHAR(200) NULL,
        ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (candidato_id, partido_id),
        CONSTRAINT fk_cp_candidato FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
        CONSTRAINT fk_cp_partido   FOREIGN KEY (partido_id)   REFERENCES partidos(id)   ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    return $feito;
}

/** "PARTIDO SOCIAL DEMOCRÁTICO" → "Partido Social Democrático" */
function titulo_pt(string $s): string
{
    $s = mb_convert_case(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? $s)), MB_CASE_TITLE);
    $s = preg_replace_callback('/(?<=\s)(Da|De|Do|Das|Dos|E|Em|Na|No|Pelo|Pela)(?=\s)/u', static fn($m) => mb_strtolower($m[1]), $s) ?? $s;
    return $s;
}

final class ImportadorTSE
{
    /** Nomes de partidos que aparecem só como sigla na composição de coligações/federações. */
    private const NOMES_CONHECIDOS = [
        'PSB' => 'Partido Socialista Brasileiro', 'PDT' => 'Partido Democrático Trabalhista',
        'PC do B' => 'Partido Comunista do Brasil', 'PV' => 'Partido Verde',
        'PSOL' => 'Partido Socialismo e Liberdade', 'REDE' => 'Rede Sustentabilidade', 'AGIR' => 'Agir',
    ];

    public array $log = [];
    public array $avisos = [];
    private PDO $pdo;
    private int $eleicaoId = 0;

    public function __construct() { $this->pdo = db(); }

    /** Lê o CSV do TSE (Latin-1, separado por ;) e devolve linhas como arrays associativos. */
    public function lerCsv(string $arquivo): array
    {
        $fh = fopen($arquivo, 'rb');
        if (!$fh) throw new RuntimeException('Não foi possível abrir o CSV.');
        $cab = null;
        $linhas = [];
        while (($row = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            $row = array_map(static fn($v) => mb_check_encoding((string) $v, 'UTF-8') ? (string) $v : mb_convert_encoding((string) $v, 'UTF-8', 'ISO-8859-1'), $row);
            if ($cab === null) {
                $cab = array_map(static fn($c) => strtoupper(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c) ?? $c)), $row);
                if (!in_array('SQ_CANDIDATO', $cab, true)) throw new RuntimeException('Este arquivo não parece ser o "consulta_cand" do TSE (coluna SQ_CANDIDATO não encontrada).');
                continue;
            }
            if (count($row) !== count($cab)) continue;
            $linhas[] = array_combine($cab, $row);
        }
        fclose($fh);
        return $linhas;
    }

    /** Filtra Presidência/Vice e garante a eleição. */
    public function importarCandidaturas(array $linhas, bool $removerDemo): array
    {
        $pres = [];
        $vices = [];
        foreach ($linhas as $l) {
            if (($l['SG_UF'] ?? '') !== 'BR') continue;
            if ((int) $l['CD_CARGO'] === 1) $pres[] = $l;
            elseif ((int) $l['CD_CARGO'] === 2) $vices[] = $l;
        }
        if (!$pres) throw new RuntimeException('Nenhuma candidatura à Presidência encontrada no CSV (SG_UF = BR, CD_CARGO = 1).');

        $this->pdo->beginTransaction();
        try {
            $this->garantirEleicao($pres[0]);
            if ($removerDemo) $this->removerDemo();

            // Pareia cada presidente com seu vice: mesmo número e SQ mais próximo
            $usados = [];
            $ids = [];
            foreach ($pres as $p) {
                $melhor = null;
                foreach ($vices as $i => $v) {
                    if (isset($usados[$i]) || $v['NR_CANDIDATO'] !== $p['NR_CANDIDATO']) continue;
                    $dist = abs((int) $v['SQ_CANDIDATO'] - (int) $p['SQ_CANDIDATO']);
                    if ($melhor === null || $dist < $melhor[1]) $melhor = [$i, $dist];
                }
                $vice = null;
                if ($melhor) { $usados[$melhor[0]] = true; $vice = $vices[$melhor[0]]; }
                else $this->avisos[] = 'Sem vice identificado para ' . titulo_pt($p['NM_URNA_CANDIDATO']) . '.';
                $ids[$p['SQ_CANDIDATO']] = $this->salvarCandidato($p, $vice);
            }

            // Mesmo número com mais de uma candidatura (ex.: substituição)
            $porNumero = [];
            foreach ($pres as $p) $porNumero[$p['NR_CANDIDATO']][] = titulo_pt($p['NM_URNA_CANDIDATO']);
            foreach ($porNumero as $nr => $nomes) {
                if (count($nomes) > 1) {
                    $this->avisos[] = "O número $nr aparece em " . count($nomes) . ' candidaturas (' . implode(', ', $nomes)
                        . '). Pode ser uma substituição. Confira a situação no DivulgaCandContas e oculte a que não estiver mais valendo (menu Candidatos → Ocultar).';
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->log[] = count($pres) . ' candidaturas à Presidência importadas/atualizadas; ' . count($usados) . ' vices vinculados.';
        return $ids;
    }

    private function garantirEleicao(array $l): void
    {
        $ano = (int) $l['ANO_ELEICAO'];
        $data = DateTime::createFromFormat('d/m/Y', $l['DT_ELEICAO'] ?? '');
        $st = $this->pdo->prepare("SELECT id FROM eleicoes WHERE ano = :a AND cargo_principal = 'Presidente da República' AND nome NOT LIKE '%demonstra%' LIMIT 1");
        $st->execute(['a' => $ano]);
        $id = (int) $st->fetchColumn();
        if (!$id) {
            $this->pdo->prepare("INSERT INTO eleicoes (nome, ano, turno, cargo_principal, abrangencia, data_eleicao, ativa) VALUES (:n, :a, :t, 'Presidente da República', 'Nacional', :d, 1)")
                ->execute(['n' => 'Eleições Gerais ' . $ano, 'a' => $ano, 't' => (int) $l['NR_TURNO'], 'd' => $data ? $data->format('Y-m-d') : null]);
            $id = (int) $this->pdo->lastInsertId();
            $this->log[] = "Eleição \"Eleições Gerais $ano\" criada.";
        }
        $this->pdo->prepare('UPDATE eleicoes SET ativa = (id = :id)')->execute(['id' => $id]);
        $this->eleicaoId = $id;
    }

    private function removerDemo(): void
    {
        $n = (int) $this->pdo->query('SELECT COUNT(*) FROM candidatos WHERE demonstracao = 1')->fetchColumn();
        if (!$n) return;
        // arquivos PDF fictícios
        foreach ($this->pdo->query('SELECT arquivo FROM documentos WHERE demonstracao = 1')->fetchAll(PDO::FETCH_COLUMN) as $arq) {
            if ($arq && str_starts_with($arq, 'uploads/documentos/') && is_file(ROOT_PATH . '/' . $arq)) @unlink(ROOT_PATH . '/' . $arq);
        }
        $this->pdo->exec('DELETE FROM candidatos WHERE demonstracao = 1'); // propostas, fontes e documentos saem em cascata
        $this->pdo->exec("DELETE FROM eleicoes WHERE nome LIKE '%demonstra%' AND id NOT IN (SELECT eleicao_id FROM candidatos)");
        $this->pdo->exec("DELETE FROM partidos WHERE sigla IN ('ALFA','BETA','GAMA','DELTA') AND id NOT IN (SELECT partido_id FROM candidatos WHERE partido_id IS NOT NULL)");
        $this->log[] = "$n candidaturas fictícias de demonstração removidas (com suas propostas e documentos).";
    }

    private function partido(string $sigla, ?string $nome = null, ?string $numero = null): int
    {
        $sigla = trim($sigla);
        $st = $this->pdo->prepare('SELECT id, nome, numero FROM partidos WHERE sigla = :s');
        $st->execute(['s' => $sigla]);
        $p = $st->fetch();
        $nomeOficial = $nome ? titulo_pt($nome) : null;                       // nome vindo do CSV (fonte forte)
        $logo = $this->logoPadrao($sigla);
        if ($p) {
            if ($nomeOficial) $this->pdo->prepare('UPDATE partidos SET nome = :n WHERE id = :id')->execute(['n' => $nomeOficial, 'id' => $p['id']]);
            $this->pdo->prepare('UPDATE partidos SET numero = COALESCE(:num, numero), logo = COALESCE(logo, :logo) WHERE id = :id')
                ->execute(['num' => $numero, 'logo' => $logo, 'id' => $p['id']]);
            return (int) $p['id'];
        }
        $nome = $nomeOficial ?? (self::NOMES_CONHECIDOS[$sigla] ?? $sigla);
        $this->pdo->prepare('INSERT INTO partidos (nome, sigla, numero, logo) VALUES (:n, :s, :num, :logo)')
            ->execute(['n' => $nome, 's' => $sigla, 'num' => $numero, 'logo' => $logo]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Logo padrão por convenção de nome: assets/images/partidos/{sigla-em-slug}.(webp|png|svg) */
    private function logoPadrao(string $sigla): ?string
    {
        foreach (['webp', 'png', 'svg', 'jpg'] as $ext) {
            $rel = 'assets/images/partidos/' . gerar_slug($sigla) . '.' . $ext;
            if (is_file(ROOT_PATH . '/' . $rel)) return $rel;
        }
        return null;
    }

    private function salvarCandidato(array $p, ?array $v): int
    {
        $sq = (int) $p['SQ_CANDIDATO'];
        $partidoId = $this->partido($p['SG_PARTIDO'], $p['NM_PARTIDO'], $p['NR_PARTIDO']);
        $nulo = static fn($x) => in_array(trim((string) $x), ['', '#NULO', '#NE', '#NULO#'], true) ? null : trim((string) $x);

        $d = [
            'eleicao_id'           => $this->eleicaoId,
            'partido_id'           => $partidoId,
            'nome'                 => titulo_pt($p['NM_CANDIDATO']),
            'nome_urna'            => titulo_pt($p['NM_URNA_CANDIDATO']),
            'numero'               => $p['NR_CANDIDATO'],
            'cargo'                => 'Presidente da República',
            'uf'                   => null,
            'tipo_agremiacao'      => titulo_pt($p['TP_AGREMIACAO']),
            'coligacao_nome'       => ($n = $nulo($p['NM_COLIGACAO'])) && $n !== 'PARTIDO ISOLADO' ? titulo_pt($n) : null,
            'coligacao_composicao' => $nulo($p['DS_COMPOSICAO_COLIGACAO']),
            'federacao_nome'       => ($f = $nulo($p['NM_FEDERACAO'] ?? null)) ? titulo_pt($f) : null,
            'vice_sq'              => $v ? (int) $v['SQ_CANDIDATO'] : null,
            'vice_nome'            => $v ? titulo_pt($v['NM_CANDIDATO']) : null,
            'vice_nome_urna'       => $v ? titulo_pt($v['NM_URNA_CANDIDATO']) : null,
            'vice_partido_sigla'   => $v ? $v['SG_PARTIDO'] : null,
            'foto_fonte'           => 'TSE — Portal de Dados Abertos (foto divulgável)',
        ];
        if ($v) $this->partido($v['SG_PARTIDO'], $v['NM_PARTIDO'], $v['NR_PARTIDO']);

        $st = $this->pdo->prepare('SELECT id FROM candidatos WHERE sq_candidato = :sq');
        $st->execute(['sq' => $sq]);
        $id = (int) $st->fetchColumn();
        if ($id) {
            $sets = implode(', ', array_map(static fn($k) => "$k = :$k", array_keys($d)));
            $this->pdo->prepare("UPDATE candidatos SET $sets, atualizado_tse = NOW() WHERE id = :id")->execute($d + ['id' => $id]);
        } else {
            $d['slug'] = $this->slugLivre(gerar_slug($d['nome_urna']));
            $d['sq_candidato'] = $sq;
            $cols = implode(', ', array_keys($d));
            $vals = implode(', ', array_map(static fn($k) => ":$k", array_keys($d)));
            $this->pdo->prepare("INSERT INTO candidatos ($cols, demonstracao, ativo, atualizado_tse) VALUES ($vals, 0, 1, NOW())")->execute($d);
            $id = (int) $this->pdo->lastInsertId();
        }
        $this->salvarPartidosDaCandidatura($id, $partidoId, $d['coligacao_composicao']);
        return $id;
    }

    private function slugLivre(string $base): string
    {
        $slug = $base; $n = 2;
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM candidatos WHERE slug = :s');
        while (true) {
            $st->execute(['s' => $slug]);
            if (!$st->fetchColumn()) return $slug;
            $slug = $base . '-' . $n++;
        }
    }

    /**
     * "PSB / PDT / FEDERAÇÃO X - FE (13-PT / 65-PC do B) / FEDERAÇÃO Y (50-PSOL / 18-REDE)"
     * → [['sigla'=>'PSB','numero'=>null,'federacao'=>null], ...]
     */
    public static function parseComposicao(?string $txt): array
    {
        if (!$txt) return [];
        $itens = [];
        $nivel = 0; $atual = '';
        foreach (preg_split('//u', $txt, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if ($ch === '(') $nivel++;
            if ($ch === ')') $nivel--;
            if ($ch === '/' && $nivel === 0) { $itens[] = trim($atual); $atual = ''; continue; }
            $atual .= $ch;
        }
        if (trim($atual) !== '') $itens[] = trim($atual);

        $out = [];
        foreach ($itens as $item) {
            if (preg_match('/^(.*?)\((.*)\)\s*$/u', $item, $m)) {
                $fed = titulo_pt(trim($m[1], " -"));
                foreach (explode('/', $m[2]) as $parte) {
                    $parte = trim($parte);
                    if (preg_match('/^(\d+)\s*-\s*(.+)$/u', $parte, $pm)) $out[] = ['sigla' => trim($pm[2]), 'numero' => $pm[1], 'federacao' => $fed];
                    elseif ($parte !== '') $out[] = ['sigla' => $parte, 'numero' => null, 'federacao' => $fed];
                }
            } elseif ($item !== '') {
                $out[] = ['sigla' => $item, 'numero' => null, 'federacao' => null];
            }
        }
        return $out;
    }

    private function salvarPartidosDaCandidatura(int $candId, int $titularId, ?string $composicao): void
    {
        $this->pdo->prepare('DELETE FROM candidatura_partidos WHERE candidato_id = :c')->execute(['c' => $candId]);
        $ins = $this->pdo->prepare('INSERT IGNORE INTO candidatura_partidos (candidato_id, partido_id, papel, federacao, ordem) VALUES (:c, :p, :papel, :f, :o)');
        $ins->execute(['c' => $candId, 'p' => $titularId, 'papel' => 'titular', 'f' => null, 'o' => 0]);
        $o = 1;
        foreach (self::parseComposicao($composicao) as $item) {
            $pid = $this->partido($item['sigla'], null, $item['numero']);
            if ($pid === $titularId) {
                if ($item['federacao']) $this->pdo->prepare('UPDATE candidatura_partidos SET federacao = :f WHERE candidato_id = :c AND partido_id = :p')->execute(['f' => $item['federacao'], 'c' => $candId, 'p' => $pid]);
                continue;
            }
            $ins->execute(['c' => $candId, 'p' => $pid, 'papel' => 'coligacao', 'f' => $item['federacao'], 'o' => $o++]);
        }
    }

    /** Extrai os arquivos de um .zip enviado para uma pasta temporária. */
    public function extrairZip(string $zipArquivo): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('A extensão "zip" do PHP está desativada. No XAMPP: Apache → Config → PHP (php.ini), procure ";extension=zip", apague o ";" do início, salve e reinicie o Apache.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipArquivo) !== true) throw new RuntimeException('Não foi possível abrir o arquivo .zip.');
        $dir = sys_get_temp_dir() . '/votai_' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = $zip->getNameIndex($i);
            if (!preg_match('/\.(jpe?g|png|pdf)$/i', $nome)) continue;            // só o que interessa
            $base = basename(str_replace('\\', '/', $nome));
            if (!preg_match('/^[\w.\-]+$/', $base)) continue;                     // nomes seguros
            file_put_contents("$dir/$base", $zip->getFromIndex($i));
        }
        $zip->close();
        return $dir;
    }

    public function importarFotos(string $dir): void
    {
        $destRel = 'assets/images/candidatos';
        @mkdir(ROOT_PATH . '/' . $destRel, 0755, true);
        $n = 0; $nv = 0;
        foreach (glob("$dir/F*_div.*") ?: [] as $arq) {
            if (!preg_match('/^F[A-Z]{2}(\d+)_div\.(jpe?g|png)$/i', basename($arq), $m)) continue;
            if (!@getimagesize($arq)) continue;
            $sq = $m[1];
            $rel = "$destRel/$sq." . strtolower($m[2] === 'jpeg' ? 'jpg' : $m[2]);
            copy($arq, ROOT_PATH . '/' . $rel);                                   // cópia fiel, sem retoques
            $st = $this->pdo->prepare('UPDATE candidatos SET foto = :f WHERE sq_candidato = :sq');
            $st->execute(['f' => $rel, 'sq' => $sq]);
            if ($st->rowCount()) { $n++; continue; }
            $st = $this->pdo->prepare('UPDATE candidatos SET vice_foto = :f WHERE vice_sq = :sq');
            $st->execute(['f' => $rel, 'sq' => $sq]);
            if ($st->rowCount()) $nv++;
        }
        $semFoto = $this->pdo->query("SELECT nome_urna FROM candidatos WHERE ativo = 1 AND demonstracao = 0 AND (foto IS NULL OR foto = '')")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($semFoto as $nome) $this->avisos[] = "Sem foto no arquivo do TSE: $nome (o site mostra as iniciais).";
        $this->log[] = "$n fotos de candidaturas e $nv fotos de vices vinculadas.";
    }

    public function importarPropostas(string $dir): void
    {
        $destRel = 'uploads/documentos';
        @mkdir(ROOT_PATH . '/' . $destRel, 0755, true);
        $n = 0;
        foreach (glob("$dir/*.pdf") ?: [] as $arq) {
            if (!preg_match('/^(\d{4})([A-Z]{2})(\d+)_(\d+)\.pdf$/i', basename($arq), $m)) continue;
            [, $ano, , $sq, $parte] = $m;
            $st = $this->pdo->prepare('SELECT id, nome_urna, eleicao_id FROM candidatos WHERE sq_candidato = :sq');
            $st->execute(['sq' => $sq]);
            $c = $st->fetch();
            if (!$c) { $this->avisos[] = "PDF sem candidatura correspondente: " . basename($arq); continue; }

            $rel = "$destRel/proposta-$sq-$parte.pdf";
            copy($arq, ROOT_PATH . '/' . $rel);
            $conteudo = (string) file_get_contents($arq);
            $paginas = null;
            if (preg_match_all('/\/Count\s+(\d+)/', $conteudo, $pm)) $paginas = max(array_map('intval', $pm[1]));
            $hash = hash('sha256', $conteudo);
            $titulo = 'Proposta de governo — ' . $c['nome_urna'] . ((int) $parte > 1 ? " (parte $parte)" : '');

            $st = $this->pdo->prepare('SELECT id FROM documentos WHERE candidato_id = :c AND arquivo = :a');
            $st->execute(['c' => $c['id'], 'a' => $rel]);
            if ($docId = $st->fetchColumn()) {
                $this->pdo->prepare('UPDATE documentos SET titulo = :t, total_paginas = :p, hash_sha256 = :h WHERE id = :id')
                    ->execute(['t' => $titulo, 'p' => $paginas, 'h' => $hash, 'id' => $docId]);
            } else {
                $this->pdo->prepare("INSERT INTO documentos (candidato_id, eleicao_id, titulo, tipo, arquivo, url_origem, total_paginas, hash_sha256, demonstracao)
                        VALUES (:c, :e, :t, 'plano_governo', :a, :u, :p, :h, 0)")
                    ->execute(['c' => $c['id'], 'e' => $c['eleicao_id'], 't' => $titulo, 'a' => $rel,
                               'u' => 'https://dadosabertos.tse.jus.br/dataset/candidatos-' . $ano, 'p' => $paginas, 'h' => $hash]);
            }
            $n++;
        }
        $this->log[] = "$n propostas de governo (PDF) vinculadas às candidaturas.";
    }

    public static function limparDir(string $dir): void
    {
        foreach (glob("$dir/*") ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }
}

/**
 * IMPORTADOR DE PROPOSTAS (.json)
 * Arquivo gerado na etapa de extração (formato "votai-propostas").
 * Tudo entra como PENDENTE, para revisão humana no painel.
 * Reimportar o mesmo arquivo não duplica (compara candidato + seção + trecho).
 */
function importar_propostas_json(string $json): array
{
    $d = json_decode($json, true);
    if (!is_array($d) || ($d['formato'] ?? '') !== 'votai-propostas' || empty($d['propostas'])) {
        throw new RuntimeException('Arquivo de propostas inválido (formato "votai-propostas" esperado).');
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT id, nome_urna FROM candidatos WHERE sq_candidato = :sq');
    $st->execute(['sq' => (string) ($d['sq_candidato'] ?? '')]);
    $cand = $st->fetch();
    if (!$cand) throw new RuntimeException('Candidatura ' . ($d['candidato'] ?? '?') . ' (SQ ' . ($d['sq_candidato'] ?? '?') . ') não encontrada. Importe primeiro o CSV do TSE.');

    // documento de origem
    $st = $pdo->prepare('SELECT id FROM documentos WHERE candidato_id = :c AND arquivo LIKE :a ORDER BY id LIMIT 1');
    $st->execute(['c' => $cand['id'], 'a' => '%' . basename((string) ($d['documento'] ?? '')) ]);
    $docId = (int) $st->fetchColumn();
    if (!$docId) {
        $st = $pdo->prepare('SELECT id FROM documentos WHERE candidato_id = :c ORDER BY id LIMIT 1');
        $st->execute(['c' => $cand['id']]);
        $docId = (int) $st->fetchColumn();
    }
    if (!$docId) throw new RuntimeException('Nenhum documento (PDF) vinculado a ' . $cand['nome_urna'] . '. Importe o zip de propostas de governo do TSE.');

    $cats = [];
    foreach ($pdo->query('SELECT id, slug FROM categorias') as $c) $cats[$c['slug']] = (int) $c['id'];

    $novas = 0; $iguais = 0; $novasSub = [];
    $pdo->beginTransaction();
    try {
        foreach ($d['propostas'] as $i => $p) {
            $catId = $cats[$p['categoria'] ?? ''] ?? null;
            if (!$catId) throw new RuntimeException('Proposta ' . ($i + 1) . ': categoria "' . ($p['categoria'] ?? '') . '" não existe.');
            $trecho = trim((string) ($p['trecho'] ?? ''));
            $resumo = trim((string) ($p['resumo'] ?? ''));
            if ($trecho === '' || $resumo === '' || trim((string) ($p['acao'] ?? '')) === '') throw new RuntimeException('Proposta ' . ($i + 1) . ': resumo, ação e trecho são obrigatórios.');

            // subcategoria (cria se não existir)
            $subId = null;
            if (!empty($p['subcategoria'])) {
                $slug = gerar_slug((string) $p['subcategoria']);
                $s = $pdo->prepare('SELECT id FROM subcategorias WHERE categoria_id = :c AND slug = :s');
                $s->execute(['c' => $catId, 's' => $slug]);
                $subId = (int) $s->fetchColumn() ?: null;
                if (!$subId) {
                    $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem),0)+1 FROM subcategorias WHERE categoria_id = ' . $catId)->fetchColumn();
                    $pdo->prepare('INSERT INTO subcategorias (categoria_id, slug, nome, ordem) VALUES (:c, :s, :n, :o)')
                        ->execute(['c' => $catId, 's' => $slug, 'n' => mb_substr((string) $p['subcategoria'], 0, 120), 'o' => $ordem]);
                    $subId = (int) $pdo->lastInsertId();
                    $novasSub[] = $p['subcategoria'];
                }
            }

            // já importada?
            $s = $pdo->prepare('SELECT f.proposta_id FROM fontes f JOIN propostas pr ON pr.id = f.proposta_id
                                WHERE pr.candidato_id = :c AND f.documento_id = :d AND f.trecho_original = :t LIMIT 1');
            $s->execute(['c' => $cand['id'], 'd' => $docId, 't' => $trecho]);
            if ($s->fetchColumn()) { $iguais++; continue; }

            $opc = static fn($k) => ($v = trim((string) ($p[$k] ?? ''))) === '' ? null : mb_substr($v, 0, 500);
            $pdo->prepare("INSERT INTO propostas (candidato_id, categoria_id, subcategoria_id, resumo, acao, meta, prazo, custo, financiamento, status, observacao_revisao)
                           VALUES (:c, :cat, :sub, :r, :a, :m, :pz, :cu, :f, 'pendente', :obs)")
                ->execute(['c' => $cand['id'], 'cat' => $catId, 'sub' => $subId, 'r' => $resumo, 'a' => mb_substr((string) $p['acao'], 0, 500),
                           'm' => $opc('meta'), 'pz' => $opc('prazo'), 'cu' => $opc('custo'), 'f' => $opc('financiamento'),
                           'obs' => 'Rascunho gerado por ' . ($d['gerado_por'] ?? 'extração') . ' — conferir com o trecho original.']);
            $pid = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO fontes (proposta_id, documento_id, pagina, localizacao, trecho_original) VALUES (:p, :d, :pg, :l, :t)')
                ->execute(['p' => $pid, 'd' => $docId, 'pg' => !empty($p['pagina']) ? (int) $p['pagina'] : null,
                           'l' => mb_substr((string) ($p['localizacao'] ?? ''), 0, 255) ?: null, 't' => $trecho]);
            registrar_status($pid, null, 'pendente', 'Importada de arquivo de extração.');
            $novas++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $log = [$cand['nome_urna'] . ": $novas propostas importadas como Pendente" . ($iguais ? " ($iguais já existiam e foram ignoradas)" : '') . '.'];
    if ($novasSub) $log[] = 'Subtemas criados: ' . implode(', ', array_unique($novasSub)) . '.';
    foreach ((array) ($d['observacoes'] ?? []) as $o) $log[] = 'Nota da extração: ' . $o;
    return $log;
}
