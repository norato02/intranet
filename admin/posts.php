<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image.php';

requireEditor();

$action  = $_GET['action'] ?? 'list';
$type    = $_GET['type'] ?? 'comunicado';
$id      = (int) ($_GET['id'] ?? 0);
$isDark  = ($_SESSION['dark_mode'] ?? false);
$success = $error = '';

// DELETE POST
if ($action === 'delete' && $id && isEditor()) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $post = Database::fetch('SELECT cover_image FROM posts WHERE id=?', [$id]);
        if ($post && $post['cover_image']) deleteImage($post['cover_image']);
        $gallery = Database::fetchAll('SELECT filename FROM post_gallery WHERE post_id=?', [$id]);
        foreach ($gallery as $g) deleteImage('gallery/' . $g['filename']);
        Database::query('DELETE FROM posts WHERE id=?', [$id]);
        header('Location: posts.php?type=' . $type . '&deleted=1'); exit;
    }
}

// DELETE GALLERY IMAGE
if ($action === 'del_gallery' && $id) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { die('Token inválido'); }
    $img = Database::fetch('SELECT * FROM post_gallery WHERE id=?', [$id]);
    if ($img) { deleteImage('gallery/' . $img['filename']); Database::query('DELETE FROM post_gallery WHERE id=?', [$id]); }
    header('Location: posts.php?action=edit&id=' . ($_GET['post_id'] ?? 0) . '&type=' . $type . '&saved=1'); exit;
}

// UPLOAD VIDEO DE CAPA (form separado — suporta arquivos grandes)
if ($action === 'upload_cover_video' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        $maxPost = ini_get('post_max_size');
        $error = "Vídeo muito grande. Limite atual: {$maxPost}. Aumente post_max_size no php.ini.";
    } elseif (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Token inválido.';
    } elseif (!empty($_FILES['cover_video_file']['name']) && $_FILES['cover_video_file']['error'] === UPLOAD_ERR_OK) {
        $vf   = $_FILES['cover_video_file'];
        $vext = strtolower(pathinfo($vf['name'], PATHINFO_EXTENSION));
        $vdir = UPLOAD_DIR . 'gallery' . DIRECTORY_SEPARATOR;
        if (!is_dir($vdir)) mkdir($vdir, 0755, true);
        $vfname = 'vid_' . uniqid('', true) . '.' . ($vext ?: 'mp4');
        if (move_uploaded_file($vf['tmp_name'], $vdir . $vfname)) {
            $vUrl  = BASE_URL . '/uploads/gallery/' . $vfname;
            $vType = in_array($vext, ['webm']) ? 'mp4' : 'mp4';
            Database::query(
                "UPDATE posts SET cover_video_url=?, cover_video_type=?, media_type='video' WHERE id=?",
                [$vUrl, $vType, $id]
            );
            header('Location: posts.php?action=edit&id='.$id.'&type='.($_GET['type']??'comunicado').'&saved=1'); exit;
        } else {
            $error = 'Erro ao salvar vídeo. Verifique as permissões da pasta uploads/gallery/';
        }
    } elseif (isset($_FILES['cover_video_file']['error'])) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE  => 'Vídeo maior que upload_max_filesize no php.ini ('.ini_get('upload_max_filesize').')',
            UPLOAD_ERR_FORM_SIZE => 'Vídeo maior que o limite do formulário',
            UPLOAD_ERR_PARTIAL   => 'Upload parcial — tente novamente',
            UPLOAD_ERR_NO_FILE   => 'Nenhum arquivo selecionado',
        ];
        $errCode = $_FILES['cover_video_file']['error'];
        $error = $phpErrors[$errCode] ?? "Erro de upload: código {$errCode}";
    }
    if (!empty($error)) {
        // Redirect back with error
        header('Location: posts.php?action=edit&id='.$id.'&type='.($_GET['type']??'comunicado').'&upload_error='.urlencode($error)); exit;
    }
}

// DELETE VIDEO
if ($action === 'del_video' && $id) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { die('Token inválido'); }
    $postId = (int)($_GET['post_id'] ?? 0);
    Database::query('DELETE FROM post_videos WHERE id=? AND post_id=?', [$id, $postId]);
    header('Location: posts.php?action=edit&id=' . $postId . '&type=' . $type . '&saved=1'); exit;
}

// ADD VIDEO
if ($action === 'add_video' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'Token inválido.'; }
    else {
        $postId     = (int)($_POST['post_id'] ?? 0);
        $videoUrl   = trim($_POST['video_url'] ?? '');
        $videoTitle = sanitize($_POST['video_title'] ?? '');
        if ($videoUrl && $postId) {
            $vtype = 'youtube';
            if (str_contains($videoUrl, 'vimeo.com')) $vtype = 'vimeo';
            elseif (str_ends_with(strtolower($videoUrl), '.mp4')) $vtype = 'mp4';
            $ord = Database::count('SELECT COUNT(*) FROM post_videos WHERE post_id=?', [$postId]) + 1;
            Database::insert('INSERT INTO post_videos (post_id,video_type,video_url,title,sort_order) VALUES (?,?,?,?,?)',
                [$postId, $vtype, $videoUrl, $videoTitle, $ord]);
        }
        header('Location: posts.php?action=edit&id=' . $postId . '&type=' . $type . '&saved=1'); exit;
    }
}

