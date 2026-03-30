<?php
declare(strict_types=1);
$__ncGeo = dirname(__DIR__) . '/assets/nc-geolocate.js';
$__ncGeoV = is_readable($__ncGeo) ? (string) filemtime($__ncGeo) : '1';
?>
</main>
<?php if (auth_logged_in()): ?>
    </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/nc-geolocate.js?v=<?= esc($__ncGeoV) ?>"></script>
</body>
</html>
