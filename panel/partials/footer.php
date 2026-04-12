<?php
declare(strict_types=1);
$__ncGeo = dirname(__DIR__) . '/assets/nc-geolocate.js';
$__ncGeoV = is_readable($__ncGeo) ? (string) filemtime($__ncGeo) : '1';
$__logged = auth_logged_in();
?>
<?php if ($__logged): ?>
            </div><!-- /.container-fluid -->
        </div><!-- /.app-content -->
        <footer class="app-footer border-top py-3 px-4 small">
            <div class="float-end d-none d-sm-inline text-secondary">NetControl WISP</div>
            <strong>NetControl</strong>
            <span class="text-secondary"> — panel operador</span>
        </footer>
    </main>
</div><!-- /.app-wrapper -->
<?php else: ?>
</main>
<?php endif; ?>
<?php if ($__logged): ?>
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
<script>
(function () {
  var SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
  var Default = {
    scrollbarTheme: 'os-theme-light',
    scrollbarAutoHide: 'leave',
    scrollbarClickScroll: true
  };
  document.addEventListener('DOMContentLoaded', function () {
    var sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
    if (sidebarWrapper && typeof OverlayScrollbarsGlobal !== 'undefined' && OverlayScrollbarsGlobal.OverlayScrollbars) {
      OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
        scrollbars: {
          theme: Default.scrollbarTheme,
          autoHide: Default.scrollbarAutoHide,
          clickScroll: Default.scrollbarClickScroll
        }
      });
    }
  });
})();
</script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<script src="assets/nc-geolocate.js?v=<?= esc($__ncGeoV) ?>"></script>
</body>
</html>
