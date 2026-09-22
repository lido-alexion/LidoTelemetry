#!/usr/bin/env bash
set -euo pipefail

app_root="/var/www/lidotelemetry"
releases_dir="${app_root}/releases"
current_link="${app_root}/current"

current_target="$(readlink -f "${current_link}")"
mapfile -t releases < <(find "${releases_dir}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-)

rollback_target=""
for release in "${releases[@]}"; do
  if [[ "$(readlink -f "${release}")" != "${current_target}" ]]; then
    rollback_target="${release}"
    break
  fi
done

test -n "${rollback_target}"
ln -sfn "${rollback_target}" "${app_root}/.current-next"
mv -Tf "${app_root}/.current-next" "${current_link}"
sudo -n /usr/bin/systemctl reload php8.4-fpm

curl --fail --silent --show-error --max-time 15 \
  --resolve telemetry.stoxla.in:443:127.0.0.1 \
  https://telemetry.stoxla.in/up >/dev/null

echo "Rolled back LidoTelemetry to ${rollback_target}"
