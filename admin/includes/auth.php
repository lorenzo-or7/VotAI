<?php
/**
 * Autenticação e utilidades do painel administrativo.
 * - Sessão com cookie HttpOnly/SameSite
 * - Proteção CSRF em todos os formulários (POST)
 * - Limite de tentativas de login
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/funcoes.php';

/** Conexão segura? (inclui hospedagens atrás de proxy que informam o protocolo) */
function conexao_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        && (defined('CONFIAR_PROXY') && CONFIAR_PROXY);
}

// Em produção (FORCAR_HTTPS = true no config.php) o painel só abre por HTTPS
if (defined('FORCAR_HTTPS') && FORCAR_HTTPS && !conexao_https() && PHP_SAPI !== 'cli') {
    header('Location: https://' . preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('votai_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_URL ?: '') . '/admin',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => conexao_https() || (defined('FORCAR_HTTPS') && FORCAR_HTTPS),
    ]);
    session_start();
}

const ADMIN_SCRIPT_TEMA = "try{var t=localStorage.getItem('votai-tema');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t;}catch(e){}";
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'sha256-" . base64_encode(hash('sha256', ADMIN_SCRIPT_TEMA, true))
    . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

const STATUS_ROTULOS = [
    'pendente'   => 'Pendente',
    'em_revisao' => 'Em revisão',
    'aprovada'   => 'Aprovada',
    'rejeitada'  => 'Rejeitada',
    'publicada'  => 'Publicada',
];

/** Transições permitidas no fluxo de revisão. */
const STATUS_TRANSICOES = [
    'pendente'   => ['em_revisao', 'rejeitada'],
    'em_revisao' => ['aprovada', 'rejeitada', 'pendente'],
    'aprovada'   => ['publicada', 'em_revisao', 'rejeitada'],
    'rejeitada'  => ['em_revisao'],
    'publicada'  => ['aprovada'],
];

const SESSAO_MAX_INATIVO = 7200;   // 2 h sem uso
const SESSAO_MAX_TOTAL   = 43200;  // 12 h no máximo, mesmo em uso
const SENHA_PADRAO       = 'Votai@2026';

/** Impressão digital da senha atual: trocar a senha derruba as outras sessões abertas. */
function marca_senha(string $hash): string
{
    return substr(hash('sha256', $hash), 0, 16);
}

function admin_usuario(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    if (empty($_SESSION['admin_id'])) return $u = null;
    if (time() - ($_SESSION['ultimo_uso'] ?? 0) > SESSAO_MAX_INATIVO
        || time() - ($_SESSION['inicio'] ?? 0) > SESSAO_MAX_TOTAL) {
        admin_logout();
        return $u = null;
    }
    $st = db()->prepare('SELECT id, nome, email, papel, senha_hash FROM usuarios_admin WHERE id = :id AND ativo = 1');
    $st->execute(['id' => (int) $_SESSION['admin_id']]);
    $row = $st->fetch() ?: null;
    if (!$row || !hash_equals((string) ($_SESSION['marca'] ?? ''), marca_senha((string) $row['senha_hash']))) {
        admin_logout();
        return $u = null;
    }
    $_SESSION['ultimo_uso'] = time();
    unset($row['senha_hash']);
    return $u = $row;
}

function exigir_login(): array
{
    $u = admin_usuario();
    if (!$u) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
    // senha padrão de instalação: obriga a trocar antes de usar o painel
    if (!empty($_SESSION['trocar_senha']) && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'conta.php'
        && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'logout.php') {
        header('Location: ' . url('admin/conta.php'));
        exit;
    }
    return $u;
}

/** Páginas que só administradores podem usar (revisores cuidam apenas das propostas). */
function exigir_admin(): array
{
    $u = exigir_login();
    if (($u['papel'] ?? '') !== 'admin') {
        flash('Somente administradores podem acessar essa área.', 'erro');
        header('Location: ' . url('admin/index.php'));
        exit;
    }
    return $u;
}

function eh_admin(): bool
{
    return (admin_usuario()['papel'] ?? '') === 'admin';
}

// ------------------------------------------------------------------ Limite de tentativas
// Guardado no banco (por IP e por e-mail): apagar o cookie não "zera" a contagem.
const LOGIN_MAX_FALHAS = 5;

