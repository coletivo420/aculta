#!/usr/bin/env bats
# Classificador de risco do Runtime Sync (scripts/runtime-sync/helper.py::classify_single_path).
# Fixtures em memória; nenhum acesso ao Runtime, banco ou Git remoto.

setup() {
  REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
  HELPER_DIR="$REPO_ROOT/scripts/runtime-sync"
}

classify() {
  python3 -I -c "
import sys
sys.path.insert(0, '$HELPER_DIR')
import helper
level, _ = helper.classify_single_path(sys.argv[1])
print(level)
" "$1"
}

@test "workflow do GitHub Actions é R0 (CI, sem efeito no Runtime)" {
  run classify ".github/workflows/php-quality.yml"
  [ "$status" -eq 0 ]
  [ "$output" = "R0" ]
}

@test "workflow novo em .github/workflows/ também é R0" {
  run classify ".github/workflows/quality-static.yml"
  [ "$output" = "R0" ]
}

@test "outro caminho em .github/ continua RX (fail-closed)" {
  run classify ".github/CODEOWNERS"
  [ "$output" = "RX" ]
}

@test "settings de servidor continuam R4" {
  run classify "web/sites/default/settings.php"
  [ "$output" = "R4" ]
}

@test "hook .install continua R3" {
  run classify "web/modules/custom/aculta_portal/modules/aculta_lms_h5p/aculta_lms_h5p.install"
  [ "$output" = "R3" ]
}

@test "código de módulo próprio continua R1" {
  run classify "web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/Block/ProximosEventosBlock.php"
  [ "$output" = "R1" ]
}

@test "caminho com quebra de linha é RX" {
  run classify $'web/modules/custom/x.php\n'
  [ "$output" = "RX" ]
}