// SAVE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new','edit'])) {
    if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        $maxPost = ini_get('post_max_size');
        $error = "Arquivo muito grande. Limite: {$maxPost}. Aumente post_max_size no php.ini.";
    } elseif (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Token inválido. Recarregue a página e tente novamente.';
    } else {
        $title      = sanitize($_POST['title'] ?? '');
        $summary    = trim($_POST['summary'] ?? '');
        $content    = $_POST['content'] ?? '';
        $imgAlt        = sanitize($_POST['cover_image_alt'] ?? '');
        $imgCaption    = sanitize($_POST['cover_image_caption'] ?? '');
        $mediaType      = in_array($_POST['media_type']??'',['image','video','slider']) ? $_POST['media_type'] : 'image';
        $coverVideoUrl  = trim($_POST['cover_video_url'] ?? '');

        // Handle MP4 file upload as cover video
        if ($mediaType === 'video' && !$coverVideoUrl &&
            !empty($_FILES['cover_video_file']['name']) &&
            $_FILES['cover_video_file']['error'] === UPLOAD_ERR_OK) {
            $vf   = $_FILES['cover_video_file'];
            $vext = strtolower(pathinfo($vf['name'], PATHINFO_EXTENSION));
            $vdir = UPLOAD_DIR . 'gallery' . DIRECTORY_SEPARATOR;
            if (!is_dir($vdir)) mkdir($vdir, 0755, true);
            $vfname = 'vid_' . uniqid('',true) . '.' . ($vext ?: 'mp4');
            if (move_uploaded_file($vf['tmp_name'], $vdir . $vfname)) {
                $coverVideoUrl = BASE_URL . '/uploads/gallery/' . $vfname;
            }
        }

        // Auto-detect video type
        if ($coverVideoUrl) {
            if (str_contains($coverVideoUrl, 'youtube.com') || str_contains($coverVideoUrl, 'youtu.be'))
                $coverVideoType = 'youtube';
            elseif (str_contains($coverVideoUrl, 'vimeo.com'))
                $coverVideoType = 'vimeo';
            else
                $coverVideoType = 'mp4';
        } else {
            $coverVideoType = null;
        }

        // Se não for imagem, limpar cover_image ao salvar
        if ($mediaType !== 'image') {
            $_POST['remove_image'] = '0'; // não forçar remoção
        }
        $postType   = in_array($_POST['post_type']??'',['comunicado','noticia']) ? $_POST['post_type'] : 'comunicado';
        $catId      = (int)($_POST['category_id']??0) ?: null;
        $status     = in_array($_POST['status']??'',['draft','published','archived']) ? $_POST['status'] : 'draft';
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isPublic   = isset($_POST['is_public']) ? 1 : 0;
        $removeImg  = ($_POST['remove_image'] ?? '') === '1';
        if (!$title)   $error = 'O título é obrigatório.';
        if (!$content) $error = 'O conteúdo é obrigatório.';
        $cover = '';
        if (!$error) {
            if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $up = uploadImage($_FILES['cover_image'], 'posts');
                if (!$up['success']) $error = $up['message'];
                else $cover = $up['filename'];
            }
        }
        if (!$error) {
            if ($action === 'new') {
                $slug  = uniqueSlug(generateSlug($title), 'posts');
                $pubAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
                $newId = Database::insert(
                    "INSERT INTO posts (title,slug,summary,content,cover_image,cover_image_alt,cover_image_caption,cover_video_url,cover_video_type,media_type,type,category_id,author_id,status,is_featured,is_public,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$title,$slug,$summary,$content,$cover,$imgAlt,$imgCaption,$coverVideoUrl,$coverVideoType,$mediaType,$postType,$catId,$_SESSION['user_id'],$status,$isFeatured,$isPublic,$pubAt]
                );
                handleGalleryUpload($newId);
                handleVideoFileUpload($newId);
                header('Location: posts.php?action=edit&id='.$newId.'&type='.$postType.'&saved=1'); exit;
            } else {
                $existing = Database::fetch('SELECT slug,cover_image FROM posts WHERE id=?', [$id]);
                if ($removeImg && $existing['cover_image']) { deleteImage($existing['cover_image']); $cover=''; }
                elseif (!$cover) $cover = $existing['cover_image'];
                elseif ($existing['cover_image']) deleteImage($existing['cover_image']);
                $slug = uniqueSlug(generateSlug($title), 'posts', $id);
                $exRow = Database::fetch('SELECT published_at FROM posts WHERE id=?', [$id]);
                $finalPubAt = $exRow['published_at'] ?? null;
                if ($status === 'published' && !$finalPubAt) $finalPubAt = date('Y-m-d H:i:s');
                Database::query(
                    "UPDATE posts SET title=?,slug=?,summary=?,content=?,cover_image=?,cover_image_alt=?,cover_image_caption=?,cover_video_url=?,cover_video_type=?,media_type=?,type=?,category_id=?,status=?,is_featured=?,is_public=?,published_at=? WHERE id=?",
                    [$title,$slug,$summary,$content,$cover,$imgAlt,$imgCaption,$coverVideoUrl,$coverVideoType,$mediaType,$postType,$catId,$status,$isFeatured,$isPublic,$finalPubAt,$id]
                );
                handleGalleryUpload($id);
                handleVideoFileUpload($id);
                $success = 'Publicação salva com sucesso!';
            }
        }
    }
}

