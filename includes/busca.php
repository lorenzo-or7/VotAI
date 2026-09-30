<?php
/**
 * BUSCA
 * ---------------------------------------------------------------
 * Arquitetura preparada para receber, no futuro, uma busca
 * inteligente (IA) sem alterar as páginas:
 *
 *   interface ProvedorBusca          → contrato comum
 *   class BuscaBancoDeDados          → implementação atual (MySQL FULLTEXT + LIKE + sinônimos)
 *   class BuscaInteligente (futuro)  → poderia interpretar a pergunta e
 *                                      devolver IDs de propostas publicadas.
 *
 * Regra que qualquer provedor deve respeitar: retornar SOMENTE
 * propostas publicadas e nunca gerar texto sobre candidaturas —
 * apenas localizar registros já revisados.
 *
 * Nenhuma API externa é utilizada nesta versão.
 */

declare(strict_types=1);

require_once __DIR__ . '/repositorio.php';

interface ProvedorBusca
{
    /**
     * @return array{termos: string[], temas: array, propostas: array, total: int}
     */
    public function buscar(string $consulta, int $limite = 60): array;
}

final class BuscaBancoDeDados implements ProvedorBusca
{
    /** Palavras ignoradas na busca. */
    private const STOPWORDS = ['a','o','as','os','de','da','do','das','dos','e','em','no','na','nos','nas','um','uma','para','por','com','que','sobre','como','ao','aos','se','mais','menos','eu','quero','ver'];

    public function buscar(string $consulta, int $limite = 60): array
    {
        $consulta = trim(mb_substr($consulta, 0, 120));
        $termos = $this->termos($consulta);
        if (!$termos) {
            return ['termos' => [], 'temas' => [], 'propostas' => [], 'total' => 0];
        }

        $temas = $this->temas($consulta, $termos);
        $scores = $this->idsPropostas($consulta, $termos, $temas, $limite);
        $propostas = $scores ? propostas_publicadas(['ids' => array_keys($scores)]) : [];
        $propostas = $this->ordenarNeutro($propostas, $scores);

        return ['termos' => $termos, 'temas' => $temas, 'propostas' => $propostas, 'total' => count($propostas)];
    }

    /** @return string[] */
    private function termos(string $q): array
    {
        $q = mb_strtolower($q);
        $partes = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $partes = array_filter($partes, fn($t) => mb_strlen($t) >= 2 && !in_array($t, self::STOPWORDS, true));
        return array_values(array_unique(array_slice($partes, 0, 8)));
    }

    /** Temas e subtemas cujo nome ou sinônimos batem com a consulta. */
    private function temas(string $consulta, array $termos): array
    {
        $cond = [];
        $params = [];
        foreach (array_merge([$consulta], $termos) as $t) {
            if (mb_strlen($t) < 3) continue;
            $like = '%' . $this->escLike($t) . '%';
            $cond[] = '(cat.nome LIKE ? OR cat.palavras_chave LIKE ? OR s.nome LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (!$cond) return [];

        $sql = "SELECT DISTINCT cat.id, cat.slug, cat.nome,
                       s.id AS sub_id, s.slug AS sub_slug, s.nome AS sub_nome,
                       (SELECT COUNT(*) FROM propostas p JOIN candidatos c ON c.id = p.candidato_id AND c.ativo = 1
                        JOIN eleicoes e ON e.id = c.eleicao_id AND e.ativa = 1
                        WHERE p.categoria_id = cat.id AND p.status = 'publicada') AS total
                FROM categorias cat
                LEFT JOIN subcategorias s ON s.categoria_id = cat.id
                WHERE cat.ativo = 1 AND (" . implode(' OR ', $cond) . ")
                ORDER BY cat.ordem, s.ordem";
        $st = db()->prepare($sql);
        $st->execute($params);

        // Agrupa: um tema com os subtemas que casaram
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $id = (int) $r['id'];
            $out[$id] ??= ['id' => $id, 'slug' => $r['slug'], 'nome' => $r['nome'], 'total' => (int) $r['total'], 'subs' => []];
            if ($r['sub_id'] && $this->casa($r['sub_nome'], $termos, $consulta)) {
                $out[$id]['subs'][(int) $r['sub_id']] = ['id' => (int) $r['sub_id'], 'slug' => $r['sub_slug'], 'nome' => $r['sub_nome']];
            }
        }
        return array_values($out);
    }

