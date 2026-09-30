<?php
/**
 * Funções utilitárias de apresentação.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const NAO_INFORMADO = 'Não informado no documento.';

if (PHP_SAPI !== 'cli' && !headers_sent()) header_remove('X-Powered-By');   // não anunciar a versão do PHP

/** Escapa HTML. Use em TODA saída de dados. */
function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absoluta a partir da raiz do projeto. */
function url(string $path = '', array $query = []): string
{
    $u = BASE_URL . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '' && $v !== []);
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/* =====================================================================
   Endereços compartilháveis
   Com o .htaccess (mod_rewrite) ativo, os links ficam curtos:
     /tema/saude · /tema/saude/saude-mental · /candidato/lula
     /comparar/lula-x-zema/saude · /proposta/123-titulo
   Sem mod_rewrite, os mesmos links usam os endereços .php de sempre.
   ===================================================================== */

/** As URLs amigáveis estão ativas? (o .htaccess avisa via variável de ambiente) */
function urls_amigaveis(): bool
{
    static $v = null;
    if ($v !== null) return $v;
    $cfg = defined('URLS_AMIGAVEIS') ? URLS_AMIGAVEIS : 'auto';
    if (is_bool($cfg)) return $v = $cfg;
    foreach (['VOTAI_REWRITE', 'REDIRECT_VOTAI_REWRITE', 'REDIRECT_REDIRECT_VOTAI_REWRITE'] as $k) {
        if (($_SERVER[$k] ?? getenv($k)) === '1') return $v = true;
    }
    return $v = false;
}

