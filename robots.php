<?php
/**
 * robots.txt — orienta os buscadores. Com os links bonitos ativos, fica em /robots.txt.
 * Gerado em PHP para o endereço do sitemap sair sempre com o domínio certo.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/funcoes.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');

$base = BASE_URL;
$sitemap = url_absoluta(urls_amigaveis() ? url('sitemap.xml') : url('sitemap.php'));

echo "# " . SITE_NAME . " — propostas das candidaturas organizadas por tema\n";
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: {$base}/admin/\n";
echo "Disallow: {$base}/api/\n";
echo "Disallow: {$base}/busca\n";
echo "Disallow: {$base}/busca.php\n";
echo "Disallow: {$base}/*?*editar=\n";
echo "Disallow: {$base}/*/apresentar\n";
echo "\n";
echo "Sitemap: {$sitemap}\n";
