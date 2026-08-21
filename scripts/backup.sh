#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$ROOT/storage/app/backups/backup-$STAMP"
mkdir -p "$OUT"

if [[ "${DB_CONNECTION:-sqlite}" == "sqlite" ]]; then
  cp "$ROOT/database/database.sqlite" "$OUT/database.sqlite"
else
  mysqldump -h "${DB_HOST:-127.0.0.1}" -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" "${DB_DATABASE:-coaching}" > "$OUT/database.sql"
fi

tar -czf "$OUT/storage.tar.gz" -C "$ROOT" storage/app
echo "Backup written to $OUT"
