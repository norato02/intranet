<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$action  = $_GET['action'] ?? 'list';
$id      = (int) ($_GET['id'] ?? 0);
$isDark  = ($_SESSION['dark_mode'] ?? false);
$success = $error = '';

if ($action === 'delete' && $id) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) $error = 'Token inválido.';
    else { Database::query('DELETE FROM nav_items WHERE id=?', [$id]); header('Location: nav.php?deleted=1'); exit; }
}

if ($action === 'toggle' && $id) {
    Database::query('UPDATE nav_items SET active = NOT active WHERE id=?', [$id]);
    header('Location: nav.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $label    = sanitize($_POST['label'] ?? '');
        $url      = sanitize($_POST['url'] ?? '');
        $icon     = sanitize($_POST['icon'] ?? '');
        $parentId = (int) ($_POST['parent_id'] ?? 0) ?: null;
        $sortOrd  = (int) ($_POST['sort_order'] ?? 0);
        $newTab   = isset($_POST['open_new_tab']) ? 1 : 0;
        if (!$label) { $error = 'Rótulo obrigatório.'; }
        else {
            if ($action === 'new') {
                Database::insert("INSERT INTO nav_items (label,url,icon,parent_id,sort_order,open_new_tab) VALUES (?,?,?,?,?,?)",
                    [$label,$url,$icon,$parentId,$sortOrd,$newTab]);
                header('Location: nav.php?saved=1'); exit;
            } else {
                Database::query("UPDATE nav_items SET label=?,url=?,icon=?,parent_id=?,sort_order=?,open_new_tab=? WHERE id=?",
                    [$label,$url,$icon,$parentId,$sortOrd,$newTab,$id]);
                $success = 'Item atualizado!';
            }
        }
    }
}

$navItems = Database::fetchAll('SELECT * FROM nav_items ORDER BY sort_order');
$navItem  = $id ? Database::fetch('SELECT * FROM nav_items WHERE id=?', [$id]) : null;
$parents  = Database::fetchAll('SELECT * FROM nav_items WHERE parent_id IS NULL AND id != ? ORDER BY sort_order', [$id ?: 0]);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Menu Navegação — Admin</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
</head>
<body class="fade-in">
<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon"><span class="material-icons">admin_panel_settings</span></div>
    <div class="brand-text">Admin <small>Menu Nav</small></div>
  </a>
  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark?'on':'' ?>"></button>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm"><span class="material-icons">home</span></a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons">logout</span></a>
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
    <a href="nav.php" class="sidebar-link active"><span class="material-icons">menu_open</span> Menu Nav</a>
    <a href="ramais.php" class="sidebar-link"><span class="material-icons">phone_in_talk</span> Ramais</a>
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link"><span class="material-icons">build</span> Sistema</a>
  </aside>
  <main class="admin-main">
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Item removido.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Item salvo.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span><?= $success ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">
      <!-- Lista -->
      <div>
        <h1 style="font-size:22px;margin-bottom:20px">Itens do Menu de Navegação</h1>
        <div class="alert alert-info" style="margin-bottom:16px">
          <span class="material-icons">info</span>
          Os itens do menu são exibidos na barra de navegação principal. Reordene pelo campo "Ordem".
        </div>
        <div class="card">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Ícone</th><th>Rótulo</th><th>URL</th><th>Ordem</th><th>Ativo</th><th>Ações</th></tr></thead>
              <tbody>
                <?php foreach ($navItems as $item): ?>
                <tr style="<?= $item['parent_id']?'padding-left:20px;opacity:.85':'' ?>">
                  <td>
                    <?php if ($item['icon']): ?>
                    <span class="material-icons" style="font-size:20px;color:var(--primary)"><?= htmlspecialchars($item['icon']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td style="font-weight:600;<?= $item['parent_id']?'padding-left:20px':'' ?>">
                    <?= $item['parent_id'] ? '↳ ' : '' ?><?= htmlspecialchars($item['label']) ?>
                  </td>
                  <td class="text-sm text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($item['url']??'—') ?></td>
                  <td><?= $item['sort_order'] ?></td>
                  <td>
                    <a href="nav.php?action=toggle&id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm btn-icon">
                      <span class="material-icons" style="color:<?= $item['active']?'#28a745':'#dc3545' ?>"><?= $item['active']?'toggle_on':'toggle_off' ?></span>
                    </a>
                  </td>
                  <td>
                    <a href="nav.php?action=edit&id=<?= $item['id'] ?>" class="btn btn-ghost btn-sm btn-icon"><span class="material-icons" style="font-size:18px">edit</span></a>
                    <a href="nav.php?action=delete&id=<?= $item['id'] ?>&csrf=<?= csrf() ?>" class="btn btn-ghost btn-sm btn-icon" style="color:#dc3545" data-confirm="Remover item '<?= htmlspecialchars($item['label']) ?>'?"><span class="material-icons" style="font-size:18px">delete</span></a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div>
        <div class="card card-body">
          <h2 style="font-size:16px;margin-bottom:16px"><?= $action==='edit'?'Editar Item':'Novo Item do Menu' ?></h2>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <div class="form-group">
              <label class="form-label">Rótulo *</label>
              <input type="text" name="label" class="form-control" required value="<?= htmlspecialchars($navItem['label']??'') ?>" placeholder="Ex: Início, Comunicados...">
            </div>
            <div class="form-group">
              <label class="form-label">URL</label>
              <input type="text" name="url" class="form-control" value="<?= htmlspecialchars($navItem['url']??'') ?>" placeholder="index.php?page=comunicados">
            </div>
            <div class="form-group">
              <label class="form-label">Ícone (Material Icons)</label>
              <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars($navItem['icon']??'') ?>" placeholder="home, campaign, newspaper...">
            </div>
            <div class="form-group">
              <label class="form-label">Item pai (submenu)</label>
              <select name="parent_id" class="form-control">
                <option value="">— Nível raiz —</option>
                <?php foreach ($parents as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($navItem['parent_id']??0)==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Ordem</label>
              <input type="number" name="sort_order" class="form-control" value="<?= $navItem['sort_order']??0 ?>" min="0">
            </div>
            <label style="display:flex;align-items:center;gap:10px;margin-bottom:20px;cursor:pointer">
              <input type="checkbox" name="open_new_tab" value="1" <?= ($navItem['open_new_tab']??0)?'checked':'' ?>> Abrir em nova aba
            </label>
            <div style="display:flex;gap:10px">
              <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Salvar</button>
              <?php if ($action==='edit'): ?><a href="nav.php" class="btn btn-ghost">Cancelar</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
</body>
</html>