/** "Plano de Saúde Mental" → "plano-de-saude-mental" (com limite de palavras). */
function slugificar(string $txt, int $maxPalavras = 8): string
{
    $txt = mb_strtolower($txt);
    $txt = strtr($txt, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'î' => 'i',
                        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
    $txt = trim((string) preg_replace('/[^a-z0-9]+/', '-', $txt), '-');
    $partes = array_slice(array_values(array_filter(explode('-', $txt), 'strlen')), 0, $maxPalavras);
    // não termina o link em palavra de ligação ("...-reajuste-de")
    $ligacao = ['a', 'o', 'as', 'os', 'e', 'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'nas', 'nos', 'para', 'com', 'por', 'ao', 'aos', 'um', 'uma'];
    while (count($partes) > 1 && in_array(end($partes), $ligacao, true)) array_pop($partes);
    return implode('-', $partes);
}

function link_pagina(string $nome, array $query = []): string
{
    if ($nome === '' || $nome === 'inicio') return url('', $query);
    return urls_amigaveis() ? url($nome, $query) : url($nome . '.php', $query);
}

function link_tema(string $tema, ?string $sub = null): string
{
    if (urls_amigaveis()) return url('tema/' . $tema . ($sub ? '/' . $sub : ''));
    return url('tema.php', ['t' => $tema, 'sub' => $sub]);
}

function link_candidato(string $slug, ?string $tema = null): string
{
    if (urls_amigaveis()) return url('candidato/' . $slug, ['tema' => $tema]);
    return url('candidato.php', ['c' => $slug, 'tema' => $tema]);
}

/** $cands: linhas com 'id' e 'slug', na ordem escolhida. */
function link_comparar(array $cands, ?string $tema = null, bool $apresentar = false): string
{
    $slugs = array_values(array_filter(array_map(static fn($c) => (string) ($c['slug'] ?? ''), $cands)));
    if (urls_amigaveis() && $tema && count($slugs) >= 2) {
        return url('comparar/' . implode('-x-', $slugs) . '/' . $tema . ($apresentar ? '/apresentar' : ''));
    }
    $ids = array_map(static fn($c) => (int) $c['id'], $cands);
    return url('comparar.php', ['c' => $ids ?: null, 'tema' => $tema, 'apresentar' => $apresentar ? '1' : null]);
}

function link_proposta(array $p): string
{
    $id = (int) $p['id'];
    if (urls_amigaveis()) {
        $s = slugificar((string) ($p['acao'] ?? ''), 7);
        return url('proposta/' . $id . ($s !== '' ? '-' . $s : ''));
    }
    return url('proposta.php', ['id' => $id]);
}

/** Endereço completo (https://site/...) para compartilhar e para as prévias de link. */
function url_absoluta(string $u): string
{
    if (preg_match('#^https?://#i', $u)) return $u;
    $site = defined('SITE_URL') && SITE_URL ? (string) SITE_URL : (string) getenv('VOTAI_SITE_URL');
    if ($site !== '') {
        $base = rtrim($site, '/');
        $rel = BASE_URL !== '' && str_starts_with($u, BASE_URL) ? substr($u, strlen(BASE_URL)) : $u;
        return $base . '/' . ltrim($rel, '/');
    }
    // Sem SITE_URL o endereço vem do cabeçalho Host (só para desenvolvimento). Em produção, defina SITE_URL no config.php.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.\-:\[\]]{1,100}$/i', $host)) $host = 'localhost';   // nunca confiar em Host inválido
    return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($u, '/');
}

/** URL de asset com versão (cache-busting pelo mtime). */
function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Número com 2 dígitos: 7 → "07". */
function pad2(int $n): string
{
    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

function plural(int $n, string $sing, string $plur): string
{
    return $n === 1 ? $sing : $plur;
}

/** Iniciais para o avatar de fallback: "Candidato A" → "CA". */
function iniciais(string $nome): string
{
    $nome = preg_replace('/\(.*?\)/u', '', $nome) ?? $nome;
    $partes = preg_split('/\s+/u', trim($nome)) ?: [];
    $ini = '';
    foreach ($partes as $p) {
        if ($p !== '' && mb_strlen($ini) < 2) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
    }
    return $ini ?: '?';
}

/** Valor ou "Não informado no documento." (nunca inventamos dados). */
function campo(?string $v): string
{
    $v = trim((string) $v);
    return $v === ''
        ? '<span class="nao-informado">' . NAO_INFORMADO . '</span>'
        : h($v);
}

/** Verifica se um caminho de arquivo relativo à raiz existe. */
function arquivo_existe(?string $rel): bool
{
    if (!$rel) return false;
    $rel = ltrim(str_replace(['..', '\\'], ['', '/'], $rel), '/');
    return is_file(ROOT_PATH . '/' . $rel);
}

/**
 * Foto padronizada da candidatura (4:5).
 * Mesmo tratamento para todos. Se o arquivo não existir ou falhar ao
 * carregar, exibe um avatar neutro com iniciais (sem cor exclusiva).
 */
function foto_candidato(array $c, string $classe = '', string $sizes = '(max-width: 700px) 45vw, 240px', bool $eager = false): string
{
    $nome = $c['nome_urna'] ?? $c['nome'] ?? '';
    $ini  = h(iniciais($nome));
    $fallback = '<span class="foto__fallback" aria-hidden="true"><span class="foto__ini">' . $ini . '</span><span class="foto__label">Foto</span></span>';
    $html = '<figure class="foto ' . h($classe) . '" data-iniciais="' . $ini . '">';
    if (arquivo_existe($c['foto'] ?? null)) {
        $html .= '<img src="' . h(url($c['foto'])) . '" alt="Foto de ' . h($nome) . '" width="800" height="1000" sizes="' . h($sizes) . '"'
              . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async" data-foto>';
    } else {
        $html .= $fallback;
        $fallback = '';
    }
    return $html . $fallback . '</figure>';
}

/** Logo do partido em área neutra; fallback = sigla em texto. */
function logo_partido(array $c, string $classe = ''): string
{
    $sigla = (string) ($c['partido_sigla'] ?? '');
    if ($sigla === '') return '';
    $html = '<span class="logo-partido ' . h($classe) . '" title="' . h($c['partido_nome'] ?? $sigla) . '">';
    $logo = $c['partido_logo'] ?? null;
    if (!arquivo_existe($logo)) $logo = logo_por_sigla($sigla);   // convenção: assets/images/partidos/{sigla}.webp
    if ($logo) {
        $html .= '<img src="' . h(url($logo)) . '" alt="" width="28" height="28" loading="lazy" data-logo>';
    }
    $html .= '<span class="logo-partido__sigla">' . h($sigla) . '</span></span>';
    return $html;
}

/** Procura o logo pelo nome do arquivo: "PC do B" → assets/images/partidos/pc-do-b.webp */
function logo_por_sigla(string $sigla): ?string
{
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtr($sigla, ['Ã' => 'A', 'ã' => 'a', 'Á' => 'A', 'á' => 'a', 'Ç' => 'C', 'ç' => 'c', 'É' => 'E', 'é' => 'e', 'Í' => 'I', 'í' => 'i', 'Ó' => 'O', 'ó' => 'o', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U'])), '-'));
    foreach (['webp', 'png', 'svg', 'jpg'] as $ext) {
        $rel = "assets/images/partidos/$slug.$ext";
        if (is_file(ROOT_PATH . '/' . $rel)) return $rel;
    }
    return null;
}

/** Destaca termos buscados (entrada já escapada). */
function destacar(string $textoEscapado, array $termos): string
{
    // Uma única passada sobre o texto original: não marca dentro de tags nem de entidades HTML.
    $termos = array_values(array_filter($termos, static fn($t) => is_string($t) && mb_strlen($t) >= 3));
    if (!$termos) return $textoEscapado;
    usort($termos, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $re = '/(' . implode('|', array_map(static fn($t) => preg_quote($t, '/'), $termos)) . ')/iu';
    $texto = html_entity_decode($textoEscapado, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $partes = preg_split($re, $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($partes === false) return $textoEscapado;
    $out = '';
    foreach ($partes as $i => $parte) $out .= $i % 2 ? '<mark>' . h($parte) . '</mark>' : h($parte);
    return $out;
}

/** Lê inteiro de GET com segurança. */
/**
 * Cabeçalhos de segurança das páginas públicas.
 * CSP: só scripts do próprio site (+ os inline informados, pelo hash), sem objetos, sem iframes de fora.
 */
function enviar_cabecalhos_seguranca(array $scriptsInline = []): void
{
    if (headers_sent()) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $hashes = array_map(static fn($js) => "'sha256-" . base64_encode(hash('sha256', $js, true)) . "'", $scriptsInline);
    header("Content-Security-Policy: default-src 'self'; script-src 'self' " . implode(' ', $hashes)
        . "; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; media-src 'self'"
        . "; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'" . ($https ? '; upgrade-insecure-requests' : ''));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if ($https) header('Strict-Transport-Security: max-age=31536000');
}

/** Lê texto de GET (listas e valores estranhos viram texto vazio). */
function get_texto(string $k, int $max = 200): string
{
    $v = $_GET[$k] ?? '';
    return is_string($v) ? mb_substr($v, 0, $max) : '';
}

function get_int(string $k): ?int
{
    $v = filter_var($_GET[$k] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v === false || $v === null ? null : (int) $v;
}

/** Lê slug de GET (apenas a-z, 0-9 e hífen). */
function get_slug(string $k): ?string
{
    $v = $_GET[$k] ?? '';
    if (!is_string($v)) return null;
    return preg_match('/^[a-z0-9-]{1,140}$/', $v) ? $v : null;
}

function nao_encontrado(string $msg = 'Página não encontrada.'): never
{
    http_response_code(404);
    $pageTitle = 'Não encontrado';
    $pageId = '';
    require ROOT_PATH . '/includes/header.php';
    echo '<main class="page page--404" id="conteudo"><section class="s-404"><p class="kicker">ERRO 404</p>'
       . '<h1 class="display display--xl"><span class="outline">NADA</span><br>POR AQUI.</h1>'
       . '<p class="lead">' . h($msg) . '</p><a class="btn-line" href="' . h(url('')) . '">Voltar ao início <span aria-hidden="true">→</span></a></section></main>';
    require ROOT_PATH . '/includes/footer.php';
    exit;
}

/** Glifos lineares próprios de cada tema (usados no índice editorial). */
function glifo_tema(string $slug): string
{
    $p = [
        'saude'               => '<path d="M40 14v52M14 40h52"/><circle cx="40" cy="40" r="30"/>',
        'educacao'            => '<path d="M10 22l30-10 30 10-30 10z"/><path d="M22 27v18c10 8 26 8 36 0V27"/><path d="M70 22v24"/>',
        'economia'            => '<path d="M10 64h60"/><path d="M14 54l16-16 12 10 24-28"/><path d="M54 20h12v12"/>',
        'emprego-e-renda'     => '<rect x="12" y="26" width="56" height="38"/><path d="M30 26v-8h20v8M12 42h56"/>',
        'seguranca-publica'   => '<path d="M40 10l26 10v18c0 16-11 26-26 32C25 64 14 54 14 38V20z"/><path d="M40 10v60"/>',
        'habitacao'           => '<path d="M10 38L40 12l30 26"/><path d="M18 32v34h44V32"/><path d="M34 66V48h12v18"/>',
        'infraestrutura'      => '<path d="M8 58h64M16 58V36h48v22"/><path d="M16 36c8-14 40-14 48 0"/><path d="M28 58V44M40 58V42M52 58V44"/>',
        'meio-ambiente'       => '<path d="M40 70V30"/><path d="M40 44C22 44 16 30 16 14c16 0 24 10 24 30z"/><path d="M40 36c0-14 8-22 24-22 0 14-8 22-24 22z"/>',
        'impostos'            => '<rect x="16" y="10" width="48" height="60"/><path d="M26 24h28M26 34h28M26 44h16"/><circle cx="50" cy="56" r="6"/>',
        'assistencia-social'  => '<circle cx="28" cy="24" r="8"/><circle cx="52" cy="24" r="8"/><path d="M12 64c0-14 7-22 16-22s16 8 16 22M36 64c0-14 7-22 16-22s16 8 16 22"/>',
        'tecnologia'          => '<rect x="20" y="20" width="40" height="40"/><path d="M30 20V10M40 20V10M50 20V10M30 70V60M40 70V60M50 70V60M20 30H10M20 40H10M20 50H10M70 30H60M70 40H60M70 50H60"/><rect x="31" y="31" width="18" height="18"/>',
        'gestao-publica'      => '<path d="M10 26L40 12l30 14z"/><path d="M16 32v28M28 32v28M40 32v28M52 32v28M64 32v28M10 66h60"/>',
        'relacoes-exteriores' => '<circle cx="40" cy="40" r="28"/><path d="M12 40h56"/><ellipse cx="40" cy="40" rx="12" ry="28"/>',
        'energia'             => '<path d="M46 8L20 44h18l-6 28 28-38H42z"/>',
    ];
    $inner = $p[$slug] ?? '<path d="M40 8l32 32-32 32L8 40z"/><circle cx="40" cy="40" r="12"/>';
    return '<svg class="glifo" viewBox="0 0 80 80" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
