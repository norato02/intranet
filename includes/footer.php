<?php // includes/footer.php ?>
</div><!-- /page-wrapper -->

<footer class="footer">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div>
        <div style="font-weight:700;color:#fff;margin-bottom:4px">
           <?= htmlspecialchars(getSetting('site_name', 'Intranet')) ?>
        </div>
        <div><?= htmlspecialchars(getSetting('footer_text', '© 2024 Unidade de Saúde.')) ?></div>
      </div>
      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/public.php">Área Pública</a>
        <a href="<?= BASE_URL ?>/index.php?page=comunicados">Comunicados</a>
        <a href="<?= BASE_URL ?>/index.php?page=noticias">Notícias</a>
        <a href="<?= BASE_URL ?>/index.php?page=sistemas">Sistemas</a>
      </div>
    </div>
    <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,.15);font-size:12px;opacity:.7">
      Versão <?= APP_VERSION ?> — PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?>
    </div>
  </div>
</footer>

<div class="toast-container" id="toastContainer"></div>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
