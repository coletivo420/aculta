#!/usr/bin/env bash
set -euo pipefail
umask 077

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd -- "$script_dir/../../.." && pwd)"
runtime_root="${ACULTA_ROOT:-/home/piradopirata/projetos/aculta}"
suite_out="${SUITE_OUT:?Defina SUITE_OUT fora do repositório.}"
snapshot="${CULTURAS_RUNTIME_SNAPSHOT:?Aponte ao snapshot íntegro pré-ULI do aculta_runtime.}"

if [[ ! -s "$snapshot" || ! -s "$snapshot.sha256" ]]; then
  echo "Snapshot SQL ou SHA-256 ausente: $snapshot" >&2
  exit 2
fi
(cd / && sha256sum --check "$snapshot.sha256")

export ACULTA_ROOT="$runtime_root"
export SUITE_OUT="$suite_out"
export ACULTA_FF_PROFILE_ROOT="${ACULTA_FF_PROFILE_ROOT:-$suite_out/profiles}"
export CULTURAS_RUNTIME_HEADLESS=1
export CULTURAS_RUNTIME_BROWSER="${CULTURAS_RUNTIME_BROWSER:-firefox}"
export CULTURAS_AXE="${CULTURAS_AXE:-0}"

exec python3 -I "$project_root/tests/browser/aculta-studio/culturas-runtime.py"
