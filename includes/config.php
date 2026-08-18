<?php
// ============================================================
// CONFIGURAÃ‡ÃƒO GERAL â€” Intranet v2.1
// ============================================================
define('APP_NAME',    'Intranet');
define('APP_VERSION', '2.2.1');

// â”€â”€ BASE_URL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Detecta automaticamente o host (IP/domÃ­nio) a partir da
// requisiÃ§Ã£o atual, e usa o nome da pasta do projeto como path.
// Funciona em XAMPP Windows, Apache Linux, qualquer IP/porta.
//
// SE ainda houver problema de redirecionamento, descomente a
// linha abaixo e ajuste para o seu endereÃ§o:
// define('BASE_URL', 'http://10.10.254.49/intranet-acqua');
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

(function () {
    // Protocolo
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';

    // Host real da requisiÃ§Ã£o (inclui porta se diferente de 80/443)
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    // Nome da pasta do projeto = nome do diretÃ³rio raiz do projeto
    // BASE_PATH ainda nÃ£o estÃ¡ definido, usamos __FILE__
    // __FILE__ = C:\xampp\htdocs\intranet-acqua\includes\config.php
    // dirname(dirname(__FILE__)) = C:\xampp\htdocs\intranet-acqua
    // basename(...) = intranet-acqua
    $projectFolder = basename(dirname(dirname(__FILE__)));

    // Verificar se estÃ¡ na raiz do servidor (sem subdiretÃ³rio)
    // usando SCRIPT_NAME: /intranet-acqua/index.php â†’ comeÃ§a com /intranet-acqua
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');

    if (str_starts_with(ltrim($scriptName, '/'), $projectFolder . '/') ||
        str_starts_with(ltrim($scriptName, '/'), $projectFolder . '\\')) {
        // Projeto estÃ¡ em subdiretÃ³rio
        $sub = '/' . $projectFolder;
    } else {
        // Pode estar em subdiretÃ³rio diferente â€” usar dirname do SCRIPT_NAME
        // e normalizar para achar a raiz do projeto
        $parts  = explode('/', trim($scriptName, '/'));
        $root   = $parts[0] ?? '';
        $sub    = $root ? '/' . $root : '';

        // Se o script estÃ¡ em admin/ ou api/, SCRIPT_NAME tem 2 nÃ­veis
        // Ex: /intranet-acqua/admin/index.php â†’ partes[0]=intranet-acqua
        // JÃ¡ estÃ¡ correto. Mas se estiver na raiz: /index.php â†’ partes[0]=index.php
        if (str_contains($root, '.php')) {
            $sub = ''; // estÃ¡ na raiz do servidor
        }
    }

    define('BASE_URL', $scheme . '://' . $host . $sub);
})();

// â”€â”€ BASE_PATH â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define('BASE_PATH', dirname(__DIR__));

// â”€â”€ BANCO DE DADOS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Dados de conexÃ£o ficam em arquivo prÃ³prio (nÃ£o sobrescrito em
// futuras atualizaÃ§Ãµes). Edite includes/db_config.php para
// alterar host/usuÃ¡rio/senha/nome do banco.
require_once __DIR__ . '/db_config.php';

// â”€â”€ UPLOAD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL  . '/uploads/');

// â”€â”€ SESSÃƒO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define('SESSION_NAME',     'acqua_session');
define('SESSION_LIFETIME', 28800); // 8 horas

// â”€â”€ AMBIENTE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// â”€â”€ SESSÃƒO SEGURA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

