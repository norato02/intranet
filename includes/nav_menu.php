<?php
// includes/nav_menu.php
//
// Renderiza os <li> do menu de navegação a partir de nav_items — usado
// por includes/header.php (área logada), public.php e pages/rh.php
// (público), pra existir só UM lugar pra manter quando o menu muda,
// em vez de três cópias divergindo aos poucos.
//
// Espera definidas antes do include:
//   $navMenuCurrentPage — slug da página atual (ex: 'home', 'comunicados')
//   $navMenuPublic      — bool: true usa publicNavUrl(), false usa navUrl()

$navMenuItemsAll = Database::fetchAll('SELECT * FROM nav_items WHERE active=1 ORDER BY sort_order');
$navMenuItems = array_values(array_filter($navMenuItemsAll, fn($i) => $i['parent_id'] === null));
$navMenuChildren = [];
foreach ($navMenuItemsAll as $i) {
    if ($i['parent_id'] !== null) $navMenuChildren[$i['parent_id']][] = $i;
}
$navMenuUrlFn = $navMenuPublic ? 'publicNavUrl' : 'navUrl';
?>
<?php foreach ($navMenuItems as $navMenuItem):
  $navMenuItemChildren = $navMenuChildren[$navMenuItem['id']] ?? [];
  $navMenuItemPage = null;
  if (preg_match('~(?:^|/)(?:index|public)\.php$~', $navMenuItem['url'])) {
      $navMenuItemPage = 'home';
  } elseif (preg_match('~(?:^|/)(?:index|public)\.php\?page=([^&]+)~', $navMenuItem['url'], $navMenuPm)) {
      $navMenuItemPage = $navMenuPm[1];
  }
  $navMenuIsActive = $navMenuItemPage !== null && $navMenuCurrentPage === $navMenuItemPage;
?>
<?php if ($navMenuItemChildren): ?>
<li class="dropdown">
  <a href="javascript:void(0)" class="<?= $navMenuIsActive ? 'active' : '' ?>">
    <?php if ($navMenuItem['icon']): ?><span class="material-icons"><?= htmlspecialchars($navMenuItem['icon']) ?></span><?php endif; ?>
    <?= htmlspecialchars($navMenuItem['label']) ?>
    <span class="material-icons" style="font-size:16px;margin-left:2px">expand_more</span>
  </a>
  <div class="dropdown-menu">
    <?php foreach ($navMenuItemChildren as $navMenuChild): ?>
    <a href="<?= htmlspecialchars($navMenuUrlFn($navMenuChild['url'])) ?>" class="dropdown-item"
       <?= $navMenuChild['open_new_tab'] ? 'target="_blank"' : '' ?>>
      <?php if ($navMenuChild['icon']): ?><span class="material-icons"><?= htmlspecialchars($navMenuChild['icon']) ?></span><?php endif; ?>
      <?= htmlspecialchars($navMenuChild['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</li>
<?php else: ?>
<li>
  <a href="<?= htmlspecialchars($navMenuUrlFn($navMenuItem['url'])) ?>"
     <?= $navMenuItem['open_new_tab'] ? 'target="_blank"' : '' ?>
     class="<?= $navMenuIsActive ? 'active' : '' ?>">
    <?php if ($navMenuItem['icon']): ?><span class="material-icons"><?= htmlspecialchars($navMenuItem['icon']) ?></span><?php endif; ?>
    <?= htmlspecialchars($navMenuItem['label']) ?>
  </a>
</li>
<?php endif; ?>
<?php endforeach; ?>
