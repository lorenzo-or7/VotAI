<?php
/**
 * Consultas públicas. Todas as funções retornam SOMENTE propostas
 * com status = 'publicada', de candidaturas ativas da eleição ativa.
 */

declare(strict_types=1);

require_once __DIR__ . '/funcoes.php';

const STATUS_PUBLICO = 'publicada';

/** Colunas padrão de candidatura (com partido). */
const SQL_CANDIDATO_COLS = "c.*,
       pa.nome AS partido_nome, pa.sigla AS partido_sigla, pa.logo AS partido_logo,
       e.nome AS eleicao_nome, e.ano AS eleicao_ano";

function eleicao_ativa(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $row = db()->query('SELECT * FROM eleicoes WHERE ativa = 1 ORDER BY ano DESC, id DESC LIMIT 1')->fetch();
    return $cache = ($row ?: null);
}

/** Estatísticas gerais (apenas contagens informativas). */
function estatisticas(): array
{
    $sql = "SELECT
        (SELECT COUNT(*) FROM candidatos c JOIN eleicoes e ON e.id = c.eleicao_id AND e.ativa = 1 WHERE c.ativo = 1) AS candidatos,
        (SELECT COUNT(*) FROM propostas p JOIN candidatos c ON c.id = p.candidato_id AND c.ativo = 1 WHERE p.status = :st) AS propostas,
        (SELECT COUNT(*) FROM categorias WHERE ativo = 1) AS temas,
        (SELECT COUNT(*) FROM documentos d JOIN candidatos c ON c.id = d.candidato_id AND c.ativo = 1) AS documentos";
    $st = db()->prepare($sql);
    $st->execute(['st' => STATUS_PUBLICO]);
    return array_map('intval', $st->fetch());
}

/** Categorias com nº de propostas publicadas e de candidaturas com propostas. */
function categorias_com_contagem(): array
{
    $sql = "SELECT cat.id, cat.slug, cat.nome, cat.descricao, cat.ordem,
                   COUNT(p.id) AS total_propostas,
                   COUNT(DISTINCT p.candidato_id) AS total_candidatos,
                   (SELECT COUNT(*) FROM subcategorias s WHERE s.categoria_id = cat.id) AS total_sub
            FROM categorias cat
            LEFT JOIN (propostas p JOIN candidatos c ON c.id = p.candidato_id AND c.ativo = 1
                       JOIN eleicoes e ON e.id = c.eleicao_id AND e.ativa = 1)
                   ON p.categoria_id = cat.id AND p.status = :st
            WHERE cat.ativo = 1
            GROUP BY cat.id
            ORDER BY cat.ordem, cat.nome";
    $st = db()->prepare($sql);
    $st->execute(['st' => STATUS_PUBLICO]);
    return $st->fetchAll();
}

function categoria_por_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM categorias WHERE slug = :s AND ativo = 1 LIMIT 1');
    $st->execute(['s' => $slug]);
    return $st->fetch() ?: null;
}

function subcategorias_com_contagem(int $categoriaId): array
{
    $sql = "SELECT s.id, s.slug, s.nome, COUNT(p.id) AS total
            FROM subcategorias s
            LEFT JOIN (propostas p JOIN candidatos c ON c.id = p.candidato_id AND c.ativo = 1
                       JOIN eleicoes e ON e.id = c.eleicao_id AND e.ativa = 1)
                   ON p.subcategoria_id = s.id AND p.status = :st
            WHERE s.categoria_id = :cat
            GROUP BY s.id ORDER BY s.ordem, s.nome";
    $st = db()->prepare($sql);
    $st->execute(['st' => STATUS_PUBLICO, 'cat' => $categoriaId]);
    return $st->fetchAll();
}

/**
 * Candidaturas da eleição ativa, em ORDEM ALFABÉTICA (critério neutro),
 * com total de propostas publicadas.
 */
