<?php
if (!isset($admin_base)) {
    $admin_base = '';
}
$materio_base = $admin_base . 'assets/materio/';
$materio_assets = $materio_base . 'assets/';
?>
        </main>
      </section>
    </div>

    <script src="<?php echo htmlspecialchars($materio_assets); ?>vendor/libs/jquery/jquery.js"></script>
    <script src="<?php echo htmlspecialchars($materio_assets); ?>vendor/libs/popper/popper.js"></script>
    <script src="<?php echo htmlspecialchars($materio_assets); ?>vendor/js/bootstrap.js"></script>
    <?php $admin_ui_js_v = @filemtime(__DIR__ . '/admin-ui.js') ?: time(); ?>
    <script src="<?php echo htmlspecialchars($admin_base); ?>includes/admin-ui.js?v=<?php echo (int)$admin_ui_js_v; ?>"></script>
  </body>
</html>
