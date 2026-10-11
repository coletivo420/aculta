#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

APACHE_CTL="$(command -v apache2ctl || command -v apachectl || true)"
if [[ -z "$APACHE_CTL" ]]; then
  for candidate in /usr/sbin/apache2ctl /usr/sbin/apachectl; do
    if [[ -x "$candidate" ]]; then
      APACHE_CTL="$candidate"
      break
    fi
  done
fi

[[ -n "$APACHE_CTL" ]] || {
  echo "Apache is required: it is the canonical Homelab web-server baseline." >&2
  exit 1
}

"$APACHE_CTL" configtest >/dev/null

APACHE_MODULES="$("$APACHE_CTL" -M 2>&1)"
for module in rewrite_module headers_module proxy_fcgi_module; do
  grep -q "\b${module}\b" <<<"$APACHE_MODULES" || {
    echo "Required Apache module is not loaded: ${module}" >&2
    exit 1
  }
done

[[ -f web/.htaccess ]] || {
  echo "Drupal Apache .htaccess is missing from web/." >&2
  exit 1
}

if command -v pgrep >/dev/null && pgrep -x nginx >/dev/null 2>&1; then
  echo "Nginx is running, but Apache is the definitive Homelab baseline." >&2
  exit 1
fi

DB_DRIVER="$(php vendor/drush/drush/drush.php php:eval 'print(\Drupal::database()->driver());')"
DB_NAME="$(php vendor/drush/drush/drush.php php:eval 'print(\Drupal::database()->getConnectionOptions()["database"]);')"
[[ "$DB_DRIVER" == "mysql" && "$DB_NAME" == "aculta_runtime" ]] || {
  echo "Drupal is not connected to the expected MariaDB Runtime (aculta_runtime)." >&2
  exit 1
}

php vendor/drush/drush/drush.php status
php vendor/drush/drush/drush.php updatedb:status
php vendor/drush/drush/drush.php config:status

echo "Homelab verification complete: Apache baseline + MariaDB Runtime."
