/**
 * Autocompletar nombre desde api_cedula_nombre.php (sesión del panel).
 */
(function () {
  function q(root, sel) {
    if (sel.startsWith('#')) {
      return document.getElementById(sel.slice(1));
    }
    return root.querySelector(sel);
  }

  function initOne(root) {
    var endpoint = root.getAttribute('data-endpoint') || 'api_cedula_nombre.php';
    var cedSel = root.getAttribute('data-cedula') || '#nc_cedula';
    var nomSel = root.getAttribute('data-nombre') || '#nc_nombre';
    var msgSel = root.getAttribute('data-msg') || '#ncCedulaMsg';
    var badgeSel = root.getAttribute('data-fuente') || '#ncNombreFuente';
    var ced = q(document, cedSel);
    var nom = q(document, nomSel);
    var msg = q(document, msgSel);
    var badge = badgeSel ? q(document, badgeSel) : null;
    var btn = root.querySelector('[data-nc-cedula-btn]');
    if (!ced || !nom || !btn) {
      return;
    }

    function setMsg(t, kind) {
      if (!msg) return;
      msg.textContent = t || '';
      msg.classList.remove('text-danger', 'text-success', 'text-secondary');
      msg.classList.add(kind === 'err' ? 'text-danger' : kind === 'ok' ? 'text-success' : 'text-secondary');
    }

    function setFuente(text) {
      if (!badge) return;
      if (text) {
        badge.style.display = '';
        badge.textContent = text;
      } else {
        badge.style.display = 'none';
        badge.textContent = '';
      }
    }

    function run() {
      var raw = (ced.value || '').trim();
      var digits = raw.replace(/\D/g, '');
      if (digits.length !== 11) {
        setMsg('Ingresá 11 dígitos (cédula RD).', 'err');
        return;
      }
      setMsg('Buscando…', '');
      btn.disabled = true;
      var url = endpoint + '?cedula=' + encodeURIComponent(raw);
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) {
          return r.json().then(function (j) {
            return { okHttp: r.ok, j: j };
          });
        })
        .then(function (x) {
          var j = x.j;
          if (!j || !j.ok) {
            setMsg((j && j.error) || 'No se encontró.', 'err');
            setFuente('');
            return;
          }
          nom.value = j.nombre || '';
          setMsg('Nombre cargado. Verificá antes de guardar.', 'ok');
          setFuente(j.fuente ? 'Fuente: ' + j.fuente : '');
        })
        .catch(function () {
          setMsg('Error de red o del servidor.', 'err');
          setFuente('');
        })
        .finally(function () {
          btn.disabled = false;
        });
    }

    btn.addEventListener('click', run);
  }

  document.querySelectorAll('[data-nc-cedula-root]').forEach(initOne);
})();