function handleGalleryUpload(int $postId): void {
    if (empty($_FILES['gallery_images']['name'][0])) return;
    $count = Database::count('SELECT COUNT(*) FROM post_gallery WHERE post_id=?', [$postId]);
    foreach ($_FILES['gallery_images']['name'] as $i => $name) {
        if ($_FILES['gallery_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $file = ['name'=>$name,'type'=>$_FILES['gallery_images']['type'][$i],'tmp_name'=>$_FILES['gallery_images']['tmp_name'][$i],'error'=>$_FILES['gallery_images']['error'][$i],'size'=>$_FILES['gallery_images']['size'][$i]];
        $up = uploadImage($file, 'gallery');
        if ($up['success']) {
            $caption = sanitize($_POST['gallery_caption'][$i] ?? '');
            Database::insert('INSERT INTO post_gallery (post_id,filename,caption,sort_order) VALUES (?,?,?,?)',
                [$postId, basename($up['filename']), $caption, ++$count]);
        }
    }
}

function handleVideoFileUpload(int $postId): void {
    // Verifica se enviou arquivo de vídeo via upload direto
    if (empty($_FILES['gallery_video']['name']) || $_FILES['gallery_video']['error'] !== UPLOAD_ERR_OK) return;
    if (empty($_POST['upload_video_file'])) return;

    $file    = $_FILES['gallery_video'];
    $allowed = ['video/mp4','video/webm','video/ogg','video/mpeg'];
    $extMap  = ['mp4'=>'mp4','webm'=>'webm','ogv'=>'webm','ogg'=>'webm','mpeg'=>'mp4','mpg'=>'mp4'];
    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $outExt  = $extMap[$origExt] ?? 'mp4';

    $dir   = UPLOAD_DIR . 'gallery' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = 'vid_' . uniqid('', true) . '.' . $outExt;
    $dest  = $dir . $fname;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return;

    // Salvar como post_video com URL local
    $title = sanitize($_POST['gallery_video_title'] ?? '');
    $ord   = Database::count('SELECT COUNT(*) FROM post_videos WHERE post_id=?', [$postId]) + 1;
    $url   = BASE_URL . '/uploads/gallery/' . $fname;

    Database::insert(
        'INSERT INTO post_videos (post_id, video_type, video_url, title, sort_order) VALUES (?,?,?,?,?)',
        [$postId, 'mp4', $url, $title, $ord]
    );
}

function ytId(string $url): string {
    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
    return $m[1] ?? '';
}
function vimeoId(string $url): string {
    preg_match('/vimeo\.com\/(\d+)/', $url, $m);
    return $m[1] ?? '';
}

$categories = Database::fetchAll('SELECT * FROM categories ORDER BY name');
$post       = $id ? Database::fetch('SELECT * FROM posts WHERE id=?', [$id]) : null;
$gallery    = $id ? Database::fetchAll('SELECT * FROM post_gallery WHERE post_id=? ORDER BY sort_order', [$id]) : [];
$videos     = $id ? Database::fetchAll('SELECT * FROM post_videos WHERE post_id=? ORDER BY sort_order', [$id]) : [];

function sidebarLinks(string $active = ''): void { ?>
    <div class="sidebar-label">Conteúdo</div>
    <a href="index.php" class="sidebar-link <?= $active==='dash'?'active':'' ?>"><span class="material-icons">dashboard</span> Dashboard</a>
    <a href="posts.php?type=comunicado" class="sidebar-link <?= $active==='com'?'active':'' ?>"><span class="material-icons">campaign</span> Comunicados</a>
    <a href="posts.php?type=noticia" class="sidebar-link <?= $active==='not'?'active':'' ?>"><span class="material-icons">newspaper</span> Notícias</a>
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
<?php }
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= $action==='list'?'Publicações':($action==='new'?'Nova':'Editar') ?> — Admin</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
  <style>
  .tabs{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px}
  .tab-btn{padding:10px 20px;font-size:14px;font-weight:600;color:var(--text-muted);background:none;border:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:.2s;display:flex;align-items:center;gap:6px}
  .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary)}
  .tab-panel{display:none}.tab-panel.active{display:block}
  .gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:12px}
  .gallery-item{position:relative;border-radius:var(--radius-sm);overflow:hidden;aspect-ratio:1;background:var(--bg)}
  .gallery-item img{width:100%;height:100%;object-fit:cover;display:block}
  .gallery-item .del-btn{position:absolute;top:4px;right:4px;background:rgba(220,53,69,.9);color:#fff;border:none;border-radius:4px;padding:3px 6px;cursor:pointer;font-size:11px;display:flex;align-items:center;gap:2px;text-decoration:none}
  .video-list{display:flex;flex-direction:column;gap:10px;margin-top:12px}
  .video-item{display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm)}
  .video-thumb{width:80px;height:52px;border-radius:6px;overflow:hidden;flex-shrink:0;background:var(--border);display:flex;align-items:center;justify-content:center}
  .video-thumb img{width:100%;height:100%;object-fit:cover}
  .video-info{flex:1;min-width:0}
  .video-info strong{font-size:13px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .video-info span{font-size:11px;color:var(--text-muted)}
  .post-edit-grid{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start}
  @media(max-width:900px){.post-edit-grid{grid-template-columns:1fr}}
  </style>
</head>
<body class="fade-in">
<nav class="navbar">
  <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">
    <div class="logo-icon"><span class="material-icons">admin_panel_settings</span></div>
    <div class="brand-text">Admin <small>Publicações</small></div>
  </a>
  <div class="navbar-end">
    <button class="dark-toggle <?= $isDark?'on':'' ?>"></button>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm"><span class="material-icons">home</span></a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#dc3545"><span class="material-icons">logout</span></a>
    <button class="btn btn-ghost btn-icon mobile-menu-btn" id="sidebarToggle"><span class="material-icons">menu</span></button>
  </div>
</nav>
<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar"><?php sidebarLinks($type==='comunicado'?'com':'not'); ?></aside>
  <main class="admin-main">

  <?php if ($action === 'list'): ?>
    <?php $label = $type==='comunicado'?'Comunicados':'Notícias Externas'; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> Excluído.</div><?php endif; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h1 style="font-size:22px"><?= $label ?></h1>
      <a href="posts.php?action=new&type=<?= $type ?>" class="btn btn-primary"><span class="material-icons">add</span> Novo</a>
    </div>
    <?php $posts = Database::fetchAll("SELECT p.*,u.name as author FROM posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.type='$type' ORDER BY p.created_at DESC"); ?>
    <div class="card">
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Img</th><th>Título</th><th>Status</th><th>Público</th><th>⭐</th><th>Mídia</th><th>Data</th><th>Ações</th></tr></thead>
          <tbody>
            <?php foreach ($posts as $p):
              $gc = Database::count('SELECT COUNT(*) FROM post_gallery WHERE post_id=?', [$p['id']]);
              $vc = Database::count('SELECT COUNT(*) FROM post_videos WHERE post_id=?', [$p['id']]);
            ?>
            <tr>
              <td><?php if ($p['cover_image'] && file_exists(UPLOAD_DIR.$p['cover_image'])): ?>
                <img src="<?= UPLOAD_URL.htmlspecialchars($p['cover_image']) ?>" style="width:56px;height:36px;object-fit:cover;border-radius:6px">
                <?php else: ?><div style="width:56px;height:36px;background:var(--primary-xlight);border-radius:6px;display:flex;align-items:center;justify-content:center"><span class="material-icons" style="font-size:16px;color:var(--primary-light)">image</span></div><?php endif; ?>
              </td>
              <td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a href="posts.php?action=edit&id=<?= $p['id'] ?>&type=<?= $type ?>"><?= htmlspecialchars($p['title']) ?></a></td>
              <td><?php $sc=['published'=>'badge-success','draft'=>'badge-warning','archived'=>'badge-info'][$p['status']]??'badge-info'; ?><span class="badge <?= $sc ?>"><?= $p['status'] ?></span></td>
              <td><?= $p['is_public']?'<span class="material-icons" style="color:#28a745">check_circle</span>':'<span class="material-icons" style="color:var(--text-muted)">lock</span>' ?></td>
              <td><?= $p['is_featured']?'<span class="material-icons" style="color:#ffc107">star</span>':'—' ?></td>
              <td style="font-size:12px;color:var(--text-muted)">
                <?= $gc?"📷{$gc} ":'' ?><?= $vc?"🎬{$vc}":'' ?><?= (!$gc&&!$vc)?'—':'' ?>
              </td>
              <td class="text-sm text-muted"><?= formatDate($p['created_at']) ?></td>
              <td>
                <a href="posts.php?action=edit&id=<?= $p['id'] ?>&type=<?= $type ?>" class="btn btn-ghost btn-sm btn-icon"><span class="material-icons" style="font-size:18px">edit</span></a>
                <a href="posts.php?action=delete&id=<?= $p['id'] ?>&type=<?= $type ?>&csrf=<?= csrf() ?>" class="btn btn-ghost btn-sm btn-icon" style="color:#dc3545" data-confirm="Excluir publicação e toda sua mídia?"><span class="material-icons" style="font-size:18px">delete</span></a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted)">Nenhuma publicação.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif (in_array($action, ['new','edit'])): ?>
    <?php if (isset($_GET['upload_error'])): ?>
    <div class="alert alert-danger"><span class="material-icons">error</span> <?= htmlspecialchars($_GET['upload_error']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])||$success): ?><div class="alert alert-success" data-auto-dismiss><span class="material-icons">check_circle</span> <?= $success ?: 'Salvo!' ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><span class="material-icons">error</span><?= $error ?></div><?php endif; ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <a href="posts.php?type=<?= $post['type']??$type ?>" class="btn btn-ghost btn-sm"><span class="material-icons">arrow_back</span></a>
      <h1 style="font-size:20px"><?= $action==='new'?'Nova Publicação':'Editar Publicação' ?></h1>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="remove_image" id="removeImageFlag" value="0">
      <div class="post-edit-grid">
        <div>
          <!-- TABS -->
          <div class="tabs">
            <button type="button" class="tab-btn active" onclick="switchTab('conteudo',this)"><span class="material-icons" style="font-size:16px">article</span> Conteúdo</button>
            <button type="button" class="tab-btn" onclick="switchTab('galeria',this)"><span class="material-icons" style="font-size:16px">photo_library</span> Galeria<?php if($gallery): ?> <span class="badge badge-primary" style="padding:2px 7px"><?= count($gallery) ?></span><?php endif; ?></button>
            <button type="button" class="tab-btn" onclick="switchTab('videos',this)"><span class="material-icons" style="font-size:16px">play_circle</span> Vídeos<?php if($videos): ?> <span class="badge badge-primary" style="padding:2px 7px"><?= count($videos) ?></span><?php endif; ?></button>
          </div>
          <!-- TAB Conteúdo -->
          <div id="tab-conteudo" class="tab-panel active">
            <div class="form-group"><label class="form-label">Título *</label>
              <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($post['title']??'') ?>" placeholder="Título da publicação" style="font-size:16px;font-weight:600;padding:13px 16px"></div>
            <div class="form-group"><label class="form-label">Resumo / Chamada</label>
              <textarea name="summary" class="form-control" rows="3" placeholder="Resumo exibido nos cards (aprox. 120 caracteres)"><?= htmlspecialchars($post['summary']??'') ?></textarea></div>
            <div class="form-group"><label class="form-label">Conteúdo *</label>
              <div class="editor-toolbar">
                <?php $btns=[['bold','format_bold'],['italic','format_italic'],['underline','format_underlined'],['strikeThrough','format_strikethrough'],'|',['justifyLeft','format_align_left'],['justifyCenter','format_align_center'],['justifyRight','format_align_right'],'|',['insertUnorderedList','format_list_bulleted'],['insertOrderedList','format_list_numbered'],'|',['h2','title','h2'],['h3','title','h3'],'|',['createLink','link'],['unlink','link_off'],'|',['formatBlock','format_quote','blockquote']];
                foreach($btns as $b){if($b==='|'){echo '<div class="editor-divider"></div>';continue;}$val=isset($b[2])?' data-val="'.$b[2].'"':'';echo '<button class="editor-btn" data-cmd="'.$b[0].'"'.$val.' type="button"><span class="material-icons" style="font-size:18px">'.$b[1].'</span></button>';}?>
              </div>
              <div id="content-editor" contenteditable="true"><?= $post['content']??'' ?></div>
              <input type="hidden" name="content" id="content-hidden" value="<?= htmlspecialchars($post['content']??'') ?>">
            </div>
          </div>
          <!-- TAB Galeria -->
          <div id="tab-galeria" class="tab-panel">
            <div class="alert alert-info" style="font-size:13px"><span class="material-icons">info</span> Fotos da galeria aparecem com lightbox na página do post.</div>
            <?php if($gallery): ?>
            <div class="gallery-grid">
              <?php foreach($gallery as $g): ?>
              <div class="gallery-item">
                <img src="<?= UPLOAD_URL ?>gallery/<?= htmlspecialchars($g['filename']) ?>" alt="">
                <a href="posts.php?action=del_gallery&id=<?= $g['id'] ?>&post_id=<?= $id ?>&type=<?= $type ?>&csrf=<?= csrf() ?>" class="del-btn" onclick="return confirm('Remover foto?')"><span class="material-icons" style="font-size:12px">delete</span></a>
                <?php if($g['caption']): ?><div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.6);color:#fff;font-size:10px;padding:3px 5px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= htmlspecialchars($g['caption']) ?></div><?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)"></div>
            <?php endif; ?>
            <?php if($id): ?>
            <form method="POST" enctype="multipart/form-data"
                  action="posts.php?action=edit&id=<?= $id ?>&type=<?= $type ?>"
                  id="galleryForm">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="remove_image" value="0">
              <input type="hidden" name="title" value="<?= htmlspecialchars($post['title']??'') ?>">
              <input type="hidden" name="summary" value="<?= htmlspecialchars($post['summary']??'') ?>">
              <input type="hidden" name="content" value="<?= htmlspecialchars($post['content']??'') ?>">
              <input type="hidden" name="post_type" value="<?= htmlspecialchars($post['type']??'comunicado') ?>">
              <input type="hidden" name="category_id" value="<?= htmlspecialchars($post['category_id']??'') ?>">
              <input type="hidden" name="status" value="<?= htmlspecialchars($post['status']??'draft') ?>">
              <input type="hidden" name="is_public" value="<?= $post['is_public']??1 ?>">
              <input type="hidden" name="is_featured" value="<?= $post['is_featured']??0 ?>">
              <div style="margin-top:4px">
                <!-- Upload de Fotos -->
                <label class="form-label">Adicionar fotos</label>
                <div class="upload-area" id="galleryUploadArea">
                  <input type="file" name="gallery_images[]" id="galleryFiles"
                         accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/svg+xml"
                         multiple onchange="previewGallery(this)">
                  <div class="upload-icon"><span class="material-icons">photo_library</span></div>
                  <p><strong>Clique ou arraste</strong> múltiplas fotos</p>
                  <div class="upload-hint">JPG, PNG, GIF, WEBP — Máx 20MB cada</div>
                </div>
                <div id="galleryPreviewGrid" class="gallery-grid"></div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px" id="gallerySubmitBtn" disabled>
                  <span class="material-icons">cloud_upload</span> Enviar Fotos Selecionadas
                </button>

                <!-- Upload de Vídeo direto (MP4) -->
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                  <label class="form-label">Adicionar vídeo (arquivo MP4)</label>
                  <div class="upload-area" id="videoUploadArea" style="border-color:#e65100">
                    <input type="file" name="gallery_video" id="galleryVideoFile"
                           accept="video/mp4,video/webm,video/ogg,.mp4,.webm,.ogv"
                           onchange="previewVideo(this)">
                    <div class="upload-icon"><span class="material-icons" style="color:#e65100">videocam</span></div>
                    <p><strong>Clique ou arraste</strong> um vídeo aqui</p>
                    <div class="upload-hint">MP4, WEBM — Máx 100MB</div>
                  </div>
                  <div id="videoPreviewWrap" style="display:none;margin-top:10px">
                    <video id="videoPreviewEl" controls style="width:100%;border-radius:8px;max-height:200px"></video>
                    <div class="form-group" style="margin-top:8px;margin-bottom:0">
                      <input type="text" name="gallery_video_title" class="form-control"
                             placeholder="Título do vídeo (opcional)">
                    </div>
                  </div>
                  <button type="submit" name="upload_video_file" value="1"
                          class="btn btn-sm" style="margin-top:10px;background:#e65100;color:#fff;border:none"
                          id="videoSubmitBtn" disabled>
                    <span class="material-icons">cloud_upload</span> Enviar Vídeo
                  </button>
                </div>
              </div>
            </form>
            <?php else: ?><div class="alert alert-warning"><span class="material-icons">info</span> Salve a publicação primeiro.</div><?php endif; ?>
          </div>
          <!-- TAB Vídeos -->
          <div id="tab-videos" class="tab-panel">
            <div class="alert alert-info" style="font-size:13px"><span class="material-icons">info</span> YouTube, Vimeo ou MP4 direto. Incorporados na página do post.</div>
            <?php if($videos): ?>
            <div class="video-list">
              <?php foreach($videos as $v): ?>
              <div class="video-item">
                <div class="video-thumb">
                  <?php if($v['video_type']==='youtube'&&$vid=ytId($v['video_url'])): ?>
                  <img src="https://img.youtube.com/vi/<?= $vid ?>/mqdefault.jpg" alt="">
                  <?php else: ?><span class="material-icons" style="color:var(--primary);font-size:28px">play_circle</span><?php endif; ?>
                </div>
                <div class="video-info">
                  <strong><?= htmlspecialchars($v['title']?:$v['video_url']) ?></strong>
                  <span><?= strtoupper($v['video_type']) ?></span>
                </div>
                <a href="posts.php?action=del_video&id=<?= $v['id'] ?>&post_id=<?= $id ?>&type=<?= $type ?>&csrf=<?= csrf() ?>" class="btn btn-ghost btn-sm btn-icon" style="color:#dc3545" onclick="return confirm('Remover vídeo?')"><span class="material-icons" style="font-size:18px">delete</span></a>
              </div>
              <?php endforeach; ?>
              <div style="padding-top:14px;border-top:1px solid var(--border)"></div>
            </div>
            <?php endif; ?>
            <?php if($id): ?>
            <form method="POST" action="posts.php?action=add_video&type=<?= $type ?>" style="margin-top:8px">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="post_id" value="<?= $id ?>">
              <div class="form-group"><label class="form-label">URL do Vídeo</label>
                <input type="url" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=... ou https://vimeo.com/...">
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Suporte: YouTube, Vimeo e links diretos .mp4</div>
              </div>
              <div class="form-group"><label class="form-label">Título (opcional)</label>
                <input type="text" name="video_title" class="form-control" placeholder="Ex: Vídeo institucional 2025"></div>
              <button type="submit" class="btn btn-outline btn-sm"><span class="material-icons">add</span> Adicionar Vídeo</button>
            </form>
            <?php else: ?><div class="alert alert-warning"><span class="material-icons">info</span> Salve a publicação primeiro.</div><?php endif; ?>
          </div>
        </div>
        <!-- Painel lateral -->
        <div style="display:flex;flex-direction:column;gap:16px">
          <div class="card card-body">
            <div class="form-group"><label class="form-label">Status</label>
              <select name="status" class="form-control">
                <option value="draft" <?= ($post['status']??'draft')==='draft'?'selected':'' ?>>📝 Rascunho</option>
                <option value="published" <?= ($post['status']??'')==='published'?'selected':'' ?>>✅ Publicado</option>
                <option value="archived" <?= ($post['status']??'')==='archived'?'selected':'' ?>>📦 Arquivado</option>
              </select></div>
            <div class="form-group"><label class="form-label">Tipo</label>
              <select name="post_type" class="form-control">
                <option value="comunicado" <?= ($post['type']??$type)==='comunicado'?'selected':'' ?>>📢 Comunicado</option>
                <option value="noticia" <?= ($post['type']??$type)==='noticia'?'selected':'' ?>>📰 Notícia</option>
              </select></div>
            <div class="form-group"><label class="form-label">Categoria</label>
              <select name="category_id" class="form-control">
                <option value="">— Sem categoria —</option>
                <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($post['category_id']??'')==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px;cursor:pointer;font-size:14px">
              <input type="checkbox" name="is_public" value="1" <?= ($post['is_public']??1)?'checked':'' ?>> Visível na área pública</label>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
              <input type="checkbox" name="is_featured" value="1" <?= ($post['is_featured']??0)?'checked':'' ?>> ⭐ Destaque no carrossel</label>
          </div>
          <!-- ── PAINEL MÍDIA DE DESTAQUE ── -->
          <?php
            $mediaType = $post['media_type'] ?? 'image';
            if (empty($mediaType)) $mediaType = 'image';
          ?>
          <div class="card card-body">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:7px">
              <span class="material-icons" style="color:var(--primary);font-size:17px">perm_media</span>
              Mídia de Destaque
            </div>

            <!-- Selector de tipo -->
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px">
              <?php foreach(['image'=>['photo_camera','Imagem de capa'],'video'=>['play_circle','Vídeo (YouTube/Vimeo/MP4)'],'slider'=>['view_carousel','Slider de imagens']] as $mt=>[$icon,$label]): ?>
              <label style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;cursor:pointer;border:2px solid <?= $mediaType===$mt?'var(--primary)':'var(--border)' ?>;background:<?= $mediaType===$mt?'var(--primary-xlight)':'var(--bg)' ?>;transition:.15s;font-size:13px;font-weight:600"
                     onclick="selectMedia('<?= $mt ?>')">
                <input type="radio" name="media_type" value="<?= $mt ?>" <?= $mediaType===$mt?'checked':'' ?> style="display:none">
                <span class="material-icons" style="font-size:17px;color:<?= $mediaType===$mt?'var(--primary)':'var(--text-muted)' ?>"><?= $icon ?></span>
                <?= $label ?>
              </label>
              <?php endforeach; ?>
            </div>

            <!-- Painel: Imagem -->
            <div id="media-image" style="<?= $mediaType!=='image'?'display:none':'' ?>">
              <?php $hasCover=!empty($post['cover_image'])&&file_exists(UPLOAD_DIR.$post['cover_image']); ?>
              <div id="imagePreviewWrap" style="<?= $hasCover?'':'display:none' ?>">
                <div class="image-preview-wrap">
                  <img id="coverPreviewImg" src="<?= $hasCover?UPLOAD_URL.htmlspecialchars($post['cover_image']):'' ?>" alt="Preview">
                  <div class="image-preview-overlay"><button type="button" onclick="removeImage()"><span class="material-icons">delete</span> Remover</button></div>
                </div>
              </div>
              <div class="upload-area" id="uploadArea" style="<?= $hasCover?'display:none':'' ?>">
                <input type="file" name="cover_image" id="coverFile" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                <div class="upload-icon"><span class="material-icons">cloud_upload</span></div>
                <p><strong>Clique ou arraste</strong></p>
                <div class="upload-hint">JPG, PNG, GIF, WEBP — Máx 20MB</div>
              </div>
              <div style="margin-top:10px">
                <div class="form-group"><label class="form-label" style="font-size:11px">Alt text</label>
                  <input type="text" name="cover_image_alt" class="form-control" style="font-size:12px" value="<?= htmlspecialchars($post['cover_image_alt']??'') ?>" placeholder="Descrição para acessibilidade"></div>
                <div class="form-group" style="margin-bottom:0"><label class="form-label" style="font-size:11px">Legenda</label>
                  <input type="text" name="cover_image_caption" class="form-control" style="font-size:12px" value="<?= htmlspecialchars($post['cover_image_caption']??'') ?>" placeholder="Foto: Nome / Fonte"></div>
              </div>
            </div>

            <!-- Painel: Vídeo -->
            <div id="media-video" style="<?= $mediaType!=='video'?'display:none':'' ?>">
              <div class="form-group">
                <label class="form-label" style="font-size:12px">URL do Vídeo</label>
                <input type="url" name="cover_video_url" id="coverVideoUrl" class="form-control"
                       value="<?= htmlspecialchars($post['cover_video_url']??'') ?>"
                       placeholder="https://youtube.com/watch?v=... ou https://vimeo.com/..."
                       oninput="previewVideoUrl(this.value)">
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px">YouTube, Vimeo ou link MP4 direto</div>
              </div>
              <div id="coverVideoPreview" style="margin-top:8px;display:<?= !empty($post['cover_video_url'])?'block':'none' ?>">
                <?php if (!empty($post['cover_video_url'])): ?>
                  <?php
                    $pvUrl = $post['cover_video_url'];
                    $pvYtId = '';
                    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $pvUrl, $pvm);
                    if (!empty($pvm[1])) $pvYtId = $pvm[1];
                  ?>
                  <?php if ($pvYtId): ?>
                  <img src="https://img.youtube.com/vi/<?= $pvYtId ?>/hqdefault.jpg" style="width:100%;border-radius:8px">
                  <?php else: ?>
                  <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:12px;color:var(--text-muted);text-align:center">
                    <span class="material-icons">play_circle</span><br><?= htmlspecialchars(mb_substr($pvUrl,0,50)) ?>
                  </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <!-- Upload MP4 direto — form separado para suportar arquivos grandes -->
              <?php if ($id): ?>
              <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                <label class="form-label" style="font-size:11px">Ou enviar arquivo MP4 diretamente</label>
                <form method="POST" enctype="multipart/form-data"
                      action="posts.php?action=upload_cover_video&id=<?= $id ?>&type=<?= $type ?>">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <div class="upload-area" style="padding:12px;border-color:#e65100" id="coverVideoUploadArea">
                    <input type="file" name="cover_video_file" id="coverVideoFile"
                           accept="video/mp4,video/webm,.mp4,.webm"
                           onchange="previewVideoFile(this)">
                    <div class="upload-icon"><span class="material-icons" style="color:#e65100;font-size:28px">videocam</span></div>
                    <p style="margin:4px 0;font-size:13px"><strong>Clique</strong> para selecionar</p>
                    <div class="upload-hint">MP4, WEBM — até o limite do servidor</div>
                  </div>
                  <div id="coverVideoFilePreview" style="display:none;margin-top:8px">
                    <video id="coverVideoFileEl" controls style="width:100%;border-radius:8px;max-height:140px"></video>
                  </div>
                  <button type="submit" class="btn btn-sm" id="coverVideoSubmitBtn"
                          style="margin-top:8px;width:100%;background:#e65100;color:#fff;border:none;display:none">
                    <span class="material-icons">cloud_upload</span> Enviar Vídeo
                  </button>
                </form>
              </div>
              <?php else: ?>
              <div class="alert alert-warning" style="font-size:12px;margin-top:12px">
                <span class="material-icons">info</span> Salve a publicação primeiro para enviar vídeo por arquivo.
              </div>
              <?php endif; ?>
            </div>

            <!-- Painel: Slider -->
            <div id="media-slider" style="<?= $mediaType!=='slider'?'display:none':'' ?>">
              <?php if($id): ?>
              <?php $sliderImgs = Database::fetchAll('SELECT * FROM post_gallery WHERE post_id=? ORDER BY sort_order', [$id]); ?>
              <?php if ($sliderImgs): ?>
              <div class="gallery-grid" style="margin-bottom:12px">
                <?php foreach($sliderImgs as $sg): ?>
                <div class="gallery-item">
                  <img src="<?= UPLOAD_URL ?>gallery/<?= htmlspecialchars($sg['filename']) ?>" alt="">
                  <a href="posts.php?action=del_gallery&id=<?= $sg['id'] ?>&post_id=<?= $id ?>&type=<?= $type ?>&csrf=<?= csrf() ?>" class="del-btn" onclick="return confirm('Remover?')"><span class="material-icons" style="font-size:12px">delete</span></a>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <form method="POST" enctype="multipart/form-data" action="posts.php?action=edit&id=<?= $id ?>&type=<?= $type ?>">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="title" value="<?= htmlspecialchars($post['title']??'') ?>">
                <input type="hidden" name="summary" value="<?= htmlspecialchars($post['summary']??'') ?>">
                <input type="hidden" name="content" value="<?= htmlspecialchars($post['content']??'') ?>">
                <input type="hidden" name="post_type" value="<?= htmlspecialchars($post['type']??'comunicado') ?>">
                <input type="hidden" name="category_id" value="<?= htmlspecialchars($post['category_id']??'') ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($post['status']??'draft') ?>">
                <input type="hidden" name="is_public" value="<?= $post['is_public']??1 ?>">
                <input type="hidden" name="is_featured" value="<?= $post['is_featured']??0 ?>">
                <input type="hidden" name="media_type" value="slider">
                <div class="upload-area" id="galleryUploadArea2">
                  <input type="file" name="gallery_images[]" id="galleryFiles2" accept="image/*" multiple onchange="previewGallery2(this)">
                  <div class="upload-icon"><span class="material-icons">photo_library</span></div>
                  <p><strong>Adicionar fotos ao slider</strong></p>
                  <div class="upload-hint">Múltiplas — JPG, PNG, WEBP</div>
                </div>
                <div id="galleryPreviewGrid2" class="gallery-grid"></div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;width:100%" id="gallerySubmitBtn2" disabled>
                  <span class="material-icons">cloud_upload</span> Enviar Fotos
                </button>
              </form>
              <?php else: ?>
              <div class="alert alert-warning" style="font-size:12px"><span class="material-icons">info</span> Salve a publicação primeiro.</div>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:10px">
            <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Salvar Publicação</button>
            <a href="posts.php?type=<?= $post['type']??$type ?>" class="btn btn-ghost">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  <?php endif; ?>
  </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
