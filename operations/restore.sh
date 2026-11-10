#!/usr/bin/env bash
# B07: restore into a NEW, ISOLATED deployment. Never overwrite production.
set -Eeuo pipefail
umask 077
usage() {
  echo 'Uso: operations/restore.sh NEW_ENV_FILE BACKUP_DIR AGE_IDENTITY CONFIRM_EMPTY_DATABASE' >&2
  exit 2
}
[[ $# -eq 4 ]] || usage
ENV_FILE=$1
BACKUP_DIR=$2
IDENTITY=$3
CONFIRM=$4
[[ -r "$ENV_FILE" && -r "$IDENTITY" && -d "$BACKUP_DIR" ]] || usage
[[ "$CONFIRM" =~ ^b07_restore_[a-zA-Z0-9_]+$ ]] || {
  echo 'B07: restauración permitida solo en bases nuevas b07_restore_*.' >&2; exit 1;
}
for command in docker age sha256sum grep; do
  command -v "$command" >/dev/null || { echo "B07: falta $command." >&2; exit 1; }
done
ROOT=$(cd "$(dirname "$0")/.." && pwd -P)
# A different project and database are compulsory; do not ever target a live stack.
PROJECT=$(sed -n 's/^COMPOSE_PROJECT_NAME=//p' "$ENV_FILE" | tail -n 1 | tr -d '\r')
COMPOSE=(docker compose -p "$PROJECT" --env-file "$ENV_FILE" -f "$ROOT/compose.production.yaml")
[[ "$PROJECT" == b07-restore-* ]] || {
  echo 'B07: COMPOSE_PROJECT_NAME debe comenzar por b07-restore-.' >&2; exit 1;
}
"${COMPOSE[@]}" up -d db
DATABASE=$("${COMPOSE[@]}" exec -T db sh -c 'printf %s "$POSTGRES_DB"')
[[ "$DATABASE" == "$CONFIRM" ]] || { echo 'B07: base de restauración no coincide.' >&2; exit 1; }
TABLES=$("${COMPOSE[@]}" exec -T db sh -c 'exec psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "SELECT count(*) FROM information_schema.tables WHERE table_schema = '\''public'\''"')
[[ "$TABLES" == 0 ]] || { echo 'B07: la base no está vacía; no se sobrescribirá.' >&2; exit 1; }
(cd "$BACKUP_DIR" && sha256sum -c SHA256SUMS) || {
  echo 'B07: integridad de los archivos de respaldo no válida.' >&2; exit 1;
}
for f in database.dump.age private-files.tar.gz.age production-secrets.env.age; do
  [[ -r "$BACKUP_DIR/$f" ]] || { echo "B07: falta $f." >&2; exit 1; }
done
# Check the secret-file cryptogram without copying its content onto disk.
age -d -i "$IDENTITY" "$BACKUP_DIR/production-secrets.env.age" > /dev/null
# The destination environment must already have the matching APP_KEY from the
# separately authorized secret recovery process. No secrets are printed.
age -d -i "$IDENTITY" "$BACKUP_DIR/database.dump.age" \
  | "${COMPOSE[@]}" exec -T db sh -c 'exec pg_restore --no-owner --no-acl -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
age -d -i "$IDENTITY" "$BACKUP_DIR/private-files.tar.gz.age" \
  | "${COMPOSE[@]}" run -T --rm --no-deps --entrypoint tar app -C /var/www/html/storage -xzf -
printf 'B07: datos y archivos restaurados en proyecto aislado %s.\n' "$PROJECT"
printf 'B07: NO se arrancó automáticamente la web; verifique APP_KEY y pruebe login, historial, permisos y PDF.\n'
