<?php
/**
 * IMAGEM DE PRÉVIA DO LINK (Open Graph) — 1200 × 630, JPEG.
 * É a imagem que aparece quando alguém cola um link do Votai no WhatsApp, Telegram, X etc.
 *
 *   og.php?tipo=proposta&id=123
 *   og.php?tipo=comparar&c[]=5&c[]=9&tema=saude
 *   og.php?tipo=tema&t=saude
 *   og.php?tipo=candidato&c=lula
 *   og.php                        (imagem geral do site)
 *
 * Usa a extensão GD do PHP (já vem no XAMPP; ative "extension=gd" no php.ini se estiver desligada).
 * Sem GD, entrega a imagem padrão pronta em assets/images/interface/og-padrao.jpg.
 * As imagens geradas ficam guardadas em uploads/og/ e são refeitas quando os dados mudam.
 * As fotos das candidaturas entram sem nenhum filtro ou alteração, no mesmo tamanho para todas.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/repositorio.php';

const OG_W = 1200;
const OG_H = 630;
$PADRAO = ROOT_PATH . '/assets/images/interface/og-padrao.jpg';

function og_enviar(string $arquivo): never
{
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($arquivo);
    exit;
}

if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) og_enviar($PADRAO);

// ---------- Entrada ----------
$tipo = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : 'site';
$dados = null;
$chave = $tipo;
try {
    switch ($tipo) {
        case 'proposta':
            $id = get_int('id');
            $p = $id ? proposta_publicada($id) : null;
            if ($p) { $dados = ['p' => $p, 'c' => candidato_por_slug($p['candidato_slug'])]; $chave .= $id . '|' . $p['atualizado_em']; }
            break;
        case 'comparar':
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', array_filter((array) ($_GET['c'] ?? []), 'is_scalar'))))), 0, 3);
            $tema = get_slug('tema') === 'todos' ? ['id' => 0, 'slug' => 'todos', 'nome' => 'Todos os temas']
                  : (get_slug('tema') ? categoria_por_slug((string) get_slug('tema')) : null);
            $cands = candidatos_por_ids($ids);
            // a chave do cache usa só candidaturas que existem, em ordem fixa: números inventados não geram arquivos novos
            usort($cands, static fn($a, $b) => strcmp((string) $a['nome_urna'], (string) $b['nome_urna']));
            $ids = array_map(static fn($c) => (int) $c['id'], $cands);
            if (count($cands) >= 2 && $tema) {
                $cont = [];
                foreach (propostas_publicadas(array_filter(['categoria_id' => (int) $tema['id'], 'candidatos' => $ids])) as $x) $cont[(int) $x['candidato_id']] = ($cont[(int) $x['candidato_id']] ?? 0) + 1;
                $dados = ['cands' => $cands, 'tema' => $tema, 'cont' => $cont];
                $chave .= implode('-', $ids) . '|' . $tema['slug'] . '|' . json_encode($cont);
            }
            break;
        case 'tema':
            $t = get_slug('t');
            foreach (categorias_com_contagem() as $x) if ($x['slug'] === $t) $dados = ['tema' => $x, 'total' => count(candidatos_lista())];
            if ($dados) $chave .= $t . '|' . $dados['tema']['total_propostas'] . '|' . $dados['tema']['total_candidatos'];
            break;
        case 'candidato':
            $slug = get_slug('c');
            foreach (candidatos_lista() as $x) if ($x['slug'] === $slug) $dados = ['c' => $x];
            if ($dados) $chave .= $slug . '|' . $dados['c']['total_propostas'] . '|' . $dados['c']['total_temas'];
            break;
    }
} catch (Throwable $e) {
    error_log('[votai og] ' . $e->getMessage());
}
if (!$dados) { $tipo = 'site'; $chave = 'site'; }
if ($tipo === 'site') $chave .= '|' . json_encode(estatisticas());

// ---------- Cache ----------
$pasta = ROOT_PATH . '/uploads/og';
if (!is_dir($pasta)) @mkdir($pasta, 0775, true);
$arquivo = $pasta . '/' . md5($chave . '|' . filemtime(__FILE__)) . '.jpg';
if (is_file($arquivo)) og_enviar($arquivo);

// limite de espaço: no máximo OG_MAX_ARQUIVOS imagens guardadas; as mais antigas saem primeiro
const OG_MAX_ARQUIVOS = 1500;
$existentes = glob($pasta . '/*.jpg') ?: [];
if (count($existentes) >= OG_MAX_ARQUIVOS) {
    usort($existentes, static fn($a, $b) => (int) @filemtime($a) <=> (int) @filemtime($b));
    foreach (array_slice($existentes, 0, (int) (OG_MAX_ARQUIVOS / 5)) as $velho) @unlink($velho);
}

// ---------- Desenho ----------
$F_TIT  = ROOT_PATH . '/assets/fonts/ttf/Archivo_900Black.ttf';
$F_TXT  = ROOT_PATH . '/assets/fonts/ttf/Archivo_500Medium.ttf';
$F_MONO = ROOT_PATH . '/assets/fonts/ttf/IBMPlexMono_500Medium.ttf';

$im = imagecreatetruecolor(OG_W, OG_H);
imageantialias($im, true);
$cor = static fn(int $r, int $g, int $b, int $a = 0) => imagecolorallocatealpha($im, $r, $g, $b, $a);
$FUNDO = $cor(6, 8, 7);   $TINTA = $cor(236, 238, 230); $TINTA2 = $cor(164, 172, 161); $TINTA3 = $cor(106, 114, 105);
$LINHA = $cor(236, 238, 230, 112); $AMARELO = $cor(255, 203, 0); $VERDE = $cor(34, 212, 112);
imagefilledrectangle($im, 0, 0, OG_W, OG_H, $FUNDO);

// losango da bandeira, discreto, ao fundo
imagesetthickness($im, 1);
$cx = 900; $cy = 315;
imagepolygon($im, [$cx, 20, $cx + 420, $cy, $cx, 610, $cx - 420, $cy], $cor(236, 238, 230, 121));
imageellipse($im, $cx, $cy, 330, 330, $cor(236, 238, 230, 123));

/** Escreve texto; devolve a largura. */
$txt = static function (string $s, string $fonte, float $tam, int $x, int $y, int $c, float $esp = 0) use ($im): int {
    if ($esp <= 0) { $b = imagettftext($im, $tam, 0, $x, $y, $c, $fonte, $s); return $b[2] - $b[0]; }
    $x0 = $x;
    foreach (mb_str_split($s) as $ch) { $b = imagettftext($im, $tam, 0, $x, $y, $c, $fonte, $ch); $x += ($b[2] - $b[0]) + (int) $esp; }
    return $x - $x0;
};
$larg = static function (string $s, string $fonte, float $tam): int {
    $b = imagettfbbox($tam, 0, $fonte, $s);
    return abs($b[2] - $b[0]);
};
/** Quebra em linhas que cabem em $max px; reticências se passar de $linhas. */
$quebrar = static function (string $s, string $fonte, float $tam, int $max, int $linhas) use ($larg): array {
    $out = []; $atual = '';
    foreach (preg_split('/\s+/u', trim($s)) as $w) {
        $teste = $atual === '' ? $w : "$atual $w";
        if ($larg($teste, $fonte, $tam) <= $max) { $atual = $teste; continue; }
        if ($atual !== '') $out[] = $atual;
        $atual = $w;
        if (count($out) === $linhas) break;
    }
    if ($atual !== '' && count($out) < $linhas) $out[] = $atual;
    if (count($out) === $linhas && implode(' ', $out) !== trim((string) preg_replace('/\s+/u', ' ', $s))) {
        $ult = $out[$linhas - 1];
        while ($ult !== '' && $larg($ult . '…', $fonte, $tam) > $max) $ult = mb_substr($ult, 0, -1);
        $out[$linhas - 1] = rtrim($ult, " ,.;:") . '…';
    }
    return $out;
};
/** Maior tamanho de fonte (entre $max e $min) em que o texto cabe em uma linha. */
$caber = static function (string $s, string $fonte, float $max, float $min, int $w) use ($larg): float {
    for ($t = $max; $t > $min; $t -= 2) if ($larg($s, $fonte, $t) <= $w) return $t;
    return $min;
};
/** Foto 4:5, sem filtro, recorte central idêntico para todas. */
$foto = static function (?string $rel, int $x, int $y, int $w, string $ini) use ($im, $cor, $F_TIT, $TINTA2, $LINHA): void {
    $h = (int) round($w * 5 / 4);
    $src = null;
    if ($rel && arquivo_existe($rel)) {
        $abs = ROOT_PATH . '/' . ltrim($rel, '/');
        $info = @getimagesize($abs);
        $src = match ($info[2] ?? 0) { IMAGETYPE_JPEG => @imagecreatefromjpeg($abs), IMAGETYPE_PNG => @imagecreatefrompng($abs), IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : null, default => null };
    }
    if ($src) {
        $sw = imagesx($src); $sh = imagesy($src);
        $alvo = 4 / 5;
        if ($sw / $sh > $alvo) { $cw = (int) round($sh * $alvo); $cx = (int) (($sw - $cw) / 2); $cy = 0; $ch = $sh; }
        else { $ch = (int) round($sw / $alvo); $cy = (int) (($sh - $ch) / 2); $cx = 0; $cw = $sw; }
        imagecopyresampled($im, $src, $x, $y, $cx, $cy, $w, $h, $cw, $ch);
        imagedestroy($src);
    } else {
        imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $cor(17, 21, 18));
        $t = (int) ($w / 3.2);
        $b = imagettfbbox($t, 0, $F_TIT, $ini);
        imagettftext($im, $t, 0, (int) ($x + ($w - ($b[2] - $b[0])) / 2), (int) ($y + $h / 2 + $t / 2), $TINTA2, $F_TIT, $ini);
    }
    imagerectangle($im, $x, $y, $x + $w, $y + $h, $LINHA);
};