<script>
// Tabs (conteúdo/galeria/vídeos)
function switchTab(name,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}

// Seletor de tipo de mídia
function selectMedia(type) {
  ['image','video','slider'].forEach(t => {
    const panel = document.getElementById('media-'+t);
    if (panel) panel.style.display = t === type ? 'block' : 'none';
  });
  document.querySelectorAll('[name="media_type"]').forEach(r => {
    r.checked = r.value === type;
    const lbl = r.closest('label');
    if (lbl) {
      lbl.style.borderColor  = r.checked ? 'var(--primary)' : 'var(--border)';
      lbl.style.background   = r.checked ? 'var(--primary-xlight)' : 'var(--bg)';
      const ico = lbl.querySelector('.material-icons');
      if (ico) ico.style.color = r.checked ? 'var(--primary)' : 'var(--text-muted)';
    }
  });
}

// Imagem de capa
function previewImage(input) {
  if (!input.files?.[0]) return;
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('coverPreviewImg').src = e.target.result;
    document.getElementById('imagePreviewWrap').style.display = 'block';
    document.getElementById('uploadArea').style.display = 'none';
    document.getElementById('removeImageFlag').value = '0';
  };
  r.readAsDataURL(input.files[0]);
}
function removeImage() {
  if (!confirm('Remover imagem de capa?')) return;
  document.getElementById('coverPreviewImg').src = '';
  document.getElementById('imagePreviewWrap').style.display = 'none';
  document.getElementById('uploadArea').style.display = 'block';
  document.getElementById('removeImageFlag').value = '1';
  const fi = document.getElementById('coverFile');
  if (fi) fi.value = '';
}

