<?php
// includes/header.php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/functions.php';
}
$siteName    = getSetting('site_name', 'Intranet');
$siteTagline = getSetting('site_tagline', 'Unidade de Saúde');
$navItemsAll = Database::fetchAll('SELECT * FROM nav_items WHERE active=1 ORDER BY sort_order');
$navItems    = array_values(array_filter($navItemsAll, fn($i) => $i['parent_id'] === null));
$navChildren = [];
foreach ($navItemsAll as $i) {
    if ($i['parent_id'] !== null) $navChildren[$i['parent_id']][] = $i;
}
$isDark      = ($_SESSION['dark_mode'] ?? false) || (isset($_COOKIE['acqua_dark']) && $_COOKIE['acqua_dark'] === 'dark');
$darkAttr    = $isDark ? 'dark' : 'light';
$logoFile    = getSetting('site_logo', '');
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $darkAttr ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= htmlspecialchars($siteName) ?></title>
  <meta name="description" content="<?= htmlspecialchars($siteTagline) ?>">
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
  <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="fade-in">

<nav class="navbar">
  <a href="<?= BASE_URL ?>/index.php" class="navbar-brand">
    <?php if ($logoFile && file_exists(UPLOAD_DIR . $logoFile)): ?>
    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($logoFile) ?>" alt="Logo" style="height:38px;width:auto;border-radius:8px">
    <?php else: ?>
    <div class="logo-icon"><span class="material-icons">local_hospital</span></div>
    <?php endif; ?>
    <div class="brand-text">
      <?= htmlspecialchars($siteName) ?>
      <small><?= htmlspecialchars($siteTagline) ?></small>
    </div>
  </a>

  <ul class="navbar-nav" id="navbarNav">
    <?php foreach ($navItems as $item):
      $children = $navChildren[$item['id']] ?? [];
    ?>
    <?php if ($children): ?>
    <li class="dropdown">
      <a href="javascript:void(0)" class="<?= (($_GET['page'] ?? '') === str_replace('index.php?page=', '', $item['url'])) ? 'active' : '' ?>">
        <?php if ($item['icon']): ?><span class="material-icons"><?= htmlspecialchars($item['icon']) ?></span><?php endif; ?>
        <?= htmlspecialchars($item['label']) ?>
        <span class="material-icons" style="font-size:16px;margin-left:2px">expand_more</span>
      </a>
      <div class="dropdown-menu">
        <?php foreach ($children as $child): ?>
        <a href="<?= htmlspecialchars(navUrl($child['url'])) ?>" class="dropdown-item"
           <?= $child['open_new_tab'] ? 'target="_blank"' : '' ?>>
          <?php if ($child['icon']): ?><span class="material-icons"><?= htmlspecialchars($child['icon']) ?></span><?php endif; ?>
          <?= htmlspecialchars($child['label']) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </li>
    <?php else: ?>
    <li>
      <a href="<?= htmlspecialchars(navUrl($item['url'])) ?>"
         <?= $item['open_new_tab'] ? 'target="_blank"' : '' ?>
         class="<?= (($_GET['page'] ?? '') === str_replace('index.php?page=', '', $item['url'])) ? 'active' : '' ?>">
        <?php if ($item['icon']): ?><span class="material-icons"><?= htmlspecialchars($item['icon']) ?></span><?php endif; ?>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    </li>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!isLoggedIn()): ?>
    <li><a href="<?= BASE_URL ?>/public.php"><span class="material-icons">public</span>Área Pública</a></li>
    <?php endif; ?>
  </ul>

  <div class="navbar-end">
    <button class="btn btn-ghost btn-icon" onclick="document.getElementById('searchWrap').style.display='flex'" title="Buscar">
      <span class="material-icons" style="font-size:20px">search</span>
    </button>
    <div style="position:relative;display:none" class="d-flex align-center" id="searchWrap">
      <input type="text" id="searchInput" class="form-control" style="width:220px;padding:8px 14px;font-size:13px;" placeholder="Buscar...">
      <button class="btn btn-ghost btn-icon" onclick="document.getElementById('searchWrap').style.display='none'" style="margin-left:4px">
        <span class="material-icons" style="font-size:18px">close</span>
      </button>
    </div>

    <button class="dark-toggle <?= $isDark ? 'on' : '' ?>" title="Modo escuro" aria-label="Alternar modo escuro"></button>

    <?php if (isLoggedIn()): ?>
    <div class="dropdown">
      <button class="btn btn-ghost btn-icon" style="border-radius:50%" title="Minha conta">
        <div class="avatar-xs"><?= mb_strtoupper(mb_substr($_SESSION['user_name'], 0, 1)) ?></div>
      </button>
      <div class="dropdown-menu">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border);margin-bottom:4px">
          <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
          <span class="badge badge-primary" style="margin-top:4px"><?= htmlspecialchars($_SESSION['user_role']) ?></span>
        </div>
        <?php if (isAdmin() || isEditor()): ?>
        <a href="<?= BASE_URL ?>/admin/index.php" class="dropdown-item">
          <span class="material-icons">dashboard</span> Painel Admin
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?page=perfil" class="dropdown-item">
          <span class="material-icons">person</span> Meu Perfil
        </a>
        <div style="border-top:1px solid var(--border);margin-top:4px;padding-top:4px">
          <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item" style="color:#dc3545">
            <span class="material-icons">logout</span> Sair
          </a>
        </div>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">
      <span class="material-icons">login</span> Entrar
    </a>
    <?php endif; ?>

    <button class="btn btn-ghost btn-icon mobile-menu-btn" id="mobileMenuBtn">
      <span class="material-icons">menu</span>
    </button>
  </div>
</nav>

<div class="page-wrapper">
