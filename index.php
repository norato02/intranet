<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$page = $_GET['page'] ?? 'home';
$pageTitle = match($page) {
    'comunicados' => 'Comunicados',
    'noticias'    => 'Notícias Externas',
    'sistemas'    => 'Sistemas',
    'post'        => '',
    'busca'       => 'Busca',
    'perfil'      => 'Meu Perfil',
    default       => ''
};

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($page === 'home'): ?>
<!-- ========================================================
     HOME — Comunicados e Notícias como conteúdo principal
     ======================================================== -->

  <!-- Hero — nome da unidade em destaque -->
  <?php
  $heroTitle    = getSetting('hero_title',    getSetting('site_tagline', 'Unidade de Saúde'));
  $heroSubtitle = getSetting('hero_subtitle', 'Portal de Comunicação Institucional');
  ?>
  <div class="hero" style="padding:20px 0">
    <div class="container hero-content">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
          <h1 style="font-size:32px;margin-bottom:4px;font-weight:800;letter-spacing:-.5px;line-height:1.15;color:#fff">
            <?= htmlspecialchars($heroTitle) ?>
          </h1>
          <p style="font-size:14px;opacity:.85;font-family:var(--font-body);font-weight:400;letter-spacing:.1px;color:#fff;margin-top:4px">
            <?= htmlspecialchars($heroSubtitle) ?>
          </p>
        </div>
        <!-- Links rápidos no hero -->
        <?php $quickLinks = Database::fetchAll("SELECT * FROM modules WHERE category='link_rapido' AND active=1 ORDER BY sort_order LIMIT 4"); ?>
        <?php if ($quickLinks): ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach ($quickLinks as $lnk): ?>
          <a href="<?= htmlspecialchars($lnk['url']) ?>" target="<?= $lnk['target'] ?>"
             style="display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:30px;background:rgba(255,255,255,.15);color:#fff;font-size:13px;font-weight:600;border:1px solid rgba(255,255,255,.3);transition:.2s;text-decoration:none;backdrop-filter:blur(4px)"
             onmouseover="this.style.background='rgba(255,255,255,.25)'"
             onmouseout="this.style.background='rgba(255,255,255,.15)'">
            <span class="material-icons" style="font-size:15px"><?= htmlspecialchars($lnk['icon']) ?></span>
            <?= htmlspecialchars($lnk['name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="container main-content">

    <!-- ── SISTEMAS — barra compacta linha única ── -->
    <?php $sistemas = Database::fetchAll("SELECT * FROM modules WHERE category='sistema' AND active=1 ORDER BY sort_order"); ?>
    <?php if ($sistemas): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:12px 18px;margin-bottom:12px;display:flex;align-items:center;gap:10px;overflow:hidden">
      <span class="material-icons" style="font-size:20px;color:var(--primary);flex-shrink:0">apps</span>
      <div style="flex:1;display:flex;gap:6px;overflow:hidden;min-width:0">
        <?php foreach ($sistemas as $sys): ?>
        <a href="<?= htmlspecialchars($sys['url']) ?>" target="<?= $sys['target'] ?>"
           title="<?= htmlspecialchars($sys['name']) ?> — <?= htmlspecialchars($sys['description'] ?? '') ?>"
           style="display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:8px;background:var(--bg);border:1px solid var(--border);text-decoration:none;transition:.15s;color:var(--text);white-space:nowrap;flex-shrink:0"
           onmouseover="this.style.borderColor='<?= htmlspecialchars($sys['color']) ?>';this.style.background='<?= htmlspecialchars($sys['color']) ?>18'"
           onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--bg)'">
          <div style="width:28px;height:28px;border-radius:7px;background:<?= htmlspecialchars($sys['color']) ?>20;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?php if (!empty($sys['icon_image']) && file_exists(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $sys['icon_image'])): ?>
            <img src="<?= UPLOAD_URL ?>modules/<?= htmlspecialchars($sys['icon_image']) ?>" style="width:18px;height:18px;object-fit:contain" alt="">
            <?php else: ?>
            <span class="material-icons" style="font-size:17px;color:<?= htmlspecialchars($sys['color']) ?>"><?= htmlspecialchars($sys['icon']) ?></span>
            <?php endif; ?>
          </div>
          <span style="font-size:13px;font-weight:700"><?= htmlspecialchars($sys['name']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <a href="index.php?page=sistemas"
         style="white-space:nowrap;font-size:13px;font-weight:700;color:var(--primary);text-decoration:none;flex-shrink:0;padding:8px 16px;border:1px solid var(--primary);border-radius:8px;transition:.15s"
         onmouseover="this.style.background='var(--primary)';this.style.color='#fff'"
         onmouseout="this.style.background='transparent';this.style.color='var(--primary)'">
        Ver todos
      </a>
    </div>
    <?php endif; ?>

    <!-- ── POST EM DESTAQUE ── -->
    <?php $featured = Database::fetch(
      "SELECT p.*,u.name as author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id
       WHERE p.status='published' AND p.is_featured=1 ORDER BY p.published_at DESC LIMIT 1"
    ); ?>
    <?php if ($featured): ?>
    <?php
      $featMediaType  = $featured['media_type'] ?? 'image';
      $featInterval   = (int) getSetting('gallery_interval', '7');
      $featSlides     = [];
      $featVideoUrl   = $featured['cover_video_url'] ?? '';
      $featVideoType  = $featured['cover_video_type'] ?? '';
      $featYtId       = '';
      $featEmbedUrl   = '';
      if ($featMediaType === 'slider') {
          $featGallery = Database::fetchAll('SELECT * FROM post_gallery WHERE post_id=? ORDER BY sort_order LIMIT 6', [$featured['id']]);
          foreach ($featGallery as $fg) {
              $featSlides[] = ['url' => UPLOAD_URL.'gallery/'.htmlspecialchars($fg['filename']), 'alt' => htmlspecialchars($fg['caption']??''), 'pos' => htmlspecialchars($fg['image_position']??'50% 50%')];
          }
      } elseif ($featMediaType === 'image' && !empty($featured['cover_image']) && file_exists(str_replace('/', DIRECTORY_SEPARATOR, UPLOAD_DIR.$featured['cover_image']))) {
          $featSlides[] = ['url' => UPLOAD_URL.htmlspecialchars($featured['cover_image']), 'alt' => htmlspecialchars($featured['cover_image_alt']??$featured['title']), 'pos' => htmlspecialchars($featured['cover_image_position']??'50% 50%')];
      }
      if ($featMediaType === 'video' && $featVideoUrl) {
          if ($featVideoType === 'youtube') {
              preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $featVideoUrl, $ym);
              if (!empty($ym[1])) { $featYtId = $ym[1]; $featEmbedUrl = 'https://www.youtube.com/embed/'.$ym[1].'?rel=0&autoplay=1'; }
          } elseif ($featVideoType === 'vimeo') {
              preg_match('/vimeo\.com\/(\d+)/', $featVideoUrl, $vm);
              if (!empty($vm[1])) $featEmbedUrl = 'https://player.vimeo.com/video/'.$vm[1].'?autoplay=1';
          } else { $featEmbedUrl = $featVideoUrl; }
      }
    ?>
    <div class="post-featured">
        <div class="pf-image">
          <?php if ($featMediaType === 'video' && $featVideoUrl): ?>
          <!-- Vídeo de capa no destaque -->
          <?php if ($featVideoType === 'mp4'): ?>
          <div onclick="event.preventDefault()" style="height:100%;background:#000;display:flex;align-items:center">
            <video controls style="width:100%;max-height:440px"><source src="<?= htmlspecialchars($featVideoUrl) ?>" type="video/mp4"></video>
          </div>
          <?php else: ?>
          <div class="video-thumb-preview" style="height:100%;border-radius:0;aspect-ratio:unset"
               onclick="event.preventDefault();openVideoLightbox('<?= htmlspecialchars($featEmbedUrl,ENT_QUOTES) ?>','<?= htmlspecialchars($featured['title'],ENT_QUOTES) ?>')">
            <?php if ($featYtId): ?>
            <img src="https://img.youtube.com/vi/<?= $featYtId ?>/maxresdefault.jpg"
                 onerror="this.src='https://img.youtube.com/vi/<?= $featYtId ?>/hqdefault.jpg'"
                 alt="" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
            <div class="video-thumb-placeholder"><span class="material-icons">play_circle</span></div>
            <?php endif; ?>
            <div class="video-play-btn"><span class="material-icons">play_circle_filled</span></div>
          </div>
          <?php endif; ?>

          <?php elseif ($featSlides): ?>
          <!-- Imagem ou slider no destaque -->
          <div class="post-slider pf-slider" data-interval="<?= count($featSlides)>1 ? $featInterval*1000 : 0 ?>">
            <div class="post-slider-track">
              <?php foreach ($featSlides as $fs): ?>
              <div class="post-slider-slide">
                <img src="<?= $fs['url'] ?>" alt="<?= $fs['alt'] ?>" style="object-position:<?= $fs['pos'] ?? '50% 50%' ?>" onclick="openLightbox(this)">
              </div>
              <?php endforeach; ?>
            </div>
            <?php if (count($featSlides) > 1): ?>
            <button class="post-slider-btn prev" onclick="event.preventDefault()"><span class="material-icons">chevron_left</span></button>
            <button class="post-slider-btn next" onclick="event.preventDefault()"><span class="material-icons">chevron_right</span></button>
            <div class="post-slider-dots">
              <?php foreach ($featSlides as $i => $fs): ?>
              <button class="post-slider-dot <?= $i===0?'active':'' ?>" onclick="event.preventDefault()"></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="pf-image-placeholder">
            <span class="material-icons"><?= $featured['type']==='comunicado'?'campaign':'newspaper' ?></span>
          </div>
          <?php endif; ?>
          <span class="post-cover-badge <?= $featured['type'] ?>">
            <?= $featured['type']==='comunicado'?'Comunicado':'Notícia' ?>
          </span>
        </div>
        <a href="index.php?page=post&slug=<?= urlencode($featured['slug']) ?>" class="pf-body" style="text-decoration:none;display:flex;flex-direction:column;justify-content:center">
          <div class="pf-label"><span class="material-icons">star</span> Em Destaque</div>
          <div class="pf-title"><?= htmlspecialchars($featured['title']) ?></div>
          <?php if ($featured['summary']): ?>
          <div class="pf-summary"><?= htmlspecialchars(mb_substr($featured['summary'],0,220)) ?><?= mb_strlen($featured['summary'])>220?'…':'' ?></div>
          <?php endif; ?>
          <div style="margin-top:20px;margin-bottom:16px">
            <span style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:var(--primary);color:#fff;border-radius:8px;font-size:13px;font-weight:700">
              Ler publicação <span class="material-icons" style="font-size:16px">arrow_forward</span>
            </span>
          </div>
          <div class="pf-meta">
            <div class="avatar-xs"><?= mb_strtoupper(mb_substr($featured['author_name'],0,1)) ?></div>
            <?= htmlspecialchars($featured['author_name']) ?>
            <span>·</span><?= formatDate($featured['published_at']) ?>
          </div>
        </a>
      </div>
    <?php endif; ?>

    <!-- ── COMUNICADOS E NOTÍCIAS LADO A LADO ── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px" class="two-col-grid">

      <!-- COMUNICADOS INTERNOS -->
      <div>
        <div class="section-header">
          <span class="section-title">
            <span class="material-icons">campaign</span> Comunicados
          </span>
          <a href="index.php?page=comunicados" class="btn btn-ghost btn-sm">Ver todos →</a>
        </div>
        <?php
        $comunicados = Database::fetchAll(
          "SELECT p.*,u.name as author_name FROM posts p
           LEFT JOIN users u ON u.id=p.author_id
           WHERE p.type='comunicado' AND p.status='published'
           ORDER BY p.published_at DESC LIMIT 5"
        );
        ?>
        <div style="display:flex;flex-direction:column;gap:14px">
          <?php foreach ($comunicados as $post): ?>
          <a href="index.php?page=post&slug=<?= urlencode($post['slug']) ?>" style="text-decoration:none" class="post-list-item">
            <div class="card" style="padding:0;overflow:hidden;display:flex;min-height:88px;transition:.2s">
              <!-- Imagem miniatura ou barra colorida -->
              <?php if ($post['cover_image'] && file_exists(str_replace('/', DIRECTORY_SEPARATOR, UPLOAD_DIR . $post['cover_image']))): ?>
              <div style="width:100px;flex-shrink:0;overflow:hidden;position:relative">
                <img src="<?= UPLOAD_URL . htmlspecialchars($post['cover_image']) ?>"
                     alt="" style="width:100%;height:100%;object-fit:cover;display:block;transition:.3s"
                     class="post-list-thumb">
              </div>
              <?php else: ?>
              <div style="width:5px;background:var(--primary);flex-shrink:0"></div>
              <?php endif; ?>
              <!-- Conteúdo -->
              <div style="padding:14px 16px;display:flex;flex-direction:column;justify-content:center;flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap">
                  <span class="badge badge-comunicado">Comunicado</span>
                  <?php if ($post['is_featured']): ?>
                  <span class="material-icons" style="font-size:14px;color:#ffc107">star</span>
                  <?php endif; ?>
                  <span style="font-size:11px;color:var(--text-muted)"><?= formatDate($post['published_at']) ?></span>
                </div>
                <div style="font-size:14px;font-weight:700;color:var(--text);line-height:1.35;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                  <?= htmlspecialchars($post['title']) ?>
                </div>
                <?php if ($post['summary']): ?>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical">
                  <?= htmlspecialchars($post['summary']) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (empty($comunicados)): ?>
          <div class="card card-body text-muted" style="text-align:center;font-size:14px">
            <span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4">campaign</span>
            Nenhum comunicado publicado ainda.
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- NOTÍCIAS EXTERNAS -->
      <div>
        <div class="section-header">
          <span class="section-title">
            <span class="material-icons">newspaper</span> Notícias Externas
          </span>
          <a href="index.php?page=noticias" class="btn btn-ghost btn-sm">Ver todas →</a>
        </div>
        <?php
        $noticias = Database::fetchAll(
          "SELECT p.*,u.name as author_name FROM posts p
           LEFT JOIN users u ON u.id=p.author_id
           WHERE p.type='noticia' AND p.status='published'
           ORDER BY p.published_at DESC LIMIT 5"
        );
        ?>
        <div style="display:flex;flex-direction:column;gap:14px">
          <?php foreach ($noticias as $post): ?>
          <a href="index.php?page=post&slug=<?= urlencode($post['slug']) ?>" style="text-decoration:none" class="post-list-item">
            <div class="card" style="padding:0;overflow:hidden;display:flex;min-height:88px;transition:.2s">
              <?php if ($post['cover_image'] && file_exists(str_replace('/', DIRECTORY_SEPARATOR, UPLOAD_DIR . $post['cover_image']))): ?>
              <div style="width:100px;flex-shrink:0;overflow:hidden;position:relative">
                <img src="<?= UPLOAD_URL . htmlspecialchars($post['cover_image']) ?>"
                     alt="" style="width:100%;height:100%;object-fit:cover;display:block;transition:.3s"
                     class="post-list-thumb">
              </div>
              <?php else: ?>
              <div style="width:5px;background:var(--accent);flex-shrink:0"></div>
              <?php endif; ?>
              <div style="padding:14px 16px;display:flex;flex-direction:column;justify-content:center;flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap">
                  <span class="badge badge-noticia">Notícia</span>
                  <?php if ($post['is_featured']): ?>
                  <span class="material-icons" style="font-size:14px;color:#ffc107">star</span>
                  <?php endif; ?>
                  <span style="font-size:11px;color:var(--text-muted)"><?= formatDate($post['published_at']) ?></span>
                </div>
                <div style="font-size:14px;font-weight:700;color:var(--text);line-height:1.35;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                  <?= htmlspecialchars($post['title']) ?>
                </div>
                <?php if ($post['summary']): ?>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical">
                  <?= htmlspecialchars($post['summary']) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (empty($noticias)): ?>
          <div class="card card-body text-muted" style="text-align:center;font-size:14px">
            <span class="material-icons" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4">newspaper</span>
            Nenhuma notícia publicada ainda.
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /.two-col-grid -->
  </div>

<!-- ========================================================
     PÁGINA DE LISTAGEM — /comunicados ou /noticias
     ======================================================== -->
<?php elseif (in_array($page, ['comunicados','noticias'])): ?>
  <?php
  $type     = $page === 'comunicados' ? 'comunicado' : 'noticia';
  $label    = $page === 'comunicados' ? 'Comunicados' : 'Notícias Externas';
  $icon     = $page === 'comunicados' ? 'campaign' : 'newspaper';
  $perPage  = (int) getSetting('posts_per_page', '10');
  $curPage  = max(1, (int) ($_GET['p'] ?? 1));
  $offset   = ($curPage - 1) * $perPage;
  $cat      = (int) ($_GET['cat'] ?? 0);
  $catWhere = $cat ? "AND p.category_id = $cat" : '';
  $total    = Database::count("SELECT COUNT(*) FROM posts p WHERE p.type='$type' AND p.status='published' $catWhere");
  $posts    = Database::fetchAll(
    "SELECT p.*,u.name as author_name,c.name as cat_name,c.color as cat_color
     FROM posts p LEFT JOIN users u ON u.id=p.author_id LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.type='$type' AND p.status='published' $catWhere
     ORDER BY p.published_at DESC LIMIT $perPage OFFSET $offset"
  );
  $cats   = Database::fetchAll("SELECT * FROM categories WHERE type='$type' ORDER BY name");
  $pages  = ceil($total / $perPage);
  ?>
  <div class="container main-content fade-in">
    <div class="section-header">
      <span class="section-title">
        <span class="material-icons"><?= $icon ?></span> <?= $label ?>
      </span>
      <span class="text-muted text-sm"><?= $total ?> publicação(ões)</span>
    </div>

    <!-- Filtros por categoria -->
    <?php if ($cats): ?>
    <div class="quick-links mb-3">
      <a href="?page=<?= $page ?>" class="quick-link <?= !$cat?'':'btn-ghost' ?>">Todos</a>
      <?php foreach ($cats as $c): ?>
      <a href="?page=<?= $page ?>&cat=<?= $c['id'] ?>" class="quick-link <?= $cat==$c['id']?'':'btn-ghost' ?>"
         style="background:<?= $cat==$c['id'] ? $c['color'] : '' ?>;color:<?= $cat==$c['id'] ? '#fff' : '' ?>">
        <?= htmlspecialchars($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Grid de posts com imagem grande -->
    <div class="post-grid">
      <?php foreach ($posts as $post): ?>
      <a href="index.php?page=post&slug=<?= urlencode($post['slug']) ?>" class="post-card">
        <div class="post-cover">
          <?php if ($post['cover_image'] && file_exists(str_replace('/', DIRECTORY_SEPARATOR, UPLOAD_DIR . $post['cover_image']))): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($post['cover_image']) ?>"
               alt="<?= htmlspecialchars($post['cover_image_alt'] ?? $post['title']) ?>" loading="lazy">
          <?php else: ?>
          <div class="post-cover-placeholder">
            <span class="material-icons"><?= $icon ?></span>
          </div>
          <?php endif; ?>
          <span class="post-cover-badge <?= $type ?>"><?= $label ?></span>
          <?php if ($post['is_featured']): ?>
          <div class="featured-star"><span class="material-icons">star</span> Destaque</div>
          <?php endif; ?>
        </div>
        <div class="post-body">
          <div class="post-meta">
            <?php if ($post['cat_name']): ?>
            <span class="badge" style="background:<?= $post['cat_color'] ?>22;color:<?= $post['cat_color'] ?>">
              <?= htmlspecialchars($post['cat_name']) ?>
            </span>
            <?php endif; ?>
            <span class="post-date"><?= formatDate($post['published_at']) ?></span>
          </div>
          <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
          <?php if ($post['summary']): ?>
          <div class="post-summary"><?= htmlspecialchars(mb_substr($post['summary'], 0, 130)) ?>…</div>
          <?php endif; ?>
          <div class="post-footer">
            <div class="post-author">
              <div class="avatar-xs"><?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?></div>
              <?= htmlspecialchars($post['author_name']) ?>
            </div>
            <span class="text-muted text-xs">
              <span class="material-icons" style="font-size:13px;vertical-align:middle">visibility</span>
              <?= $post['views'] ?>
            </span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($posts)): ?>
    <div class="card card-body text-muted" style="text-align:center;padding:48px;margin-top:16px">
      <span class="material-icons" style="font-size:48px;display:block;margin-bottom:12px;opacity:.3"><?= $icon ?></span>
      Nenhuma publicação encontrada.
    </div>
    <?php endif; ?>

    <!-- Paginação -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?page=<?= $page ?>&p=<?= $i ?><?= $cat ? '&cat='.$cat : '' ?>"
         class="page-btn <?= $i === $curPage ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

<!-- ========================================================
     SINGLE POST
     ======================================================== -->
<?php elseif ($page === 'post'): ?>
  <?php
  $slug = $_GET['slug'] ?? '';
  $post = Database::fetch(
    "SELECT p.*,u.name as author_name,u.sector,c.name as cat_name,c.color as cat_color
     FROM posts p LEFT JOIN users u ON u.id=p.author_id LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.slug=? AND p.status='published'", [$slug]
  );
  if (!$post) { header('Location: ' . BASE_URL . '/index.php'); exit; }
  Database::query('UPDATE posts SET views=views+1 WHERE id=?', [$post['id']]);
  $pageTitle = $post['title'];
  $backPage  = $post['type'] === 'comunicado' ? 'comunicados' : 'noticias';
  ?>
  <div class="container main-content fade-in" style="max-width:880px">
    <div class="mb-2" style="display:flex;align-items:center;gap:10px">
      <a href="index.php?page=<?= $backPage ?>" class="btn btn-ghost btn-sm">
        <span class="material-icons">arrow_back</span>
        <?= $post['type']==='comunicado' ? 'Comunicados' : 'Notícias Externas' ?>
      </a>
    </div>
    <div class="card" style="overflow:visible;border-radius:var(--radius-lg)">
      <!-- ── Mídia de Destaque (imagem / vídeo / slider) ── -->
      <?php
        $mediaType       = $post['media_type'] ?? 'image';
        $galleryInterval = (int) getSetting('gallery_interval', '7');

        // Monta slides para slider ou imagem única
        $slides = [];
        if ($mediaType === 'slider') {
            $pgallery = Database::fetchAll('SELECT * FROM post_gallery WHERE post_id=? ORDER BY sort_order', [$post['id']]);
            foreach ($pgallery as $gi) {
                $slides[] = ['url' => UPLOAD_URL.'gallery/'.htmlspecialchars($gi['filename']), 'alt' => htmlspecialchars($gi['caption']??''), 'caption' => htmlspecialchars($gi['caption']??''), 'pos' => htmlspecialchars($gi['image_position']??'50% 50%')];
            }
        } elseif ($mediaType === 'image' && !empty($post['cover_image']) && file_exists(UPLOAD_DIR . $post['cover_image'])) {
            $slides[] = ['url' => UPLOAD_URL.htmlspecialchars($post['cover_image']), 'alt' => htmlspecialchars($post['cover_image_alt']??$post['title']), 'caption' => htmlspecialchars($post['cover_image_caption']??''), 'pos' => htmlspecialchars($post['cover_image_position']??'50% 50%')];
        }

        // Vídeo de capa
        $coverVideoUrl  = $post['cover_video_url'] ?? '';
        $coverVideoType = $post['cover_video_type'] ?? '';
        $coverEmbedUrl  = '';
        $coverYtId      = '';
        if ($mediaType === 'video' && $coverVideoUrl) {
            if ($coverVideoType === 'youtube') {
                preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $coverVideoUrl, $ym);
                if (!empty($ym[1])) { $coverYtId = $ym[1]; $coverEmbedUrl = 'https://www.youtube.com/embed/'.$ym[1].'?rel=0&autoplay=1'; }
            } elseif ($coverVideoType === 'vimeo') {
                preg_match('/vimeo\.com\/(\d+)/', $coverVideoUrl, $vm);
                if (!empty($vm[1])) $coverEmbedUrl = 'https://player.vimeo.com/video/'.$vm[1].'?autoplay=1';
            } else {
                $coverEmbedUrl = $coverVideoUrl; // mp4 direto
            }
        }
      ?>

      <?php if ($mediaType === 'video' && $coverVideoUrl): ?>
      <!-- Vídeo de destaque clicável -->
      <?php if ($coverVideoType === 'mp4'): ?>
      <div style="background:#000">
        <video controls style="width:100%;max-height:520px;display:block">
          <source src="<?= htmlspecialchars($coverVideoUrl) ?>" type="video/mp4">
        </video>
      </div>
      <?php else: ?>
      <div class="video-thumb-preview post-cover-video"
           onclick="openVideoLightbox('<?= htmlspecialchars($coverEmbedUrl, ENT_QUOTES) ?>', '<?= htmlspecialchars($post['title'], ENT_QUOTES) ?>')">
        <?php if ($coverYtId): ?>
        <img src="https://img.youtube.com/vi/<?= $coverYtId ?>/maxresdefault.jpg"
             onerror="this.src='https://img.youtube.com/vi/<?= $coverYtId ?>/hqdefault.jpg'"
             alt="<?= htmlspecialchars($post['title']) ?>">
        <?php else: ?>
        <div class="video-thumb-placeholder"><span class="material-icons">play_circle</span></div>
        <?php endif; ?>
        <div class="video-play-btn"><span class="material-icons">play_circle_filled</span></div>
      </div>
      <?php endif; ?>

      <?php elseif ($slides): ?>
      <!-- Imagem única ou Slider -->
      <div class="post-slider" data-interval="<?= count($slides) > 1 ? $galleryInterval * 1000 : 0 ?>">
        <div class="post-slider-track">
          <?php foreach ($slides as $slide): ?>
          <div class="post-slider-slide">
            <img src="<?= $slide['url'] ?>" alt="<?= $slide['alt'] ?>" loading="lazy"
                 style="object-position:<?= $slide['pos'] ?? '50% 50%' ?>"
                 onclick="openLightbox(this)">
            <?php if ($slide['caption']): ?>
            <div class="post-slider-caption"><?= $slide['caption'] ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1): ?>
        <button class="post-slider-btn prev"><span class="material-icons">chevron_left</span></button>
        <button class="post-slider-btn next"><span class="material-icons">chevron_right</span></button>
        <div class="post-slider-dots">
          <?php foreach ($slides as $i => $s): ?>
          <button class="post-slider-dot <?= $i===0?'active':'' ?>"></button>
          <?php endforeach; ?>
        </div>
        <div class="post-slider-counter">
          <span class="psc-current">1</span> / <?= count($slides) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="card-body" style="padding:36px 44px">
        <!-- Meta topo -->
        <div class="post-meta mb-2">
          <span class="badge badge-<?= $post['type'] ?>">
            <?= $post['type']==='comunicado' ? 'Comunicado' : 'Notícia Externa' ?>
          </span>
          <?php if ($post['cat_name']): ?>
          <span class="badge" style="background:<?= $post['cat_color'] ?>22;color:<?= $post['cat_color'] ?>">
            <?= htmlspecialchars($post['cat_name']) ?>
          </span>
          <?php endif; ?>
          <?php if ($post['is_featured']): ?>
          <span style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;color:#d39e00">
            <span class="material-icons" style="font-size:14px">star</span> Destaque
          </span>
          <?php endif; ?>
          <span class="text-muted text-sm">
            <?= formatDate($post['published_at'], 'd/m/Y \à\s H:i') ?>
          </span>
        </div>

        <!-- Título -->
        <h1 style="font-size:28px;margin-bottom:18px;line-height:1.3">
          <?= htmlspecialchars($post['title']) ?>
        </h1>

        <!-- Resumo em destaque -->
        <?php if ($post['summary']): ?>
        <p style="font-size:16px;color:var(--text-muted);border-left:4px solid var(--primary);padding:14px 20px;background:var(--primary-xlight);border-radius:0 var(--radius-sm) var(--radius-sm) 0;margin-bottom:28px;line-height:1.7">
          <?= htmlspecialchars($post['summary']) ?>
        </p>
        <?php endif; ?>

        <!-- Conteúdo HTML -->
        <div class="post-content"><?= $post['content'] ?></div>



        <!-- Vídeos -->
        <?php $pvideos = Database::fetchAll('SELECT * FROM post_videos WHERE post_id=? ORDER BY sort_order', [$post['id']]); ?>
        <?php if ($pvideos): ?>
        <div class="post-videos">
          <div class="post-videos-title"><span class="material-icons">play_circle</span> Vídeos</div>
          <?php foreach ($pvideos as $pv): ?>
          <?php
            $embedUrl = '';
            $ytId = '';
            if ($pv['video_type']==='youtube') {
              preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $pv['video_url'], $m);
              if (!empty($m[1])) { $ytId = $m[1]; $embedUrl = 'https://www.youtube.com/embed/'.$m[1].'?rel=0&autoplay=1'; }
            } elseif ($pv['video_type']==='vimeo') {
              preg_match('/vimeo\.com\/(\d+)/', $pv['video_url'], $m);
              if (!empty($m[1])) $embedUrl = 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1';
            }
          ?>
          <div class="video-item-wrap">
            <?php if ($pv['title']): ?><div class="video-item-label"><?= htmlspecialchars($pv['title']) ?></div><?php endif; ?>
            <?php if ($pv['video_type']==='mp4'): ?>
            <div class="video-embed-wrap">
              <video controls><source src="<?= htmlspecialchars($pv['video_url']) ?>" type="video/mp4"></video>
            </div>
            <?php elseif ($embedUrl): ?>
            <div class="video-thumb-preview" onclick="openVideoLightbox('<?= htmlspecialchars($embedUrl, ENT_QUOTES) ?>', '<?= htmlspecialchars($pv['title'] ?? '', ENT_QUOTES) ?>')">
              <?php if ($ytId): ?>
              <img src="https://img.youtube.com/vi/<?= $ytId ?>/maxresdefault.jpg"
                   onerror="this.src='https://img.youtube.com/vi/<?= $ytId ?>/hqdefault.jpg'"
                   alt="<?= htmlspecialchars($pv['title'] ?? '') ?>">
              <?php else: ?>
              <div class="video-thumb-placeholder"><span class="material-icons">play_circle</span></div>
              <?php endif; ?>
              <div class="video-play-btn"><span class="material-icons">play_circle_filled</span></div>
              <?php if ($pv['title']): ?><div class="video-thumb-label"><?= htmlspecialchars($pv['title']) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Rodapé do post -->
        <div style="margin-top:36px;padding-top:22px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
          <div style="display:flex;align-items:center;gap:12px">
            <div class="avatar-xs" style="width:38px;height:38px;font-size:15px">
              <?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($post['author_name']) ?></div>
              <div class="text-muted text-xs"><?= htmlspecialchars($post['sector'] ?? '') ?></div>
            </div>
          </div>
          <span class="text-muted text-sm">
            <span class="material-icons" style="font-size:15px;vertical-align:middle">visibility</span>
            <?= $post['views'] ?> visualizações
          </span>
        </div>
      </div>
    </div>

    <!-- Relacionados -->
    <?php
    $related = Database::fetchAll(
      "SELECT p.*,u.name as author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id
       WHERE p.type=? AND p.status='published' AND p.id!=?
       ORDER BY p.published_at DESC LIMIT 3",
      [$post['type'], $post['id']]
    );
    ?>
    <?php if ($related): ?>
    <div style="margin-top:32px">
      <div class="section-header">
        <span class="section-title">
          <span class="material-icons"><?= $post['type']==='comunicado'?'campaign':'newspaper' ?></span>
          Mais <?= $post['type']==='comunicado' ? 'Comunicados' : 'Notícias Externas' ?>
        </span>
      </div>
      <div class="post-grid">
        <?php foreach ($related as $r): ?>
        <a href="index.php?page=post&slug=<?= urlencode($r['slug']) ?>" class="post-card">
          <div class="post-cover">
            <?php if ($r['cover_image'] && file_exists(UPLOAD_DIR . $r['cover_image'])): ?>
            <img src="<?= UPLOAD_URL . htmlspecialchars($r['cover_image']) ?>" alt="" loading="lazy">
            <?php else: ?>
            <div class="post-cover-placeholder">
              <span class="material-icons"><?= $post['type']==='comunicado'?'campaign':'newspaper' ?></span>
            </div>
            <?php endif; ?>
            <span class="post-cover-badge <?= $r['type'] ?>">
              <?= $r['type']==='comunicado'?'Comunicado':'Notícia' ?>
            </span>
          </div>
          <div class="post-body">
            <div class="post-meta"><span class="post-date"><?= formatDate($r['published_at']) ?></span></div>
            <div class="post-title"><?= htmlspecialchars($r['title']) ?></div>
            <?php if ($r['summary']): ?>
            <div class="post-summary"><?= htmlspecialchars(mb_substr($r['summary'], 0, 100)) ?>…</div>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