// Preview URL de vídeo (YouTube thumbnail)
function previewVideoUrl(url) {
  const wrap = document.getElementById('coverVideoPreview');
  if (!wrap) return;
  const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
  if (m) {
    wrap.innerHTML = `<img src="https://img.youtube.com/vi/${m[1]}/hqdefault.jpg" style="width:100%;border-radius:8px">`;
    wrap.style.display = 'block';
  } else if (url.trim()) {
    wrap.innerHTML = `<div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;color:var(--text-muted);text-align:center"><span class="material-icons">play_circle</span><br>${url.substring(0,50)}</div>`;
    wrap.style.display = 'block';
  } else {
    wrap.style.display = 'none';
  }
}

// Preview arquivo MP4 de capa
function previewVideoFile(input) {
  const wrap = document.getElementById('coverVideoFilePreview');
  const vid  = document.getElementById('coverVideoFileEl');
  const btn  = document.getElementById('coverVideoSubmitBtn');
  if (!input.files?.[0]) {
    wrap.style.display = 'none';
    if (btn) btn.style.display = 'none';
    return;
  }
  const file = input.files[0];
  vid.src = URL.createObjectURL(file);
  wrap.style.display = 'block';
  if (btn) {
    btn.style.display = 'flex';
    btn.innerHTML = '<span class="material-icons">cloud_upload</span>&nbsp;Enviar: ' + file.name + ' (' + (file.size/1024/1024).toFixed(1) + ' MB)';
  }
  // Limpar URL externa se preenchida
  const urlField = document.getElementById('coverVideoUrl');
  if (urlField) { urlField.value = ''; document.getElementById('coverVideoPreview').style.display = 'none'; }
}

