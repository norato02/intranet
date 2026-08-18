<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image.php';

requireAdmin();

$isDark  = ($_SESSION['dark_mode'] ?? false) || (isset($_COOKIE['acqua_dark']) && $_COOKIE['acqua_dark'] === 'dark');
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        // Salvar configurações de texto
        $allowed = [
            'site_name', 'site_tagline', 'hero_title', 'hero_subtitle', 'footer_text',
            'primary_color', 'secondary_color',
            'posts_per_page', 'allow_registration', 'session_timeout', 'gallery_interval',
        ];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                Database::query(
                    'UPDATE settings SET setting_value=? WHERE setting_key=?',
                    [sanitize($_POST[$key]), $key]
                );
            }
        }

        // Upload do logotipo usando a nova função uploadImage()
        if (!empty($_FILES['site_logo']['name']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['site_logo'], '');  // salva direto em uploads/
            if (!$upload['success']) {
                $error = 'Logo: ' . $upload['message'];
            } else {
                // Guardar apenas o nome do arquivo (sem subfolder)
                $logoName = basename($upload['filename']);
                Database::query(
                    'UPDATE settings SET setting_value=? WHERE setting_key=?',
                    [$logoName, 'site_logo']
                );
            }
        }

        if (!$error) $success = 'Configurações salvas com sucesso!';
    }
}

// Carregar todas as configurações em array associativo
$settings = [];
foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function s(array $s, string $k, string $d = ''): string {
    return htmlspecialchars($s[$k] ?? $d);
}

// Sidebar helper
function adminSidebar(string $active = ''): void { ?>
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
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link active"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link"><span class="material-icons">build</span> Sistema</a>
<?php }
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configurações — Admin</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
</head>
<body class="fade-in">

<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon" style="background:linear-gradient(135deg,var(--primary-dark),var(--accent2))">
      <span class="material-icons">admin_panel_settings</span>
    </div>
    <div class="brand-text">Admin <small>Configurações</small></div>
  </a>
  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark?'on':'' ?>"></button>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm"><span class="material-icons">home</span></a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons">logout</span></a>
    <button class="btn btn-ghost btn-icon mobile-menu-btn" id="sidebarToggle"><span class="material-icons">menu</span></button>
  </div>
</nav>

