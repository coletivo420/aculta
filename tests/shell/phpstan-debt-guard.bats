#!/usr/bin/env bats
#
# Guardrails do baseline PHPStan nível 10.
# Cada cenário exige PASS ou FAIL por execução real do script, sem mock.

setup() {
  ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
  GUARD="$ROOT/scripts/phpstan-debt-guard.php"
  REPORT="$ROOT/scripts/phpstan-debt-report.php"
  FIX="$ROOT/tests/fixtures/phpstan-debt"
  TMP="$(mktemp -d)"
}

teardown() {
  rm -rf "$TMP"
}

check() {
  php "$GUARD" check \
    --baseline="$1" \
    --reference-baseline="${2:-$1}" \
    --reference-level="${3:-10}" \
    --analysis="${4:-$FIX/analysis-empty.json}" \
    --config="${5:-$FIX/config-level10.neon}"
}

@test "baseline intacto e código sem novas ocorrências: PASS" {
  run check "$FIX/baseline-a.neon"
  [ "$status" -eq 0 ]
  [[ "$output" == *"PASS"* ]]
}

@test "novo erro fora do baseline: FAIL" {
  run check "$FIX/baseline-a.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-unsuppressed.json"
  [ "$status" -eq 1 ]
  [[ "$output" == *"erro não suprimido"* ]]
}

@test "nova entrada de dívida no baseline: FAIL" {
  run check "$FIX/baseline-added.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-unsuppressed.json"
  [ "$status" -eq 1 ]
  [[ "$output" == *"nova dívida"* ]]
}

@test "crescimento da contagem existente: FAIL" {
  run check "$FIX/baseline-growth.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-empty.json"
  [ "$status" -eq 1 ]
  [[ "$output" == *"contagem aumentada"* ]]
}

@test "remoção legítima de dívida com baseline atualizado: PASS" {
  run check "$FIX/baseline-removed.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-empty.json"
  [ "$status" -eq 0 ]
  [[ "$output" == *"redução legítima"* ]]
}

@test "baseline obsoleto (ignore.unmatched): FAIL" {
  run check "$FIX/baseline-obsolete.neon" "$FIX/baseline-obsolete.neon" 10 "$FIX/analysis-unmatched.json"
  [ "$status" -eq 1 ]
  [[ "$output" == *"baseline inconsistente"* ]]
}

@test "configuração rebaixada para nível 5: FAIL" {
  run check "$FIX/baseline-a.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-empty.json" "$FIX/config-level5.neon"
  [ "$status" -eq 1 ]
  [[ "$output" == *"mínimo obrigatório"* ]]
}

@test "desativação de reportUnmatchedIgnoredErrors: FAIL" {
  run check "$FIX/baseline-a.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-empty.json" "$FIX/config-no-unmatched.neon"
  [ "$status" -eq 1 ]
  [[ "$output" == *"reportUnmatchedIgnoredErrors"* ]]
}

@test "exclusão de escopo não autorizada: FAIL" {
  run check "$FIX/baseline-a.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-empty.json" "$FIX/config-extra-exclude.neon"
  [ "$status" -eq 1 ]
  [[ "$output" == *"exclusão não autorizada"* ]]
}

@test "JSON interrompido ou inválido: falha de infraestrutura" {
  run check "$FIX/baseline-a.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-invalid.json"
  [ "$status" -eq 2 ]
  [[ "$output" == *"JSON do PHPStan inválido"* ]]
}

@test "baseline malformado (path absoluto e count zero): FAIL" {
  run check "$FIX/baseline-malformed.neon"
  [ "$status" -eq 1 ]
  [[ "$output" == *"path deve ser relativo"* ]]
  [[ "$output" == *"count deve ser inteiro positivo"* ]]
}

@test "identidade de erro disfarçada (mesmo path e identificador): FAIL" {
  run check "$FIX/baseline-identity.neon" "$FIX/baseline-a.neon" 10 "$FIX/analysis-empty.json"
  [ "$status" -eq 1 ]
  [[ "$output" == *"identidade de erro alterada"* ]]
}

@test "diferença de nível na transição não compara crescimento" {
  run check "$FIX/baseline-growth.neon" "$FIX/baseline-a.neon" 5 "$FIX/analysis-empty.json"
  [ "$status" -eq 0 ]
  [[ "$output" == *"transição"* ]]
}

@test "arquivo tocado com dívida congelada: FAIL" {
  run php "$GUARD" diff \
    --baseline="$FIX/baseline-a.neon" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --changed-files="$FIX/changed-a.txt"
  [ "$status" -eq 1 ]
  [[ "$output" == *"precisa reduzir"* ]]
}

@test "módulo tocado sem redução de dívida: FAIL" {
  run php "$GUARD" diff \
    --baseline="$FIX/baseline-a.neon" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --changed-files="$FIX/changed-mod-a.txt"
  [ "$status" -eq 1 ]
  [[ "$output" == *"precisa reduzir"* ]]
}

@test "módulo tocado com redução de dívida: PASS" {
  run php "$GUARD" diff \
    --baseline="$FIX/baseline-removed.neon" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --changed-files="$FIX/changed-mod-a.txt"
  [ "$status" -eq 0 ]
  [[ "$output" == *"módulos tocados"* ]]
}

@test "relatório separa congelado, não suprimido e evolução" {
  run php "$REPORT" \
    --baseline="$FIX/baseline-a.neon" \
    --analysis="$FIX/analysis-unsuppressed.json" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --out="$TMP/report.md" \
    --json-out="$TMP/report.json" \
    --sha="test-sha" \
    --phpstan-version="PHPStan test"
  [ "$status" -eq 0 ]
  grep -q "Dívida congelada no baseline" "$TMP/report.md"
  grep -q "Erros não suprimidos" "$TMP/report.md"
  grep -q '"uncovered": 1' "$TMP/report.json"
  grep -q '"frozen": 3' "$TMP/report.json"
}

@test "novo @phpstan-ignore adicionado no diff: FAIL" {
  run php "$GUARD" diff \
    --baseline="$FIX/baseline-removed.neon" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --changed-files="$FIX/changed-mod-a.txt" \
    --diff-text="$FIX/diff-with-new-ignore.txt"
  [ "$status" -eq 1 ]
  [[ "$output" == *"novo @phpstan-ignore proibido"* ]]
}

@test "diff sem novo @phpstan-ignore: PASS" {
  run php "$GUARD" diff \
    --baseline="$FIX/baseline-removed.neon" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --changed-files="$FIX/changed-mod-a.txt" \
    --diff-text="$FIX/diff-clean.txt"
  [ "$status" -eq 0 ]
}

@test "arquivo tocado com redução de dívida: PASS" {
  run php "$GUARD" diff \
    --baseline="$FIX/baseline-file-reduced.neon" \
    --reference-baseline="$FIX/baseline-a.neon" \
    --changed-files="$FIX/changed-a.txt" \
    --diff-text="$FIX/diff-clean.txt"
  [ "$status" -eq 0 ]
  [[ "$output" == *"reduziu a dívida"* ]]
}
