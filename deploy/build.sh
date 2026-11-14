#!/usr/bin/env bash
#
# Arma el paquete para subir a un hosting cPanel SIN SSH.
#
#   bash deploy/build.sh [dominio] [correo-admin]
#   bash deploy/build.sh radawes.com admin@radawes.com
#
# Resultado en dist/:
#   radawes-hosting-AAAAMMDD.zip   → se descomprime en la carpeta de inicio de cPanel (crea rye/ y public_html/)
#   radawes-base-datos.sql         → se importa con phpMyAdmin
#   CREDENCIALES.txt               → usuario y contraseña del primer administrador (NO subir)
#
# Requisitos locales: XAMPP (PHP + MariaDB encendido), Composer, Node, Python.
set -euo pipefail

DOMAIN="${1:-radawes.com}"
ADMIN_EMAIL="${2:-admin@$DOMAIN}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$ROOT/build"
DIST="$ROOT/dist"
STAMP="$(date +%Y%m%d)"
MYSQL_BIN="${MYSQL_BIN:-/c/xampp/mysql/bin}"
BUILD_DB="rye_build_$STAMP"

cd "$ROOT"
echo "==> Paquete para https://$DOMAIN (admin: $ADMIN_EMAIL)"

echo "==> 1/6 Compilando estilos y scripts"
npm run build --silent

echo "==> 2/6 Copiando el sistema a build/rye"
rm -rf "$BUILD" && mkdir -p "$BUILD/rye" "$BUILD/public_html" "$DIST"
tar --exclude=./.git --exclude=./node_modules --exclude=./vendor --exclude=./build --exclude=./dist \
    --exclude=./tests --exclude=./.env --exclude=./.env.backup --exclude=./.claude --exclude=./public \
    --exclude='./storage/logs/*' --exclude='./storage/framework/cache/data/*' \
    --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' \
    --exclude='./bootstrap/cache/*.php' \
    -cf - . | (cd "$BUILD/rye" && tar -xf -)
mkdir -p "$BUILD/rye/storage/logs" "$BUILD/rye/storage/framework/"{cache/data,sessions,views} "$BUILD/rye/bootstrap/cache"

echo "==> 3/6 Instalando dependencias de producción (sin herramientas de desarrollo)"
(cd "$BUILD/rye" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

echo "==> 4/6 Preparando public_html y .env"
cp -r public/css public/js public/favicon.ico public/robots.txt "$BUILD/public_html/"
cp public/google*.html "$BUILD/public_html/" 2>/dev/null || true
mkdir -p "$BUILD/public_html/images" "$BUILD/public_html/uploads"
cp -r public/images/brands "$BUILD/public_html/images/"
cp deploy/index.php "$BUILD/public_html/index.php"
cp deploy/htaccess "$BUILD/public_html/.htaccess"
printf '# Fotos subidas desde el panel\n' > "$BUILD/public_html/uploads/LEEME.txt"
APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
sed -e "s#__APP_KEY__#$APP_KEY#" -e "s#__DOMAIN__#$DOMAIN#g" deploy/env.production > "$BUILD/rye/.env"

echo "==> 5/6 Generando la base de datos inicial"
ADMIN_PASSWORD="$(php -r '$c="ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789"; $p=""; for($i=0;$i<16;$i++){$p.=$c[random_int(0,strlen($c)-1)];} echo $p;')"
"$MYSQL_BIN/mysql.exe" -u root -e "DROP DATABASE IF EXISTS \`$BUILD_DB\`; CREATE DATABASE \`$BUILD_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
APP_ENV=production DB_DATABASE="$BUILD_DB" STORE_BASE_DOMAIN="$DOMAIN" ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    php artisan migrate:fresh --seed --seeder='Database\Seeders\ProductionSeeder' --force --no-interaction > /dev/null
"$MYSQL_BIN/mysqldump.exe" -u root --no-tablespaces --single-transaction --skip-comments \
    --default-character-set=utf8mb4 "$BUILD_DB" > "$DIST/radawes-base-datos.sql"
"$MYSQL_BIN/mysql.exe" -u root -e "DROP DATABASE \`$BUILD_DB\`;"

echo "==> 6/6 Comprimiendo"
ZIP="$DIST/radawes-hosting-$STAMP.zip"
rm -f "$ZIP"
python - "$BUILD" "$ZIP" <<'PY'
import os, sys, zipfile
src, out = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for top in ('rye', 'public_html'):
        for root, dirs, files in os.walk(os.path.join(src, top)):
            rel = os.path.relpath(root, src).replace(os.sep, '/')
            if not files and not dirs:
                z.writestr(rel + '/', '')          # conservar carpetas vacías (storage/…)
            for f in files:
                p = os.path.join(root, f)
                z.write(p, rel + '/' + f)
print('zip listo')
PY

# La guía usa radawes.com como ejemplo: se reemplaza por el dominio real
sed "s/radawes\.com/$DOMAIN/g" deploy/GUIA-INSTALACION-CPANEL.md > "$DIST/GUIA-INSTALACION-CPANEL.md"

cat > "$DIST/CREDENCIALES.txt" <<EOF
Primer acceso al panel — guárdalo en un lugar seguro y NO lo subas al hosting.

Panel:      https://$DOMAIN/admin/login
Correo:     $ADMIN_EMAIL
Contraseña: $ADMIN_PASSWORD

Después de entrar, crea los usuarios reales (supervisores y vendedores) en Panel → Usuarios.
EOF

echo
echo "Listo:"
ls -lh "$DIST"