function candidatos_lista(): array
{
    $sql = "SELECT " . SQL_CANDIDATO_COLS . ",
                   (SELECT COUNT(*) FROM propostas p WHERE p.candidato_id = c.id AND p.status = :st) AS total_propostas,
                   (SELECT COUNT(DISTINCT p.categoria_id) FROM propostas p WHERE p.candidato_id = c.id AND p.status = :st2) AS total_temas
            FROM candidatos c
            JOIN eleicoes e ON e.id = c.eleicao_id AND e.ativa = 1
            LEFT JOIN partidos pa ON pa.id = c.partido_id
            WHERE c.ativo = 1
            ORDER BY c.nome_urna ASC";
    $st = db()->prepare($sql);
    $st->execute(['st' => STATUS_PUBLICO, 'st2' => STATUS_PUBLICO]);
    return $st->fetchAll();
}

function candidato_por_slug(string $slug): ?array
{
    $sql = "SELECT " . SQL_CANDIDATO_COLS . "
            FROM candidatos c
            JOIN eleicoes e ON e.id = c.eleicao_id
            LEFT JOIN partidos pa ON pa.id = c.partido_id
            WHERE c.slug = :s AND c.ativo = 1 AND e.ativa = 1 LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute(['s' => $slug]);
    return $st->fetch() ?: null;
}

/** Busca candidaturas por IDs, preservando a ordem informada. */
function candidatos_por_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT " . SQL_CANDIDATO_COLS . "
            FROM candidatos c
            JOIN eleicoes e ON e.id = c.eleicao_id
            LEFT JOIN partidos pa ON pa.id = c.partido_id
            WHERE c.ativo = 1 AND e.ativa = 1 AND c.id IN ($ph)";
    $st = db()->prepare($sql);
    $st->execute($ids);
    $rows = [];
    foreach ($st->fetchAll() as $r) $rows[(int) $r['id']] = $r;
    $out = [];
    foreach ($ids as $id) if (isset($rows[$id])) $out[] = $rows[$id];
    return $out;
}

function documentos_do_candidato(int $candidatoId): array
{
    $st = db()->prepare('SELECT * FROM documentos WHERE candidato_id = :c ORDER BY data_publicacao DESC, id DESC');
    $st->execute(['c' => $candidatoId]);
    return $st->fetchAll();
}

/**
 * Propostas publicadas com fonte principal (primeira fonte cadastrada).
 * Filtros: candidato_id, candidatos (array), categoria_id, subcategoria_id, ids (array), limite
 */
