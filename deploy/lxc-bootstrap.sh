#!/bin/bash
# NetControl — Debian LXC: Apache + PHP + MariaDB + clon desde GitHub.
# Uso en el CT (root):   bash lxc-bootstrap.sh
# Con red: curl -fsSL URL_RAW | bash
set -euo pipefail

REPO_URL="${NETCONTROL_REPO_URL:-https://github.com/token64/NetControl.git}"
TARGET="${NETCONTROL_DIR:-/var/www/NetControl}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
  apache2 libapache2-mod-php \
  php php-cli php-mysqli php-mbstring php-xml php-curl \
  mariadb-server git

systemctl enable --now apache2 mariadb

install -d -m 0755 /var/www
if [[ -d "$TARGET/.git" ]]; then
  git -C "$TARGET" pull --ff-only
else
  rm -rf "$TARGET"
  git clone --depth 1 "$REPO_URL" "$TARGET"
fi

mysql --protocol=socket -u root -e \
  "CREATE DATABASE IF NOT EXISTS panel_wisp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql --protocol=socket -u root panel_wisp < "$TARGET/sql/schema.sql"

DBPASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 26)"
mysql --protocol=socket -u root -e \
  "CREATE USER IF NOT EXISTS 'netcontrol'@'localhost' IDENTIFIED BY '${DBPASS}';
   GRANT ALL PRIVILEGES ON panel_wisp.* TO 'netcontrol'@'localhost';
   FLUSH PRIVILEGES;"

export TARGET DBPASS
export ADMIN_HASH="$(php -r 'echo password_hash("admin", PASSWORD_DEFAULT);')"
export CRON_SECRET="$(openssl rand -hex 24)"
export INSTALL_TOKEN="$(openssl rand -hex 16)"

php <<'EOPHP'
<?php
declare(strict_types=1);
$base = getenv('TARGET') ?: '';
$cfg  = $base . '/panel/config.php';
$src  = $base . '/panel/config.example.php';
copy($src, $cfg);
$t = file_get_contents($cfg);
$t = str_replace("const DB_USER = 'root'", "const DB_USER = 'netcontrol'", $t);
$t = str_replace("const DB_PASS = ''", "const DB_PASS = '" . addcslashes((string) getenv('DBPASS'), "'\\") . "'", $t);
$t = preg_replace(
    '/^const ADMIN_PASSWORD_HASH = .*$/m',
    'const ADMIN_PASSWORD_HASH = \'' . addcslashes((string) getenv('ADMIN_HASH'), "'\\") . '\';',
    $t,
    1
);
$t = preg_replace(
    '/^const CRON_SECRET = .*$/m',
    'const CRON_SECRET = \'' . addcslashes((string) getenv('CRON_SECRET'), "'\\") . '\';',
    $t,
    1
);
$t = preg_replace(
    '/^const INSTALL_TOKEN = .*$/m',
    'const INSTALL_TOKEN = \'' . addcslashes((string) getenv('INSTALL_TOKEN'), "'\\") . '\';',
    $t,
    1
);
file_put_contents($cfg, $t);
EOPHP

chown -R www-data:www-data "$TARGET/panel"

cat >/etc/apache2/conf-available/netcontrol.conf <<EOF
Alias /netcontrol ${TARGET}/panel
<Directory ${TARGET}/panel>
    AllowOverride All
    Require all granted
</Directory>
EOF

a2enconf netcontrol >/dev/null
a2enmod rewrite >/dev/null
systemctl reload apache2

CREDS="/root/netcontrol-credentials.txt"
{
  echo "NetControl — archivo sensible; guárdalo y borra este archivo del CT cuando termines."
  echo "Panel: http://<IP>/netcontrol/  (inicio de sesión: admin / admin — cámbialo enseguida)"
  echo "Opcional install.php: http://<IP>/netcontrol/install.php?token=${INSTALL_TOKEN}"
  echo "MySQL usuario netcontrol / ${DBPASS}"
  echo "CRON_SECRET: ${CRON_SECRET}"
} >"$CREDS"
chmod 0600 "$CREDS"

echo "Listo. Credenciales: ${CREDS}"
IP1="$(hostname -I | awk '{print $1}')"
echo "Probar: http://${IP1}/netcontrol/"
