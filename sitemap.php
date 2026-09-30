<?php
/**
 * sitemap.xml — lista de todas as páginas públicas para buscadores (Google, Bing).
 * Com os links bonitos ativos, fica em /sitemap.xml.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/repositorio.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');

$urls = [];
$add = static function (string $link, ?string $mod = null, string $freq = 'weekly', string $prio = '0.5') use (&$urls): void {
    $urls[] = ['loc' => url_absoluta($link), 'mod' => $mod ? date('Y-m-d', strtotime($mod)) : null, 'freq' => $freq, 'prio' => $prio];
};

try {
    $hoje = date('Y-m-d');
    $add(url(''), $hoje, 'daily', '1.0');   // o "Tema do dia" muda todo dia
    foreach (['temas' => '0.8', 'candidatos' => '0.8', 'comparar' => '0.7', 'metodologia' => '0.5', 'atualizacoes' => '0.5', 'correcoes' => '0.3', 'privacidade' => '0.2'] as $pag => $prio) {
        $add(link_pagina($pag), null, $pag === 'atualizacoes' ? 'daily' : 'monthly', $prio);
    }

    $maisRecente = [];   // data da última mudança por tema e por candidatura
    $props = propostas_publicadas([]);
    foreach ($props as $p) {
        $m = (string) ($p['atualizado_em'] ?? '');
        foreach (['t:' . $p['categoria_slug'], 'c:' . $p['candidato_slug']] as $k) {
            if ($m > ($maisRecente[$k] ?? '')) $maisRecente[$k] = $m;
        }
    }

    foreach (categorias_com_contagem() as $t) {
        if ((int) $t['total_propostas'] === 0) continue;
        $add(link_tema($t['slug']), $maisRecente['t:' . $t['slug']] ?? null, 'weekly', '0.8');
        foreach (subcategorias_com_contagem((int) $t['id']) as $s) {
            if ((int) $s['total'] > 0) $add(link_tema($t['slug'], $s['slug']), $maisRecente['t:' . $t['slug']] ?? null, 'weekly', '0.6');
        }
    }
    foreach (candidatos_lista() as $c) {
        $add(link_candidato($c['slug']), $maisRecente['c:' . $c['slug']] ?? null, 'weekly', '0.8');
    }
    foreach ($props as $p) {
        $add(link_proposta($p), $p['atualizado_em'] ?? null, 'monthly', '0.6');
    }
} catch (Throwable $e) {
    error_log('[votai sitemap] ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as $u) {
    echo '  <url><loc>', htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'), '</loc>';
    if ($u['mod']) echo '<lastmod>', $u['mod'], '</lastmod>';
    echo '<changefreq>', $u['freq'], '</changefreq><priority>', $u['prio'], '</priority></url>', "\n";
}
echo '</urlset>', "\n";