<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar"><?php adminSidebar(); ?></aside>

  <main class="admin-main">
    <h1 style="font-size:22px;margin-bottom:24px">Configurações do Sistema</h1>

    <?php if ($success): ?>
    <div class="alert alert-success" data-auto-dismiss>
      <span class="material-icons">check_circle</span> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">
      <span class="material-icons">error</span> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        <!-- ── Identidade ── -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">
              <span class="material-icons" style="font-size:18px;color:var(--primary);margin-right:6px;vertical-align:middle">info</span>
              Identidade do Site
            </span>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Nome do Site</label>
              <input type="text" name="site_name" class="form-control"
                     value="<?= s($settings,'site_name','Intranet') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Subtítulo / Tagline</label>
              <input type="text" name="site_tagline" class="form-control"
                     value="<?= s($settings,'site_tagline',getSetting('site_tagline','Unidade de Saúde')) ?>">
            </div>

            <!-- ── Faixa Hero ── -->
            <div style="margin:4px 0 16px;padding:16px;background:var(--bg);border-radius:var(--radius-sm);border:1px solid var(--border)">
              <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:6px;margin-bottom:6px">
                <span class="material-icons" style="font-size:18px;color:var(--primary)">gradient</span>
                Faixa de Destaque — Hero
              </div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
                Aparece na faixa verde logo abaixo do menu, em todas as páginas.
              </div>
              <div class="form-group">
                <label class="form-label">Título do Hero</label>
                <input type="text" name="hero_title" class="form-control"
                       value="<?= htmlspecialchars($settings['hero_title'] ?? '') ?>"
                       placeholder="<?= htmlspecialchars($settings['site_tagline'] ?? getSetting('site_tagline','Unidade de Saúde')) ?>">
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px">
                  Nome da unidade exibido na faixa verde. Se vazio, usa <strong><?= htmlspecialchars($settings['site_tagline'] ?? 'Subtítulo') ?></strong>.
                </div>
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Subtítulo do Hero</label>
                <input type="text" name="hero_subtitle" class="form-control"
                       value="<?= htmlspecialchars($settings['hero_subtitle'] ?? '') ?>"
                       placeholder="<?= htmlspecialchars(($settings['site_tagline'] ?? getSetting('site_tagline','Unidade de Saúde')) . ' — Portal de Comunicação Institucional') ?>">
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px">Texto menor abaixo do título. Deixe vazio para ocultar.</div>
              </div>
            </div>

            <!-- Logo upload com preview -->
            <div class="form-group">
              <label class="form-label">Logo do Site</label>
              <?php
              $logoFile = $settings['site_logo'] ?? '';
              $logoPath = UPLOAD_DIR . $logoFile;
              $hasLogo  = $logoFile && file_exists($logoPath);
              ?>
              <?php if ($hasLogo): ?>
              <div style="margin-bottom:12px;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);display:flex;align-items:center;gap:12px">
                <img src="<?= UPLOAD_URL . htmlspecialchars($logoFile) ?>"
                     id="logoPreview"
                     style="max-height:48px;max-width:160px;object-fit:contain;border-radius:6px"
                     alt="Logo atual">
                <span class="text-muted text-sm"><?= htmlspecialchars(basename($logoFile)) ?></span>
              </div>
              <?php else: ?>
              <div style="margin-bottom:12px">
                <img id="logoPreview" src="" style="max-height:48px;display:none;border-radius:6px" alt="">
              </div>
              <?php endif; ?>

              <div class="upload-area" style="padding:16px">
                <input type="file" name="site_logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                       onchange="previewLogo(this)">
                <div class="upload-icon" style="margin-bottom:6px">
                  <span class="material-icons" style="font-size:28px">cloud_upload</span>
                </div>
                <p style="font-size:13px">Clique para selecionar o logo</p>
                <div class="upload-hint">JPG, PNG, GIF, WEBP, SVG — Máx 15MB</div>
              </div>
            </div>

            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Texto do Rodapé</label>
              <textarea name="footer_text" class="form-control" rows="2"><?= s($settings,'footer_text') ?></textarea>
            </div>
          </div>
        </div>

        <!-- ── Aparência + Auth ── -->
        <div style="display:flex;flex-direction:column;gap:24px">
          <div class="card">
            <div class="card-header">
              <span class="card-title">
                <span class="material-icons" style="font-size:18px;color:var(--primary);margin-right:6px;vertical-align:middle">palette</span>
                Aparência
              </span>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Cor Primária — Ciano-esverdeado (navbar, botões, links)</label>
                <div style="display:flex;gap:12px;align-items:center">
                  <input type="color" name="primary_color" id="primaryColor"
                         value="<?= s($settings,'primary_color','#00897B') ?>"
                         style="width:56px;height:48px;padding:3px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer"
                         oninput="syncColor('primary',this.value)">
                  <div>
                    <code id="primaryHex" style="font-size:14px;font-weight:700"><?= s($settings,'primary_color','#00897B') ?></code>
                    <div class="text-xs text-muted" style="margin-top:2px">Padrão: #00897B</div>
                  </div>
                  <div id="primarySwatch" style="width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:<?= s($settings,'primary_color','#00897B') ?>"></div>
                </div>
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Cor Secundária — Verde Água Escuro (gradientes, rodapé)</label>
                <div style="display:flex;gap:12px;align-items:center">
                  <input type="color" name="secondary_color" id="secondaryColor"
                         value="<?= s($settings,'secondary_color','#004D40') ?>"
                         style="width:56px;height:48px;padding:3px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer"
                         oninput="syncColor('secondary',this.value)">
                  <div>
                    <code id="secondaryHex" style="font-size:14px;font-weight:700"><?= s($settings,'secondary_color','#004D40') ?></code>
                    <div class="text-xs text-muted" style="margin-top:2px">Padrão: #004D40</div>
                  </div>
                  <div id="secondarySwatch" style="width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:<?= s($settings,'secondary_color','#004D40') ?>"></div>
                </div>
              </div>
              <!-- Preview ao vivo -->
              <div style="margin-top:16px;padding:14px;background:var(--bg);border-radius:var(--radius-sm);border:1px solid var(--border)">
                <div class="text-xs text-muted" style="margin-bottom:10px">Preview ao vivo:</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                  <div id="previewBtn" style="padding:9px 20px;border-radius:8px;font-weight:700;font-size:13px;color:#fff;background:<?= s($settings,'primary_color','#00897B') ?>">Botão Primário</div>
                  <div id="previewBadge" style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:<?= s($settings,'primary_color','#00897B') ?>22;color:<?= s($settings,'primary_color','#00897B') ?>">Badge</div>
                  <div id="previewGrad" style="padding:9px 20px;border-radius:8px;font-size:13px;font-weight:700;color:#fff;background:linear-gradient(135deg,<?= s($settings,'primary_color','#00897B') ?>,<?= s($settings,'secondary_color','#004D40') ?>)">Gradiente</div>
                </div>
              </div>
              <div class="alert alert-info" style="margin-top:14px;font-size:12px">
                <span class="material-icons" style="font-size:16px">info</span>
                Para cores personalizadas avançadas edite <code>assets/css/style.css</code> (variável <code>--primary</code>).
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">
                <span class="material-icons" style="font-size:18px;color:var(--primary);margin-right:6px;vertical-align:middle">tune</span>
                Geral & Autenticação
              </span>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Posts por página</label>
                <input type="number" name="posts_per_page" class="form-control"
                       value="<?= s($settings,'posts_per_page','10') ?>" min="1" max="50">
              </div>
              <div class="form-group">
                <label class="form-label">Intervalo do carrossel de fotos (segundos)</label>
                <input type="number" name="gallery_interval" class="form-control"
                       value="<?= s($settings,'gallery_interval','7') ?>" min="2" max="60">
                <div class="text-xs text-muted" style="margin-top:4px">Tempo entre cada foto na galeria das publicações. Padrão: 7 segundos.</div>
              </div>
              <div class="form-group">
                <label class="form-label">Timeout da sessão (minutos)</label>
                <input type="number" name="session_timeout" class="form-control"
                       value="<?= s($settings,'session_timeout','480') ?>" min="30" max="1440">
                <div class="text-xs text-muted" style="margin-top:4px">480 = 8 horas (padrão)</div>
              </div>
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
                <input type="checkbox" name="allow_registration" value="1"
                       <?= ($settings['allow_registration']??'0')==='1'?'checked':'' ?>>
                Permitir auto-cadastro de usuários
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center">
            <span class="material-icons">save</span> Salvar Configurações
          </button>
        </div>

      </div><!-- /grid -->
    </form>

    <!-- Info do sistema -->
    <div class="card" style="margin-top:28px">
      <div class="card-header"><span class="card-title">Informações do Sistema</span></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;font-size:13px">
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">Versão</div>
            <div style="font-weight:700;margin-top:3px"><?= APP_VERSION ?></div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">PHP</div>
            <div style="font-weight:700;margin-top:3px"><?= PHP_VERSION ?></div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">GD (imagens)</div>
            <div style="font-weight:700;margin-top:3px;color:<?= extension_loaded('gd')?'#28a745':'#dc3545' ?>">
              <?= extension_loaded('gd') ? '✓ Ativo' : '✗ Não disponível' ?>
            </div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">Publicações</div>
            <div style="font-weight:700;margin-top:3px"><?= Database::count('SELECT COUNT(*) FROM posts') ?></div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">Usuários</div>
            <div style="font-weight:700;margin-top:3px"><?= Database::count('SELECT COUNT(*) FROM users') ?></div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">Módulos ativos</div>
            <div style="font-weight:700;margin-top:3px"><?= Database::count("SELECT COUNT(*) FROM modules WHERE active=1") ?></div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">Upload Dir</div>
            <div style="font-weight:700;margin-top:3px;font-size:11px;color:<?= is_writable(UPLOAD_DIR)?'#28a745':'#dc3545' ?>">
              <?= is_writable(UPLOAD_DIR) ? '✓ Gravável' : '✗ Sem permissão' ?>
            </div>
          </div>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px">
            <div class="text-muted text-xs">MySQL</div>
            <div style="font-weight:700;margin-top:3px;font-size:11px">
              <?= Database::fetch('SELECT VERSION() as v')['v'] ?? '—' ?>
            </div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
