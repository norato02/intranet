<?php
// ============================================================
// pages/rh.php � P�gina de Recursos Humanos
// M�dulos: Apontatu e Oris RH
// ============================================================
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// P�gina p�blica � n�o requer login

$siteName  = getSetting('site_name', 'Intranet Acqua');
$tagline   = getSetting('site_tagline', 'Unidade de Sa�de');
$logoFile  = getSetting('site_logo', '');
$isDark    = isset($_COOKIE['acqua_dark']) && $_COOKIE['acqua_dark'] === 'dark';
$darkAttr  = $isDark ? 'dark' : 'light';
$pageTitle = 'Recursos Humanos';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $darkAttr ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>

  <style>
    /* -- M�dulos RH ------------------------------------------- */
    .rh-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 24px;
      margin-top: 8px;
    }

    .rh-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      text-decoration: none;
      color: var(--text);
      display: flex;
      flex-direction: column;
      transition: all var(--transition);
      position: relative;
      box-shadow: var(--shadow-sm);
    }

    .rh-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: var(--card-color, var(--primary));
      transform: scaleX(0);
      transform-origin: left;
      transition: transform var(--transition);
    }

    .rh-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--shadow-lg);
      border-color: color-mix(in srgb, var(--card-color, var(--primary)) 40%, transparent);
    }

    .rh-card:hover::before {
      transform: scaleX(1);
    }

    /* Cabe�alho do card com logo */
    .rh-card-header {
      padding: 32px 28px 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      border-bottom: 1px solid var(--border);
      background: color-mix(in srgb, var(--card-color, var(--primary)) 5%, var(--bg-card));
    }

    .rh-logo-wrap {
      width: 100px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--bg-card);
      border-radius: var(--radius);
      padding: 10px 16px;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--border-light);
    }

    .rh-logo-wrap img,
    .rh-logo-wrap svg {
      max-width: 100%;
      max-height: 40px;
      object-fit: contain;
    }

    .rh-card-header h3 {
      font-size: 17px;
      font-weight: 800;
      color: var(--text);
      text-align: center;
      font-family: var(--font-heading);
    }

    /* Corpo do card */
    .rh-card-body {
      padding: 22px 28px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .rh-card-desc {
      font-size: 14px;
      color: var(--text-muted);
      line-height: 1.6;
      flex: 1;
    }

    /* Chips de funcionalidades */
    .rh-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 4px;
    }

    .rh-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      background: color-mix(in srgb, var(--card-color, var(--primary)) 12%, transparent);
      color: var(--card-color, var(--primary));
      border: 1px solid color-mix(in srgb, var(--card-color, var(--primary)) 25%, transparent);
    }

    .rh-chip .material-icons {
      font-size: 13px;
    }

    /* Rodap� do card */
    .rh-card-footer {
      padding: 16px 28px 24px;
    }

    .rh-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 12px 20px;
      border-radius: var(--radius);
      background: var(--card-color, var(--primary));
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      font-family: var(--font-body);
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: all var(--transition);
      box-shadow: 0 2px 8px color-mix(in srgb, var(--card-color, var(--primary)) 35%, transparent);
    }

    .rh-btn:hover {
      filter: brightness(1.1);
      transform: translateY(-1px);
      box-shadow: 0 4px 16px color-mix(in srgb, var(--card-color, var(--primary)) 45%, transparent);
      color: #fff;
    }

    .rh-btn .material-icons {
      font-size: 18px;
    }

    /* Badge "Acesso externo" */
    .rh-external-badge {
      position: absolute;
      top: 16px;
      right: 16px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      background: var(--bg);
      border: 1px solid var(--border);
      color: var(--text-muted);
    }

    .rh-external-badge .material-icons {
      font-size: 12px;
    }

    /* -- Breadcrumb ------------------------------------------- */
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      color: var(--text-muted);
      margin-bottom: 24px;
    }

    .breadcrumb a {
      color: var(--text-muted);
      transition: color var(--transition);
    }

    .breadcrumb a:hover {
      color: var(--primary);
    }

    .breadcrumb .material-icons {
      font-size: 15px;
    }

    /* -- Aviso informativo ------------------------------------ */
    .rh-info-banner {
      background: color-mix(in srgb, var(--primary) 8%, var(--bg-card));
      border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
      border-left: 4px solid var(--primary);
      border-radius: var(--radius);
      padding: 14px 18px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 28px;
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.6;
    }

    .rh-info-banner .material-icons {
      color: var(--primary);
      font-size: 20px;
      flex-shrink: 0;
      margin-top: 1px;
    }

    /* -- Chat Bubble ------------------------------------------ */
    .chat-bubble-btn {
      position: fixed;
      bottom: 28px;
      right: 28px;
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--accent2));
      color: #fff;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 20px color-mix(in srgb, var(--primary) 50%, transparent),
                  0 2px 8px rgba(0,0,0,.2);
      transition: all var(--transition);
      z-index: 999;
    }

    .chat-bubble-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 28px color-mix(in srgb, var(--primary) 60%, transparent),
                  0 4px 12px rgba(0,0,0,.25);
    }

    .chat-bubble-btn .material-icons {
      font-size: 26px;
      transition: all var(--transition);
    }

    .chat-bubble-btn.open .material-icons {
      transform: rotate(90deg);
    }

    /* Notifica��o pulsante */
    .chat-bubble-btn::after {
      content: '';
      position: absolute;
      top: 4px;
      right: 4px;
      width: 12px;
      height: 12px;
      background: #FF5252;
      border-radius: 50%;
      border: 2px solid var(--bg-card);
      animation: chat-pulse 2s infinite;
    }

    .chat-bubble-btn.open::after {
      display: none;
    }

    @keyframes chat-pulse {
      0%  { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.3); opacity: .7; }
      100%{ transform: scale(1); opacity: 1; }
    }

    /* Tooltip do chat */
    .chat-tooltip {
      position: fixed;
      bottom: 96px;
      right: 28px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 10px 16px;
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
      box-shadow: var(--shadow);
      white-space: nowrap;
      pointer-events: none;
      opacity: 0;
      transform: translateY(8px);
      transition: all var(--transition);
      z-index: 998;
    }

    .chat-tooltip.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .chat-tooltip::after {
      content: '';
      position: absolute;
      bottom: -6px;
      right: 22px;
      width: 12px;
      height: 12px;
      background: var(--bg-card);
      border-right: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      transform: rotate(45deg);
    }

    /* -- Painel do Chat --------------------------------------- */
    .chat-panel {
      position: fixed;
      bottom: 100px;
      right: 28px;
      width: 380px;
      height: 580px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      z-index: 997;
      overflow: hidden;
      display: none;
      flex-direction: column;
      transform: scale(0.9) translateY(20px);
      transform-origin: bottom right;
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      opacity: 0;
    }

    .chat-panel.open {
      display: flex;
      transform: scale(1) translateY(0);
      opacity: 1;
    }

    .chat-panel-header {
      padding: 16px 20px;
      background: linear-gradient(135deg, var(--primary), var(--accent2));
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }

    .chat-panel-header-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .chat-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(255,255,255,.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .chat-avatar .material-icons {
      font-size: 22px;
    }

    .chat-panel-header h4 {
      font-size: 15px;
      font-weight: 700;
      font-family: var(--font-body);
    }

    .chat-panel-header p {
      font-size: 11px;
      opacity: .85;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .chat-online-dot {
      width: 7px;
      height: 7px;
      background: #4ADE80;
      border-radius: 50%;
      display: inline-block;
    }

    .chat-close-btn {
      background: rgba(255,255,255,.15);
      border: none;
      cursor: pointer;
      color: #fff;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background var(--transition);
    }

    .chat-close-btn:hover {
      background: rgba(255,255,255,.3);
    }

    .chat-close-btn .material-icons {
      font-size: 18px;
    }

    /* ================================================================
       �REA DO IFRAME DO CHATBASE
       ================================================================
       Substitua o conte�do do #chat-iframe-area pelo iframe do Chatbase.

       COMO OBTER O IFRAME:
       1. Acesse app.chatbase.co ? seu chatbot ? "Connect" ? "Embed"
       2. Copie o c�digo <iframe ...> gerado
       3. Cole no lugar indicado abaixo (dentro de #chat-iframe-area)

       EXEMPLO do c�digo que o Chatbase gera:
       <iframe
         src="https://www.chatbase.co/chatbot-iframe/SEU_CHATBOT_ID"
         width="100%"
         style="height:100%;min-height:700px"
         frameborder="0">
       </iframe>
       ================================================================ */
    .chat-iframe-area {
      flex: 1;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .chat-iframe-area iframe {
      width: 100%;
      height: 100%;
      border: none;
      flex: 1;
    }

    /* Placeholder enquanto o iframe n�o for configurado */
    .chat-placeholder {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 32px 24px;
      text-align: center;
      gap: 16px;
    }

    .chat-placeholder .material-icons {
      font-size: 48px;
      color: var(--primary);
      opacity: .6;
    }

    .chat-placeholder p {
      font-size: 14px;
      color: var(--text-muted);
      line-height: 1.6;
    }

    .chat-placeholder code {
      font-size: 12px;
      background: var(--bg);
      padding: 2px 8px;
      border-radius: 4px;
      color: var(--primary);
      font-family: monospace;
    }

    /* -- Responsivo ------------------------------------------- */
    @media (max-width: 640px) {
      .rh-grid { grid-template-columns: 1fr; }
      .chat-panel { width: calc(100vw - 32px); right: 16px; }
      .chat-bubble-btn { bottom: 20px; right: 20px; }
    }
  </style>
</head>
<body class="fade-in">

<!-- -- NAVBAR -- -->
<nav class="navbar">
  <a href="<?= BASE_URL ?>/public.php" class="navbar-brand">
    <?php if ($logoFile && file_exists(UPLOAD_DIR . $logoFile)): ?>
    <img src="<?= UPLOAD_URL . htmlspecialchars($logoFile) ?>" alt="Logo"
         style="height:38px;width:auto;border-radius:8px">
    <?php else: ?>
    <div class="logo-icon"><span class="material-icons">local_hospital</span></div>
    <?php endif; ?>
    <div class="brand-text">
      <?= htmlspecialchars($siteName) ?>
      <small><?= htmlspecialchars($tagline) ?></small>
    </div>
  </a>

  <ul class="navbar-nav" id="navbarNav">
    <?php $navMenuCurrentPage = 'rh'; $navMenuPublic = true; include __DIR__ . '/../includes/nav_menu.php'; ?>
  </ul>

  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark ? 'on' : '' ?>" title="Modo escuro"
            aria-label="Alternar modo escuro"></button>
    <?php if (isLoggedIn()): ?>
    <div class="dropdown">
      <button class="btn btn-ghost btn-icon" style="border-radius:50%" title="Minha conta">
        <div class="avatar-xs"><?= mb_strtoupper(mb_substr($_SESSION['user_name'], 0, 1)) ?></div>
      </button>
      <div class="dropdown-menu">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border);margin-bottom:4px">
          <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
        </div>
        <?php if (isAdmin() || isEditor()): ?>
        <a href="<?= BASE_URL ?>/admin/" class="dropdown-item">
          <span class="material-icons">dashboard</span> Painel Admin
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item" style="color:#dc3545">
          <span class="material-icons">logout</span> Sair
        </a>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">
      <span class="material-icons">lock</span> &Aacute;rea Restrita
    </a>
    <?php endif; ?>
    <button class="btn btn-ghost btn-icon mobile-menu-btn" id="mobileMenuBtn">
      <span class="material-icons">menu</span>
    </button>
  </div>
</nav>

<!-- -- CONTE�DO PRINCIPAL -- -->
<div class="page-wrapper">
  <div class="container main-content">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="<?= BASE_URL ?>/public.php">
        <span class="material-icons">home</span>
      </a>
      <span class="material-icons">chevron_right</span>
      <span style="color:var(--text);font-weight:600">Recursos Humanos</span>
    </div>

    <!-- Cabe�alho da p�gina -->
    <div class="section-header">
      <span class="section-title">
        <span class="material-icons">badge</span>
        Recursos Humanos
      </span>
      <span class="text-muted text-sm">2 sistemas dispon&iacute;veis</span>
    </div>

    <!-- Banner informativo -->
    <div class="rh-info-banner">
      <span class="material-icons">info</span>
      <div>
        Acesse os sistemas de RH com suas <strong>credenciais institucionais</strong>.
        Em caso de d&uacute;vidas sobre acesso ou cadastro, entre em contato com o setor de
        Recursos Humanos.
      </div>
    </div>

    <!-- Grid de m�dulos -->
    <div class="rh-grid">

      <!-- ---------------------------------------------------- -->
      <!-- CARD 1 � APONTATU                                    -->
      <!-- ---------------------------------------------------- -->
      <div class="rh-card" style="--card-color:#e52051">

        <span class="rh-external-badge">
          <span class="material-icons">open_in_new</span>
          Acesso externo
        </span>

        <div class="rh-card-header">
          <!-- Logo Apontatu (SVG inline) -->
          <div class="rh-logo-wrap">
            <svg viewBox="0 0 266 118.9" xmlns="http://www.w3.org/2000/svg"
                 style="width:100%;height:auto">
              <defs>
                <style>
                  .ap0{fill:#e52051}
                  .ap2{fill:#798291}
                </style>
              </defs>
              <g>
                <path class="ap2" d="M36,71.9v17.2h-8.4v-3.3c-1.7,2.2-5.2,4-10.3,4-10.1,0-17.3-7.2-17.3-18s7.2-17.9,18-17.9,18,7.2,18,18h0ZM18,61.6c-5.8,0-9.7,3.8-9.7,10.3s3.8,10.3,9.7,10.3,9.6-3.8,9.6-10.3-3.8-10.3-9.6-10.3Z"/>
                <path class="ap2" d="M39.9,103.1v-31.4c0-11.2,8-17.9,18.2-17.9s17.8,7.2,17.8,17.9-7.2,18-17.4,18-8.5-1.8-10.2-4v17.3h-8.4ZM57.8,61.5c-5.8,0-9.6,3.8-9.6,10.3s3.8,10.3,9.6,10.3,9.7-3.8,9.7-10.3-3.8-10.3-9.7-10.3Z"/>
                <path class="ap2" d="M96.7,53.9c10.8,0,17.9,7.2,17.9,17.9s-7.2,18-17.9,18-18-7.2-18-18,7.2-17.9,18-17.9ZM96.6,82.2c5.8,0,9.7-3.8,9.7-10.3s-3.8-10.3-9.7-10.3-9.6,3.8-9.6,10.3,3.8,10.3,9.6,10.3Z"/>
                <path class="ap2" d="M149.8,68.5v20.7h-8.4v-20.5c0-4.9-3.6-7.2-7.6-7.2s-7.7,2.2-7.7,7.2v20.5h-8.3v-20.7c0-6,4.5-14.7,16-14.7s16,8.6,16,14.7h0Z"/>
                <path class="ap2" d="M171.2,89.7c-11.4,0-17.5-5.8-17.5-16.5v-32.7h8.4v13.9h7.9v7.7h-7.9v11.1c0,6.3,2.6,8.9,9.1,8.9v7.7h0Z"/>
                <path class="ap2" d="M208.3,71.9v17.2h-8.4v-3.3c-1.7,2.2-5.2,4-10.3,4-10.1,0-17.3-7.2-17.3-18s7.2-17.9,17.9-17.9,18,7.2,18,18h0ZM190.3,61.6c-5.8,0-9.7,3.8-9.7,10.3s3.8,10.3,9.7,10.3,9.6-3.8,9.6-10.3-3.8-10.3-9.6-10.3Z"/>
                <path class="ap2" d="M229.3,89.7c-11.4,0-17.5-5.8-17.5-16.5v-32.7h8.4v13.9h7.9v7.7h-7.9v11.1c0,6.3,2.6,8.9,9.1,8.9v7.7h0Z"/>
                <path class="ap2" d="M232.1,75.1v-20.7h8.4v20.5c0,4.9,3.6,7.2,7.6,7.2s7.6-2.3,7.6-7.2v-20.5h8.3v20.7c0,6.1-4.5,14.7-16,14.7s-16-8.6-16-14.7h0Z"/>
                <path class="ap0" d="M109.8,8.7h0c-1.9-1.6-4.8-.4-5.1,2.1l-3.7,28.8h6.4l3.7-27.9c.2-1.1-.3-2.2-1.2-2.9h0Z"/>
                <path class="ap0" d="M73.2,39.6h6.7l4.7-35.7c.3-2.1-1.4-3.9-3.5-3.8h0c-1.6,0-3,1.3-3.2,2.9l-4.7,36.6h0Z"/>
                <path class="ap0" d="M93.8,39.6l4.5-34.1c.2-1.7-.8-3.2-2.4-3.7h0c-2-.6-4,.8-4.3,2.8l-4.5,35s6.8,0,6.8,0Z"/>
                <path class="ap0" d="M36.4,34.1h0c-1.7,0-3.1,1.3-3.3,2.9l-.3,2.5h6.8l.2-1.7c.3-2-1.3-3.8-3.3-3.8h0Z"/>
                <path class="ap0" d="M114.5,39.6h7.3l1.7-13.2h0c-1.6-3.8-6.9-3-7.4.9l-1.6,12.2h0Z"/>
                <path class="ap0" d="M7.8,34.1h0c-2.3,0-4.2,1.9-4.2,4.2v1.2h8.4v-.7c.4-2.5-1.6-4.8-4.1-4.8h0Z"/>
                <path class="ap0" d="M131.2,34.1h0c-2.3,0-4.2,1.9-4.2,4.2v1.2h8.3v-.7c.4-2.5-1.6-4.8-4.1-4.8h0Z"/>
                <path class="ap0" d="M66.3,3.3h0c-.9.4-1.6,1.3-1.7,2.4l-4.4,33.9h5.9l4.3-33.1c.3-2.3-2-4-4.1-3.1h0Z"/>
                <path class="ap0" d="M50.3,15.1s0,0,0,0c-.4.5-.7,1.1-.7,1.7l-3,22.8h6.5l2.9-22c.4-3.1-3.5-4.9-5.6-2.5h0Z"/>
                <path class="ap0" d="M18.9,39.6h6.7l.2-1.7c.3-2-1.3-3.8-3.3-3.8h0c-1.7,0-3.1,1.2-3.3,2.9l-.3,2.6h0Z"/>
              </g>
            </svg>
          </div>
          <h3>Ponto Eletr&ocirc;nico</h3>
        </div>

        <div class="rh-card-body">
          <p class="rh-card-desc">
            Sistema de controle de ponto e frequ&ecirc;ncia dos colaboradores.
            Registre entradas e sa&iacute;das, solicite ajustes e acompanhe seu
            hist&oacute;rico de frequ&ecirc;ncia.
          </p>
          <div class="rh-chips">
            <span class="rh-chip">
              <span class="material-icons">fingerprint</span>
              Ponto eletr&ocirc;nico
            </span>
            <span class="rh-chip">
              <span class="material-icons">history</span>
              Hist&oacute;rico
            </span>
            <span class="rh-chip">
              <span class="material-icons">edit_note</span>
              Ajustes
            </span>
            <span class="rh-chip">
              <span class="material-icons">beach_access</span>
              F&eacute;rias
            </span>
          </div>
        </div>

        <div class="rh-card-footer">
          <a href="https://app.apontatu.com.br/credenciamento/login/"
             target="_blank"
             rel="noopener noreferrer"
             class="rh-btn">
            <span class="material-icons">login</span>
            Acessar Apontatu
          </a>
        </div>

      </div>
      <!-- /CARD APONTATU -->

      <!-- ---------------------------------------------------- -->
      <!-- CARD 2 � ORIS RH                                     -->
      <!-- ---------------------------------------------------- -->
      <div class="rh-card" style="--card-color:#1e3a5f">

        <span class="rh-external-badge">
          <span class="material-icons">open_in_new</span>
          Acesso externo
        </span>

        <div class="rh-card-header">
          <!-- Logo Oris (PNG) -->
          <div class="rh-logo-wrap">
            <img src="<?= BASE_URL ?>/uploads/modules/logoOris.png" alt="Oris by tuapps"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <!-- Fallback se a imagem n�o estiver no servidor -->
            <div style="display:none;align-items:center;gap:6px">
              <span class="material-icons" style="font-size:28px;color:#1e3a5f">people</span>
              <span style="font-size:16px;font-weight:800;color:#1e3a5f;letter-spacing:-0.5px">oris</span>
            </div>
          </div>
          <h3>Portal de RH</h3>
        </div>

        <div class="rh-card-body">
          <p class="rh-card-desc">
            Portal de Recursos Humanos para acesso a holerites, informes de
            rendimentos, solicita&ccedil;&otilde;es e demais servi&ccedil;os do departamento pessoal.
          </p>
          <div class="rh-chips">
            <span class="rh-chip">
              <span class="material-icons">receipt_long</span>
              Holerite
            </span>
            <span class="rh-chip">
              <span class="material-icons">description</span>
              Informe IR
            </span>
            <span class="rh-chip">
              <span class="material-icons">assignment</span>
              Solicita&ccedil;&otilde;es
            </span>
            <span class="rh-chip">
              <span class="material-icons">folder_shared</span>
              Documentos
            </span>
          </div>
        </div>

        <div class="rh-card-footer">
          <a href="https://portal.orisrh.com/"
             target="_blank"
             rel="noopener noreferrer"
             class="rh-btn" style="background:#1e3a5f">
            <span class="material-icons">login</span>
            Acessar Portal Oris
          </a>
        </div>

      </div>
      <!-- /CARD ORIS -->

    </div><!-- /rh-grid -->

    <!-- Nota de suporte -->
    <div style="margin-top:32px;padding:16px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;gap:12px">
      <span class="material-icons" style="color:var(--text-muted);font-size:20px">help_outline</span>
      <p style="font-size:13px;color:var(--text-muted);margin:0">
        Com problemas de acesso? entre em
        contato diretamente com o setor de RH pelo ramal interno.
        <a href="<?= BASE_URL ?>/public.php?page=ramais" style="color:var(--primary);font-weight:600;margin-left:4px">
          Ver ramais &rarr;
        </a>
      </p>
    </div>

  </div><!-- /container -->
</div><!-- /page-wrapper -->

<!-- ================================================================
     CHAT BUBBLE + PAINEL DO CHATBASE
     ================================================================ -->

<!-- Tooltip que aparece antes de abrir o chat -->
<div class="chat-tooltip" id="chatTooltip">
  &#128172; Assistente Virtual
</div>

<!-- Bot�o flutuante do chat -->
<button class="chat-bubble-btn" id="chatBubbleBtn"
        onclick="toggleChat()"
        title="Assistente Virtual">
  <span class="material-icons" id="chatBubbleIcon">chat</span>
</button>

<!-- Painel do chat -->
<div class="chat-panel" id="chatPanel">

  <!-- Cabe�alho do painel -->
  <div class="chat-panel-header">
    <div class="chat-panel-header-info">
      <div class="chat-avatar">
        <span class="material-icons">smart_toy</span>
      </div>
      <div>
        <h4>Assistente Virtual</h4>
        <p>
          <span class="chat-online-dot"></span>
          Online agora
        </p>
      </div>
    </div>
    <button class="chat-close-btn" onclick="toggleChat()">
      <span class="material-icons">close</span>
    </button>
  </div>

  <!-- ============================================================
       ??? COLE O IFRAME DO CHATBASE AQUI ???
       ============================================================
       1. Acesse: https://app.chatbase.co
       2. V� no seu chatbot ? Connect ? Embed
       3. Copie o <iframe ...> e substitua o bloco abaixo

       SUBSTITUA ESTE BLOCO:
       <div class="chat-placeholder"> ... </div>

       POR ALGO COMO:
       <div class="chat-iframe-area">
         <iframe
           src="https://www.chatbase.co/chatbot-iframe/SEU_ID_AQUI"
           width="100%"
           style="height:100%;min-height:700px"
           frameborder="0">
         </iframe>
       </div>
       ============================================================ -->
  <div class="chat-placeholder">
    <span class="material-icons">smart_toy</span>
    <div>
      <p><strong>Assistente Virtual</strong></p>
      <p>Cole o iframe do Chatbase aqui.</p>
      <p style="margin-top:8px">Procure por <code>COLE O IFRAME DO CHATBASE AQUI</code> no c&oacute;digo.</p>
    </div>
  </div>
  <!-- ??? FIM DA �REA DO IFRAME ??? -->

</div>
<!-- /chat-panel -->

<!-- Script do chat bubble -->
<script>
(function () {
  const btn      = document.getElementById('chatBubbleBtn');
  const panel    = document.getElementById('chatPanel');
  const icon     = document.getElementById('chatBubbleIcon');
  const tooltip  = document.getElementById('chatTooltip');
  let isOpen     = false;
  let tooltipTimer;

  // Mostra tooltip ap�s 2 segundos na p�gina
  tooltipTimer = setTimeout(() => {
    if (!isOpen) tooltip.classList.add('visible');
    // Esconde o tooltip ap�s 4 segundos
    setTimeout(() => tooltip.classList.remove('visible'), 4000);
  }, 2000);

  window.toggleChat = function () {
    isOpen = !isOpen;
    tooltip.classList.remove('visible');
    clearTimeout(tooltipTimer);

    if (isOpen) {
      panel.style.display = 'flex';
      // For�a reflow para a anima��o funcionar
      panel.offsetHeight;
      panel.classList.add('open');
      icon.textContent = 'close';
      btn.classList.add('open');
    } else {
      panel.classList.remove('open');
      icon.textContent = 'chat';
      btn.classList.remove('open');
      // Aguarda a anima��o antes de esconder
      setTimeout(() => {
        if (!isOpen) panel.style.display = 'none';
      }, 300);
    }
  };

  // Fecha o chat ao clicar fora
  document.addEventListener('click', function (e) {
    if (isOpen && !panel.contains(e.target) && !btn.contains(e.target)) {
      window.toggleChat();
    }
  });
})();
</script>

<!-- -- RODAP� -- -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