function login_tabela(): void
{
    static $ok = false;
    if ($ok) return;
    db()->exec("CREATE TABLE IF NOT EXISTS login_tentativas (
        chave CHAR(64) NOT NULL PRIMARY KEY,
        falhas INT UNSIGNED NOT NULL DEFAULT 0,
        bloqueado_ate DATETIME NULL,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ok = true;
}

function login_chave_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    // IPv6: agrupa pelo prefixo /64 (um atacante controla todos os endereços do bloco)
    if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false) $ip = bin2hex(substr($bin, 0, 8)) . '::/64';
    return hash('sha256', 'ip:' . $ip);
}

/** Conta: pelo id do usuário quando existe (variações de maiúsculas/acentos no e-mail caem na mesma chave). */
function login_chave_conta(?array $row, string $email): string
{
    return hash('sha256', $row ? 'uid:' . (int) $row['id'] : 'email:' . $email);
}

/** A senha padrão de instalação só vale no computador local (XAMPP), nunca na hospedagem. */
function acesso_local(): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return in_array($ip, ['127.0.0.1', '::1'], true) && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}

/** Segundos que ainda faltam de bloqueio (0 = liberado). */
function login_bloqueado(array $chaves): int
{
    try {
        $st = db()->prepare('SELECT MAX(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_ate)) FROM login_tentativas WHERE chave IN (?, ?) AND bloqueado_ate > NOW()');
        $st->execute($chaves);
        return max(0, (int) $st->fetchColumn());
    } catch (PDOException $e) {
        return 0;
    }
}