// Galeria (tab dentro do post)
function previewGallery(input) {
  const grid = document.getElementById('galleryPreviewGrid');
  const btn  = document.getElementById('gallerySubmitBtn');
  grid.innerHTML = '';
  if (!input.files?.length) { if(btn) btn.disabled=true; return; }
  if (btn) { btn.disabled=false; btn.innerHTML=`<span class="material-icons">cloud_upload</span> Enviar ${input.files.length} foto${input.files.length>1?'s':''}`; }
  [...input.files].forEach(file => {
    const r = new FileReader();
    r.onload = e => {
      const d = document.createElement('div'); d.className='gallery-item';
      d.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
      grid.appendChild(d);
    };
    r.readAsDataURL(file);
  });
}
// Galeria no painel de slider
function previewGallery2(input) {
  const grid = document.getElementById('galleryPreviewGrid2');
  const btn  = document.getElementById('gallerySubmitBtn2');
  grid.innerHTML = '';
  if (!input.files?.length) { if(btn) btn.disabled=true; return; }
  if (btn) { btn.disabled=false; btn.innerHTML=`<span class="material-icons">cloud_upload</span> Enviar ${input.files.length} foto${input.files.length>1?'s':''}`; }
  [...input.files].forEach(file => {
    const r = new FileReader();
    r.onload = e => {
      const d = document.createElement('div'); d.className='gallery-item';
      d.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
      grid.appendChild(d);
    };
    r.readAsDataURL(file);
  });
}

// Drag & drop - imagem de capa
const uploadArea=document.getElementById('uploadArea');
if(uploadArea){uploadArea.addEventListener('dragover',e=>{e.preventDefault();uploadArea.classList.add('drag-over');});uploadArea.addEventListener('dragleave',()=>uploadArea.classList.remove('drag-over'));uploadArea.addEventListener('drop',e=>{e.preventDefault();uploadArea.classList.remove('drag-over');const fi=document.getElementById('coverFile');if(fi&&e.dataTransfer.files.length){fi.files=e.dataTransfer.files;previewImage(fi);}});}

// Editor de conteúdo
const editor=document.getElementById('content-editor'),hidden=document.getElementById('content-hidden');
if(editor&&hidden){editor.addEventListener('input',()=>{hidden.value=editor.innerHTML;});document.querySelectorAll('.editor-btn[data-cmd]').forEach(btn=>{btn.addEventListener('click',e=>{e.preventDefault();document.execCommand(btn.dataset.cmd,false,btn.dataset.val||null);editor.focus();hidden.value=editor.innerHTML;});});}
</script>
</body>
</html>
