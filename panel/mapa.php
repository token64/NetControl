<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$navSection = 'clientes-mapa';
$pageTitle = 'Mapa — NetControl';

$mapDefaultLat = defined('MAP_DEFAULT_LAT') ? (float) MAP_DEFAULT_LAT : 18.5;
$mapDefaultLng = defined('MAP_DEFAULT_LNG') ? (float) MAP_DEFAULT_LNG : -69.9;
$mapDefaultZoom = defined('MAP_DEFAULT_ZOOM') ? max(1, min(18, (int) MAP_DEFAULT_ZOOM)) : 12;

$markers = [];
$res = db()->query('SELECT id, nombre, latitud, longitud, estado FROM clientes WHERE latitud IS NOT NULL AND longitud IS NOT NULL ORDER BY nombre ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $markers[] = [
            'id' => (int) $row['id'],
            'lat' => (float) $row['latitud'],
            'lng' => (float) $row['longitud'],
            'title' => (string) $row['nombre'],
            'estado' => (string) $row['estado'],
        ];
    }
}

$totalClientes = 0;
$countRes = db()->query('SELECT COUNT(*) AS c FROM clientes');
if ($countRes && $crow = $countRes->fetch_assoc()) {
    $totalClientes = (int) $crow['c'];
}

$conMapa = count($markers);

$ncHeadExtra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">';

require __DIR__ . '/partials/header.php';
?>

<div class="nc-map-page">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 mb-3">
        <div class="flex-grow-1">
            <h1 class="h3 mb-1">Mapa de clientes</h1>
            <p class="text-secondary small mb-0">OpenStreetMap vía Leaflet — no requiere clave de API. Coordenadas desde la ficha de cada cliente.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill text-bg-secondary nc-map-stat"><i class="bi bi-people me-1"></i><?= $conMapa ?> con ubicación</span>
            <?php if ($totalClientes > 0): ?>
                <span class="badge rounded-pill text-bg-dark border border-secondary nc-map-stat"><i class="bi bi-database me-1"></i><?= $totalClientes ?> clientes en total</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($conMapa === 0 && $totalClientes > 0): ?>
        <div class="alert alert-info nc-flash border-secondary">
            Ningún cliente tiene <strong>latitud y longitud</strong> cargadas. Editá una ficha y guardá las coordenadas para verla aquí.
        </div>
    <?php elseif ($conMapa === 0): ?>
        <div class="alert alert-secondary nc-flash border-secondary">
            Creá clientes y asignales coordenadas en su ficha para visualizarlos en el mapa.
        </div>
    <?php endif; ?>

    <div class="row g-3 align-items-stretch">
        <div class="col-lg-8 order-lg-1 order-2">
            <div id="ncMap" class="nc-map-wrap" role="region" aria-label="Mapa de clientes"></div>
            <p class="small text-secondary mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Sin marcadores, el mapa abre en el centro definido en <code>config.php</code> (<code>MAP_DEFAULT_*</code> en el ejemplo).</p>
        </div>
        <div class="col-lg-4 order-lg-2 order-1">
            <div class="nc-card nc-map-sidebar p-0 h-100 d-flex flex-column">
                <div class="p-3 border-bottom border-secondary border-opacity-25">
                    <h2 class="h6 text-uppercase text-secondary mb-0 small">En el mapa</h2>
                </div>
                <?php if ($conMapa === 0): ?>
                    <div class="p-3 text-secondary small">Lista vacía hasta que haya coordenadas.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush nc-map-list flex-grow-1 overflow-auto">
                        <?php foreach ($markers as $m): ?>
                            <li class="list-group-item nc-map-list-item border-secondary border-opacity-25 bg-transparent text-light px-3 py-2">
                                <button type="button" class="btn btn-link text-start text-decoration-none p-0 text-light w-100 nc-map-focus" data-id="<?= (int) $m['id'] ?>" data-lat="<?= esc((string) $m['lat']) ?>" data-lng="<?= esc((string) $m['lng']) ?>">
                                    <span class="fw-semibold d-block text-truncate"><?= esc($m['title']) ?></span>
                                    <span class="small text-secondary"><?= esc($m['estado']) ?></span>
                                </button>
                                <a class="btn btn-sm btn-outline-secondary mt-2" href="cliente_edit.php?id=<?= (int) $m['id'] ?>">Ficha</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    window.__ncMarkers = <?= json_encode($markers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    window.__ncMapDefaults = <?= json_encode([
        'lat' => $mapDefaultLat,
        'lng' => $mapDefaultLng,
        'zoom' => $mapDefaultZoom,
    ], JSON_THROW_ON_ERROR) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script>
(function () {
    var markers = window.__ncMarkers || [];
    var def = window.__ncMapDefaults || { lat: 18.5, lng: -69.9, zoom: 12 };
    var el = document.getElementById('ncMap');
    if (!el || typeof L === 'undefined') return;

    var map = L.map(el, { scrollWheelZoom: true });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    var layerGroup = L.layerGroup().addTo(map);
    markers.forEach(function (m) {
        var mk = L.marker([m.lat, m.lng]);
        mk.ncClientId = m.id;
        mk.bindPopup(
            '<strong>' + esc(m.title) + '</strong><br><span class="text-secondary">' + esc(m.estado) + '</span><br>' +
            '<a class="nc-map-popup-link" href="cliente_edit.php?id=' + encodeURIComponent(m.id) + '">Abrir ficha</a>'
        );
        mk.addTo(layerGroup);
    });

    if (markers.length > 1) {
        map.fitBounds(L.latLngBounds(markers.map(function (m) { return [m.lat, m.lng]; })), { padding: [28, 28], maxZoom: 15 });
    } else if (markers.length === 1) {
        map.setView([markers[0].lat, markers[0].lng], Math.max(def.zoom + 2, 14));
    } else {
        map.setView([def.lat, def.lng], def.zoom);
    }

    document.querySelectorAll('.nc-map-focus').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-id'), 10);
            var lat = parseFloat(btn.getAttribute('data-lat'));
            var lng = parseFloat(btn.getAttribute('data-lng'));
            if (isNaN(lat) || isNaN(lng)) return;
            map.setView([lat, lng], Math.max(map.getZoom(), 15));
            layerGroup.eachLayer(function (layer) {
                if (layer instanceof L.Marker && layer.ncClientId === id) {
                    layer.openPopup();
                }
            });
        });
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
