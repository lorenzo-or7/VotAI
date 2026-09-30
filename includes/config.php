<?php
/**
 * VOTAI — Configuração geral
 * ---------------------------------------------------------------
 * Ajuste as credenciais do banco abaixo. Em produção, prefira
 * definir variáveis de ambiente (VOTAI_DB_HOST, VOTAI_DB_NAME,
 * VOTAI_DB_USER, VOTAI_DB_PASS) em vez de escrever a senha aqui.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

// --- Banco de dados --------------------------------------------------------
define('DB_HOST', getenv('VOTAI_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('VOTAI_DB_PORT') ?: '3307');
define('DB_NAME', getenv('VOTAI_DB_NAME') ?: 'votai');
define('DB_USER', getenv('VOTAI_DB_USER') ?: 'root');
define('DB_PASS', getenv('VOTAI_DB_PASS') !== false ? (string) getenv('VOTAI_DB_PASS') : '');

// --- Marca (troque aqui se o nome mudar) -----------------------------------
define('SITE_NAME', 'Votai');
define('SITE_TAGLINE', 'Voto + AI');
define('SITE_DESCRIPTION', 'Escolha um problema do Brasil e veja o que cada candidatura propõe, com acesso ao documento oficial de origem.');

// --- Modo demonstração -----------------------------------------------------
// 'auto' = mostra o aviso "dados fictícios" só enquanto houver candidatos de demonstração.
// true/false força o aviso ligado/desligado.
define('DEMO_MODE', 'auto');

// --- Caminho base ----------------------------------------------------------
// Detectado automaticamente (funciona na raiz do domínio ou em subpasta,
// ex.: http://localhost/votai). Se precisar, defina manualmente: '/votai'
define('BASE_URL', (static function (): string {
    if (getenv('VOTAI_BASE_URL') !== false) {
        return rtrim((string) getenv('VOTAI_BASE_URL'), '/');
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root    = realpath(ROOT_PATH) ?: ROOT_PATH;
    if ($docRoot !== '' && str_starts_with($root, $docRoot)) {
        $rel = str_replace('\\', '/', substr($root, strlen($docRoot)));
        return rtrim($rel, '/');
    }
    return '';
})());

// --- Ambiente --------------------------------------------------------------
define('APP_DEBUG', getenv('VOTAI_DEBUG') === '1');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');
