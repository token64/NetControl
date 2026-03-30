/**
 * Botones: data-nc-geobtn
 * Opcional: data-nc-lat="#id" data-nc-lon="#id" data-nc-msg="#id"
 */
(function () {
  function fillGeo(btn) {
    var selLat = btn.getAttribute('data-nc-lat');
    var selLon = btn.getAttribute('data-nc-lon');
    var selMsg = btn.getAttribute('data-nc-msg');
    var lat = selLat ? document.querySelector(selLat) : null;
    var lon = selLon ? document.querySelector(selLon) : null;
    var msg = selMsg ? document.querySelector(selMsg) : null;
    if (!lat || !lon) {
      return;
    }
    if (!navigator.geolocation) {
      if (msg) {
        msg.textContent = 'Tu navegador no ofrece geolocalización.';
      }
      return;
    }
    if (msg) {
      msg.textContent = 'Obteniendo posición…';
    }
    navigator.geolocation.getCurrentPosition(
      function (p) {
        lat.value = p.coords.latitude.toFixed(7);
        lon.value = p.coords.longitude.toFixed(7);
        if (msg) {
          msg.textContent = '';
        }
      },
      function (err) {
        if (msg) {
          msg.textContent =
            err.code === 1 ? 'Permiso denegado.' : 'No se pudo obtener la posición.';
        }
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  }

  function bind(root) {
    root.querySelectorAll('[data-nc-geobtn]').forEach(function (btn) {
      if (btn.dataset.ncGeobound) {
        return;
      }
      btn.dataset.ncGeobound = '1';
      btn.addEventListener('click', function () {
        fillGeo(btn);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      bind(document);
    });
  } else {
    bind(document);
  }
})();
