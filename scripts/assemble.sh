#!/usr/bin/env bash
# Собирает сайт: ядро AgeCMS (vendor/agecms) + тема и модули из site/ в каталог $1.
# Ядро не правится. Данные (data/), кэш и загрузки в $1 не трогаются, их держат отдельно.
set -euo pipefail
cd "$(dirname "$0")/.."
DEST="${1:?Usage: scripts/assemble.sh <папка для сборки>}"
[ -f vendor/agecms/index.php ] || { echo "Ядро не найдено: git submodule update --init" >&2; exit 1; }
mkdir -p "$DEST"
rsync -a --delete \
  --exclude '.git' --exclude '/tests' --exclude '/bench' --exclude '/docs' \
  --exclude '/data' --exclude '/assets/cache' --exclude '/assets/uploads' \
  --exclude '/themes' --exclude '/modules' \
  vendor/agecms/ "$DEST/"
mkdir -p "$DEST/themes" "$DEST/modules"
rsync -a --delete vendor/agecms/themes/ "$DEST/themes/"
rsync -a --delete vendor/agecms/modules/ "$DEST/modules/"
rsync -a site/themes/ "$DEST/themes/"
for m in ${KOL_MODULES:-catalog}; do
  [ -d "site/modules/$m" ] && rsync -a --delete "site/modules/$m/" "$DEST/modules/$m/"
done
echo "Собрано: $DEST (ядро $(git -C vendor/agecms rev-parse --short HEAD))"
