#!/usr/bin/env bash
# Запускается НА СЕРВЕРЕ из клона репозитория: подтягивает изменения из git, пересобирает сайт, подписывает модули,
# сбрасывает кэш. Данные сайта (data/, загрузки, кэш) не трогаются.
#   scripts/server-update.sh                 обычное обновление
#   SEED=1 scripts/server-update.sh          ещё раз загрузить страницы и меню из content/ (затрёт правки текстов в админке)
# Переменные: KOL_WWW (папка сайта, по умолчанию /var/www/kolerovka/www), KOL_URL (адрес сайта для первой установки).
set -euo pipefail
cd "$(dirname "$0")/.."
WWW="${KOL_WWW:-/var/www/kolerovka/www}"
URL="${KOL_URL:-https://ch.oai.su}"
RUN=(runuser -u www-data --)

git pull -q --ff-only
git submodule update --init -q
scripts/assemble.sh "$WWW"
chown -R www-data:www-data "$WWW"

FIRST=0
if [ ! -f "$WWW/data/install.lock" ]; then
  FIRST=1
  PASS="$(openssl rand -hex 10)"
  "${RUN[@]}" php "$WWW/bin/install.php" --url="$URL" --name="Колеровка красок" --no-demo --admin=admin --password="$PASS" >/dev/null
  umask 077
  printf 'admin\n%s\n' "$PASS" > /root/kolerovka-admin-login.txt
  echo "Установлено. Логин и пароль админки: /root/kolerovka-admin-login.txt"
fi
for m in "$WWW"/modules/*/; do
  [ -f "$m/manifest.json" ] && "${RUN[@]}" php "$WWW/bin/sign-module.php" "$(basename "$m")" >/dev/null
done
if [ "$FIRST" = 1 ] || [ "${SEED:-0}" = 1 ]; then
  "${RUN[@]}" php scripts/seed.php "$WWW"
fi
"${RUN[@]}" php "$WWW/bin/clear-cache.php" >/dev/null
echo "обновлено: $(git rev-parse --short HEAD)"
