#!/usr/bin/env bash
# Выкладка через git: проверяет, что всё закоммичено и запушено, затем на сервере обновляет клон и пересобирает сайт.
#   scripts/deploy.sh            выложить
#   SEED=1 scripts/deploy.sh     выложить и заново загрузить страницы из content/
# Хост из ~/.ssh/config: DEPLOY_HOST (обязателен), папка клона: DEPLOY_DIR (/var/www/tr/repo).
set -euo pipefail
cd "$(dirname "$0")/.."
HOST="${DEPLOY_HOST:?задайте DEPLOY_HOST, имя хоста из ~/.ssh/config}"
DIR="${DEPLOY_DIR:-/var/www/tr/repo}"
if [ -n "$(git status --porcelain)" ]; then
  echo "Есть незакоммиченные изменения: сначала git commit." >&2; exit 1
fi
git push -q origin HEAD
ssh "$HOST" "cd '$DIR' && SEED='${SEED:-0}' scripts/server-update.sh"