<script>
function syncColor(type, hex) {
  // Atualizar hex display e swatch
  document.getElementById(type + 'Hex').textContent = hex;
  document.getElementById(type + 'Swatch').style.background = hex;

  // Preview ao vivo
  const p = document.getElementById('primaryColor').value;
  const s = document.getElementById('secondaryColor').value;

  const btn = document.getElementById('previewBtn');
  if (btn) btn.style.background = p;

  const badge = document.getElementById('previewBadge');
  if (badge) { badge.style.background = p + '22'; badge.style.color = p; }

  const grad = document.getElementById('previewGrad');
  if (grad) grad.style.background = 'linear-gradient(135deg,' + p + ',' + s + ')';

  // Aplicar ao CSS da página em tempo real
  document.documentElement.style.setProperty('--primary', p);
  document.documentElement.style.setProperty('--primary-dark', shiftColor(p, -20));
  document.documentElement.style.setProperty('--primary-light', shiftColor(p, 40));
  document.documentElement.style.setProperty('--accent2', s);
}

// Clareia (+) ou escurece (-) uma cor hex
function shiftColor(hex, amount) {
  const r = Math.min(255, Math.max(0, parseInt(hex.slice(1,3),16) + amount));
  const g = Math.min(255, Math.max(0, parseInt(hex.slice(3,5),16) + amount));
  const b = Math.min(255, Math.max(0, parseInt(hex.slice(5,7),16) + amount));
  return '#' + [r,g,b].map(v => v.toString(16).padStart(2,'0')).join('');
}
</script>
<script>
function previewLogo(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('logoPreview');
    if (img) { img.src = e.target.result; img.style.display = 'block'; }
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
</body>
</html>
