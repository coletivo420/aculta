#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
command -v php >/dev/null || { echo "PHP CLI is required." >&2; exit 1; }
php -m | grep -qi '^pdo_mysql$' || { echo "PHP pdo_mysql is required." >&2; exit 1; }
[[ -f web/sites/default/settings.homelab.php ]] || {
  echo "Create the ignored Homelab settings file from settings.homelab.php.example and configure trusted hosts/private files." >&2
  exit 1
}
[[ -f vendor/autoload.php ]] || composer install
DB_DRIVER="$(php vendor/drush/drush/drush.php php:eval 'print(\Drupal::database()->driver());')"
DB_NAME="$(php vendor/drush/drush/drush.php php:eval 'print(\Drupal::database()->getConnectionOptions()["database"]);')"
[[ "$DB_DRIVER" == "mysql" && "$DB_NAME" == "aculta_runtime" ]] || {
  echo "Refusing bootstrap: Drupal is not connected to the MariaDB Runtime (aculta_runtime)." >&2
  exit 1
}
bash scripts/homelab/verify-aculta-homelab.sh
echo "Bootstrap checks complete. Apache is the canonical Homelab baseline; review config/update status before any mutation."
