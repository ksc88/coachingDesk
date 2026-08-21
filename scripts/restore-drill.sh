#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LATEST="$(ls -1dt "$ROOT"/storage/app/backups/backup-* 2>/dev/null | head -1 || true)"
if [[ -z "${LATEST}" ]]; then
  echo "No backup found. Run scripts/backup.sh first."
  exit 1
fi

echo "Restore drill using $LATEST"
if [[ -f "$LATEST/database.sqlite" ]]; then
  cp "$LATEST/database.sqlite" "$ROOT/database/database.sqlite.restored"
  echo "SQLite restored copy: database/database.sqlite.restored"
fi
if [[ -f "$LATEST/storage.tar.gz" ]]; then
  mkdir -p /tmp/coaching-restore-drill
  tar -xzf "$LATEST/storage.tar.gz" -C /tmp/coaching-restore-drill
  echo "Storage archive extracted to /tmp/coaching-restore-drill"
fi
echo "Restore drill completed (non-destructive)."
