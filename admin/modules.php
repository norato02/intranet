<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image.php';

requireEditor();

$action   = $_GET['action'] ?? 'list';
$cat      = $_GET['cat'] ?? 'sistema';
$id       = (int) ($_GET['id'] ?? 0);
$isDark   = ($_SESSION['dark_mode'] ?? false);
$success  = $error = '';

// DELETE
if ($action === 'delete' && $id) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $m = Database::fetch('SELECT icon_image FROM modules WHERE id=?', [$id]);
        if ($m && $m['icon_image']) @unlink(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $m['icon_image']);
        Database::query('DELETE FROM modules WHERE id=?', [$id]);
        header('Location: modules.php?cat=' . $cat . '&deleted=1'); exit;
    }
}

// TOGGLE ACTIVE
if ($action === 'toggle' && $id) {
    Database::query('UPDATE modules SET active = NOT active WHERE id=?', [$id]);
    header('Location: modules.php?cat=' . $cat); exit;
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new','edit'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $name      = sanitize($_POST['name'] ?? '');
        $desc      = sanitize($_POST['description'] ?? '');
        $url       = trim($_POST['url'] ?? '');
        $icon      = sanitize($_POST['icon'] ?? 'link');
        $color     = sanitize($_POST['color'] ?? '#00897B');
        $catSave   = in_array($_POST['category'] ?? '', ['sistema','link_rapido','navbar']) ? $_POST['category'] : 'sistema';
        $target    = ($_POST['target'] ?? '') === '_self' ? '_self' : '_blank';
        $isPublic  = isset($_POST['is_public']) ? 1 : 0;
        $sortOrd   = (int) ($_POST['sort_order'] ?? 0);
        $removeImg = isset($_POST['remove_icon_image']) && $_POST['remove_icon_image'] === '1';

        if (!$name || !$url) { $error = 'Nome e URL são obrigatórios.'; }

        $iconImage = '';
        if (!$error) {
            // Upload de imagem para o ícone do sistema
            if (!empty($_FILES['icon_image']['name'])) {
                $fileErr = $_FILES['icon_image']['error'];
                if ($fileErr === UPLOAD_ERR_OK) {
                    $up = uploadIconFile($_FILES['icon_image'], 'modules');
                    if (!$up['success']) {
                        $error = 'Erro no upload: ' . $up['message'];
                    } else {
                        $iconImage = basename($up['filename']);
                    }
                } elseif ($fileErr !== UPLOAD_ERR_NO_FILE) {
                    $phpErrors = [
                        UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que upload_max_filesize no php.ini (' . ini_get('upload_max_filesize') . ')',
                        UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que MAX_FILE_SIZE no formulário',
                        UPLOAD_ERR_PARTIAL    => 'Upload parcial — tente novamente',
                        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
                        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco',
                        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão PHP',
                    ];
                    $error = 'Erro de upload: ' . ($phpErrors[$fileErr] ?? "Código {$fileErr}");
                }
                // UPLOAD_ERR_NO_FILE = nenhum arquivo selecionado, ok
            }
        }

        if (!$error) {
            if ($action === 'new') {
                Database::insert(
                    "INSERT INTO modules (name,description,url,icon,icon_image,color,category,target,is_public,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$name,$desc,$url,$icon,$iconImage,$color,$catSave,$target,$isPublic,$sortOrd]
                );
                header('Location: modules.php?cat=' . $catSave . '&saved=1'); exit;
            } else {
                $existing = Database::fetch('SELECT icon_image FROM modules WHERE id=?', [$id]);
                if ($removeImg && $existing['icon_image']) {
                    @unlink(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $existing['icon_image']);
                    $iconImage = '';
                } elseif (!$iconImage) {
                    $iconImage = $existing['icon_image'] ?? '';
                } else {
                    if ($existing['icon_image']) @unlink(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $existing['icon_image']);
                }
                Database::query(
                    "UPDATE modules SET name=?,description=?,url=?,icon=?,icon_image=?,color=?,category=?,target=?,is_public=?,sort_order=? WHERE id=?",
                    [$name,$desc,$url,$icon,$iconImage,$color,$catSave,$target,$isPublic,$sortOrd,$id]
                );
                $success = 'Módulo atualizado com sucesso!';
            }
        }
    }
}

