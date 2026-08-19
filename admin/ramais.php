<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireEditor();
$isDark = ($_SESSION['dark_mode'] ?? false) || (isset($_COOKIE['acqua_dark']) && $_COOKIE['acqua_dark'] === 'dark');

function redirectWithMsg(string $url, string $msg, string $type = 'success'): void {
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}
$msg = ''; $msgType = 'success';
if (!empty($_SESSION['flash_msg'])) {
    $msg     = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) redirectWithMsg('ramais.php', 'Token inválido.', 'danger');
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) { Database::query('DELETE FROM ramais WHERE id = ?', [$id]); redirectWithMsg('ramais.php', 'Ramal excluído.', 'success'); }
    redirectWithMsg('ramais.php', 'ID inválido.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) redirectWithMsg('ramais.php', 'Token inválido.', 'danger');
    $ids = array_filter(array_map('intval', json_decode($_POST['ids'] ?? '[]', true)));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::query("DELETE FROM ramais WHERE id IN ($placeholders)", $ids);
        redirectWithMsg('ramais.php', count($ids) . ' ramal(is) excluído(s).', 'success');
    }
    redirectWithMsg('ramais.php', 'Nenhum item selecionado.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) redirectWithMsg('ramais.php', 'Token inválido.', 'danger');
    $id    = (int)($_POST['id'] ?? 0);
    $andar = trim($_POST['andar'] ?? '');
    $setor = trim($_POST['setor'] ?? '');
    $ramal = trim($_POST['ramal'] ?? '');
    $linha = trim($_POST['linha'] ?? '');
    if (!$andar || !$setor || !$ramal) redirectWithMsg('ramais.php' . ($id ? "?edit=$id" : ''), 'Preencha Andar, Setor e Ramal.', 'danger');
    $dupRow = Database::fetch('SELECT id FROM ramais WHERE ramal = ? AND id != ?', [$ramal, $id]);
    if ($dupRow) redirectWithMsg('ramais.php' . ($id ? "?edit=$id" : ''), "O ramal \"$ramal\" já está cadastrado.", 'danger');
    if ($id > 0) {
        Database::query('UPDATE ramais SET andar=?, setor=?, ramal=?, linha=?, updated_at=NOW() WHERE id=?', [$andar, $setor, $ramal, $linha, $id]);
        redirectWithMsg('ramais.php', 'Ramal atualizado.', 'success');
    } else {
        $maxOrder = Database::fetch('SELECT MAX(sort_order) as m FROM ramais')['m'] ?? 0;
        Database::query('INSERT INTO ramais (andar, setor, ramal, linha, sort_order) VALUES (?,?,?,?,?)', [$andar, $setor, $ramal, $linha, $maxOrder + 1]);
        redirectWithMsg('ramais.php', 'Ramal adicionado.', 'success');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf'] ?? '')) { echo json_encode(['ok' => false, 'error' => 'token']); exit; }
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (is_array($ids)) { foreach ($ids as $order => $id) Database::query('UPDATE ramais SET sort_order = ? WHERE id = ?', [$order + 1, (int)$id]); }
    echo json_encode(['ok' => true]); exit;
}

if (($_GET['action'] ?? '') === 'export_csv') {
    $all = Database::fetchAll('SELECT andar, setor, ramal, linha FROM ramais ORDER BY sort_order, andar, setor');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ramais_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Andar', 'Setor', 'Ramal', 'Linha Direta'], ';');
    foreach ($all as $row) fputcsv($out, [$row['andar'], $row['setor'], $row['ramal'], $row['linha']], ';');
    fclose($out); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf'] ?? '')) { echo json_encode(['ok' => false, 'error' => 'Token inválido.']); exit; }
    if (empty($_FILES['csv']['tmp_name'])) { echo json_encode(['ok' => false, 'error' => 'Nenhum arquivo enviado.']); exit; }
    $handle = fopen($_FILES['csv']['tmp_name'], 'r');
    fgetcsv($handle, 0, ';');
    $inserted = 0; $skipped = 0;
    $maxOrder = Database::fetch('SELECT MAX(sort_order) as m FROM ramais')['m'] ?? 0;
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (count($row) < 3) { $skipped++; continue; }
        [$andar, $setor, $ramal] = array_map('trim', $row);
        $linha = trim($row[3] ?? '');
        if (!$andar || !$setor || !$ramal) { $skipped++; continue; }
        if (Database::fetch('SELECT id FROM ramais WHERE ramal = ?', [$ramal])) { $skipped++; continue; }
        $maxOrder++;
        Database::query('INSERT INTO ramais (andar, setor, ramal, linha, sort_order) VALUES (?,?,?,?,?)', [$andar, $setor, $ramal, $linha, $maxOrder]);
        $inserted++;
    }
    fclose($handle);
    echo json_encode(['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped]); exit;
}

$busca  = trim($_GET['q'] ?? ''); $andarF = trim($_GET['andar'] ?? '');
$where  = 'WHERE 1=1'; $params = [];
if ($busca)  { $where .= ' AND (setor LIKE ? OR ramal LIKE ? OR linha LIKE ?)'; $params = ["%$busca%","%$busca%","%$busca%"]; }
if ($andarF) { $where .= ' AND andar = ?'; $params[] = $andarF; }
$ramais    = Database::fetchAll("SELECT * FROM ramais $where ORDER BY sort_order, andar, setor", $params);
$totalAll  = Database::fetch('SELECT COUNT(*) as c FROM ramais')['c'] ?? 0;
$andares   = Database::fetchAll('SELECT DISTINCT andar FROM ramais ORDER BY sort_order, andar');
$editRamal = isset($_GET['edit']) ? Database::fetch('SELECT * FROM ramais WHERE id = ?', [(int)$_GET['edit']]) : null;
$openNew   = ($msgType === 'danger' && !$editRamal && !isset($_GET['edit']));
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ramais — <?= htmlspecialchars(getSetting('site_name','Intranet')) ?></title>
  <meta name="csrf-reorder" content="<?= csrf() ?>">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
  <style>
    .toast-wrap{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
    .toast{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;font-size:14px;font-weight:500;min-width:260px;max-width:360px;box-shadow:0 4px 24px rgba(0,0,0,.18);pointer-events:all;animation:toastIn .28s cubic-bezier(.34,1.56,.64,1) both}
    .toast.success{background:#16a34a;color:#fff}.toast.danger{background:#dc2626;color:#fff}
    .toast .material-icons{font-size:18px;flex-shrink:0}
    .toast-close{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.7;line-height:1;padding:0;font-size:18px}
    @keyframes toastIn{from{opacity:0;transform:translateY(20px) scale(.95)}to{opacity:1;transform:none}}
    @keyframes toastOut{to{opacity:0;transform:translateY(20px) scale(.9)}}
    .sortable-ghost{opacity:.35;background:var(--primary-xlight) !important}
    .drag-handle{cursor:grab}.drag-handle:active{cursor:grabbing}
    #bulk-bar{display:none;position:sticky;top:0;z-index:50;background:var(--primary);color:#fff;padding:10px 18px;border-radius:var(--radius-sm);margin-bottom:12px;align-items:center;gap:14px;font-size:14px;font-weight:600}
    #bulk-bar.show{display:flex}
    .copy-btn{background:none;border:none;cursor:pointer;color:var(--text-muted);padding:2px 4px;border-radius:4px;vertical-align:middle;transition:color .15s}
    .copy-btn:hover{color:var(--primary)}.copy-btn .material-icons{font-size:15px;vertical-align:middle}
    #drop-zone{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:20px;text-align:center;font-size:13px;color:var(--text-muted);cursor:pointer;transition:border-color .2s}
    #drop-zone.dragover{border-color:var(--primary);background:var(--primary-xlight)}
    #drop-zone input{display:none}
  </style>
</head>
<body class="fade-in">
<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon" style="background:linear-gradient(135deg,var(--primary-dark),var(--accent2))"><span class="material-icons">admin_panel_settings</span></div>
    <div class="brand-text">Admin <small>Painel Administrativo</small></div>
  </a>
  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark?'on':'' ?>"></button>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm"><span class="material-icons">home</span> Intranet</a>
    <a href="<?= BASE_URL ?>/public.php" class="btn btn-ghost btn-sm" target="_blank"><span class="material-icons">public</span> Público</a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons">logout</span> Sair</a>
    <button class="btn btn-ghost btn-icon mobile-menu-btn" id="sidebarToggle"><span class="material-icons">menu</span></button>
  </div>
</nav>
<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-label">Conteúdo</div>
    <a href="index.php" class="sidebar-link"><span class="material-icons">dashboard</span> Dashboard</a>
    <a href="posts.php?type=comunicado" class="sidebar-link"><span class="material-icons">campaign</span> Comunicados</a>
    <a href="posts.php?type=noticia" class="sidebar-link"><span class="material-icons">newspaper</span> Notícias</a>
    <a href="posts.php?action=new" class="sidebar-link"><span class="material-icons">add_circle</span> Novo Post</a>
    <a href="categories.php" class="sidebar-link"><span class="material-icons">label</span> Categorias</a>
    <div class="sidebar-label">Módulos</div>
    <a href="modules.php?cat=sistema" class="sidebar-link"><span class="material-icons">apps</span> Sistemas</a>
    <a href="modules.php?cat=link_rapido" class="sidebar-link"><span class="material-icons">bolt</span> Links Rápidos</a>
    <a href="nav.php" class="sidebar-link"><span class="material-icons">menu_open</span> Menu Nav</a>
    <a href="ramais.php" class="sidebar-link active"><span class="material-icons">phone_in_talk</span> Ramais</a>
    <?php if (isAdmin()): ?>
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link"><span class="material-icons">build</span> Sistema</a>
    <?php endif; ?>
  </aside>
  <main class="admin-main">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px">
      <div>
        <h1 style="font-size:22px;margin-bottom:4px">Ramais</h1>
        <p class="text-muted text-sm">
          <?= count($ramais) ?>
          <?php if ($busca || $andarF): ?>de <?= $totalAll ?> ramal(is) encontrado(s)<?php else: ?>ramal(is) cadastrado(s)<?php endif; ?>
        </p>
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span id="reorder-msg" style="font-size:13px"></span>
        <button id="reorder-save" style="display:none;align-items:center;gap:6px" class="btn btn-ghost btn-sm">💾 Salvar ordem</button>
        <a href="ramais.php?action=export_csv" class="btn btn-ghost btn-sm"><span class="material-icons" style="font-size:16px">download</span> Exportar</a>
        <button onclick="openModal('modalImport')" class="btn btn-ghost btn-sm"><span class="material-icons" style="font-size:16px">upload</span> Importar</button>
        <button onclick="openModal('modalNovo')" class="btn btn-primary"><span class="material-icons">add</span> Novo Ramal</button>
      </div>
    </div>

    <div class="card" style="padding:14px 18px;margin-bottom:12px">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <div style="flex:1;min-width:200px;position:relative">
          <span class="material-icons" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--primary);font-size:17px">search</span>
          <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar setor, ramal ou linha…" style="width:100%;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;background:var(--bg);color:var(--text);outline:none">
        </div>
        <select name="andar" style="padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;background:var(--bg);color:var(--text);outline:none;min-width:150px">
          <option value="">Todos os andares</option>
          <?php foreach ($andares as $a): ?>
          <option value="<?= htmlspecialchars($a['andar']) ?>" <?= $andarF===$a['andar']?'selected':'' ?>><?= htmlspecialchars($a['andar']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($busca || $andarF): ?><a href="ramais.php" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
      </form>
    </div>

    <div id="bulk-bar">
      <span class="material-icons">check_box</span>
      <span id="bulk-count">0 selecionado(s)</span>
      <form method="POST" id="bulk-form" onsubmit="return confirmBulk()">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="bulk_delete">
        <input type="hidden" name="ids" id="bulk-ids">
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4)">
          <span class="material-icons" style="font-size:15px">delete</span> Excluir selecionados
        </button>
      </form>
      <button onclick="clearSelection()" class="btn btn-ghost btn-sm" style="color:#fff;margin-left:auto">Cancelar</button>
    </div>

    <div class="card" style="overflow:hidden;padding:0">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse" id="ramais-table">
          <thead><tr style="background:var(--primary)">
            <th style="padding:12px 10px;text-align:center;width:36px"><input type="checkbox" id="check-all" style="cursor:pointer;accent-color:#fff"></th>
            <th style="padding:12px 10px;text-align:center;width:36px"><span class="material-icons" style="font-size:16px;opacity:.7;color:#fff">drag_indicator</span></th>
            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff">Andar</th>
            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff">Setor</th>
            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff">Ramal</th>
            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff">Linha</th>
            <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff">Ações</th>
          </tr></thead>
          <tbody>
          <?php foreach ($ramais as $r): ?>
          <tr data-id="<?= $r['id'] ?>" style="border-bottom:1px solid var(--border)" onmouseover="this.style.background='var(--primary-xlight)'" onmouseout="this.style.background=''">
            <td style="padding:11px 10px;text-align:center"><input type="checkbox" class="row-check" data-id="<?= $r['id'] ?>" style="cursor:pointer;accent-color:var(--primary)"></td>
            <td class="drag-handle" style="padding:11px 10px;text-align:center;color:var(--text-muted)"><span class="material-icons" style="font-size:20px;vertical-align:middle;opacity:.5">drag_indicator</span></td>
            <td style="padding:11px 16px"><span style="background:var(--primary-xlight);color:var(--primary);padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700"><?= htmlspecialchars($r['andar']) ?></span></td>
            <td style="padding:11px 16px;font-size:14px;font-weight:500;color:var(--text)"><?= htmlspecialchars($r['setor']) ?></td>
            <td style="padding:11px 16px">
              <span style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:3px 10px;border-radius:20px;font-size:13px;font-weight:700"><?= htmlspecialchars($r['ramal']) ?></span>
              <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($r['ramal']) ?>',this)" title="Copiar"><span class="material-icons">content_copy</span></button>
            </td>
            <td style="padding:11px 16px;font-size:13px;color:var(--text-muted)">
              <?php if ($r['linha']): ?><?= htmlspecialchars($r['linha']) ?><button class="copy-btn" onclick="copyText('<?= htmlspecialchars($r['linha']) ?>',this)" title="Copiar"><span class="material-icons">content_copy</span></button><?php else: ?>—<?php endif; ?>
            </td>
            <td style="padding:11px 16px;text-align:center">
              <div style="display:flex;gap:8px;justify-content:center">
                <a href="ramais.php?edit=<?= $r['id'] ?>" class="btn btn-ghost btn-sm"><span class="material-icons" style="font-size:16px">edit</span></a>
                <form method="POST" onsubmit="return confirm('Excluir este ramal?')" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons" style="font-size:16px">delete</span></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($ramais)): ?>
          <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--text-muted)">Nenhum ramal encontrado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div id="modalNovo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:480px;padding:28px;margin:20px" onclick="event.stopPropagation()">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h2 style="font-size:18px;font-weight:700">Novo Ramal</h2>
      <button onclick="closeModal('modalNovo')" style="background:none;border:none;cursor:pointer;color:var(--text-muted)"><span class="material-icons">close</span></button>
    </div>
    <form method="POST" action="ramais.php">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="0">
      <div class="form-group"><label class="form-label">Andar *</label><input type="text" name="andar" class="form-control" placeholder="Ex: Térreo, 1° Andar…" list="andares-list" required><datalist id="andares-list"><?php foreach ($andares as $a): ?><option value="<?= htmlspecialchars($a['andar']) ?>"><?php endforeach; ?></datalist></div>
      <div class="form-group"><label class="form-label">Setor *</label><input type="text" name="setor" class="form-control" placeholder="Nome do setor" required></div>
      <div class="form-group"><label class="form-label">Ramal *</label><input type="text" name="ramal" class="form-control" placeholder="Ex: 232" required></div>
      <div class="form-group"><label class="form-label">Linha Direta</label><input type="text" name="linha" class="form-control phone-mask" placeholder="Ex: (91) 3197-6328"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" onclick="closeModal('modalNovo')" class="btn btn-ghost">Cancelar</button>
        <button type="submit" class="btn btn-primary"><span class="material-icons" style="font-size:16px">save</span> Salvar</button>
      </div>
    </form>
  </div>
</div>

<?php if ($editRamal): ?>
<div id="modalEditar" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:480px;padding:28px;margin:20px" onclick="event.stopPropagation()">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h2 style="font-size:18px;font-weight:700">Editar Ramal</h2>
      <a href="ramais.php" style="color:var(--text-muted)"><span class="material-icons">close</span></a>
    </div>
    <form method="POST" action="ramais.php">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editRamal['id'] ?>">
      <div class="form-group"><label class="form-label">Andar *</label><input type="text" name="andar" class="form-control" value="<?= htmlspecialchars($editRamal['andar']) ?>" list="andares-list2" required><datalist id="andares-list2"><?php foreach ($andares as $a): ?><option value="<?= htmlspecialchars($a['andar']) ?>"><?php endforeach; ?></datalist></div>
      <div class="form-group"><label class="form-label">Setor *</label><input type="text" name="setor" class="form-control" value="<?= htmlspecialchars($editRamal['setor']) ?>" required></div>
      <div class="form-group"><label class="form-label">Ramal *</label><input type="text" name="ramal" class="form-control" value="<?= htmlspecialchars($editRamal['ramal']) ?>" required></div>
      <div class="form-group"><label class="form-label">Linha Direta</label><input type="text" name="linha" class="form-control phone-mask" value="<?= htmlspecialchars($editRamal['linha']) ?>" placeholder="Ex: (91) 3197-6328"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <a href="ramais.php" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary"><span class="material-icons" style="font-size:16px">save</span> Salvar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div id="modalImport" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:480px;padding:28px;margin:20px" onclick="event.stopPropagation()">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h2 style="font-size:18px;font-weight:700">Importar CSV</h2>
      <button onclick="closeModal('modalImport')" style="background:none;border:none;cursor:pointer;color:var(--text-muted)"><span class="material-icons">close</span></button>
    </div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">Colunas separadas por <strong>;</strong> na ordem: <code>Andar ; Setor ; Ramal ; Linha Direta</code>. Primeira linha (cabeçalho) é ignorada. Ramais duplicados são pulados.</p>
    <div id="drop-zone" onclick="document.getElementById('csv-file').click()">
      <span class="material-icons" style="font-size:32px;opacity:.4;display:block;margin-bottom:8px">upload_file</span>
      <span id="drop-label">Clique ou arraste o arquivo .csv aqui</span>
      <input type="file" id="csv-file" accept=".csv,text/csv">
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button type="button" onclick="closeModal('modalImport')" class="btn btn-ghost">Cancelar</button>
      <button type="button" id="import-btn" class="btn btn-primary" disabled><span class="material-icons" style="font-size:16px">upload</span> Importar</button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toast-wrap"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
<?php if ($msg): ?>showToast(<?= json_encode($msg) ?>,<?= json_encode($msgType) ?>);<?php endif; ?>
function showToast(text,type,duration){type=type||'success';duration=duration===undefined?4000:duration;var wrap=document.getElementById('toast-wrap');var icons={success:'check_circle',danger:'error'};var t=document.createElement('div');t.className='toast '+type;t.innerHTML='<span class="material-icons">'+(icons[type]||'info')+'</span><span>'+text+'</span><button class="toast-close" onclick="dismissToast(this.parentElement)"><span class="material-icons">close</span></button>';wrap.appendChild(t);if(duration)setTimeout(function(){dismissToast(t);},duration);}
function dismissToast(el){if(!el||!el.parentElement)return;el.style.animation='toastOut .25s ease forwards';setTimeout(function(){el.remove();},260);}
function openModal(id){var m=document.getElementById(id);if(m)m.style.display='flex';}
function closeModal(id){var m=document.getElementById(id);if(m)m.style.display='none';}
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('[id^="modal"]:not(#modalEditar)').forEach(function(m){if(m.style.display==='flex')m.style.display='none';});});
document.querySelectorAll('[id^="modal"]').forEach(function(bd){bd.addEventListener('click',function(e){if(e.target===bd&&bd.id!=='modalEditar')bd.style.display='none';});});
<?php if ($openNew): ?>openModal('modalNovo');<?php endif; ?>
function copyText(text,btn){navigator.clipboard.writeText(text).then(function(){var icon=btn.querySelector('.material-icons');icon.textContent='check';btn.style.color='#16a34a';setTimeout(function(){icon.textContent='content_copy';btn.style.color='';},1500);}).catch(function(){showToast('Não foi possível copiar.','danger');});}
document.querySelectorAll('.phone-mask').forEach(function(input){input.addEventListener('input',function(){var v=this.value.replace(/\D/g,'').slice(0,11);if(v.length<=2)this.value=v.length?'('+v:'';else if(v.length<=6)this.value='('+v.slice(0,2)+') '+v.slice(2);else if(v.length<=10)this.value='('+v.slice(0,2)+') '+v.slice(2,6)+'-'+v.slice(6);else this.value='('+v.slice(0,2)+') '+v.slice(2,7)+'-'+v.slice(7);});});
(function(){var tbody=document.querySelector('#ramais-table tbody');var saveBtn=document.getElementById('reorder-save');var saveMsg=document.getElementById('reorder-msg');var csrf=document.querySelector('meta[name="csrf-reorder"]').content;var changed=false;if(!tbody)return;Sortable.create(tbody,{handle:'.drag-handle',animation:150,ghostClass:'sortable-ghost',onEnd:function(){changed=true;saveBtn.style.display='inline-flex';saveMsg.textContent='';}});saveBtn.addEventListener('click',function(){var ids=Array.from(tbody.querySelectorAll('tr[data-id]')).map(function(tr){return tr.dataset.id;});saveBtn.disabled=true;saveBtn.textContent='Salvando…';fetch('ramais.php',{method:'POST',body:new URLSearchParams({action:'reorder',csrf:csrf,ids:JSON.stringify(ids)})}).then(function(r){return r.json();}).then(function(d){if(d.ok){showToast('Ordem salva!','success');saveBtn.style.display='none';saveMsg.textContent='';changed=false;}else{showToast('Erro ao salvar ordem.','danger');}}).catch(function(){showToast('Erro de conexão.','danger');}).finally(function(){saveBtn.disabled=false;saveBtn.textContent='💾 Salvar ordem';});});window.addEventListener('beforeunload',function(e){if(changed){e.preventDefault();e.returnValue='';}});})();
var bulkBar=document.getElementById('bulk-bar');var bulkCount=document.getElementById('bulk-count');var bulkIds=document.getElementById('bulk-ids');var checkAll=document.getElementById('check-all');
function updateBulkBar(){var checked=document.querySelectorAll('.row-check:checked');var n=checked.length;if(n>0){bulkBar.classList.add('show');bulkCount.textContent=n+' selecionado(s)';bulkIds.value=JSON.stringify(Array.from(checked).map(function(c){return c.dataset.id;}));}else{bulkBar.classList.remove('show');}checkAll.indeterminate=n>0&&n<document.querySelectorAll('.row-check').length;checkAll.checked=n===document.querySelectorAll('.row-check').length&&n>0;}
function clearSelection(){document.querySelectorAll('.row-check').forEach(function(c){c.checked=false;});checkAll.checked=false;updateBulkBar();}
function confirmBulk(){var n=document.querySelectorAll('.row-check:checked').length;return confirm('Excluir '+n+' ramal(is) selecionado(s)? Esta ação não pode ser desfeita.');}
document.querySelectorAll('.row-check').forEach(function(c){c.addEventListener('change',updateBulkBar);});
checkAll.addEventListener('change',function(){document.querySelectorAll('.row-check').forEach(function(c){c.checked=checkAll.checked;});updateBulkBar();});
(function(){var dropZone=document.getElementById('drop-zone');var fileInput=document.getElementById('csv-file');var importBtn=document.getElementById('import-btn');var dropLabel=document.getElementById('drop-label');var csrf=document.querySelector('meta[name="csrf-reorder"]').content;var selectedFile=null;function setFile(file){if(!file)return;selectedFile=file;dropLabel.textContent=file.name;importBtn.disabled=false;}fileInput.addEventListener('change',function(){setFile(this.files[0]);});dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('dragover');});dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('dragover');});dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('dragover');setFile(e.dataTransfer.files[0]);});importBtn.addEventListener('click',function(){if(!selectedFile)return;var fd=new FormData();fd.append('action','import_csv');fd.append('csrf',csrf);fd.append('csv',selectedFile);importBtn.disabled=true;importBtn.textContent='Importando…';fetch('ramais.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.ok){closeModal('modalImport');showToast(d.inserted+' ramal(is) importado(s). '+(d.skipped?d.skipped+' pulado(s).':''),'success',6000);setTimeout(function(){location.reload();},1500);}else{showToast(d.error||'Erro ao importar.','danger');}}).catch(function(){showToast('Erro de conexão.','danger');}).finally(function(){importBtn.disabled=false;importBtn.innerHTML='<span class="material-icons" style="font-size:16px">upload</span> Importar';});});})();
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
</body>
</html>
