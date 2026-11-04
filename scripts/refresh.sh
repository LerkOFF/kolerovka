#!/usr/bin/env bash
# Пока dev-сервер запущен: пересобрать тему и модули, подписать модули и сбросить кэш страниц.
set -euo pipefail
cd "$(dirname "$0")/.."
STATE="${KOL_DEV_DIR:-/tmp/kol-dev}"
scripts/assemble.sh "$STATE/www" >/dev/null
export AGECMS_DATA_DIR="$STATE/data" AGECMS_CACHE_DIR="$STATE/cache" AGECMS_MODULES_DIR="$STATE/www/modules" AGECMS_UPLOADS_DIR="$STATE/uploads"
for m in "$STATE"/www/modules/*/; do
  [ -f "$m/manifest.json" ] && php "$STATE/www/bin/sign-module.php" "$(basename "$m")" >/dev/null
done
php "$STATE/www/bin/clear-cache.php" >/dev/null
echo "обновлено"
