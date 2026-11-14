#!/usr/bin/env bash
# Executa testes Functional (BrowserTestBase) no site de teste isolado do
# ACULTA. Não usa dados do Runtime: o site de teste é selecionado pelo
# User-Agent gerado pelo PHPUnit e vive em web/sites/simpletest.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
target="${1:?informe o caminho do teste, por exemplo web/modules/custom/aculta_portal/modules/aculta_statistics/tests/src/Functional}"

set -a
# shellcheck disable=SC1091
. "$root/secrets/simpletest-db.env"
set +a

export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-https://aculta.toca.net.br}"
export BROWSERTEST_OUTPUT_DIRECTORY="${BROWSERTEST_OUTPUT_DIRECTORY:-${SUITE_OUT:-$HOME/aculta-functional-out}/browser}"

if [[ "$SIMPLETEST_BASE_URL" != https://aculta.toca.net.br* ]]; then
  echo "Aviso: SIMPLETEST_BASE_URL fora do host de desenvolvimento documentado: $SIMPLETEST_BASE_URL" >&2
fi
if [[ "$SIMPLETEST_BASE_URL" == *"aculta.org"* ]]; then
  echo "Recusado: use o servidor de desenvolvimento, não produção." >&2
  exit 2
fi

mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
cd "$root"
exec vendor/bin/phpunit -c web/core/phpunit.xml.dist "$target"
