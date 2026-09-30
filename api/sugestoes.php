<?php
/**
 * Sugestões da busca (JSON) — usado pelo campo de busca enquanto se digita.
 * GET ?q=texto  →  { temas: [...], propostas: [...] }
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/busca.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');

$q = trim(get_texto('q', 80));
if (mb_strlen($q) < 2) {
    echo json_encode(['temas' => [], 'propostas' => []]);
    exit;
}

$res = provedor_busca()->buscar($q, 6);

$temas = [];
foreach ($res['temas'] as $t) {
    $temas[] = ['tipo' => 'Tema', 'nome' => $t['nome'], 'url' => link_tema($t['slug']), 'info' => $t['total'] . ' propostas'];
    foreach ($t['subs'] as $s) {
        $temas[] = ['tipo' => 'Subtema', 'nome' => $s['nome'], 'url' => link_tema($t['slug'], $s['slug']) . '#candidaturas', 'info' => $t['nome']];
    }
}

$props = [];
foreach (array_slice($res['propostas'], 0, 5) as $p) {
    $props[] = [
        'tipo' => 'Proposta',
        'nome' => $p['acao'],
        'url'  => link_candidato($p['candidato_slug']) . '#proposta-' . (int) $p['id'],
        'info' => $p['candidato_nome'] . ' · ' . $p['categoria_nome'],
    ];
}

echo json_encode(['temas' => array_slice($temas, 0, 5), 'propostas' => $props], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