<!-- ========================================================
     SISTEMAS
     ======================================================== -->
<?php elseif ($page === 'sistemas'): ?>
  <?php $sistemas = Database::fetchAll("SELECT * FROM modules WHERE category='sistema' AND active=1 ORDER BY sort_order"); ?>
  <div class="container main-content fade-in">
    <div class="section-header">
      <span class="section-title"><span class="material-icons">apps</span> Sistemas Institucionais</span>
      <span class="text-muted text-sm"><?= count($sistemas) ?> disponíveis</span>
    </div>
    <div class="systems-grid">
      <?php foreach ($sistemas as $sys): ?>
      <a href="<?= htmlspecialchars($sys['url']) ?>" target="<?= $sys['target'] ?>"
         class="system-card" style="--card-color:<?= htmlspecialchars($sys['color']) ?>">
        <div class="sys-icon">
          <?php if (!empty($sys['icon_image']) && file_exists(UPLOAD_DIR . 'modules' . DIRECTORY_SEPARATOR . $sys['icon_image'])): ?>
          <img src="<?= UPLOAD_URL ?>modules/<?= htmlspecialchars($sys['icon_image']) ?>"
               alt="<?= htmlspecialchars($sys['name']) ?>">
          <?php else: ?>
          <span class="material-icons"><?= htmlspecialchars($sys['icon']) ?></span>
          <?php endif; ?>
        </div>
        <div class="sys-name"><?= htmlspecialchars($sys['name']) ?></div>
        <div class="sys-desc"><?= htmlspecialchars($sys['description']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

<!-- ========================================================
     BUSCA
     ======================================================== -->
<?php elseif ($page === 'busca'): ?>
  <?php
  $q = sanitize($_GET['q'] ?? '');
  $results = strlen($q) > 2
    ? Database::fetchAll(
        "SELECT p.*,u.name as author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id
         WHERE p.status='published' AND (p.title LIKE ? OR p.summary LIKE ? OR p.content LIKE ?)
         ORDER BY p.published_at DESC LIMIT 24",
        ["%$q%","%$q%","%$q%"]
      )
    : [];
  ?>
  <div class="container main-content fade-in">
    <div class="section-header">
      <span class="section-title">
        <span class="material-icons">search</span>
        Resultados para: <em style="color:var(--primary)">"<?= htmlspecialchars($q) ?>"</em>
      </span>
      <span class="text-muted text-sm"><?= count($results) ?> resultado(s)</span>
    </div>
    <div class="post-grid">
      <?php foreach ($results as $post): ?>
      <a href="index.php?page=post&slug=<?= urlencode($post['slug']) ?>" class="post-card">
        <div class="post-cover">
          <?php if ($post['cover_image'] && file_exists(str_replace('/', DIRECTORY_SEPARATOR, UPLOAD_DIR . $post['cover_image']))): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($post['cover_image']) ?>" alt="" loading="lazy">
          <?php else: ?>
          <div class="post-cover-placeholder">
            <span class="material-icons"><?= $post['type']==='comunicado'?'campaign':'newspaper' ?></span>
          </div>
          <?php endif; ?>
          <span class="post-cover-badge <?= $post['type'] ?>"><?= $post['type'] ?></span>
        </div>
        <div class="post-body">
          <div class="post-meta"><span class="post-date"><?= formatDate($post['published_at']) ?></span></div>
          <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
          <?php if ($post['summary']): ?>
          <div class="post-summary"><?= htmlspecialchars(mb_substr($post['summary'],0,110)) ?>…</div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if (empty($results) && $q): ?>
    <div class="card card-body text-muted" style="text-align:center;padding:48px">
      <span class="material-icons" style="font-size:48px;display:block;margin-bottom:12px;opacity:.3">search_off</span>
      Nenhum resultado para "<?= htmlspecialchars($q) ?>".
    </div>
    <?php endif; ?>
  </div>

<!-- ========================================================
     RAMAIS
     ======================================================== -->
<?php elseif ($page === 'ramais'): ?>
  <?php require __DIR__ . '/pages/ramais.php'; ?>

<?php endif; ?>

<style>
/* Hover nos cards de lista */
.post-list-item .card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.post-list-item:hover .post-list-thumb { transform: scale(1.08); }
@media(max-width:900px){ .two-col-grid{ grid-template-columns:1fr!important; } }
@media(max-width:600px){ .card-body{ padding:20px 18px!important; } }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
