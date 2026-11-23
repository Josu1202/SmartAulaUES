#!/usr/bin/env bash
set -Eeuo pipefail

[[ $# -eq 3 && ( $3 == private || $3 == public ) ]] || {
    echo 'Uso: bash operations/check-production.sh ENV_FILE https://dominio private|public' >&2
    exit 2
}
[[ $2 =~ ^https://[a-zA-Z0-9.-]+$ ]] || exit 2
root=$(cd "$(dirname "$0")/.." && pwd -P)
compose=(docker compose --env-file "$1" -f "$root/compose.production.yaml")
for service in db app worker scheduler; do
    id=$("${compose[@]}" ps -q "$service")
    [[ -n $id && $(docker inspect --format '{{.State.Health.Status}}' "$id") == healthy ]] || {
        echo "Servicio sin salud: $service" >&2
        exit 1
    }
    echo "Servicio saludable: $service"
done

app=$("${compose[@]}" ps -q app)
db=$("${compose[@]}" ps -q db)
[[ $(docker inspect --format '{{len .HostConfig.PortBindings}}' "$db") == 0 ]]
[[ $(docker inspect --format '{{range .HostConfig.PortBindings}}{{range .}}{{println .HostIp}}{{end}}{{end}}' "$app") == 127.0.0.1 ]]
[[ $(docker inspect --format '{{range .Mounts}}{{if and (eq .Type "volume") (eq .Destination "/var/lib/postgresql/data")}}{{.Name}}{{end}}{{end}}' "$db") ]]
private_mount='{{range .Mounts}}{{if and (eq .Type "volume") (eq .Destination "/var/www/html/storage/app")}}{{.Name}}{{end}}{{end}}'
volume=$(docker inspect --format "$private_mount" "$app")
[[ -n $volume ]]
for service in worker scheduler; do
    id=$("${compose[@]}" ps -q "$service")
    [[ $(docker inspect --format "$private_mount" "$id") == "$volume" ]]
    [[ $(docker inspect --format '{{.Image}}' "$id") == "$(docker inspect --format '{{.Image}}' "$app")" ]]
done
status=$("${compose[@]}" exec -T app php artisan migrate:status --no-ansi)
printf '%s\n' "$status"
! grep -q 'Pending' <<< "$status" || exit 1
"${compose[@]}" exec -T app php artisan b07:status --require-scheduler
if [[ $3 == public ]]; then
    curl --fail --silent --show-error --max-time 20 "$2/health/ready"
    login_headers=$(curl --fail --silent --show-error --max-time 20 -D - "$2/login" -o /dev/null)
    grep -Eiq '^HTTP/[^ ]+ 200' <<< "$login_headers"
    grep -Ei '^set-cookie: [^;]*session=' <<< "$login_headers" |
        grep -Ei ';[[:space:]]*secure([;[:space:]]|$)' |
        grep -Ei ';[[:space:]]*httponly([;[:space:]]|$)' >/dev/null
    redirect=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{redirect_url}' "http://${2#https://}/login")
    [[ $redirect == "$2/login" ]]
    terminal_status=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' "$2/api/v1/terminal/state")
    [[ $terminal_status == 401 ]]
fi
printf '\nComprobación %s aprobada.\n' "$3"
