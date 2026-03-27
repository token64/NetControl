#!/bin/bash
# En el CT como root: restaura login admin/admin (corrige hash bcrypt mal escrito).
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
php "${DIR}/patch-admin-hash.php"
