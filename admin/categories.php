<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireEditor();

$action  = $_GET['action'] ?? 'list';
$id      = (int) ($_GET['id'] ?? 0);
$isDark  = ($_SESSION['dark_mode'] ?? false);
$success = $error = '';

if ($action === 'delete' && $id) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) $error = 'Token inválido.';
    else { Database::query('DELETE FROM categories WHERE id=?', [$id]); header('Location: categories.php?deleted=1'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $name  = sanitize($_POST['name'] ?? '');
        $type  = in_array($_POST['type']??'', ['comunicado','noticia']) ? $_POST['type'] : 'comunicado';
        $color = sanitize($_POST['color'] ?? '#00897B');
        if (!$name) { $error = 'Nome obrigatório.'; }
        else {
            $slug = uniqueSlug(generateSlug($name), 'categories', $id);
            if ($action === 'new') {
                Database::insert("INSERT INTO categories (name,slug,type,color) VALUES (?,?,?,?)", [$name,$slug,$type,$color]);
                header('Location: categories.php?saved=1'); exit;
            } else {
                Database::query("UPDATE categories SET name=?,slug=?,type=?,color=? WHERE id=?", [$name,$slug,$type,$color,$id]);
                $success = 'Categoria atualizada!';
            }
        }
    }
}

$cats = Database::fetchAll("SELECT c.*,(SELECT COUNT(*) FROM posts p WHERE p.category_id=c.id) as post_count FROM categories c ORDER BY c.type,c.name");
$cat  = $id ? Database::fetch('SELECT * FROM categories WHERE id=?', [$id]) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Categorias — Admin</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
</head>
<body class="fade-in">
<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon"><span class="material-icons">admin_panel_settings</span></div>
    <div class="brand-text">Admin <small>Categorias</small></div>
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
    <a href="categories.php" class="sidebar-link active"><span class="material-icons">label</span> Categorias</a>
    <div class="sidebar-label">Módulos</div>
    <a href="modules.php?cat=sistema" class="sidebar-link"><span class="material-icons">apps</span> Sistemas</a>
    <a href="modules.php?cat=link_rapido" class="sidebar-link"><span class="material-icons">bolt</span> Links Rápidos</a>
    <a href="nav.php" class="sidebar-link"><span class="material-icons">menu_open</span> Menu Nav</a>
    <?php if (isAdmin()): ?>
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link"><span class="material-icons">build</span> Sistema</a>
    <?php endif; ?>
  </aside>
  <main class="admin-main">
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Categoria excluída.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Categoria salva.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span><?= $success ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">
      <!-- Lista -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
          <h1 style="font-size:22px">Categorias</h1>
        </div>
        <div class="card">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Cor</th><th>Nome</th><th>Tipo</th><th>Posts</th><th>Slug</th><th>Ações</th></tr></thead>
              <tbody>
                <?php foreach ($cats as $c): ?>
                <tr>
                  <td><div style="width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($c['color']) ?>"></div></td>
                  <td style="font-weight:600"><?= htmlspecialchars($c['name']) ?></td>
                  <td><span class="badge badge-<?= $c['type'] ?>"><?= $c['type'] ?></span></td>
                  <td><?= $c['post_count'] ?></td>
                  <td class="text-muted text-sm" style="font-family:monospace"><?= htmlspecialchars($c['slug']) ?></td>
                  <td>
                    <a href="categories.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon"><span class="material-icons" style="font-size:18px">edit</span></a>
                    <a href="categories.php?action=delete&id=<?= $c['id'] ?>&csrf=<?= csrf() ?>" class="btn btn-ghost btn-sm btn-icon" style="color:#dc3545" data-confirm="Excluir categoria?"><span class="material-icons" style="font-size:18px">delete</span></a>
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
          <h2 style="font-size:16px;margin-bottom:16px"><?= $action==='edit'?'Editar Categoria':'Nova Categoria' ?></h2>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <div class="form-group">
              <label class="form-label">Nome *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($cat['name']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Tipo</label>
              <select name="type" class="form-control">
                <option value="comunicado" <?= ($cat['type']??'')==='comunicado'?'selected':'' ?>>Comunicado</option>
                <option value="noticia" <?= ($cat['type']??'')==='noticia'?'selected':'' ?>>Notícia</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Cor</label>
              <input type="color" name="color" value="<?= htmlspecialchars($cat['color']??'#00897B') ?>" style="width:100%;height:44px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;padding:2px">
            </div>
            <div style="display:flex;gap:10px">
              <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Salvar</button>
              <?php if ($action==='edit'): ?><a href="categories.php" class="btn btn-ghost">Cancelar</a><?php endif; ?>
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
