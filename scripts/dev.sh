#!/usr/bin/env bash
# Локальный экземпляр сайта. Всё состояние (база, кэш, загрузки, пароль админа) лежит вне репозитория.
#   scripts/dev.sh            собрать, при первом запуске установить, загрузить content/, запустить сервер
#   scripts/dev.sh --no-serve то же без запуска сервера
# Переменные: KOL_DEV_DIR (по умолчанию /tmp/kol-dev), KOL_PORT (8098).
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$PWD"
STATE="${KOL_DEV_DIR:-/tmp/kol-dev}"
PORT="${KOL_PORT:-8098}"
WWW="$STATE/www"
mkdir -p "$STATE"
scripts/assemble.sh "$WWW"

export AGECMS_DATA_DIR="$STATE/data" AGECMS_CACHE_DIR="$STATE/cache" AGECMS_MODULES_DIR="$WWW/modules" AGECMS_UPLOADS_DIR="$STATE/uploads" KOL_WWW="$WWW"
mkdir -p "$AGECMS_UPLOADS_DIR"
if [ ! -f "$AGECMS_DATA_DIR/install.lock" ]; then
  PASS="$(openssl rand -hex 10)"
  php "$WWW/bin/install.php" --url="http://127.0.0.1:$PORT" --name="Колеровка красок" --no-demo --admin=admin --password="$PASS" >/dev/null
  printf 'admin\n%s\n' "$PASS" > "$STATE/admin-login.txt"
  chmod 600 "$STATE/admin-login.txt"
  echo "Установлено. Логин и пароль админки: $STATE/admin-login.txt"
fi
php -r 'require $argv[1]."/core/bootstrap.php"; $c = \AgeCMS\Config::all(); $c["mail"]["transport"] = "log"; $c["mail"]["from"] = "noreply@kolerovka.test"; \AgeCMS\Config::save($c);' "$WWW"
for m in "$WWW"/modules/*/; do
  [ -f "$m/manifest.json" ] && php "$WWW/bin/sign-module.php" "$(basename "$m")" >/dev/null
done
php scripts/seed.php "$WWW"
[ "${1:-}" = "--no-serve" ] && exit 0
echo "http://127.0.0.1:$PORT/  (Ctrl+C остановит)"
exec php -S "127.0.0.1:$PORT" -t "$WWW" "$ROOT/scripts/dev-router.php"