// moldura, marca e rodapé comuns
$M = 60;
imagerectangle($im, 24, 24, OG_W - 25, OG_H - 25, $LINHA);
$txt('VOTAI', $F_TIT, 22, $M, 82, $TINTA, 7);
imagefilledrectangle($im, $M, 96, $M + 34, 99, $AMARELO);
imagefilledrectangle($im, $M + 38, 96, $M + 72, 99, $VERDE);
$rodape = 'Plataforma informativa e apartidária · fonte oficial em cada proposta';

switch ($tipo) {
    case 'proposta':
        $p = $dados['p']; $c = $dados['c'] ?: [];
        $rot = 'PROPOSTA Nº ' . str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT);
        $txt($rot, $F_MONO, 15, OG_W - $M - $larg($rot, $F_MONO, 15) - 20, 82, $TINTA2, 2);
        $foto($c['foto'] ?? null, $M, 150, 240, iniciais((string) $p['candidato_nome']));
        $x = $M + 240 + 44; $w = OG_W - $x - $M;
        $txt(mb_strtoupper($p['candidato_nome']), $F_TIT, $caber(mb_strtoupper($p['candidato_nome']), $F_TIT, 40, 24, $w), $x, 190, $TINTA);
        $txt($p['candidato_numero'] . ' · ' . ($p['partido_sigla'] ?? ''), $F_MONO, 16, $x, 226, $TINTA2, 2);
        $trilha = mb_strtoupper($p['categoria_nome'] . ($p['subcategoria_nome'] ? ' / ' . $p['subcategoria_nome'] : ''));
        $txt(mb_strimwidth($trilha, 0, 60, '…'), $F_MONO, 15, $x, 282, $AMARELO, 2);
        $y = 336;
        foreach ($quebrar($p['acao'], $F_TIT, 34, $w, 4) as $l) { $txt($l, $F_TIT, 34, $x, $y, $TINTA); $y += 48; }
        $fonte = 'FONTE OFICIAL · ' . mb_strtoupper(tipo_documento($p['documento_tipo'] ?? null)) . ($p['pagina'] ? ' · P. ' . (int) $p['pagina'] : '');
        imagefilledellipse($im, $x + 5, 524, 10, 10, $VERDE);
        $txt($fonte, $F_MONO, 14, $x + 22, 530, $VERDE, 1.5);
        break;

    case 'comparar':
        $txt('COMPARAÇÃO LADO A LADO', $F_MONO, 15, OG_W - $M - 330, 82, $TINTA2, 2);
        $tema = mb_strtoupper($dados['tema']['nome']);
        $txt($tema, $F_TIT, $caber($tema, $F_TIT, 78, 40, OG_W - 2 * $M), $M, 196, $TINTA);
        $n = count($dados['cands']); $fw = 150; $gap = $n === 2 ? 220 : 90;
        $total = $n * $fw + ($n - 1) * $gap; $x0 = (int) ((OG_W - $total) / 2);
        foreach ($dados['cands'] as $k => $cc) {
            $x = $x0 + $k * ($fw + $gap);
            $foto($cc['foto'] ?? null, $x, 250, $fw, iniciais((string) $cc['nome_urna']));
            $nome = mb_strtoupper($cc['nome_urna']);
            $t = $caber($nome, $F_TIT, 24, 13, $fw + $gap - 20);
            $txt($nome, $F_TIT, $t, (int) ($x + ($fw - $larg($nome, $F_TIT, $t)) / 2), 470, $TINTA);
            $q = ($dados['cont'][(int) $cc['id']] ?? 0) . ' PROPOSTAS · ' . ($cc['partido_sigla'] ?? '');
            $txt($q, $F_MONO, 13, (int) ($x + ($fw - $larg($q, $F_MONO, 13) - strlen($q)) / 2), 500, $TINTA2, 1);
            if ($k < $n - 1) $txt('×', $F_TIT, 48, (int) ($x + $fw + $gap / 2 - 16), 350, $AMARELO);
        }
        $rodape = 'Sem notas, sem ranking · trecho original e página de cada proposta';
        break;

    case 'tema':
        $t = $dados['tema'];
        $txt('TEMA', $F_MONO, 15, OG_W - $M - 60, 82, $TINTA2, 2);
        $nome = mb_strtoupper($t['nome']);
        $linhas = $quebrar($nome, $F_TIT, 88, OG_W - 2 * $M, 2);
        $y = count($linhas) > 1 ? 222 : 262;
        foreach ($linhas as $l) { $txt($l, $F_TIT, 88, $M, $y, $TINTA); $y += 96; }
        $txt('O que cada candidatura propõe, com a fonte oficial.', $F_TXT, 28, $M, $y + 6, $TINTA2);
        $txt((int) $t['total_propostas'] . ' PROPOSTAS · ' . (int) $t['total_candidatos'] . ' DE ' . (int) $dados['total'] . ' CANDIDATURAS', $F_MONO, 16, $M, $y + 56, $AMARELO, 2);
        break;

    case 'candidato':
        $c = $dados['c'];
        $txt('CANDIDATURA', $F_MONO, 15, OG_W - $M - 150, 82, $TINTA2, 2);
        $foto($c['foto'] ?? null, $M, 150, 260, iniciais((string) $c['nome_urna']));
        $x = $M + 260 + 50; $w = OG_W - $x - $M; $y = 250;
        foreach ($quebrar(mb_strtoupper($c['nome_urna']), $F_TIT, 64, $w, 2) as $l) { $txt($l, $F_TIT, 64, $x, $y, $TINTA); $y += 72; }
        $txt('Nº ' . $c['numero'] . ' · ' . ($c['partido_nome'] ?? ''), $F_TXT, 26, $x, $y + 10, $TINTA2);
        $txt((int) $c['total_propostas'] . ' PROPOSTAS EM ' . (int) $c['total_temas'] . ' TEMAS', $F_MONO, 16, $x, $y + 70, $AMARELO, 2);
        break;

    default:
        $e = estatisticas();
        $txt('ELEIÇÕES 2026', $F_MONO, 15, OG_W - $M - 170, 82, $TINTA2, 2);
        $txt('O QUE ELES', $F_TIT, 70, $M, 250, $TINTA);
        $txt('PROPÕEM PARA', $F_TIT, 70, $M, 336, $TINTA);
        $w1 = $txt('O BRASIL', $F_TIT, 70, $M, 422, $TINTA);
        $txt('?', $F_TIT, 70, $M + $w1 + 6, 422, $AMARELO);
        $txt($e['candidatos'] . ' CANDIDATURAS · ' . $e['propostas'] . ' PROPOSTAS · ' . $e['temas'] . ' TEMAS', $F_MONO, 16, $M, 486, $TINTA2, 2);
}

$txt($rodape, $F_TXT, 17, $M, OG_H - 50, $TINTA3);

$tmp = $arquivo . '.' . getmypid() . '.tmp';
if (is_dir($pasta) && is_writable($pasta) && imagejpeg($im, $tmp, 86)) {
    @rename($tmp, $arquivo);
    imagedestroy($im);
    og_enviar($arquivo);
}
// sem permissão de escrita: entrega direto, sem cache
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
imagejpeg($im, null, 86);
imagedestroy($im);
