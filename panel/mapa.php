<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Mapa — NetControl';

$markers = [];
$res = db()->query('SELECT id, nombre, latitud, longitud, estado FROM clientes WHERE latitud IS NOT NULL AND longitud IS NOT NULL');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $markers[] = [
            'lat' => (float) $row['latitud'],
            'lng' => (float) $row['longitud'],
            'title' => (string) $row['nombre'],
            'estado' => (string) $row['estado'],
        ];
    }
}

$hasKey = GOOGLE_MAPS_KEY !== '';
require __DIR__ . '/partials/header.php';
?>

<h1 class="h3 mb-3">Mapa de clientes</h1>

<?php if (! $hasKey): ?>
    <div class="alert alert-warning nc-flash">Configura <code>GOOGLE_MAPS_KEY</code> en <code>config.php</code> para mostrar el mapa.</div>
<?php else: ?>
    <div id="map" class="nc-map-wrap"></div>
    <script>
        window.__ncMarkers = <?= json_encode($markers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= esc(GOOGLE_MAPS_KEY) ?>&callback=ncInitMap"></script>
    <script>
        function ncInitMap() {
            var center = { lat: 18.5, lng: -69.9 };
            if (window.__ncMarkers.length > 0) {
                center = { lat: window.__ncMarkers[0].lat, lng: window.__ncMarkers[0].lng };
            }
            var map = new google.maps.Map(document.getElementById('map'), { zoom: 12, center: center });
            window.__ncMarkers.forEach(function (m) {
                new google.maps.Marker({
                    position: { lat: m.lat, lng: m.lng },
                    map: map,
                    title: m.title
                });
            });
        }
    </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
