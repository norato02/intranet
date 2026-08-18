<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Toggle em sessão e cookie
$isDark = ($_SESSION['dark_mode'] ?? false);
$newDark = !$isDark;
$_SESSION['dark_mode'] = $newDark;

// Cookie por 30 dias
setcookie('acqua_dark', $newDark ? 'dark' : 'light', time() + (30 * 86400), '/', '', false, false);

// Persistir em banco se logado
if (isLoggedIn()) {
    Database::query('UPDATE users SET dark_mode=? WHERE id=?', [(int)$newDark, $_SESSION['user_id']]);
}

echo json_encode(['dark' => $newDark]);