function propostas_publicadas(array $f = []): array
{
    $where  = ['p.status = ?', 'c.ativo = 1'];
    $params = [STATUS_PUBLICO];

    if (!empty($f['candidato_id']))    { $where[] = 'p.candidato_id = ?';    $params[] = (int) $f['candidato_id']; }
    if (!empty($f['categoria_id']))    { $where[] = 'p.categoria_id = ?';    $params[] = (int) $f['categoria_id']; }
    if (!empty($f['subcategoria_id'])) { $where[] = 'p.subcategoria_id = ?'; $params[] = (int) $f['subcategoria_id']; }
    foreach (['candidatos' => 'p.candidato_id', 'ids' => 'p.id'] as $k => $col) {
        if (!empty($f[$k]) && is_array($f[$k])) {
            $ids = array_values(array_filter(array_map('intval', $f[$k])));
            if (!$ids) return [];
            $where[] = $col . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
    }

    $sql = "SELECT p.id, p.candidato_id, p.categoria_id, p.subcategoria_id, p.resumo, p.acao, p.meta, p.prazo,
                   p.custo, p.financiamento, p.revisado_em, p.atualizado_em,
                   cat.nome AS categoria_nome, cat.slug AS categoria_slug,
                   sub.nome AS subcategoria_nome, sub.slug AS subcategoria_slug,
                   c.nome_urna AS candidato_nome, c.slug AS candidato_slug, c.numero AS candidato_numero,
                   pa.sigla AS partido_sigla,
                   f.pagina, f.localizacao, f.trecho_original,
                   d.id AS documento_id, d.titulo AS documento_titulo, d.tipo AS documento_tipo,
                   d.arquivo AS documento_arquivo, d.url_origem AS documento_url
            FROM propostas p
            JOIN candidatos c   ON c.id = p.candidato_id
            JOIN eleicoes el    ON el.id = c.eleicao_id AND el.ativa = 1
            LEFT JOIN partidos pa ON pa.id = c.partido_id
            JOIN categorias cat ON cat.id = p.categoria_id AND cat.ativo = 1
            LEFT JOIN subcategorias sub ON sub.id = p.subcategoria_id
            LEFT JOIN fontes f  ON f.id = (SELECT MIN(f2.id) FROM fontes f2 WHERE f2.proposta_id = p.id)
            LEFT JOIN documentos d ON d.id = f.documento_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cat.ordem, sub.ordem, p.id";

    if (!empty($f['limite'])) {
        $sql .= ' LIMIT ' . max(1, min(500, (int) $f['limite']));
    }

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}


/** Uma proposta publicada pelo número de registro (ou null). */
function proposta_publicada(int $id): ?array
{
    $r = propostas_publicadas(['ids' => [$id]]);
    return $r[0] ?? null;
}

/** A proposta tem meta, prazo ou custo escritos no documento? */
function tem_quantificacao(array $p): bool
{
    foreach (['meta', 'prazo', 'custo'] as $k) if (trim((string) ($p[$k] ?? '')) !== '') return true;
    return false;
}

/**
 * TEMA DO DIA — critério 100% automático e neutro:
 *  - o tema muda a cada dia, em rodízio pela ordem dos temas;
 *  - de cada candidatura entra UMA proposta desse tema, também em rodízio diário;
 *  - a ordem das candidaturas é a alfabética, girando o ponto de partida a cada dia.
 * Ninguém escolhe manualmente o que aparece.
 */
function tema_do_dia(?DateTimeImmutable $data = null): ?array
{
    $data ??= new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $n = intdiv($data->setTime(12, 0)->getTimestamp(), 86400);   // nº do dia (estável ao longo do dia)

    $temas = array_values(array_filter(categorias_com_contagem(), static fn($t) => (int) $t['total_propostas'] > 0));
    if (!$temas) return null;
    $tema = $temas[$n % count($temas)];

    $cands = candidatos_lista();
    if (!$cands) return null;
    $porCand = [];
    foreach (propostas_publicadas(['categoria_id' => (int) $tema['id']]) as $p) $porCand[(int) $p['candidato_id']][] = $p;

    $itens = [];
    foreach ($cands as $c) {
        $lista = $porCand[(int) $c['id']] ?? [];
        $itens[] = ['candidato' => $c, 'proposta' => $lista ? $lista[$n % count($lista)] : null, 'total' => count($lista)];
    }
    $giro = $n % count($itens);
    $itens = array_merge(array_slice($itens, $giro), array_slice($itens, 0, $giro));

    return ['tema' => $tema, 'itens' => $itens, 'data' => $data];
}

/**
 * ÚLTIMAS ATUALIZAÇÕES — lidas do histórico de revisão (propostas_historico).
 * Agrupa por dia e candidatura: propostas publicadas pela primeira vez,
 * propostas corrigidas (voltaram a ser publicadas) e propostas retiradas do site.
 */
function atualizacoes_recentes(int $maxGrupos = 40): array
{
    try {
        $st = db()->prepare("SELECT h.proposta_id, h.status_anterior, h.status_novo, h.criado_em, p.status AS status_atual,
                                    c.id AS candidato_id, c.nome_urna, c.slug, c.numero
                             FROM propostas_historico h
                             JOIN propostas p ON p.id = h.proposta_id
                             JOIN candidatos c ON c.id = p.candidato_id AND c.ativo = 1
                             JOIN eleicoes e ON e.id = c.eleicao_id AND e.ativa = 1
                             WHERE h.status_novo = :pub OR h.status_anterior = :pub2
                             ORDER BY h.criado_em, h.id");
        $st->execute(['pub' => STATUS_PUBLICO, 'pub2' => STATUS_PUBLICO]);
        $linhas = $st->fetchAll();
    } catch (PDOException) {
        return [];
    }

    $jaPublicada = [];
    $dias = [];   // [dia][cand] => [...]
    foreach ($linhas as $l) {
        $pid = (int) $l['proposta_id'];
        $dia = substr((string) $l['criado_em'], 0, 10);
        $cid = (int) $l['candidato_id'];
        $g = &$dias[$dia][$cid];
        $g ??= ['candidato' => $l, 'novas' => [], 'corrigidas' => [], 'retiradas' => [], 'hora' => $l['criado_em']];
        $g['hora'] = max($g['hora'], $l['criado_em']);
        if ($l['status_novo'] === STATUS_PUBLICO) {
            unset($g['retiradas'][$pid]);
            if (isset($jaPublicada[$pid])) $g['corrigidas'][$pid] = $l['status_atual'];
            else $g['novas'][$pid] = $l['status_atual'];
            $jaPublicada[$pid] = true;
        } else {
            $g['retiradas'][$pid] = $l['status_atual'];
            unset($g['corrigidas'][$pid]);
        }
        unset($g);
    }

    $out = [];
    krsort($dias);
    foreach ($dias as $dia => $cands) {
        uasort($cands, static fn($a, $b) => strcmp($a['candidato']['nome_urna'], $b['candidato']['nome_urna']));
        foreach ($cands as $g) {
            // correções que acabaram retiradas no mesmo dia contam só como retiradas
            $g['corrigidas'] = array_diff_key($g['corrigidas'], $g['retiradas']);
            if (!$g['novas'] && !$g['corrigidas'] && !$g['retiradas']) continue;
            $g['dia'] = $dia;
            $out[] = $g;
            if (count($out) >= $maxGrupos) break 2;
        }
    }
    return $out;
}

/** Contagem de propostas por tema de uma candidatura (inclui temas com zero). */
function mapa_propostas_candidato(int $candidatoId): array
{
    $sql = "SELECT cat.id, cat.slug, cat.nome, cat.ordem, COUNT(p.id) AS total
            FROM categorias cat
            LEFT JOIN propostas p ON p.categoria_id = cat.id AND p.candidato_id = :c AND p.status = :st
            WHERE cat.ativo = 1
            GROUP BY cat.id ORDER BY cat.ordem";
    $st = db()->prepare($sql);
    $st->execute(['c' => $candidatoId, 'st' => STATUS_PUBLICO]);
    return $st->fetchAll();
}

/** Link para o documento na página indicada. */
function link_documento(array $p): ?string
{
    if (!empty($p['documento_arquivo']) && arquivo_existe($p['documento_arquivo'])) {
        return url($p['documento_arquivo']) . (!empty($p['pagina']) ? '#page=' . (int) $p['pagina'] : '');
    }
    if (!empty($p['documento_url']) && preg_match('#^https?://#i', (string) $p['documento_url'])) {
        return (string) $p['documento_url'];
    }
    return null;
}

/** Partidos ligados à candidatura (titular + coligação/federação). */
function partidos_da_candidatura(int $candidatoId): array
{
    try {
        $st = db()->prepare('SELECT pa.id, pa.sigla, pa.nome, pa.numero, pa.logo, cp.papel, cp.federacao
            FROM candidatura_partidos cp JOIN partidos pa ON pa.id = cp.partido_id
            WHERE cp.candidato_id = :c ORDER BY cp.papel = \'titular\' DESC, cp.ordem, pa.sigla');
        $st->execute(['c' => $candidatoId]);
        return $st->fetchAll();
    } catch (PDOException) {
        return []; // tabela ainda não criada (antes da primeira importação)
    }
}

/** Há candidaturas fictícias ativas? (controla os avisos de demonstração) */
function demo_ativo(): bool
{
    static $v = null;
    if ($v !== null) return $v;
    if (DEMO_MODE === false) return $v = false;              // desligado à força
    return $v = (bool) db()->query('SELECT COUNT(*) FROM candidatos WHERE demonstracao = 1 AND ativo = 1')->fetchColumn();
}

function tipo_documento(?string $tipo): string
{
    return match ($tipo) {
        'plano_governo'          => 'Proposta de governo',
        'programa_partidario'    => 'Programa partidário',
        'documento_complementar' => 'Documento complementar',
        default                  => 'Documento oficial',
    };
}
