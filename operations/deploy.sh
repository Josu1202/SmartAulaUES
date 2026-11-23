#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

usage() {
    echo 'Uso: bash operations/deploy.sh initial|update ENV_FILE SHA DATABASE https://dominio [BACKUP_DIR AGE_RECIPIENT SSH_HOST REMOTE_DIR]' >&2
    exit 2
}
[[ $# == 5 && $1 == initial || $# == 9 && $1 == update ]] || usage
mode=$1
env_file=$(realpath "$2")
sha=$3
database=$4
url=$5
root=$(cd "$(dirname "$0")/.." && pwd -P)
state=/var/lib/smartaulaues
marker=$state/maintenance
[[ $sha =~ ^[a-f0-9]{40}$ && $database =~ ^[a-zA-Z][a-zA-Z0-9_]*$ && $url =~ ^https://[a-zA-Z0-9.-]+$ ]] || usage
[[ $(git -C "$root" rev-parse HEAD) == "$sha" && -z $(git -C "$root" status --porcelain) ]]
[[ -f $env_file && ! -L $2 && $(stat -c '%a' "$env_file") == 600 && $env_file != "$root/"* ]]
[[ $(grep -c '^PRODUCTION_APP_URL=' "$env_file") == 1 && $(grep '^PRODUCTION_APP_URL=' "$env_file") == "PRODUCTION_APP_URL=$url" ]]
[[ $(grep -c '^PRODUCTION_DB_DATABASE=' "$env_file") == 1 && $(grep '^PRODUCTION_DB_DATABASE=' "$env_file") == "PRODUCTION_DB_DATABASE=$database" ]]
[[ $(grep -c '^COMPOSE_PROJECT_NAME=' "$env_file") == 1 && $(grep '^COMPOSE_PROJECT_NAME=' "$env_file") == 'COMPOSE_PROJECT_NAME=smartaulaues-production' ]]
grep -Eq '^APP_KEY=base64:[A-Za-z0-9+/]{43}=$' "$env_file"
grep -Eq '^DB_PASSWORD=[a-fA-F0-9]{32,}$' "$env_file"
grep -Eq '^SMARTAULAUES_TRUSTED_PROXIES=[0-9a-fA-F.:]+(/[0-9]{1,3})?$' "$env_file"
for key in PRODUCTION_APP_URL PRODUCTION_DB_DATABASE SMARTAULAUES_TRUSTED_PROXIES APP_KEY DB_PASSWORD DB_USERNAME COMPOSE_PROJECT_NAME PRODUCTION_HTTP_PORT; do
    [[ ! -v $key ]] || { echo "Variable del shell interfiere con el entorno privado: $key" >&2; exit 1; }
done
exec 9>"$state/deploy.lock"
flock -n 9 || { echo 'Hay otro despliegue en curso.' >&2; exit 1; }
export SMARTAULAUES_IMAGE_TAG=$sha
compose=(docker compose --env-file "$env_file" -f "$root/compose.production.yaml")
"${compose[@]}" config --quiet
if [[ $mode == update ]]; then
    [[ $8 != -* && $8 =~ ^[a-zA-Z0-9_.@-]+$ && $9 =~ ^/[a-zA-Z0-9_./-]+$ ]] || usage
    for tool in age rsync ssh; do command -v "$tool" >/dev/null; done
    ssh -o BatchMode=yes "$8" "test -d '$9' && test -w '$9' && command -v sha256sum >/dev/null"
    [[ -n $("${compose[@]}" ps -q app) && -n $("${compose[@]}" ps -q db) ]]
fi

# Build before maintenance; only one service needs to build the shared image.
"${compose[@]}" build app
touch "$marker"
chmod 644 "$marker"
trap 'echo "Despliegue interrumpido: Caddy conserva la barrera de mantenimiento." >&2' ERR
headers=$(curl --silent --show-error --max-time 20 -D - -o /dev/null "$url/login")
grep -Eiq '^HTTP/[^ ]+ 503' <<< "$headers"
grep -Eiq '^X-SmartAulaUES-Maintenance: 1[[:space:]]*$' <<< "$headers"

"${compose[@]}" up -d --wait --wait-timeout 120 db
sql() {
    "${compose[@]}" exec -T db sh -c 'exec psql -X -v ON_ERROR_STOP=1 -At -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "$1"' sh "$1"
}
[[ $(sql 'SELECT current_database()') == "$database" ]]
if [[ $mode == initial ]]; then
    [[ -z $("${compose[@]}" ps -q app worker scheduler) ]]
    [[ $(sql "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'") == 0 ]]
else
    "${compose[@]}" exec -T app php artisan down
    "${compose[@]}" stop worker scheduler
    echo 'Comprueba que las solicitudes previas a mantenimiento ya terminaron y que no hay otros escritores.' >&2
    read -r -p "Escribe el nombre de la base para confirmar el drenaje: " drained
    [[ $drained == "$database" ]]
    backup=$(bash "$root/operations/backup.sh" "$env_file" "$6" "$7" "$database" --maintenance-confirmed)
    printf '%s\n' "$backup"
    archive=$(sed -n 's/^B07: respaldo cifrado listo en //p' <<< "$backup")
    [[ -d $archive && $(basename "$archive") =~ ^smartaulaues-[0-9]{8}T[0-9]{6}Z$ ]]
    (cd "$archive" && sha256sum -c SHA256SUMS)
    rsync -a -e 'ssh -o BatchMode=yes' -- "$archive" "$8:$9/"
    ssh -o BatchMode=yes "$8" "cd '$9/$(basename "$archive")' && sha256sum -c SHA256SUMS"
fi

"${compose[@]}" run --rm --no-deps app php artisan migrate --force --no-interaction
if [[ $mode == initial ]]; then
    [[ $(sql 'SELECT count(*) FROM users') == 0 ]]
    "${compose[@]}" run --rm --no-deps app php artisan app:create-coordinator --name='Coordinación SmartAulaUES'
    [[ $(sql "SELECT count(*) = 1 AND count(*) FILTER (WHERE role = 'coordinator' AND active) = 1 FROM users") == t ]]
    echo 'Base inicial verificada: una cuenta activa de Coordinación.'
fi
"${compose[@]}" up -d --no-build --force-recreate --wait --wait-timeout 300 app worker scheduler
bash "$root/operations/check-production.sh" "$env_file" "$url" private
echo "SHA verificado en el servidor: $sha. La barrera pública sigue activa."