    /**
     * Ordena por relevância e, entre resultados de mesma relevância,
     * alterna as candidaturas (rodízio) — nenhuma aparece sempre primeiro.
     */
    private function ordenarNeutro(array $propostas, array $scores): array
    {
        $faixas = [];
        foreach ($propostas as $p) {
            $faixa = (string) round($scores[(int) $p['id']] ?? 0);
            $faixas[$faixa][(int) $p['candidato_id']][] = $p;
        }
        uksort($faixas, static fn($a, $b) => (float) $b <=> (float) $a);
        $out = [];
        $rodada = 0;
        foreach ($faixas as $porCand) {
            $cands = array_keys($porCand);
            sort($cands);
            // ponto de partida do rodízio muda a cada faixa
            $n = count($cands);
            $cands = array_merge(array_slice($cands, $rodada % $n), array_slice($cands, 0, $rodada % $n));
            $rodada++;
            while ($porCand) {
                foreach ($cands as $c) {
                    if (!empty($porCand[$c])) $out[] = array_shift($porCand[$c]);
                    if (empty($porCand[$c])) unset($porCand[$c]);
                }
            }
        }
        return $out;
    }

    /** @return array<int, float> id => relevância (ordenado) */
    private function idsPropostas(string $consulta, array $termos, array $temas, int $limite): array
    {
        $scores = [];

        // 1) FULLTEXT (modo booleano com prefixo) em resumo/ação/meta e no trecho original
        $bool = implode(' ', array_map(fn($t) => '+' . preg_replace('/[^\p{L}\p{N}]/u', '', $t) . '*', array_filter($termos, fn($t) => mb_strlen($t) >= 3)));
        if ($bool !== '') {
            $sql = "SELECT p.id,
                           MATCH(p.resumo, p.acao, p.meta) AGAINST (:b1 IN BOOLEAN MODE) * 2
                         + COALESCE(MAX(MATCH(f.trecho_original) AGAINST (:b2 IN BOOLEAN MODE)), 0) AS score
                    FROM propostas p
                    LEFT JOIN fontes f ON f.proposta_id = p.id
                    WHERE p.status = 'publicada'
                      AND (MATCH(p.resumo, p.acao, p.meta) AGAINST (:b3 IN BOOLEAN MODE)
                           OR MATCH(f.trecho_original) AGAINST (:b4 IN BOOLEAN MODE))
                    GROUP BY p.id
                    ORDER BY score DESC
                    LIMIT :lim";
            try {
                $st = db()->prepare($sql);
                foreach (['b1', 'b2', 'b3', 'b4'] as $k) $st->bindValue($k, $bool);
                $st->bindValue('lim', $limite, PDO::PARAM_INT);
                $st->execute();
                foreach ($st->fetchAll() as $r) $scores[(int) $r['id']] = (float) $r['score'] + 10;
            } catch (PDOException $e) {
                // Sem índice FULLTEXT no servidor: segue apenas com LIKE abaixo
                error_log('[votai] FULLTEXT indisponível: ' . $e->getMessage());
            }
        }

        // 2) Fallback LIKE (palavras curtas, acentos e termos parciais)
        if (count($scores) < 5) {
            $cond = [];
            $params = [];
            foreach ($termos as $t) {
                $like = '%' . $this->escLike($t) . '%';
                $cond[] = '(p.resumo LIKE ? OR p.acao LIKE ? OR p.meta LIKE ?)';
                array_push($params, $like, $like, $like);
            }
            $st = db()->prepare("SELECT p.id FROM propostas p WHERE p.status = 'publicada' AND (" . implode(' OR ', $cond) . ") LIMIT " . (int) $limite);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $scores[(int) $id] = ($scores[(int) $id] ?? 0) + 5;
        }

        // 3) Propostas dos temas/subtemas reconhecidos (sinônimos: "SUS" → Saúde)
        foreach ($temas as $tema) {
            $subs = array_keys($tema['subs']);
            if ($subs) {
                $ph = implode(',', array_fill(0, count($subs), '?'));
                $st = db()->prepare("SELECT id FROM propostas WHERE status = 'publicada' AND subcategoria_id IN ($ph) LIMIT " . (int) $limite);
                $st->execute($subs);
                $peso = 4;
            } else {
                $st = db()->prepare("SELECT id FROM propostas WHERE status = 'publicada' AND categoria_id = ? LIMIT " . (int) $limite);
                $st->execute([$tema['id']]);
                $peso = 2;
            }
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $scores[(int) $id] = ($scores[(int) $id] ?? 0) + $peso;
        }

        arsort($scores);
        return array_slice($scores, 0, $limite, true);
    }

    private function casa(string $texto, array $termos, string $consulta): bool
    {
        $t = mb_strtolower($texto);
        if (mb_strlen($consulta) >= 3 && str_contains($t, mb_strtolower($consulta))) return true;
        foreach ($termos as $termo) if (mb_strlen($termo) >= 3 && str_contains($t, $termo)) return true;
        return false;
    }

    private function escLike(string $s): string
    {
        return addcslashes($s, '%_\\');
    }
}

/** Ponto único de escolha do provedor. Troque aqui no futuro. */
function provedor_busca(): ProvedorBusca
{
    return new BuscaBancoDeDados();
}
