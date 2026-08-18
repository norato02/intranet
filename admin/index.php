<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireEditor(); // TI (admin) e Comunicação (editor)

$isDark = ($_SESSION['dark_mode'] ?? false) || (isset($_COOKIE['acqua_dark']) && $_COOKIE['acqua_dark'] === 'dark');

$stats = [
    'comunicados' => Database::count("SELECT COUNT(*) FROM posts WHERE type='comunicado' AND status='published'"),
    'noticias'    => Database::count("SELECT COUNT(*) FROM posts WHERE type='noticia' AND status='published'"),
    'rascunhos'   => Database::count("SELECT COUNT(*) FROM posts WHERE status='draft'"),
    'modulos'     => Database::count("SELECT COUNT(*) FROM modules WHERE active=1"),
];
$recentPosts = Database::fetchAll("SELECT p.*,u.name as author FROM posts p LEFT JOIN users u ON u.id=p.author_id ORDER BY p.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — <?= htmlspecialchars(getSetting('site_name','Intranet')) ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
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
    <a href="index.php" class="sidebar-link active"><span class="material-icons">dashboard</span> Dashboard</a>
    <a href="posts.php?type=comunicado" class="sidebar-link"><span class="material-icons">campaign</span> Comunicados</a>
    <a href="posts.php?type=noticia" class="sidebar-link"><span class="material-icons">newspaper</span> Notícias</a>
    <a href="posts.php?action=new" class="sidebar-link"><span class="material-icons">add_circle</span> Novo Post</a>
    <a href="categories.php" class="sidebar-link"><span class="material-icons">label</span> Categorias</a>
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
    <div style="margin-bottom:28px">
      <h1 style="font-size:22px;margin-bottom:4px">Dashboard</h1>
      <p class="text-muted text-sm">Bem-vindo, <?= htmlspecialchars($_SESSION['user_name']) ?> — <?= htmlspecialchars(ucfirst($_SESSION['user_role'])) ?> · <?= date('d/m/Y H:i') ?></p>
    </div>

    <!-- Stats -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,137,123,.12)"><span class="material-icons" style="color:var(--primary)">campaign</span></div>
        <div><div class="stat-value" data-count="<?= $stats['comunicados'] ?>"><?= $stats['comunicados'] ?></div><div class="stat-label">Comunicados publicados</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(38,166,154,.12)"><span class="material-icons" style="color:var(--accent)">newspaper</span></div>
        <div><div class="stat-value" data-count="<?= $stats['noticias'] ?>"><?= $stats['noticias'] ?></div><div class="stat-label">Notícias publicadas</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,193,7,.12)"><span class="material-icons" style="color:#d39e00">edit_note</span></div>
        <div><div class="stat-value" data-count="<?= $stats['rascunhos'] ?>"><?= $stats['rascunhos'] ?></div><div class="stat-label">Rascunhos</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(40,167,69,.12)"><span class="material-icons" style="color:#28a745">apps</span></div>
        <div><div class="stat-value" data-count="<?= $stats['modulos'] ?>"><?= $stats['modulos'] ?></div><div class="stat-label">Módulos ativos</div></div>
      </div>
    </div>

    <!-- Publicações recentes -->
    <div class="card mb-3">
      <div class="card-header">
        <span class="card-title">Publicações Recentes</span>
        <div style="display:flex;gap:8px">
          <a href="posts.php?type=comunicado" class="btn btn-ghost btn-sm">Comunicados</a>
          <a href="posts.php?type=noticia" class="btn btn-ghost btn-sm">Notícias</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Imagem</th><th>Título</th><th>Tipo</th><th>Status</th><th>Autor</th><th>Data</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recentPosts as $p): ?>
            <tr>
              <td>
                <?php if (!empty($p['cover_image']) && file_exists(UPLOAD_DIR . $p['cover_image'])): ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($p['cover_image']) ?>" style="width:56px;height:36px;object-fit:cover;border-radius:6px;display:block" alt="">
                <?php else: ?>
                <div style="width:56px;height:36px;background:var(--primary-xlight);border-radius:6px;display:flex;align-items:center;justify-content:center"><span class="material-icons" style="font-size:16px;color:var(--primary-light)">image</span></div>
                <?php endif; ?>
              </td>
              <td style="font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <a href="posts.php?action=edit&id=<?= $p['id'] ?>&type=<?= $p['type'] ?>"><?= htmlspecialchars($p['title']) ?></a>
              </td>
              <td><?php if ($p['type']==='comunicado'): ?><span class="badge badge-comunicado">Comunicado</span><?php else: ?><span class="badge badge-noticia">Notícia</span><?php endif; ?></td>
              <td>
                <?php $sc=['published'=>'badge-success','draft'=>'badge-warning','archived'=>'badge-info'][$p['status']]??'badge-info'; ?>
                <span class="badge <?= $sc ?>"><?= $p['status'] ?></span>
                <?php if ($p['is_featured']): ?> <span class="material-icons" style="font-size:14px;color:#ffc107;vertical-align:middle">star</span><?php endif; ?>
              </td>
              <td class="text-sm text-muted"><?= htmlspecialchars($p['author']) ?></td>
              <td class="text-sm text-muted"><?= formatDate($p['created_at']) ?></td>
              <td>
                <a href="posts.php?action=edit&id=<?= $p['id'] ?>&type=<?= $p['type'] ?>" class="btn btn-ghost btn-sm btn-icon"><span class="material-icons" style="font-size:18px">edit</span></a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentPosts)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted)">Nenhuma publicação ainda.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Ações rápidas -->
    <div class="card">
      <div class="card-header"><span class="card-title">Ações Rápidas</span></div>
      <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px">
        <a href="posts.php?action=new&type=comunicado" class="btn btn-primary"><span class="material-icons">campaign</span> Novo Comunicado</a>
        <a href="posts.php?action=new&type=noticia" class="btn btn-outline"><span class="material-icons">newspaper</span> Nova Notícia</a>
        <a href="modules.php?action=new&cat=sistema" class="btn btn-outline"><span class="material-icons">add_link</span> Novo Sistema</a>
        <a href="modules.php?action=new&cat=link_rapido" class="btn btn-ghost"><span class="material-icons">bolt</span> Novo Link Rápido</a>
        <?php if (isAdmin()): ?>
        <a href="users.php?action=new" class="btn btn-ghost"><span class="material-icons">person_add</span> Novo Usuário</a>
        <a href="settings.php" class="btn btn-ghost"><span class="material-icons">settings</span> Configurações</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
</body>
</html>
