#!/usr/bin/env bash
# B07: encrypted offline-consistent backup (Linux production host; never from Git).
set -Eeuo pipefail
umask 077

usage() {
  echo 'Uso: operations/backup.sh ENV_FILE DEST_DIR AGE_RECIPIENT CONFIRM_DATABASE --maintenance-confirmed' >&2
  exit 2
}
[[ $# -eq 5 && "${5:-}" == '--maintenance-confirmed' ]] || usage
ENV_FILE=$1
DEST_DIR=$2
RECIPIENT=$3
CONFIRM=$4
[[ -f "$ENV_FILE" && -r "$ENV_FILE" && "$RECIPIENT" == age1* ]] || usage
[[ "$CONFIRM" =~ ^[a-zA-Z][a-zA-Z0-9_]*$ ]] || usage
for command in docker age sha256sum date mktemp; do
  command -v "$command" >/dev/null || { echo "B07: falta $command." >&2; exit 1; }
done

# This script only runs on the named Compose production stack and when writes
# are manually frozen. Do NOT assume a database dump and files are consistent
# while application, scheduler, or worker are writing.
ROOT=$(cd "$(dirname "$0")/.." && pwd -P)
COMPOSE=(docker compose --env-file "$ENV_FILE" -f "$ROOT/compose.production.yaml")
DATABASE=$("${COMPOSE[@]}" exec -T db sh -c 'printf %s "$POSTGRES_DB"')
[[ "$DATABASE" == "$CONFIRM" ]] || { echo 'B07: base no coincide; respaldo cancelado.' >&2; exit 1; }
"${COMPOSE[@]}" exec -T app sh -c 'test -f storage/framework/down' || {
  echo 'B07: active antes el modo mantenimiento de la web.' >&2; exit 1;
}
RUNNING=$("${COMPOSE[@]}" ps --status running --services)
if grep -Eq '^(worker|scheduler)$' <<< "$RUNNING"; then
  echo 'B07: detenga primero worker y scheduler para una copia coherente.' >&2; exit 1
fi

mkdir -p "$DEST_DIR"
DEST_DIR=$(cd "$DEST_DIR" && pwd -P)
case "$DEST_DIR/" in "$ROOT/"*) echo 'B07: no guardar respaldos dentro del repositorio.' >&2; exit 1;; esac
STAMP=$(date -u +'%Y%m%dT%H%M%SZ')
FINAL="$DEST_DIR/smartaulaues-$STAMP"
[[ ! -e "$FINAL" ]] || { echo 'B07: carpeta de destino ya existe.' >&2; exit 1; }
WORK=$(mktemp -d "$DEST_DIR/.b07-pending.XXXXXXXX")
cleanup() { [[ -z "${WORK:-}" ]] || rm -rf -- "$WORK"; }
trap cleanup EXIT

# Only encrypted bytes are ever written to the backup host. Database and
# private file archives are piped from Docker directly into age encryption.
"${COMPOSE[@]}" exec -T db sh -c 'exec pg_dump -Fc -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
  | age -r "$RECIPIENT" -o "$WORK/database.dump.age"
"${COMPOSE[@]}" exec -T app tar -C /var/www/html/storage -czf - app \
  | age -r "$RECIPIENT" -o "$WORK/private-files.tar.gz.age"
age -r "$RECIPIENT" -o "$WORK/production-secrets.env.age" "$ENV_FILE"
(
  cd "$WORK"
  sha256sum database.dump.age private-files.tar.gz.age production-secrets.env.age > SHA256SUMS
)
printf 'B07 encrypted backup\nUTC=%s\nDatabase=%s\nMaintenance=confirmed\n' "$STAMP" "$DATABASE" > "$WORK/MANIFEST.txt"
chmod -R go-rwx "$WORK"
mv -- "$WORK" "$FINAL"
WORK=
printf 'B07: respaldo cifrado listo en %s\n' "$FINAL"
printf 'B07: copie el directorio completo FUERA del servidor y verifique su integridad.\n'