function login_falhou(array $chaves): void
{
    // Por IP: a cada 5 falhas seguidas, bloqueio de 5, 10, 20, 40... minutos (máx. 24 h).
    // Por e-mail: bloqueio fixo de 5 min (se fosse crescente, um atacante poderia trancar o dono da conta para fora).
    [$ip, $email] = $chaves;
    try {
    $st = db()->prepare('INSERT INTO login_tentativas (chave, falhas) VALUES (:c, 1)
            ON DUPLICATE KEY UPDATE
              bloqueado_ate = IF(MOD(falhas + 1, ' . LOGIN_MAX_FALHAS . ') = 0,
                                 NOW() + INTERVAL LEAST(1440, :base * POW(:fator, FLOOR((falhas + 1) / ' . LOGIN_MAX_FALHAS . ') - 1)) MINUTE,
                                 bloqueado_ate),
              falhas = falhas + 1');   // a ordem importa: o MySQL aplica as atribuições da esquerda para a direita
    $st->execute(['c' => $ip, 'base' => 5, 'fator' => 2]);
    $st->execute(['c' => $email, 'base' => 5, 'fator' => 1]);
    // limpeza ocasional de registros antigos
    if (random_int(1, 50) === 1) db()->exec('DELETE FROM login_tentativas WHERE atualizado_em < NOW() - INTERVAL 7 DAY');
    } catch (PDOException $e) {
        error_log('[votai login] ' . $e->getMessage());
    }
}

function admin_login(string $email, string $senha): bool|string
{
    $email = mb_strtolower(trim($email));
    try { login_tabela(); } catch (PDOException $e) { error_log('[votai login] ' . $e->getMessage()); }

    $st = db()->prepare('SELECT id, senha_hash FROM usuarios_admin WHERE email = :e AND ativo = 1 LIMIT 1');
    $st->execute(['e' => $email]);
    $row = $st->fetch() ?: null;
    $chaves = [login_chave_ip(), login_chave_conta($row, $email)];

    $espera = login_bloqueado($chaves);
    if ($espera > 0) {
        usleep(400000);
        return 'Muitas tentativas. Aguarde ' . max(1, (int) ceil($espera / 60)) . ' minuto(s) e tente de novo.';
    }

    // password_verify sempre executado (tempo constante mesmo sem usuário)
    $hash = $row['senha_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
    if (!password_verify($senha, $hash) || !$row) {
        login_falhou($chaves);
        usleep(400000);
        return false;
    }
    if (hash_equals(SENHA_PADRAO, $senha) && !acesso_local()) {
        // proteção: ninguém de fora assume a conta usando a senha que está no README
        return 'Esta conta ainda usa a senha padrão de instalação, que só funciona no computador local (XAMPP). Troque a senha lá antes de publicar o banco.';
    }

    // sucesso: zera só o contador da conta (o do IP continua valendo)
    db()->prepare('DELETE FROM login_tentativas WHERE chave = ?')->execute([$chaves[1]]);

    $novoHash = $row['senha_hash'];
    if (password_needs_rehash($row['senha_hash'], PASSWORD_DEFAULT)) {
        $novoHash = password_hash($senha, PASSWORD_DEFAULT);
        db()->prepare('UPDATE usuarios_admin SET senha_hash = :h WHERE id = :id')->execute(['h' => $novoHash, 'id' => $row['id']]);
    }
    session_regenerate_id(true);
    $_SESSION = [
        'admin_id'   => (int) $row['id'],
        'ultimo_uso' => time(),
        'inicio'     => time(),
        'marca'      => marca_senha($novoHash),
    ];
    if (hash_equals(SENHA_PADRAO, $senha)) $_SESSION['trocar_senha'] = 1;
    db()->prepare('UPDATE usuarios_admin SET ultimo_login = NOW() WHERE id = :id')->execute(['id' => $row['id']]);
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ------------------------------------------------------------------ CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verificar(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $t = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $esperado = (string) ($_SESSION['csrf'] ?? '');
    if ($t === '' || $esperado === '' || !hash_equals($esperado, $t)) {
        http_response_code(400);
        exit('Requisição inválida (token de segurança expirado). Volte e recarregue a página.');
    }
}

// ------------------------------------------------------------------ Utilidades
function flash(?string $msg = null, string $tipo = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirecionar(string $path, array $q = []): never
{
    header('Location: ' . url($path, $q));
    exit;
}

function post(string $k, int $max = 5000): string
{
    $v = $_POST[$k] ?? '';
    return is_string($v) ? trim(mb_substr($v, 0, $max)) : '';
}

/** Texto opcional: string vazia vira NULL (campo "não informado"). */
function post_opcional(string $k, int $max = 500): ?string
{
    $v = post($k, $max);
    return $v === '' ? null : $v;
}

function post_int(string $k): ?int
{
    $v = filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v === false ? null : (int) $v;
}

function gerar_slug(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','î'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'item';
}

/**
 * Upload seguro. Retorna caminho relativo salvo, null (nenhum arquivo) ou lança RuntimeException.
 * $tipos: ['image/webp' => 'webp', ...]
 */
function salvar_upload(string $campo, string $pastaRel, string $nomeBase, array $tipos, int $maxBytes): ?string
{
    if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio do arquivo (código ' . (int) $f['error'] . ').');
    if ($f['size'] > $maxBytes) throw new RuntimeException('Arquivo maior que o permitido (' . round($maxBytes / 1048576) . ' MB).');
    if (!is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Envio inválido.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!isset($tipos[$mime])) throw new RuntimeException('Tipo de arquivo não permitido (' . h($mime) . ').');

    if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && !@getimagesize($f['tmp_name'])) {
        throw new RuntimeException('Imagem inválida.');
    }
    if ($mime === 'image/svg+xml') {
        $svg = (string) file_get_contents($f['tmp_name']);
        if (preg_match('/<[\w:]*script|<[\w:]*foreignObject|<[\w:]*(use|animate|set|iframe|embed|object)\b|\bon\w+\s*=|javascript|&#|<!ENTITY|<!DOCTYPE|xlink:href\s*=\s*["\']?\s*data:/i', $svg)) {
            throw new RuntimeException('SVG contém conteúdo não permitido (scripts ou eventos).');
        }
    }

    $pastaAbs = ROOT_PATH . '/' . trim($pastaRel, '/');
    if (!is_dir($pastaAbs) && !mkdir($pastaAbs, 0755, true)) throw new RuntimeException('Não foi possível criar a pasta de destino.');
    $nome = gerar_slug($nomeBase) . '-' . bin2hex(random_bytes(3)) . '.' . $tipos[$mime];
    if (!move_uploaded_file($f['tmp_name'], $pastaAbs . '/' . $nome)) throw new RuntimeException('Não foi possível salvar o arquivo.');
    return trim($pastaRel, '/') . '/' . $nome;
}

function status_badge(string $s): string
{
    return '<span class="badge badge--' . h($s) . '">' . h(STATUS_ROTULOS[$s] ?? $s) . '</span>';
}

function registrar_status(int $propostaId, ?string $de, string $para, ?string $obs = null): void
{
    db()->prepare('INSERT INTO propostas_historico (proposta_id, usuario_id, status_anterior, status_novo, observacao) VALUES (:p, :u, :de, :para, :obs)')
        ->execute(['p' => $propostaId, 'u' => admin_usuario()['id'] ?? null, 'de' => $de, 'para' => $para, 'obs' => $obs]);
}
