<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$action  = $_GET['action'] ?? 'list';
$id      = (int) ($_GET['id'] ?? 0);
$isDark  = ($_SESSION['dark_mode'] ?? false);
$success = $error = '';

// DELETE
if ($action === 'delete' && $id) {
    if ($id === (int)$_SESSION['user_id']) { $error = 'Você não pode excluir seu próprio usuário.'; $action = 'list'; }
    elseif (!verifyCsrf($_GET['csrf'] ?? '')) { $error = 'Token inválido.'; $action = 'list'; }
    else { Database::query('DELETE FROM users WHERE id=?', [$id]); header('Location: users.php?deleted=1'); exit; }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new','edit'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $name   = sanitize($_POST['name'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $role   = in_array($_POST['role']??'', ['admin','editor','user']) ? $_POST['role'] : 'user';
        $sector = sanitize($_POST['sector'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $pwd    = trim($_POST['password'] ?? '');

        if (!$name || !$email) { $error = 'Nome e e-mail são obrigatórios.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'E-mail inválido.'; }

        if (!$error) {
            if ($action === 'new') {
                if (!$pwd) { $error = 'Senha obrigatória para novos usuários.'; }
                else {
                    $exists = Database::count('SELECT COUNT(*) FROM users WHERE email=?', [$email]);
                    if ($exists) { $error = 'E-mail já cadastrado.'; }
                    else {
                        Database::insert("INSERT INTO users (name,email,password,role,sector,active) VALUES (?,?,?,?,?,?)",
                            [$name,$email,hashPassword($pwd),$role,$sector,$active]);
                        header('Location: users.php?saved=1'); exit;
                    }
                }
            } else {
                $params = [$name,$email,$role,$sector,$active];
                $sql = "UPDATE users SET name=?,email=?,role=?,sector=?,active=?";
                if ($pwd) { $sql .= ',password=?'; $params[] = hashPassword($pwd); }
                $sql .= ' WHERE id=?'; $params[] = $id;
                Database::query($sql, $params);
                $success = 'Usuário atualizado!';
            }
        }
    }
}

$users = Database::fetchAll('SELECT * FROM users ORDER BY name');
$user  = $id ? Database::fetch('SELECT * FROM users WHERE id=?', [$id]) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Usuários — Admin</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
</head>
<body class="fade-in">
<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon"><span class="material-icons">admin_panel_settings</span></div>
    <div class="brand-text">Admin <small>Usuários</small></div>
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
    <div class="sidebar-label">Módulos</div>
    <a href="modules.php?cat=sistema" class="sidebar-link"><span class="material-icons">apps</span> Sistemas</a>
    <a href="modules.php?cat=link_rapido" class="sidebar-link"><span class="material-icons">bolt</span> Links Rápidos</a>
    <a href="nav.php" class="sidebar-link"><span class="material-icons">menu_open</span> Menu Nav</a>
    <a href="ramais.php" class="sidebar-link"><span class="material-icons">phone_in_talk</span> Ramais</a>
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link active"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link"><span class="material-icons">build</span> Sistema</a>
  </aside>
  <main class="admin-main">

  <?php if ($action === 'list'): ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Usuário excluído.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Usuário salvo.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= $error ?></div><?php endif; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h1 style="font-size:22px">Usuários</h1>
      <a href="users.php?action=new" class="btn btn-primary"><span class="material-icons">person_add</span> Novo Usuário</a>
    </div>
    <div class="card">
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Nome</th><th>E-mail</th><th>Setor</th><th>Nível</th><th>Ativo</th><th>Último acesso</th><th>Ações</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="avatar-xs"><?= mb_strtoupper(mb_substr($u['name'],0,1)) ?></div>
                  <span style="font-weight:600"><?= htmlspecialchars($u['name']) ?></span>
                </div>
              </td>
              <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
              <td class="text-sm text-muted"><?= htmlspecialchars($u['sector']??'—') ?></td>
              <td>
                <?php $rc = ['admin'=>'badge-danger','editor'=>'badge-warning','user'=>'badge-primary'][$u['role']] ?? 'badge-primary'; ?>
                <span class="badge <?= $rc ?>"><?= $u['role'] ?></span>
              </td>
              <td><?= $u['active'] ? '<span class="material-icons" style="color:#28a745">check_circle</span>' : '<span class="material-icons" style="color:#dc3545">cancel</span>' ?></td>
              <td class="text-sm text-muted"><?= $u['last_login'] ? formatDate($u['last_login'],'d/m/Y H:i') : '—' ?></td>
              <td>
                <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon"><span class="material-icons" style="font-size:18px">edit</span></a>
                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                <a href="users.php?action=delete&id=<?= $u['id'] ?>&csrf=<?= csrf() ?>" class="btn btn-ghost btn-sm btn-icon" style="color:#dc3545" data-confirm="Excluir usuário <?= htmlspecialchars($u['name']) ?>?"><span class="material-icons" style="font-size:18px">delete</span></a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif (in_array($action, ['new','edit'])): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <a href="users.php" class="btn btn-ghost btn-sm"><span class="material-icons">arrow_back</span></a>
      <h1 style="font-size:20px"><?= $action==='new'?'Novo Usuário':'Editar Usuário' ?></h1>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span><?= $success ?></div><?php endif; ?>
    <div class="card card-body" style="max-width:560px">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <div class="form-group">
          <label class="form-label">Nome completo *</label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($user['name']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">E-mail institucional *</label>
          <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Senha <?= $action==='edit'?'(deixe em branco para manter)':'' ?></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" <?= $action==='new'?'required':'' ?>>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label class="form-label">Setor</label>
            <input type="text" name="sector" class="form-control" value="<?= htmlspecialchars($user['sector']??'') ?>" placeholder="TI, Comunicação...">
          </div>
          <div class="form-group">
            <label class="form-label">Nível de acesso</label>
            <select name="role" class="form-control">
              <option value="user" <?= ($user['role']??'user')==='user'?'selected':'' ?>>Usuário</option>
              <option value="editor" <?= ($user['role']??'')==='editor'?'selected':'' ?>>Editor</option>
              <option value="admin" <?= ($user['role']??'')==='admin'?'selected':'' ?>>Administrador</option>
            </select>
          </div>
        </div>
        <label style="display:flex;align-items:center;gap:10px;margin-bottom:20px;cursor:pointer">
          <input type="checkbox" name="active" value="1" <?= ($user['active']??1)?'checked':'' ?>> Usuário ativo
        </label>
        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Salvar</button>
          <a href="users.php" class="btn btn-ghost">Cancelar</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
  </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
</body>
</html>
