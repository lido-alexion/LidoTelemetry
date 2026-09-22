#!/usr/bin/env bash
set -euo pipefail

archive="${1:?release archive is required}"
release_id="${LIDOTELEMETRY_RELEASE_ID:?LIDOTELEMETRY_RELEASE_ID is required}"
expected_commit="${LIDOTELEMETRY_EXPECTED_COMMIT:?LIDOTELEMETRY_EXPECTED_COMMIT is required}"

app_root="/var/www/lidotelemetry"
releases_dir="${app_root}/releases"
shared_dir="${app_root}/shared"
release_dir="${releases_dir}/${release_id}"
current_link="${app_root}/current"

test -f "${archive}"
test -f "${shared_dir}/.env"

mkdir -p "${releases_dir}" "${shared_dir}/storage"
test ! -e "${release_dir}"
mkdir "${release_dir}"
tar -xzf "${archive}" -C "${release_dir}"

ln -s "${shared_dir}/.env" "${release_dir}/.env"
rm -rf "${release_dir}/storage"
ln -s "${shared_dir}/storage" "${release_dir}/storage"
mkdir -p   "${shared_dir}/storage/app/private"   "${shared_dir}/storage/app/public"   "${shared_dir}/storage/framework/cache/data"   "${shared_dir}/storage/framework/sessions"   "${shared_dir}/storage/framework/testing"   "${shared_dir}/storage/framework/views"   "${shared_dir}/storage/logs"

chmod -R ug+rwX "${shared_dir}/storage" "${release_dir}/bootstrap/cache"

cd "${release_dir}"
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

printf '%s\n' "${expected_commit}" > "${release_dir}/DEPLOYED_COMMIT"
ln -sfn "${release_dir}" "${app_root}/.current-next"
mv -Tf "${app_root}/.current-next" "${current_link}"

sudo -n /usr/bin/systemctl reload php8.4-fpm

mapfile -t old_releases < <(find "${releases_dir}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | tail -n +6 | cut -d' ' -f2-)
for old_release in "${old_releases[@]}"; do
  rm -rf -- "${old_release}"
done

curl --fail --silent --show-error --max-time 15 \
  --resolve telemetry.stoxla.in:443:127.0.0.1 \
  https://telemetry.stoxla.in/up >/dev/null

echo "Activated LidoTelemetry release ${release_id}"