$modules  = Database::fetchAll("SELECT * FROM modules WHERE category=? ORDER BY sort_order, name", [$cat]);
$module   = $id ? Database::fetch('SELECT * FROM modules WHERE id=?', [$id]) : null;
$catLabel = match($cat) { 'link_rapido' => 'Links Rápidos', 'navbar' => 'Navbar', default => 'Sistemas' };
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Módulos — Admin</title>
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
    <div class="brand-text">Admin <small>Módulos</small></div>
  </a>
  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark?'on':'' ?>"></button>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm"><span class="material-icons">home</span></a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons">logout</span></a>
    <button class="btn btn-ghost btn-icon mobile-menu-btn" id="sidebarToggle"><span class="material-icons">menu</span></button>
  </div>
</nav>

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-label">Conteúdo</div>
    <a href="index.php" class="sidebar-link"><span class="material-icons">dashboard</span> Dashboard</a>
    <a href="posts.php?type=comunicado" class="sidebar-link"><span class="material-icons">campaign</span> Comunicados</a>
    <a href="posts.php?type=noticia" class="sidebar-link"><span class="material-icons">newspaper</span> Notícias</a>
    <a href="posts.php?action=new" class="sidebar-link"><span class="material-icons">add_circle</span> Novo Post</a>
    <a href="categories.php" class="sidebar-link"><span class="material-icons">label</span> Categorias</a>
    <div class="sidebar-label">Módulos</div>
    <a href="modules.php?cat=sistema" class="sidebar-link <?= $cat==='sistema'?'active':'' ?>">
      <span class="material-icons">apps</span> Sistemas
    </a>
    <a href="modules.php?cat=link_rapido" class="sidebar-link <?= $cat==='link_rapido'?'active':'' ?>">
      <span class="material-icons">bolt</span> Links Rápidos
    </a>
    <a href="nav.php" class="sidebar-link"><span class="material-icons">menu_open</span> Menu Nav</a>
    <a href="ramais.php" class="sidebar-link"><span class="material-icons">phone_in_talk</span> Ramais</a>
    <?php if (isAdmin()): ?>
    <div class="sidebar-label">Administração</div>
    <a href="users.php" class="sidebar-link"><span class="material-icons">group</span> Usuários</a>
    <a href="settings.php" class="sidebar-link"><span class="material-icons">settings</span> Configurações</a>
    <a href="system.php" class="sidebar-link"><span class="material-icons">build</span> Sistema</a>
    <?php endif; ?>
  </aside>

  <main class="admin-main">

  <?php if ($action === 'list'): ?>

    <!-- Alertas -->
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Módulo excluído com sucesso.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Módulo salvo com sucesso.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h1 style="font-size:22px"><?= $catLabel ?></h1>
      <a href="modules.php?action=new&cat=<?= $cat ?>" class="btn btn-primary">
        <span class="material-icons">add</span> Novo Módulo
      </a>
    </div>

    <!-- Tabs de categoria -->
    <div class="d-flex gap-1 mb-3">
      <a href="modules.php?cat=sistema" class="btn <?= $cat==='sistema'?'btn-primary':'btn-ghost' ?>">
        <span class="material-icons">apps</span> Sistemas
      </a>
      <a href="modules.php?cat=link_rapido" class="btn <?= $cat==='link_rapido'?'btn-primary':'btn-ghost' ?>">
        <span class="material-icons">bolt</span> Links Rápidos
      </a>
    </div>

    <div class="alert alert-info">
      <span class="material-icons">info</span>
      <span>Todos os <strong>Sistemas</strong> são exibidos publicamente (sem login). Arraste as linhas para reordenar.</span>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th width="50">⠿</th>
              <th width="70">Ícone</th>
              <th>Nome</th>
              <th>Descrição</th>
              <th>URL</th>
              <th>Público</th>
              <th>Ativo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody id="sortableList">
            <?php foreach ($modules as $m): ?>
            <tr draggable="true" data-id="<?= $m['id'] ?>">
              <td style="cursor:grab;color:var(--text-muted)"><span class="material-icons" style="font-size:18px">drag_indicator</span></td>
              <td>
                <div style="width:48px;height:48px;border-radius:12px;background:<?= htmlspecialchars($m['color']) ?>22;display:flex;align-items:center;justify-content:center;overflow:hidden">
                  <?php if (!empty($m['icon_image']) && file_exists(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $m['icon_image'])): ?>
                  <img src="<?= UPLOAD_URL ?>modules/<?= htmlspecialchars($m['icon_image']) ?>" alt="<?= htmlspecialchars($m['name']) ?>" style="width:36px;height:36px;object-fit:contain;border-radius:6px;background:var(--bg)">
                  <?php else: ?>
                  <span class="material-icons" style="font-size:24px;color:<?= htmlspecialchars($m['color']) ?>"><?= htmlspecialchars($m['icon']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="font-weight:700;font-size:14px"><?= htmlspecialchars($m['name']) ?></td>
              <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($m['description'] ?? '—') ?></td>
              <td style="font-size:12px;color:var(--text-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= $m['url'] === '#' ? '<span style="color:var(--text-light);font-style:italic">URL não definida</span>' : htmlspecialchars($m['url']) ?>
              </td>
              <td>
                <?= $m['is_public']
                  ? '<span class="badge badge-success">Público</span>'
                  : '<span class="badge" style="background:var(--bg);color:var(--text-muted);border:1px solid var(--border)">Restrito</span>' ?>
              </td>
              <td>
                <a href="modules.php?action=toggle&id=<?= $m['id'] ?>&cat=<?= $cat ?>" title="<?= $m['active']?'Desativar':'Ativar' ?>">
                  <span class="material-icons" style="font-size:28px;color:<?= $m['active']?'#28a745':'#dc3545' ?>"><?= $m['active']?'toggle_on':'toggle_off' ?></span>
                </a>
              </td>
              <td>
                <div style="display:flex;gap:4px">
                  <a href="modules.php?action=edit&id=<?= $m['id'] ?>&cat=<?= $cat ?>" class="btn btn-ghost btn-sm btn-icon" title="Editar">
                    <span class="material-icons" style="font-size:18px">edit</span>
                  </a>
                  <a href="modules.php?action=delete&id=<?= $m['id'] ?>&cat=<?= $cat ?>&csrf=<?= csrf() ?>"
                     class="btn btn-ghost btn-sm btn-icon" style="color:#dc3545"
                     data-confirm="Excluir o módulo '<?= htmlspecialchars($m['name']) ?>'?">
                    <span class="material-icons" style="font-size:18px">delete</span>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($modules)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">
              Nenhum módulo cadastrado. <a href="modules.php?action=new&cat=<?= $cat ?>">Adicionar agora →</a>
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-muted text-sm mt-2">💡 Arraste pelo ícone <span class="material-icons" style="font-size:14px;vertical-align:middle">drag_indicator</span> para reordenar.</p>

  <?php elseif (in_array($action, ['new','edit'])): ?>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <a href="modules.php?cat=<?= $cat ?>" class="btn btn-ghost btn-sm"><span class="material-icons">arrow_back</span></a>
      <h1 style="font-size:20px"><?= $action==='new'?'Novo Módulo':'Editar Módulo' ?></h1>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span><?= $success ?></div><?php endif; ?>

    <form method="POST" action="modules.php?action=<?= $action ?>&id=<?= $id ?>&cat=<?= $cat ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="remove_icon_image" id="removeIconFlag" value="0">
    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">
      <!-- Form principal -->
      <div class="card card-body">

          <div class="form-group">
            <label class="form-label">Nome do Sistema / Link *</label>
            <input type="text" name="name" class="form-control" required
                   value="<?= htmlspecialchars($module['name']??'') ?>"
                   placeholder="Ex: GLPI, Portal SESPA, SALUTEM...">
          </div>

          <div class="form-group">
            <label class="form-label">Descrição curta</label>
            <input type="text" name="description" class="form-control"
                   value="<?= htmlspecialchars($module['description']??'') ?>"
                   placeholder="Ex: Sistema de Chamados de TI">
          </div>

          <div class="form-group">
            <label class="form-label">URL de acesso *</label>
            <input type="text" name="url" class="form-control" required
                   value="<?= htmlspecialchars($module['url']??'') ?>"
                   placeholder="https://sistema.exemplo.com ou # (sem URL ainda)">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
              Use <code>#</code> temporariamente se a URL ainda não estiver definida.
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Categoria</label>
              <select name="category" class="form-control">
                <option value="sistema" <?= ($module['category']??$cat)==='sistema'?'selected':'' ?>>⚙️ Sistema</option>
                <option value="link_rapido" <?= ($module['category']??$cat)==='link_rapido'?'selected':'' ?>>⚡ Link Rápido</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Abertura do link</label>
              <select name="target" class="form-control">
                <option value="_blank" <?= ($module['target']??'_blank')==='_blank'?'selected':'' ?>>Nova aba</option>
                <option value="_self" <?= ($module['target']??'')==='_self'?'selected':'' ?>>Mesma aba</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Ícone (Material Icons)</label>
              <input type="text" name="icon" class="form-control" id="iconInput"
                     value="<?= htmlspecialchars($module['icon']??'link') ?>"
                     placeholder="build, analytics, biotech...">
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                <a href="https://fonts.google.com/icons" target="_blank">Ver ícones disponíveis ↗</a>
                · Usado quando não há imagem de ícone.
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Cor do cartão</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" name="color" id="colorPicker"
                       value="<?= htmlspecialchars($module['color']??'#00897B') ?>"
                       style="width:52px;height:44px;padding:3px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                <input type="text" id="colorText" class="form-control"
                       value="<?= htmlspecialchars($module['color']??'#00897B') ?>"
                       placeholder="#00897B" style="font-family:monospace;font-size:13px">
              </div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Ordem de exibição</label>
              <input type="number" name="sort_order" class="form-control"
                     value="<?= $module['sort_order']??0 ?>" min="0" placeholder="1, 2, 3...">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
                <input type="checkbox" name="is_public" value="1"
                       <?= ($module['is_public']??1)?'checked':'' ?>>
                Visível sem login (área pública)
              </label>
            </div>
          </div>

          <div style="display:flex;gap:10px;margin-top:8px">
            <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Salvar Módulo</button>
            <a href="modules.php?cat=<?= $cat ?>" class="btn btn-ghost">Cancelar</a>
          </div>
      </div>

      <!-- Painel do ícone / preview -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- Upload imagem ícone -->
        <div class="card card-body">
          <div style="font-size:14px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px">
            <span class="material-icons" style="color:var(--primary);font-size:18px">image</span>
            Imagem do Ícone
          </div>
          <p class="text-muted text-sm" style="margin-bottom:14px">
            Faça upload do logotipo do sistema. Sobrepõe o ícone de texto.
            Formatos: JPG, PNG, GIF, WEBP, BMP — Máx 5MB.
          </p>

          <?php
          $hasIcon = !empty($module['icon_image']) && file_exists(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $module['icon_image']);
          ?>

          <!-- Preview do ícone atual -->
          <div id="iconPreviewWrap" style="<?= $hasIcon?'':'display:none' ?>;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px">
              <div id="iconPreviewBox" style="width:64px;height:64px;border-radius:14px;background:<?= htmlspecialchars($module['color']??'#00897B') ?>22;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                <img id="iconPreviewImg"
                     src="<?= $hasIcon ? UPLOAD_URL . 'modules/' . htmlspecialchars($module['icon_image']) : '' ?>"
                     alt="" style="width:40px;height:40px;object-fit:contain">
              </div>
              <div>
                <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($module['name']??'') ?></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($module['description']??'') ?></div>
              </div>
            </div>
            <button type="button" onclick="removeIconImage()"
                    style="margin-top:8px;width:100%;padding:7px;border:1.5px solid #dc3545;background:transparent;color:#dc3545;border-radius:var(--radius-sm);cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:6px">
              <span class="material-icons" style="font-size:16px">delete</span> Remover imagem
            </button>
          </div>

          <!-- Área upload — input único dentro do form principal -->
          <div class="upload-area" id="iconUploadArea" style="<?= $hasIcon?'display:none':'' ?>">
            <label for="iconFileMain" style="cursor:pointer;display:block">
              <div class="upload-icon"><span class="material-icons">image</span></div>
              <p><strong>Clique ou arraste</strong> o logotipo aqui</p>
              <div class="upload-hint">PNG, SVG, ICO, JPG — Máx 5MB</div>
            </label>
          </div>
          <!-- Input único — diretamente no form principal, sem DataTransfer -->
          <input type="file" name="icon_image" id="iconFileMain"
                 accept="image/*,.svg,.ico,.cur"
                 style="display:none"
                 onchange="previewIconImage(this)">
        </div>

        <!-- Preview do cartão -->
        <div class="card card-body">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--text-muted)">Preview do Cartão</div>
          <div style="border:1px solid var(--border);border-radius:var(--radius);padding:20px 12px;text-align:center;position:relative;overflow:hidden" id="cardPreview">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:var(--prev-color, #00897B)" id="previewBar"></div>
            <div id="previewIconBox" style="width:60px;height:60px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;background:var(--prev-color-bg,rgba(0,137,123,.12))">
              <img id="previewIconImgEl" src="<?= $hasIcon ? UPLOAD_URL . 'modules/' . htmlspecialchars($module['icon_image']) : '' ?>" style="width:36px;height:36px;object-fit:contain;border-radius:6px;<?= $hasIcon?'':'display:none' ?>" alt="">
              <span class="material-icons" id="previewIconMI" style="font-size:30px;color:var(--prev-color,#00897B);<?= $hasIcon?'display:none':'' ?>"><?= htmlspecialchars($module['icon']??'link') ?></span>
            </div>
            <div id="previewName" style="font-size:13px;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($module['name']??'Sistema') ?></div>
            <div id="previewDesc" style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($module['description']??'Descrição') ?></div>
          </div>
        </div>
      </div>
    </div>
    </form>

  <?php endif; ?>

  </main>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
<script>
// Sync color picker ↔ text input ↔ preview
const colorPicker = document.getElementById('colorPicker');
const colorText   = document.getElementById('colorText');
const previewBar  = document.getElementById('previewBar');
const prevIconMI  = document.getElementById('previewIconMI');
const prevBox     = document.getElementById('previewIconBox');

function applyColor(hex) {
  if (!/^#[0-9a-f]{3,8}$/i.test(hex)) return;
  if (previewBar) previewBar.style.background = hex;
  if (prevIconMI) prevIconMI.style.color = hex;
  if (prevBox)    prevBox.style.background = hex + '22';
  const iconPreviewBox2 = document.getElementById('iconPreviewBox');
  if (iconPreviewBox2) iconPreviewBox2.style.background = hex + '22';
}

if (colorPicker && colorText) {
  colorPicker.addEventListener('input', () => { colorText.value = colorPicker.value; applyColor(colorPicker.value); });
  colorText.addEventListener('input', () => {
    if (/^#[0-9a-f]{6}$/i.test(colorText.value)) { colorPicker.value = colorText.value; applyColor(colorText.value); }
  });
  applyColor(colorPicker.value);
}

// Sync icon name → preview
const iconInput = document.getElementById('iconInput');
if (iconInput) {
  iconInput.addEventListener('input', () => {
    const mi = document.getElementById('previewIconMI');
    if (mi) mi.textContent = iconInput.value || 'link';
  });
}

// Sync name → preview
document.querySelector('input[name=name]')?.addEventListener('input', e => {
  const el = document.getElementById('previewName');
  if (el) el.textContent = e.target.value || 'Sistema';
});
document.querySelector('input[name=description]')?.addEventListener('input', e => {
  const el = document.getElementById('previewDesc');
  if (el) el.textContent = e.target.value || 'Descrição';
});

// Preview icon image upload
function previewIconImage(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const reader = new FileReader();
  reader.onload = e => {
    // Preview no form
    const mainImg = document.getElementById('iconPreviewImg');
    if (mainImg) mainImg.src = e.target.result;
    const wrap = document.getElementById('iconPreviewWrap');
    const area = document.getElementById('iconUploadArea');
    if (wrap) wrap.style.display = 'block';
    if (area) area.style.display = 'none';
    // Preview no card
    const prevImg = document.getElementById('previewIconImgEl');
    const prevMI  = document.getElementById('previewIconMI');
    if (prevImg) { prevImg.src = e.target.result; prevImg.style.display = 'block'; }
    if (prevMI)  prevMI.style.display = 'none';
    // Mostrar nome do arquivo selecionado
    const hint = document.querySelector('#iconUploadArea .upload-hint');
    if (hint) hint.textContent = file.name + ' selecionado';
  };
  // SVG: ler como texto funciona melhor para preview
  if (file.type === 'image/svg+xml' || file.name.toLowerCase().endsWith('.svg')) {
    reader.readAsDataURL(file);
  } else {
    reader.readAsDataURL(file);
  }
}

function removeIconImage() {
  document.getElementById('removeIconFlag').value = '1';
  document.getElementById('iconPreviewWrap').style.display = 'none';
  document.getElementById('iconUploadArea').style.display = 'block';
  const prevImg = document.getElementById('previewIconImgEl');
  const prevMI  = document.getElementById('previewIconMI');
  if (prevImg) { prevImg.src = ''; prevImg.style.display = 'none'; }
  if (prevMI)  prevMI.style.display = 'block';
  const main = document.getElementById('iconFileMain');
  if (main) main.value = '';
}

// Click on upload area triggers file input
const iconUploadArea = document.getElementById('iconUploadArea');
const iconFileMain   = document.getElementById('iconFileMain');
if (iconUploadArea && iconFileMain) {
  iconUploadArea.addEventListener('click', () => iconFileMain.click());
  iconUploadArea.addEventListener('dragover', e => { e.preventDefault(); iconUploadArea.classList.add('drag-over'); });
  iconUploadArea.addEventListener('dragleave', () => iconUploadArea.classList.remove('drag-over'));
  iconUploadArea.addEventListener('drop', e => {
    e.preventDefault();
    iconUploadArea.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
      // Criar um DataTransfer para atribuir ao input (fallback para drag)
      try {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        iconFileMain.files = dt.files;
      } catch(err) { /* fallback sem DataTransfer */ }
      previewIconImage(iconFileMain.files.length ? iconFileMain : { files: e.dataTransfer.files });
    }
  });
}
</script>
</body>
</html>
