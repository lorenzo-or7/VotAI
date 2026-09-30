<?php
/**
 * Conexão PDO (singleton). Sempre use prepared statements:
 *   $st = db()->prepare('SELECT ... WHERE id = :id');
 *   $st->execute(['id' => $id]);
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reais
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(503);
        error_log('[votai] Falha na conexão: ' . $e->getMessage());
        $msg = APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Verifique as credenciais em includes/config.php e se o banco foi importado (database/database.sql).';
        exit('<!doctype html><meta charset="utf-8"><title>Banco indisponível</title>'
            . '<body style="background:#060807;color:#ECEFE7;font:16px/1.5 system-ui;padding:10vh 8vw">'
            . '<p style="font:12px monospace;letter-spacing:.2em;color:#FFCB00">ERRO 503 · BANCO DE DADOS</p>'
            . '<h1 style="font-size:40px;margin:.2em 0">Não foi possível conectar ao banco.</h1><p>' . $msg . '</p></body>');
    }

    return $pdo;
}
