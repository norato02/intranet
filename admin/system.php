<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$isDark  = ($_SESSION['dark_mode'] ?? false);
$success = $error = '';
$action  = $_POST['action'] ?? '';

// ── Detecção de SO ────────────────────────────────────────────
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// ── Caminhos de log por SO ────────────────────────────────────
function getLogCandidates(): array {
    global $isWindows;
    $base = BASE_PATH;

    if ($isWindows) {
        // XAMPP Windows: logs na pasta do XAMPP
        $drive   = substr($base, 0, 2); // ex: C:
        $xamppLogDir = $drive . '/xampp/apache/logs/';
        return [
            $base . '/php_errors.log',
            $base . '/error.log',
            $xamppLogDir . 'error.log',
            $xamppLogDir . 'php_error.log',
            'C:/xampp/apache/logs/error.log',
            'C:/xampp/php/logs/php_error_log.txt',
            ini_get('error_log') ?: '',
        ];
    }

    return [
        BASE_PATH . '/php_errors.log',
        BASE_PATH . '/error.log',
        '/var/log/apache2/error.log',
        '/var/log/apache2/php_errors.log',
        '/var/log/httpd/error_log',
        '/var/log/php_errors.log',
        ini_get('error_log') ?: '',
    ];
}

// ── Sessões: caminho compatível com XAMPP e Linux ─────────────
function getSessionPath(): string {
    $sp = session_save_path();
    if ($sp && is_dir($sp)) return $sp;
    // XAMPP: PHP usa temp dir do Windows
    return sys_get_temp_dir();
}

// ── AÇÕES ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Token inválido.';
    } else {
        switch ($action) {

            case 'clear_opcache':
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                    $success = 'OPcache limpo com sucesso.';
                } else {
                    $error = 'OPcache não disponível. No XAMPP habilite zend_extension=opcache no php.ini.';
                }
                break;

            case 'clear_sessions':
                $sessionPath = getSessionPath();
                $count  = 0;
                $cutoff = time() - 86400;
                // XAMPP usa padrão diferente de nome de sessão
                $patterns = [$sessionPath . '/sess_*', $sessionPath . '\\sess_*'];
                foreach ($patterns as $pat) {
                    foreach (glob($pat) ?: [] as $file) {
                        if (is_file($file) && filemtime($file) < $cutoff) {
                            @unlink($file);
                            $count++;
                        }
                    }
                }
                $success = "Sessões antigas removidas: {$count} arquivo(s).";
                break;

            case 'clear_temp_uploads':
                $count  = 0;
                $cutoff = time() - (48 * 3600);
                foreach (['posts', 'gallery', 'modules', 'avatars'] as $sub) {
                    $dir = UPLOAD_DIR . $sub . DIRECTORY_SEPARATOR;
                    if (!is_dir($dir)) continue;
                    foreach (glob($dir . 'img_*') ?: [] as $file) {
                        if (!is_file($file) || filemtime($file) >= $cutoff) continue;
                        $fname  = $sub . '/' . basename($file);
                        $inPost = Database::count('SELECT COUNT(*) FROM posts WHERE cover_image=?', [$fname]);
                        $inGal  = Database::count('SELECT COUNT(*) FROM post_gallery WHERE filename=?', [basename($file)]);
                        $inMod  = Database::count('SELECT COUNT(*) FROM modules WHERE icon_image=?', [basename($file)]);
                        if (!$inPost && !$inGal && !$inMod) { @unlink($file); $count++; }
                    }
                }
                $success = "Uploads órfãos removidos: {$count} arquivo(s).";
                break;

            case 'clear_php_log':
                $logFile = ini_get('error_log');
                $cleared = false;
                // Tentar o log configurado no php.ini primeiro
                if ($logFile && file_exists($logFile) && is_writable($logFile)) {
                    file_put_contents($logFile, '');
                    $success = 'Log PHP limpo: ' . $logFile;
                    $cleared = true;
                }
                // Fallback: procurar em locais comuns
                if (!$cleared) {
                    foreach (array_filter(getLogCandidates()) as $f) {
                        if ($f && file_exists($f) && is_writable($f)) {
                            file_put_contents($f, '');
                            $success = 'Log PHP limpo: ' . $f;
                            $cleared = true;
                            break;
                        }
                    }
                }
                if (!$cleared) {
                    $hint = $isWindows
                        ? 'Verifique error_log no php.ini do XAMPP (C:/xampp/php/php.ini).'
                        : 'Verifique error_log no php.ini e permissões do arquivo.';
                    $error = 'Log não encontrado ou sem permissão. ' . $hint;
                }
                break;

            case 'clear_apache_log':
                $cleared = false;
                foreach (array_filter(getLogCandidates()) as $f) {
                    if ($f && file_exists($f) && is_writable($f)) {
                        file_put_contents($f, '');
                        $success = 'Log Apache/XAMPP limpo: ' . $f;
                        $cleared = true;
                        break;
                    }
                }
                if (!$cleared) {
                    if ($isWindows) {
                        $error = 'Log não encontrado. Verifique C:/xampp/apache/logs/error.log';
                    } else {
                        $error = 'Sem permissão. Execute: sudo truncate -s 0 /var/log/apache2/error.log';
                    }
                }
                break;
        }
    }
}

