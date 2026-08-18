<?php
// ============================================================
// CONFIGURAÇÃO GERAL — Intranet v2.1
// ============================================================
define('APP_NAME',    'Intranet');
define('APP_VERSION', '2.1.0');

// ── BASE_URL ─────────────────────────────────────────────────
// Detecta automaticamente o host (IP/domínio) a partir da
// requisição atual, e usa o nome da pasta do projeto como path.
// Funciona em XAMPP Windows, Apache Linux, qualquer IP/porta.
//
// SE ainda houver problema de redirecionamento, descomente a
// linha abaixo e ajuste para o seu endereço:
// define('BASE_URL', 'http://10.10.254.49/intranet-acqua');
// ─────────────────────────────────────────────────────────────

(function () {
    // Protocolo
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';

    // Host real da requisição (inclui porta se diferente de 80/443)
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    // Nome da pasta do projeto = nome do diretório raiz do projeto
    // BASE_PATH ainda não está definido, usamos __FILE__
    // __FILE__ = C:\xampp\htdocs\intranet-acqua\includes\config.php
    // dirname(dirname(__FILE__)) = C:\xampp\htdocs\intranet-acqua
    // basename(...) = intranet-acqua
    $projectFolder = basename(dirname(dirname(__FILE__)));

    // Verificar se está na raiz do servidor (sem subdiretório)
    // usando SCRIPT_NAME: /intranet-acqua/index.php → começa com /intranet-acqua
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');

    if (str_starts_with(ltrim($scriptName, '/'), $projectFolder . '/') ||
        str_starts_with(ltrim($scriptName, '/'), $projectFolder . '\\')) {
        // Projeto está em subdiretório
        $sub = '/' . $projectFolder;
    } else {
        // Pode estar em subdiretório diferente — usar dirname do SCRIPT_NAME
        // e normalizar para achar a raiz do projeto
        $parts  = explode('/', trim($scriptName, '/'));
        $root   = $parts[0] ?? '';
        $sub    = $root ? '/' . $root : '';

        // Se o script está em admin/ ou api/, SCRIPT_NAME tem 2 níveis
        // Ex: /intranet-acqua/admin/index.php → partes[0]=intranet-acqua
        // Já está correto. Mas se estiver na raiz: /index.php → partes[0]=index.php
        if (str_contains($root, '.php')) {
            $sub = ''; // está na raiz do servidor
        }
    }

    define('BASE_URL', $scheme . '://' . $host . $sub);
})();

// ── BASE_PATH ────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));

// ── BANCO DE DADOS ───────────────────────────────────────────
// Dados de conexão ficam em arquivo próprio (não sobrescrito em
// futuras atualizações). Edite includes/db_config.php para
// alterar host/usuário/senha/nome do banco.
require_once __DIR__ . '/db_config.php';

// ── UPLOAD ───────────────────────────────────────────────────
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL  . '/uploads/');

// ── SESSÃO ───────────────────────────────────────────────────
define('SESSION_NAME',     'acqua_session');
define('SESSION_LIFETIME', 28800); // 8 horas

// ── AMBIENTE ─────────────────────────────────────────────────
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── SESSÃO SEGURA ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => str_starts_with(BASE_URL, 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('America/Belem');
