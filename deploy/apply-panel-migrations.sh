#!/bin/bash
# Aplica migraciones SQL usando panel/config.php (mismas credenciales que el panel).
# No guarda contraseñas en el script.
#
# Uso en la VM NetControl:
#   sudo bash deploy/apply-panel-migrations.sh
#   sudo bash deploy/apply-panel-migrations.sh /var/www/NetControl
set -euo pipefail

ROOT="${1:-/var/www/NetControl}"
cd "$ROOT"

if [[ ! -f panel/config.php ]]; then
  echo "No existe panel/config.php en $ROOT" >&2
  exit 1
fi

run_sql() {
  local rel="$1"
  echo "==> $rel"
  php panel/tools/apply_sql_file.php "$rel"
}

# Orden: columnas mikrotiks → catálogo planes/redes (idempotente con IF NOT EXISTS)
run_sql sql/migration_mikrotiks_equipo_ubicacion.sql
run_sql sql/migration_mikrotiks_enlace_notas.sql
run_sql sql/migration_v2_planes_redes.sql

echo "Listo. Recargá Apache si hace falta: sudo systemctl reload apache2"
