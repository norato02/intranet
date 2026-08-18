<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isEditor()) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$ids  = $data['ids'] ?? [];

foreach ($ids as $order => $id) {
    Database::query('UPDATE modules SET sort_order=? WHERE id=?', [(int)$order + 1, (int)$id]);
}

echo json_encode(['success' => true]);
