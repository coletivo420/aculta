#!/usr/bin/env bash
set -uo pipefail
umask 077

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
suite_out="${SUITE_OUT:?Defina SUITE_OUT fora do repositório.}"
browsers="${CULTURAS_BROWSER_MATRIX:-firefox,chrome,chromium}"
failed=0

IFS=',' read -r -a browser_list <<<"$browsers"
for browser in "${browser_list[@]}"; do
  case "$browser" in
    firefox | chrome | chromium) ;;
    *)
      echo "Browser não suportado na matriz: $browser" >&2
      exit 2
      ;;
  esac
  export CULTURAS_RUNTIME_BROWSER="$browser"
  export SUITE_OUT="$suite_out/$browser"
  mkdir -p "$SUITE_OUT"
  echo "=== Culturas / $browser headless ==="
  if bash "$script_dir/culturas-fast-gate.sh"; then
    echo "PASS geometria/teclado: $browser"
  else
    status=$?
    echo "FAIL: $browser (exit $status)" >&2
    failed=1
  fi
done

exit "$failed"