// ── INFORMAÇÕES ───────────────────────────────────────────────
function fmtBytes(int $bytes): string {
    if ($bytes <= 0)         return '0 B';
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

function logSize(string $path): string {
    if (!$path || !file_exists($path)) return '—';
    $s = @filesize($path);
    return $s === false ? '(sem permissão)' : fmtBytes($s);
}

// Upload info
$uploadInfo = [];
foreach (['posts', 'gallery', 'modules', 'avatars'] as $sub) {
    $dir   = UPLOAD_DIR . $sub . DIRECTORY_SEPARATOR;
    $files = array_filter(glob($dir . '*') ?: [], 'is_file');
    $size  = array_sum(array_map(fn($f) => @filesize($f) ?: 0, $files));
    $uploadInfo[$sub] = ['count' => count($files), 'size' => $size];
}
$totalBytes = array_sum(array_column($uploadInfo, 'size'));

// PHP log
$phpLogPath = ini_get('error_log') ?: '';
$phpLogSize = logSize($phpLogPath);

// OPcache
$opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;

// Sessions
$sessionPath  = getSessionPath();
$sessionCount = count(glob($sessionPath . '/sess_*') ?: []);
if ($sessionCount === 0 && $isWindows) {
    $sessionCount = count(glob($sessionPath . '\\sess_*') ?: []);
}

// Apache log detection
$apacheLog = '';
foreach (array_filter(getLogCandidates()) as $f) {
    if ($f && file_exists($f)) { $apacheLog = $f; break; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sistema — Admin</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
  <style>
  .sys-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:28px}
  .sys-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px}
  .sys-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:6px}
  .sys-title .material-icons{font-size:16px;color:var(--primary)}
  .sys-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px;gap:8px}
  .sys-row:last-child{border:none}
  .sys-row span:first-child{color:var(--text-muted);flex-shrink:0}
  .sys-val{font-weight:600;color:var(--text);text-align:right;word-break:break-all;font-size:12px}
  .ok{color:#28a745!important}.warn{color:#e67e22!important}.err{color:#dc3545!important}
  .action-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
  .action-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;display:flex;flex-direction:column;gap:10px}
  .action-card h3{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
  .action-card h3 .material-icons{font-size:20px;color:var(--primary)}
  .action-card p{font-size:13px;color:var(--text-muted);margin:0;flex:1;line-height:1.5}
  .upload-bar{background:var(--bg);border-radius:4px;height:6px;overflow:hidden;margin-top:6px}
  .upload-bar-fill{height:100%;background:var(--primary);border-radius:4px}
  .os-badge{display:inline-flex;align-items:center;gap:5px;background:var(--primary-xlight);color:var(--primary);font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:18px}
  pre{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:11px;max-height:280px;overflow:auto;color:var(--text);line-height:1.6;white-space:pre-wrap;word-break:break-all;margin:10px 0 0}
  </style>
</head>
<body class="fade-in">
<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon"><span class="material-icons">admin_panel_settings</span></div>
    <div class="brand-text">Admin <small>Sistema</small></div>
  </a>
  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark?'on':'' ?>"></button>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm"><span class="material-icons">home</span></a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons">logout</span></a>
  </div>
</nav>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-label">Conteúdo</div>
    <a href="index.php" class="sidebar-link"><span class="material-icons">dashboard</span> Dashboard</a>
    <a href="posts.php?type=comunicado" class="sidebar-link"><span class="material-icons">campaign</span> Comunicados</a>
    <a href="posts.php?type=noticia" class="sidebar-link"><span class="material-icons">newspaper</span> Notícias</a>
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link active"><span class="material-icons">build</span> Sistema</a>
  </aside>
  <main class="admin-main">
    <h1 style="font-size:22px;margin-bottom:6px">Sistema &amp; Manutenção</h1>

    <!-- Badge do SO detectado -->
    <div class="os-badge">
      <span class="material-icons" style="font-size:14px"><?= $isWindows ? 'laptop_windows' : 'computer' ?></span>
      <?= $isWindows ? 'XAMPP Windows detectado' : 'Linux / Apache detectado' ?>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger"><span class="material-icons">error</span> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── INFORMAÇÕES PHP ── -->
    <div class="sys-grid">
      <div class="sys-card">
        <div class="sys-title"><span class="material-icons">code</span> PHP</div>
        <div class="sys-row"><span>Versão</span><span class="sys-val <?= version_compare(PHP_VERSION,'8.0','>=') ? 'ok':'warn' ?>"><?= PHP_VERSION ?></span></div>
        <div class="sys-row"><span>Sistema</span><span class="sys-val"><?= PHP_OS ?></span></div>
        <div class="sys-row"><span>upload_max_filesize</span><span class="sys-val"><?= ini_get('upload_max_filesize') ?></span></div>
        <div class="sys-row"><span>post_max_size</span><span class="sys-val"><?= ini_get('post_max_size') ?></span></div>
        <div class="sys-row"><span>memory_limit</span><span class="sys-val"><?= ini_get('memory_limit') ?></span></div>
        <div class="sys-row"><span>GD (imagens)</span><span class="sys-val <?= extension_loaded('gd')?'ok':'err' ?>"><?= extension_loaded('gd')?'Ativo':'Ausente' ?></span></div>
        <div class="sys-row"><span>php.ini</span><span class="sys-val" style="font-size:10px"><?= php_ini_loaded_file() ?: '(não encontrado)' ?></span></div>
      </div>

      <div class="sys-card">
        <div class="sys-title"><span class="material-icons">memory</span> OPcache</div>
        <?php if ($opcache): ?>
        <div class="sys-row"><span>Status</span><span class="sys-val ok">Ativo</span></div>
        <div class="sys-row"><span>Scripts em cache</span><span class="sys-val"><?= number_format($opcache['opcache_statistics']['num_cached_scripts'] ?? 0) ?></span></div>
        <div class="sys-row"><span>Hits</span><span class="sys-val ok"><?= number_format($opcache['opcache_statistics']['hits'] ?? 0) ?></span></div>
        <div class="sys-row"><span>Memória usada</span><span class="sys-val"><?= fmtBytes($opcache['memory_usage']['used_memory'] ?? 0) ?></span></div>
        <div class="sys-row"><span>Memória livre</span><span class="sys-val"><?= fmtBytes($opcache['memory_usage']['free_memory'] ?? 0) ?></span></div>
        <?php else: ?>
        <div class="sys-row"><span>Status</span><span class="sys-val warn">Inativo</span></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:8px;line-height:1.5">
          <?php if ($isWindows): ?>
          No XAMPP: abra <code>php.ini</code>, descomente <code>zend_extension=opcache</code> e reinicie o Apache.
          <?php else: ?>
          Execute: <code>sudo phpenmod opcache && sudo systemctl restart apache2</code>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="sys-card">
        <div class="sys-title"><span class="material-icons">people</span> Sessões</div>
        <div class="sys-row"><span>Sessões ativas</span><span class="sys-val"><?= $sessionCount ?></span></div>
        <div class="sys-row"><span>Pasta</span><span class="sys-val" style="font-size:10px" title="<?= htmlspecialchars($sessionPath) ?>"><?= htmlspecialchars(mb_substr($sessionPath, 0, 40)) ?>…</span></div>
        <div class="sys-row"><span>Nome da sessão</span><span class="sys-val"><?= session_name() ?></span></div>
      </div>

      <div class="sys-card">
        <div class="sys-title"><span class="material-icons">bug_report</span> Log de Erros</div>
        <div class="sys-row"><span>Arquivo configurado</span>
          <span class="sys-val" style="font-size:10px" title="<?= htmlspecialchars($phpLogPath) ?>">
            <?= $phpLogPath ? htmlspecialchars(basename($phpLogPath)) : '(não configurado)' ?>
          </span>
        </div>
        <div class="sys-row"><span>Tamanho</span>
          <span class="sys-val <?= ($phpLogPath && @filesize($phpLogPath) > 1048576) ? 'err' : 'ok' ?>">
            <?= $phpLogSize ?>
          </span>
        </div>
        <div class="sys-row"><span>Log Apache encontrado</span>
          <span class="sys-val <?= $apacheLog ? 'ok' : 'warn' ?>">
            <?= $apacheLog ? htmlspecialchars(basename($apacheLog)) : '—' ?>
          </span>
        </div>
        <div class="sys-row"><span>display_errors</span>
          <span class="sys-val <?= ini_get('display_errors') ? 'warn' : 'ok' ?>">
            <?= ini_get('display_errors') ? 'Ligado (dev)' : 'Desligado' ?>
          </span>
        </div>
      </div>
    </div>

    <!-- ── UPLOADS ── -->
    <h2 style="font-size:15px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px">
      <span class="material-icons" style="color:var(--primary)">folder</span>
      Uploads — <?= fmtBytes($totalBytes) ?> total
    </h2>
    <div class="sys-grid" style="margin-bottom:28px">
      <?php foreach ($uploadInfo as $sub => $info):
        $pct = $totalBytes > 0 ? round($info['size'] / $totalBytes * 100) : 0;
      ?>
      <div class="sys-card" style="padding:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="font-weight:700;text-transform:capitalize"><?= $sub ?>/</span>
          <span style="font-size:12px;color:var(--text-muted)"><?= $info['count'] ?> arquivo(s) &nbsp;·&nbsp; <?= fmtBytes($info['size']) ?></span>
        </div>
        <div class="upload-bar"><div class="upload-bar-fill" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── AÇÕES DE MANUTENÇÃO ── -->
    <h2 style="font-size:15px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <span class="material-icons" style="color:var(--primary)">build</span> Manutenção
    </h2>
    <div class="action-grid">

      <div class="action-card">
        <h3><span class="material-icons">memory</span> Limpar OPcache</h3>
        <p>Força o PHP a recompilar os scripts. Útil após atualizar arquivos no servidor.</p>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="clear_opcache">
          <button type="submit" class="btn btn-primary btn-sm" <?= !function_exists('opcache_reset')?'disabled':'' ?>>
            <span class="material-icons">refresh</span> Limpar OPcache
          </button>
        </form>
      </div>

      <div class="action-card">
        <h3><span class="material-icons">people</span> Limpar Sessões Antigas</h3>
        <p>Remove sessões com mais de 24h. Não afeta usuários ativos.</p>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="clear_sessions">
          <button type="submit" class="btn btn-primary btn-sm">
            <span class="material-icons">delete_sweep</span> Limpar Sessões
          </button>
        </form>
      </div>

      <div class="action-card">
        <h3><span class="material-icons">folder_delete</span> Limpar Uploads Órfãos</h3>
        <p>Remove imagens sem referência no banco com mais de 48h.</p>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="clear_temp_uploads">
          <button type="submit" class="btn btn-primary btn-sm"
                  data-confirm="Remover uploads sem referência no banco? Não pode ser desfeito.">
            <span class="material-icons">auto_delete</span> Limpar Órfãos
          </button>
        </form>
      </div>

      <div class="action-card">
        <h3><span class="material-icons">bug_report</span> Limpar Log PHP</h3>
        <p>Apaga o conteúdo do log de erros PHP. O arquivo permanece, só o conteúdo é zerado.</p>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="clear_php_log">
          <button type="submit" class="btn btn-outline btn-sm"
                  data-confirm="Limpar o log PHP? Os erros serão perdidos.">
            <span class="material-icons">cleaning_services</span> Limpar Log PHP
          </button>
        </form>
      </div>

      <div class="action-card">
        <h3><span class="material-icons">dns</span> Limpar Log Apache</h3>
        <p>
          <?php if ($isWindows): ?>
          Zera o log do Apache no XAMPP. O arquivo precisa estar acessível.
          <?php else: ?>
          Zera o log de erros do Apache. Requer permissão de escrita no arquivo.
          <?php endif; ?>
        </p>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="clear_apache_log">
          <button type="submit" class="btn btn-outline btn-sm"
                  data-confirm="Limpar o log do Apache?">
            <span class="material-icons">dns</span> Limpar Log Apache
          </button>
        </form>
      </div>

      <div class="action-card">
        <h3><span class="material-icons">article</span> Ver Log de Erros</h3>
        <p>Exibe as últimas 80 linhas do log de erros PHP aqui no painel.</p>
        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleLog()">
          <span class="material-icons">visibility</span> Ver Log
        </button>
        <div id="logViewer" style="display:none">
          <pre><?php
            $lf = ini_get('error_log');
            if (!$lf || !file_exists($lf)) {
                // Tentar alternativas
                foreach (array_filter(getLogCandidates()) as $f) {
                    if ($f && file_exists($f) && is_readable($f)) { $lf = $f; break; }
                }
            }
            if ($lf && file_exists($lf) && is_readable($lf)) {
                $lines = file($lf) ?: [];
                echo htmlspecialchars(implode('', array_slice($lines, -80)));
            } else {
                echo '(Log não encontrado ou sem permissão de leitura)';
                if ($isWindows) echo "\n\nDica XAMPP: abra php.ini e defina error_log = C:/xampp/php/logs/php_error_log.txt";
            }
          ?></pre>
        </div>
      </div>
    </div>

    <!-- Dicas por SO -->
    <?php if ($isWindows): ?>
    <div class="alert alert-info" style="margin-top:8px;font-size:13px;line-height:1.8">
      <span class="material-icons">laptop_windows</span>
      <div>
        <strong>Dicas XAMPP:</strong><br>
        • php.ini: <code><?= php_ini_loaded_file() ?: 'C:/xampp/php/php.ini' ?></code><br>
        • Logs Apache: <code>C:/xampp/apache/logs/error.log</code><br>
        • Para limitar upload edite <code>upload_max_filesize</code> e <code>post_max_size</code> no php.ini e reinicie o Apache no painel XAMPP.
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info" style="margin-top:8px;font-size:13px;line-height:1.8">
      <span class="material-icons">terminal</span>
      <div>
        <strong>Comandos úteis Linux:</strong><br>
        <code>sudo truncate -s 0 /var/log/apache2/error.log</code><br>
        <code>sudo systemctl restart apache2</code><br>
        <code>sudo chown -R www-data:www-data <?= htmlspecialchars(UPLOAD_DIR) ?></code>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
<script>function toggleLog(){var el=document.getElementById('logViewer');el.style.display=el.style.display==='none'?'block':'none';}</script>
</body>
</html>
