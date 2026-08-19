<?php

/**
 * Constrói caminho absoluto no disco, compatível com Windows e Linux.
 * Evita caminhos mistos como C:\uploads/modules/file.svg
 */
function uploadPath(string ...$parts): string {
    $joined = UPLOAD_DIR . implode(DIRECTORY_SEPARATOR, array_map(
        fn($p) => trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p), DIRECTORY_SEPARATOR),
        $parts
    ));
    return $joined;
}

// ============================================================
// AUTENTICAÇÃO E PERMISSÕES
// ============================================================

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function isEditor(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'] ?? '', ['admin', 'editor']);
}

function requireLogin(string $redirect = 'login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/' . $redirect);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/index.php?error=forbidden');
        exit;
    }
}

function requireEditor(): void {
    requireLogin();
    if (!isEditor()) {
        header('Location: ' . BASE_URL . '/index.php?error=forbidden');
        exit;
    }
}

function login(string $email, string $password): array {
    $user = Database::fetch('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'E-mail ou senha inválidos.'];
    }
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['user_sector'] = $user['sector'];
    $_SESSION['dark_mode']   = (bool) $user['dark_mode'];
    $_SESSION['login_time']  = time();
    Database::query('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    return ['success' => true, 'user' => $user];
}

function logout(): void {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return Database::fetch('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateSlug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(['ã','â','á','à','ä','Ã','Â','Á','À','Ä'], 'a', $text);
    $text = str_replace(['ê','é','è','ë','Ê','É','È','Ë'], 'e', $text);
    $text = str_replace(['î','í','ì','ï','Î','Í','Ì','Ï'], 'i', $text);
    $text = str_replace(['õ','ô','ó','ò','ö','Õ','Ô','Ó','Ò','Ö'], 'o', $text);
    $text = str_replace(['û','ú','ù','ü','Û','Ú','Ù','Ü'], 'u', $text);
    $text = str_replace(['ç','Ç'], 'c', $text);
    $text = str_replace(['ñ','Ñ'], 'n', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function uniqueSlug(string $slug, string $table, int $excludeId = 0): string {
    $original = $slug;
    $i = 1;
    while (true) {
        $exists = Database::count(
            "SELECT COUNT(*) FROM $table WHERE slug = ? AND id != ?",
            [$slug, $excludeId]
        );
        if (!$exists) break;
        $slug = $original . '-' . $i++;
    }
    return $slug;
}

function getSetting(string $key, string $default = ''): string {
    $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
    if (!$row) return $default;
    $val = $row['setting_value'] ?? '';
    // Retorna o default se o valor salvo for string vazia
    return ($val !== '' && $val !== null) ? $val : $default;
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function formatDate(string $date, string $format = 'd/m/Y'): string {
    return date($format, strtotime($date));
}

function timeAgo(string $datetime): string {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'agora mesmo';
    if ($time < 3600) return floor($time/60) . ' min atrás';
    if ($time < 86400) return floor($time/3600) . 'h atrás';
    if ($time < 2592000) return floor($time/86400) . 'd atrás';
    return date('d/m/Y', strtotime($datetime));
}

/**
 * Versão do arquivo de asset (CSS/JS) baseada na data de modificação,
 * pra usar como ?v= e forçar o navegador a buscar a versão nova sempre
 * que o arquivo mudar — sem precisar lembrar de atualizar um número na mão.
 */
function assetVersion(string $relativePath): string {
    $full = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $mtime = @filemtime($full);
    return $mtime ? (string) $mtime : APP_VERSION;
}

/**
 * URLs de nav_items são salvas relativas à raiz do site (ex: "index.php?page=ramais").
 * Prefixa com BASE_URL para funcionar em páginas fora da raiz (ex: pages/rh.php),
 * sem mexer em links absolutos, âncoras ou javascript:void(0).
 */
function navUrl(string $url): string {
    if ($url === '' || preg_match('~^(https?://|//|#|javascript:|mailto:)~i', $url)) {
        return $url;
    }
    return BASE_URL . '/' . ltrim($url, '/');
}

/**
 * Retorna um bloco <style> inline com as variáveis CSS de cor
 * lidas do banco de dados. Injete no <head> APÓS o style.css.
 * Não usa header(), não conflita com session_start().
 */
function getColorVarsStyle(): string {
    $primary   = getSetting('primary_color',   '#00897B');
    $secondary = getSetting('secondary_color', '#004D40');

    // Validar hex
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary))   $primary   = '#00897B';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondary)) $secondary = '#004D40';

    // Calcular variações
    $shift = function(string $hex, int $amt): string {
        $r = min(255, max(0, hexdec(substr($hex,1,2)) + $amt));
        $g = min(255, max(0, hexdec(substr($hex,3,2)) + $amt));
        $b = min(255, max(0, hexdec(substr($hex,5,2)) + $amt));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    };

    $primaryDark  = $shift($primary, -20);
    $primaryLight = $shift($primary,  40);
    $xlR = min(255, hexdec(substr($primary,1,2)) + 180);
    $xlG = min(255, hexdec(substr($primary,3,2)) + 180);
    $xlB = min(255, hexdec(substr($primary,5,2)) + 180);
    $primaryXlight = sprintf('#%02x%02x%02x', $xlR, $xlG, $xlB);
    $accent  = $shift($primary, 20);
    $pR = hexdec(substr($primary,1,2));
    $pG = hexdec(substr($primary,3,2));
    $pB = hexdec(substr($primary,5,2));

    return "<style>:root{"
        . "--primary:{$primary};"
        . "--primary-dark:{$primaryDark};"
        . "--primary-light:{$primaryLight};"
        . "--primary-xlight:{$primaryXlight};"
        . "--accent:{$accent};"
        . "--accent2:{$secondary};"
        . "}"
        . "[data-theme=\"dark\"]{--primary-xlight:rgba({$pR},{$pG},{$pB},0.15);}"
        . "</style>";
}

/**
 * Upload de ícone para módulos.
 * Suporta: SVG, ICO, PNG, JPG, GIF, WEBP, BMP — sem processamento GD para SVG/ICO.
 */
function uploadIconFile(array $file, string $subfolder): array {
    // Detectar extensão pelo nome do arquivo — mais confiável que mime para ICO/SVG
    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Mapa extensão → ext de saída
    $extMap = [
        'svg'  => 'svg',
        'ico'  => 'ico',
        'cur'  => 'ico',
        'png'  => 'png',
        'jpg'  => 'jpg',
        'jpeg' => 'jpg',
        'gif'  => 'gif',
        'webp' => 'webp',
        'bmp'  => 'png',
    ];

    // Formatos que salvamos diretamente (sem GD)
    $rawFormats = ['svg', 'ico'];

    if (!isset($extMap[$origExt])) {
        // Fallback: tentar por mime
        $mime = mime_content_type($file['tmp_name']);
        $mimeMap = [
            'image/svg+xml'            => 'svg',
            'image/x-icon'             => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'application/octet-stream' => 'ico', // Linux frequentemente retorna isso para .ico
            'text/html'                => 'svg', // SVGs com <?xml são detectados como text/html em alguns sistemas
            'text/xml'                 => 'svg', // SVGs com <?xml em outros
            'application/xml'          => 'svg',
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($mimeMap[$mime])) {
            return ['success' => false, 'message' => "Formato não suportado: .{$origExt} ({$mime}). Use SVG, ICO, PNG, JPG ou WEBP."];
        }
        $outExt = $mimeMap[$mime];
    } else {
        $outExt = $extMap[$origExt];
    }

    // Normalizar separador para evitar caminhos mistos no Windows
    $dir = rtrim(str_replace('\\', '/', UPLOAD_DIR), '/') . '/' . $subfolder . '/';
    $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir); // converter de volta para o OS
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = 'img_' . uniqid('', true) . '.' . $outExt;
    $dest  = $dir . $fname;

    // SVG e ICO: mover direto sem passar pelo GD
    if (in_array($outExt, $rawFormats)) {
        // Mover PRIMEIRO (evita lock de arquivo no Windows)
        // No Windows, file_get_contents() antes do move_uploaded_file pode bloquear o arquivo temporário
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            // Fallback: tentar copy + unlink (funciona em mais contextos no Windows)
            if (!@copy($file['tmp_name'], $dest)) {
                return ['success' => false, 'message' => 'Erro ao salvar arquivo. Verifique as permissões da pasta uploads/modules/.'];
            }
            @unlink($file['tmp_name']);
        }
        // Validar SVG APÓS mover (sem risco de lock)
        if ($outExt === 'svg') {
            $svgContent = @file_get_contents($dest);
            if ($svgContent === false || stripos($svgContent, '<svg') === false) {
                @unlink($dest);
                return ['success' => false, 'message' => 'Arquivo SVG inválido. Verifique se o arquivo é um SVG válido.'];
            }
        }
        return ['success' => true, 'filename' => $subfolder . '/' . $fname, 'message' => ''];
    }

    // PNG/JPG/GIF/WEBP: processar pelo GD normalmente
    return uploadImage($file, $subfolder);
}

