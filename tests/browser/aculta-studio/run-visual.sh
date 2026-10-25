#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
export STUDIO_USER="${STUDIO_USER:-teste.comum}"
export STUDIO_USER_ID="${STUDIO_USER_ID:-5}"
export STUDIO_LOGIN_URI="${STUDIO_LOGIN_URI:-https://conta.aculta.toca.net.br}"
export STUDIO_PAGE="${STUDIO_PAGE:-https://conta.aculta.toca.net.br/studio/marley}"
export SUITE_OUT="${SUITE_OUT:-/tmp/aculta-studio-visual}"

if [[ "${DISPLAY:-}" != ":0" ]]; then
  echo "DISPLAY=:0 é obrigatório para o Firefox ESR visível." >&2
  exit 2
fi
case "$STUDIO_PAGE $STUDIO_LOGIN_URI" in
  *aculta.org*)
    echo "A suíte visual só pode acessar o servidor de testes ou um Runtime local autorizado." >&2
    exit 2
    ;;
esac

cd "$project_root"
studio_runtime="$(php vendor/drush/drush/drush.php php:eval '$e = \Drupal::service("extension.list.module")->getExtensionInfo("aculta_studio"); $r = \Drupal::service("router.route_provider")->getRouteByName("aculta_studio.home"); echo ($e["version"] ?? "unknown") . "|" . ($r ? $r->getPath() : "absent");' "--uri=$STUDIO_LOGIN_URI")"
studio_version="${studio_runtime%%|*}"
studio_home="${studio_runtime#*|}"
if [[ "$studio_home" != "/studio/marley" ]]; then
  echo "Runtime incompatível: aculta_studio=$studio_version; rota aculta_studio.home=$studio_home. Esperado: /studio/marley. Atualize o Runtime de teste para a branch antes da revisão visual." >&2
  exit 3
fi
export STUDIO_COLOR_MODE_STATE="$(php vendor/drush/drush/drush.php php:eval 'echo json_encode(\Drupal::service("aculta_portal.color_mode_preference")->forUser((int) getenv("STUDIO_USER_ID")));' "--uri=$STUDIO_LOGIN_URI")"
restore_color_mode() {
  local exit_code=$?
  php vendor/drush/drush/drush.php php:eval '$uid = (int) getenv("STUDIO_USER_ID"); $mode = json_decode(getenv("STUDIO_COLOR_MODE_STATE"), TRUE); if ($mode === NULL) { \Drupal::service("user.data")->delete("aculta_portal", $uid, "color_mode"); } else { \Drupal::service("aculta_portal.color_mode_preference")->save($uid, $mode); }' "--uri=$STUDIO_LOGIN_URI" >/dev/null || exit_code=1
  exit "$exit_code"
}
trap restore_color_mode EXIT
node tests/browser/aculta-studio/visual.mjs
