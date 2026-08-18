<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Token de segurança inválido. Tente novamente.';
    } else {
        $result = login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
        $error = $result['message'];
    }
}

$isDark = isset($_COOKIE['acqua_dark']) && $_COOKIE['acqua_dark'] === 'dark';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= htmlspecialchars(getSetting('site_name', 'Intranet')) ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
</head>
<body>
<div class="login-page">
  <div class="login-box fade-in">
    <div class="login-logo">
      <div class="logo-circle">
        <span class="material-icons">local_hospital</span>
      </div>
      <h1><?= htmlspecialchars(getSetting('site_name', 'Intranet')) ?></h1>
      <p><?= htmlspecialchars(getSetting('site_tagline', 'Unidade de Saúde')) ?></p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
      <span class="material-icons">error</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['logout'])): ?>
    <div class="alert alert-info">
      <span class="material-icons">info</span>
      Você saiu da sessão com sucesso.
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div class="form-group">
        <label class="form-label">E-mail institucional</label>
        <div style="position:relative">
          <span class="material-icons" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:20px">email</span>
          <input type="email" name="email" class="form-control" style="padding-left:42px"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="seu@empresa.com" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between">
          Senha
          <a href="<?= BASE_URL ?>/forgot.php" style="font-size:12px;font-weight:400">Esqueceu a senha?</a>
        </label>
        <div style="position:relative">
          <span class="material-icons" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:20px">lock</span>
          <input type="password" name="password" id="passwordField" class="form-control" style="padding-left:42px;padding-right:42px"
                 placeholder="••••••••" required>
          <button type="button" onclick="togglePassword()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted)">
            <span class="material-icons" id="eyeIcon" style="font-size:20px">visibility</span>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100" style="margin-top:8px;padding:13px">
        <span class="material-icons">login</span> Entrar
      </button>
    </form>

    <div style="margin-top:20px;text-align:center">
      <a href="<?= BASE_URL ?>/public.php" class="text-muted text-sm">
        <span class="material-icons" style="font-size:14px;vertical-align:middle">public</span>
        Acessar área pública
      </a>
    </div>

    <div style="margin-top:24px;text-align:center;font-size:12px;color:var(--text-muted)">
      Problemas de acesso? Contate o setor de TI.
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
<script>
function togglePassword() {
  const field = document.getElementById('passwordField');
  const icon  = document.getElementById('eyeIcon');
  if (field.type === 'password') { field.type = 'text'; icon.textContent = 'visibility_off'; }
  else { field.type = 'password'; icon.textContent = 'visibility'; }
}
</script>
</body>
</html>
