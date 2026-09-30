<?php
/**
 * CANAL DE CORREÇÕES — relatos enviados pelo público, guardados no próprio banco.
 * Nada é enviado para serviços externos. O IP nunca é guardado: só um hash
 * (para limitar abusos), apagado depois de 30 dias.
 */

declare(strict_types=1);

require_once __DIR__ . '/funcoes.php';

const CORRECAO_TIPOS = [
    'resumo'      => 'O resumo não corresponde ao texto original',
    'trecho'      => 'O trecho original está errado ou incompleto',
    'pagina'      => 'A página ou o documento indicado está errado',
    'tema'        => 'A proposta está no tema/subtema errado',
    'faltando'    => 'Falta uma proposta que está no documento',
    'candidatura' => 'Dado da candidatura errado (nome, número, partido, foto)',
    'privacidade' => 'Pedido sobre dados pessoais (LGPD)',
    'outro'       => 'Outro problema',
];

const CORRECAO_STATUS = [
    'nova'       => 'Nova',
    'em_analise' => 'Em análise',
    'corrigida'  => 'Corrigida',
    'descartada' => 'Sem alteração',
];

const CORRECAO_MAX_POR_IP_HORA = 5;
const CORRECAO_MAX_POR_DIA     = 300;   // teto geral contra enxurrada automática
const CORRECAO_TEMPO_MINIMO    = 4;     // segundos: robôs enviam na hora

function correcoes_tabela(): void
{
    static $ok = false;
    if ($ok) return;
    db()->exec("CREATE TABLE IF NOT EXISTS correcoes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        proposta_id INT UNSIGNED NULL,
        tipo VARCHAR(20) NOT NULL,
        pagina VARCHAR(40) NULL,
        descricao TEXT NOT NULL,
        email VARCHAR(190) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'nova',
        nota_interna TEXT NULL,
        ip_hash CHAR(64) NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status, criado_em),
        KEY idx_ip (ip_hash, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ok = true;
}

/** Chave secreta local (gerada uma vez) para assinar o formulário. */
function correcoes_segredo(): string
{
    $arq = ROOT_PATH . '/uploads/.segredo-formulario';
    $s = is_file($arq) ? trim((string) @file_get_contents($arq)) : '';
    if (strlen($s) < 32) {
        $s = bin2hex(random_bytes(32));
        @file_put_contents($arq, $s, LOCK_EX);
        @chmod($arq, 0600);
        if (!is_file($arq)) $s = hash('sha256', DB_NAME . DB_USER . DB_PASS . __FILE__);   // pasta sem gravação: chave derivada
    }
    return $s;
}

/** Carimbo assinado com a hora em que o formulário foi aberto (anti-robô, sem cookie). */
function correcoes_carimbo(): string
{
    $t = (string) time();
    return $t . '.' . hash_hmac('sha256', 'correcao|' . $t, correcoes_segredo());
}

function correcoes_carimbo_valido(string $c): bool
{
    [$t, $sig] = array_pad(explode('.', $c, 2), 2, '');
    if (!ctype_digit($t) || !hash_equals(hash_hmac('sha256', 'correcao|' . $t, correcoes_segredo()), $sig)) return false;
    $idade = time() - (int) $t;
    return $idade >= CORRECAO_TEMPO_MINIMO && $idade <= 86400;
}

function correcoes_ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false) $ip = bin2hex(substr($bin, 0, 8));
    return hash_hmac('sha256', $ip, correcoes_segredo());
}

/**
 * Valida e grava um relato. Devolve [true, id] ou [false, lista de erros].
 * $dados: tipo, proposta (nº), pagina, descricao, email, site (armadilha), carimbo
 */
function correcoes_registrar(array $dados): array
{
    $erros = [];
    $s = static fn(string $k, int $max) => is_string($dados[$k] ?? null) ? trim(mb_substr($dados[$k], 0, $max)) : '';

    // armadilhas para robôs: campo invisível preenchido ou envio instantâneo
    if ($s('site', 200) !== '' || !correcoes_carimbo_valido($s('carimbo', 200))) {
        return [false, ['Não foi possível enviar. Recarregue a página, aguarde alguns segundos e tente de novo.']];
    }

    $tipo = $s('tipo', 20);
    if (!isset(CORRECAO_TIPOS[$tipo])) $erros[] = 'Escolha o tipo de problema.';

    $num = preg_replace('/\D/', '', $s('proposta', 12));
    $propostaId = $num !== '' ? (int) $num : null;
    if ($propostaId !== null) {
        $st = db()->prepare('SELECT id FROM propostas WHERE id = :id');
        $st->execute(['id' => $propostaId]);
        if (!$st->fetchColumn()) $erros[] = 'Não encontramos a proposta Nº ' . str_pad((string) $propostaId, 4, '0', STR_PAD_LEFT) . '. Confira o número.';
    }

    $pagina = $s('pagina', 40);
    $descricao = $s('descricao', 3000);
    if (mb_strlen($descricao) < 15) $erros[] = 'Descreva o problema com um pouco mais de detalhe (mínimo de 15 caracteres).';
    if (preg_match_all('#https?://#i', $descricao) > 3) $erros[] = 'Use no máximo 3 links na descrição.';

    $email = mb_strtolower($s('email', 190));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'O e-mail informado não parece válido (ele é opcional).';

    if ($erros) return [false, $erros];

    correcoes_tabela();
    $pdo = db();
    $ip = correcoes_ip_hash();
    $st = $pdo->prepare('SELECT
        (SELECT COUNT(*) FROM correcoes WHERE ip_hash = :ip AND criado_em > NOW() - INTERVAL 1 HOUR),
        (SELECT COUNT(*) FROM correcoes WHERE criado_em > NOW() - INTERVAL 1 DAY)');
    $st->execute(['ip' => $ip]);
    [$porIp, $porDia] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
    if ($porIp >= CORRECAO_MAX_POR_IP_HORA || $porDia >= CORRECAO_MAX_POR_DIA) {
        return [false, ['Recebemos muitos envios em pouco tempo. Tente novamente mais tarde.']];
    }

    $pdo->prepare('INSERT INTO correcoes (proposta_id, tipo, pagina, descricao, email, ip_hash) VALUES (:p, :t, :pg, :d, :e, :ip)')
        ->execute(['p' => $propostaId, 't' => $tipo, 'pg' => $pagina ?: null, 'd' => $descricao, 'e' => $email ?: null, 'ip' => $ip]);
    $id = (int) $pdo->lastInsertId();

    // privacidade: hash de IP some depois de 30 dias
    if (random_int(1, 20) === 1) $pdo->exec('UPDATE correcoes SET ip_hash = NULL WHERE ip_hash IS NOT NULL AND criado_em < NOW() - INTERVAL 30 DAY');

    return [true, $id];
}

function correcoes_contar_novas(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM correcoes WHERE status = 'nova'")->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}
