#!/usr/bin/env bash
# Suite automatizada deterministica para ACULTA Runtime Sync v2.
# Utiliza repositorios Git temporarios (bare remote + checkout) e Drush falso instrumentado.
# NUNCA toca no Runtime real nem depende de rede externa.
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
REPO_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd -P)"
SYNC_SCRIPT="$REPO_ROOT/scripts/runtime-sync.sh"
HELPER_PY="$REPO_ROOT/scripts/runtime-sync/helper.py"

PASS_COUNT=0
FAIL_COUNT=0

assert_ok() {
  local cond="$1" msg="$2"
  if eval "$cond"; then
    printf 'PASS %s\n' "$msg"
    PASS_COUNT=$((PASS_COUNT + 1))
  else
    printf 'FAIL %s\n' "$msg" >&2
    FAIL_COUNT=$((FAIL_COUNT + 1))
  fi
}

WORK_BASE="$(mktemp -d "${TMPDIR:-/tmp}/aculta-rsync-test.XXXXXX")"
chmod 700 "$WORK_BASE"
trap 'rm -rf -- "$WORK_BASE" 2>/dev/null || true' EXIT

# Criar um fixture Git com bare remote + checkout em branch dev + fake drush
setup_fixture() {
  local name="$1"
  local dir="$WORK_BASE/$name"
  mkdir -p "$dir/remote.git" "$dir/Author" "$dir/runtime" "$dir/bin"
  git init --bare -q "$dir/remote.git"

  # Inicializar checkout author para popular origin/dev
  git init -q "$dir/Author"
  git -C "$dir/Author" config user.name "Test Agent"
  git -C "$dir/Author" config user.email "agent@example.invalid"
  git -C "$dir/Author" checkout -q -b dev

  mkdir -p "$dir/Author/scripts/runtime-sync" "$dir/Author/docs" "$dir/Author/web/modules/custom/aculta_portal/src" "$dir/Author/config/sync"
  cp "$SYNC_SCRIPT" "$dir/Author/scripts/runtime-sync.sh"
  cp "$HELPER_PY" "$dir/Author/scripts/runtime-sync/helper.py"
  cp "$REPO_ROOT/scripts/runtime-sync/risk-rules.yml" "$dir/Author/scripts/runtime-sync/risk-rules.yml"
  chmod +x "$dir/Author/scripts/runtime-sync.sh" "$dir/Author/scripts/runtime-sync/helper.py"
  echo "# Docs" >"$dir/Author/docs/README.md"
  echo "<?php // v1" >"$dir/Author/web/modules/custom/aculta_portal/src/Sample.php"
  cat >"$dir/Author/.gitignore" <<'EOF'
/var/
/vendor/
EOF
  git -C "$dir/Author" add .
  git -C "$dir/Author" commit -q -m "Commit inicial"
  git -C "$dir/Author" remote add origin "$dir/remote.git"
  git -C "$dir/Author" push -q -u origin dev

  # Clonar checkout runtime
  git clone -q -b dev "$dir/remote.git" "$dir/runtime"
  git -C "$dir/runtime" config user.name "Runtime Sync"
  git -C "$dir/runtime" config user.email "runtime@example.invalid"

  # Criar fake drush instrumentado
  cat >"$dir/bin/fake-drush" <<'DRUSH'
#!/usr/bin/env bash
set -euo pipefail
LOG_FILE="${FAKE_DRUSH_LOG:?}"
printf '%s\n' "$*" >>"$LOG_FILE"

cmd=""
for arg in "$@"; do
  case "$arg" in
    --*) ;;
    status|updatedb:status|config:status|deploy:hook-status|cr)
      cmd="$arg"
      break
      ;;
  esac
done

if [[ -n "${FAKE_DRUSH_SLEEP:-}" && "$cmd" == "cr" ]]; then
  sleep "$FAKE_DRUSH_SLEEP"
fi

case "$cmd" in
  status)
    printf '%s\n' "${FAKE_DRUSH_DB:-aculta_runtime}"
    ;;
  updatedb:status)
    if [[ "${FAKE_DRUSH_UPDB_FAIL:-0}" == "1" ]]; then
      exit 1
    fi
    if [[ "${FAKE_DRUSH_MALFORMED:-0}" == "1" ]]; then
      printf 'NOT VALID JSON {{{\n'
      exit 0
    fi
    printf '%s\n' "${FAKE_DRUSH_UPDB_JSON:-[]}"
    ;;
  config:status)
    if [[ "${FAKE_DRUSH_CFG_FAIL:-0}" == "1" ]]; then
      exit 1
    fi
    printf '%s\n' "${FAKE_DRUSH_CFG_JSON:-[]}"
    ;;
  deploy:hook-status)
    printf '%s\n' "${FAKE_DRUSH_DEPLOY_JSON:-[]}"
    ;;
  cr)
    echo $(( $(cat "${FAKE_DRUSH_CR_COUNT:?}" 2>/dev/null || echo 0) + 1 )) >"${FAKE_DRUSH_CR_COUNT:?}"
    if [[ "${FAKE_DRUSH_CR_FAIL:-0}" == "1" ]]; then
      echo "Erro simulado no drush cr" >&2
      exit 1
    fi
    echo "Cache rebuild complete." >&2
    ;;
  *)
    echo "Comando desconhecido: $cmd" >&2
    exit 1
    ;;
esac
DRUSH
  chmod +x "$dir/bin/fake-drush"
  : >"$dir/drush.log"
  echo 0 >"$dir/cr.count"
}

run_sync() {
  local fix_dir="$1"
  shift
  ACULTA_SYNC_ROOT="$fix_dir/runtime" \
  ACULTA_SYNC_STATE_DIR="$fix_dir/runtime/var/runtime-sync" \
  ACULTA_SYNC_ENV="${TEST_SYNC_ENV:-test}" \
  ACULTA_SYNC_EXPECTED_HOST="${TEST_SYNC_HOST:-$(hostname -s)}" \
  ACULTA_SYNC_EXPECTED_DB="${TEST_SYNC_DB:-aculta_runtime}" \
  ACULTA_SYNC_URI="${TEST_SYNC_URI:-https://aculta.toca.net.br}" \
  ACULTA_SYNC_DRUSH_BIN="$fix_dir/bin/fake-drush" \
  FAKE_DRUSH_LOG="$fix_dir/drush.log" \
  FAKE_DRUSH_CR_COUNT="$fix_dir/cr.count" \
  bash "$SYNC_SCRIPT" "$@"
}

# ==============================================================================
# Caso 1 & 2: Bug do v1 (HEAD == origin/dev com cache pendente) -> reconstrói cache;
#             chamada seguinte com mesmo SHA -> NOOP sem rodar drush cr novamente.
# ==============================================================================
setup_fixture "c1_same_sha"
fix="$WORK_BASE/c1_same_sha"
# Simular commit de codigo R1 já presente no checkout runtime e em origin/dev, sem state.json ainda
echo "<?php // v2" >"$fix/runtime/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix/runtime" add .
git -C "$fix/runtime" commit -q -m "Mudanca de codigo local ja enviada"
git -C "$fix/runtime" push -q origin dev
head_sha="$(git -C "$fix/runtime" rev-parse HEAD)"

# 1a. Plan deve apontar que cache/validacao está pendente mesmo com HEAD == origin/dev
plan_json="$(run_sync "$fix" plan --format=json)"
assert_ok '[[ "$(printf "%s" "$plan_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"outcome\"])")" != "NOOP" ]]' \
  "T01a: plan com HEAD == origin/dev e baseline UNKNOWN nao reporta falso NOOP"

# 1b. sync-code deve rodar drush cr mesmo com HEAD == origin/dev!
run_sync "$fix" sync-code >/dev/null 2>&1
cr_calls="$(cat "$fix/cr.count")"
st_json="$(run_sync "$fix" status --format=json)"
assert_ok '[[ "$cr_calls" -eq 1 ]]' \
  "T01b: sync-code com HEAD == origin/dev e cache pendente executa drush cr exatamente 1 vez"
assert_ok '[[ "$(printf "%s" "$st_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"last_validated_sha\"])")" == "$head_sha" ]]' \
  "T01c: last_validated_sha passa a registrar o SHA validado apos drush cr"

# 2. Executar sync-code novamente no mesmo SHA: deve retornar NOOP e NAO rodar drush cr
sync2_json="$(run_sync "$fix" sync-code --format=json)"
cr_calls_after="$(cat "$fix/cr.count")"
assert_ok '[[ "$cr_calls_after" -eq 1 && "$(printf "%s" "$sync2_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"outcome\"])")" == "NOOP" ]]' \
  "T02: segunda execucao com HEAD == origin/dev e cache ja validado retorna NOOP sem novo drush cr"

# ==============================================================================
# Caso 3 & 4: Fast-forward seguro de codigo (R1) vs mudanca apenas documental (R0)
# ==============================================================================
git -C "$fix/Author" pull -q origin dev
echo "<?php // v3" >"$fix/Author/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix/Author" add .
git -C "$fix/Author" commit -q -m "Novo codigo R1"
git -C "$fix/Author" push -q origin dev
r1_sha="$(git -C "$fix/Author" rev-parse HEAD)"

# 3a. plan --refresh deve conseguir classificar R1 sem alterar HEAD do runtime
plan_ref_json="$(run_sync "$fix" plan --refresh --format=json)"
assert_ok '[[ "$(printf "%s" "$plan_ref_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"risk\"])")" == "R1" ]]' \
  "T03a: plan --refresh classifica delta remoto R1 sem modificar checkout"
assert_ok '[[ "$(git -C "$fix/runtime" rev-parse HEAD)" == "$head_sha" ]]' \
  "T03b: plan --refresh preserva HEAD local inalterado"

# 3b. sync-code faz fast-forward e roda drush cr 1 vez
run_sync "$fix" sync-code >/dev/null 2>&1
assert_ok '[[ "$(git -C "$fix/runtime" rev-parse HEAD)" == "$r1_sha" && "$(cat "$fix/cr.count")" -eq 2 ]]' \
  "T03c: sync-code aplica fast-forward R1 e reconstroi cache"

# 4. Commit exclusivamente documental (R0) apos baseline validado -> atualiza SHA e dispensa drush cr
echo "Nova linha de doc" >>"$fix/Author/docs/README.md"
git -C "$fix/Author" add docs/README.md
git -C "$fix/Author" commit -q -m "Doc only R0"
git -C "$fix/Author" push -q origin dev
r0_sha="$(git -C "$fix/Author" rev-parse HEAD)"

r0_sync_json="$(run_sync "$fix" sync-code --format=json)"
assert_ok '[[ "$(git -C "$fix/runtime" rev-parse HEAD)" == "$r0_sha" && "$(cat "$fix/cr.count")" -eq 2 ]]' \
  "T04: delta exclusivamente documental (R0) com baseline valido atualiza checkout sem drush cr desnecessario"

# ==============================================================================
# Caso 5: Bloqueio de mudancas estruturais (composer.lock, config/sync, .install, hook, rename)
# ==============================================================================
setup_fixture "c5_structural"
fix5="$WORK_BASE/c5_structural"
# Adotar baseline inicial no fixture
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix5" sync-code --adopt-baseline >/dev/null 2>&1

# 5a. Alterar composer.lock (R2)
echo '{"packages":[]}' >"$fix5/Author/composer.lock"
git -C "$fix5/Author" add composer.lock
git -C "$fix5/Author" commit -q -m "Muda lockfile"
git -C "$fix5/Author" push -q origin dev

rc=0
run_sync "$fix5" sync-code >/dev/null 2>&1 || rc=$?
st5="$(run_sync "$fix5" status --format=json)"
assert_ok '[[ "$rc" -ne 0 && "$(printf "%s" "$st5" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"pending_structural_risk\"])")" == "R2" ]]' \
  "T05a: alteracao em composer.lock e classificada como R2 e bloqueia sync-code"

# 5b. Alterar config/sync e .install (R3) e arquivo com newline/hook
echo "foo: bar" >"$fix5/Author/config/sync/system.site.yml"
echo "<?php function aculta_portal_update_10001() {}" >"$fix5/Author/web/modules/custom/aculta_portal/aculta_portal.install"
git -C "$fix5/Author" add .
git -C "$fix5/Author" commit -q -m "Muda config e install"
git -C "$fix5/Author" push -q origin dev

rc=0
run_sync "$fix5" sync-code >/dev/null 2>&1 || rc=$?
st5b="$(run_sync "$fix5" status --format=json)"
assert_ok '[[ "$rc" -ne 0 && "$(printf "%s" "$st5b" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"pending_structural_risk\"])")" == "R3" ]]' \
  "T05b: alteracao em config/sync e .install e classificada como R3 e bloqueia sync-code"

# ==============================================================================
# Caso 6: Worktree suja, untracked, branch errada, heads divergentes -> BLOCK sem perda
# ==============================================================================
setup_fixture "c6_dirty"
fix6="$WORK_BASE/c6_dirty"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix6" sync-code --adopt-baseline >/dev/null 2>&1

echo "trabalho de outro agente" >"$fix6/runtime/untracked-agent.txt"
rc=0
run_sync "$fix6" sync-code >/dev/null 2>&1 || rc=$?
assert_ok '[[ "$rc" -ne 0 && -f "$fix6/runtime/untracked-agent.txt" ]]' \
  "T06a: arquivo untracked de outro agente bloqueia sync-code e e integralmente preservado"
rm -f "$fix6/runtime/untracked-agent.txt"

# Heads divergentes (non-fast-forward)
echo "<?php // divergente local" >>"$fix6/runtime/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix6/runtime" commit -am "Commit divergente local" -q
echo "<?php // divergente remoto" >>"$fix6/Author/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix6/Author" commit -am "Commit divergente remoto" -q
git -C "$fix6/Author" push -q origin dev

rc=0
run_sync "$fix6" sync-code >/dev/null 2>&1 || rc=$?
assert_ok '[[ "$rc" -ne 0 ]]' \
  "T06b: heads divergentes (nao fast-forward) bloqueiam sync-code"

# ==============================================================================
# Caso 7 & 8: Host, URI (incluindo producao), DB errados, Drush malformado e pendencias Drush
# ==============================================================================
setup_fixture "c7_env_drush"
fix7="$WORK_BASE/c7_env_drush"

rc=0
TEST_SYNC_HOST="host-invalido" run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset TEST_SYNC_HOST
assert_ok '[[ "$rc" -ne 0 ]]' "T07a: host incorreto bloqueia sync-code"

rc=0
TEST_SYNC_DB="banco_errado" run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset TEST_SYNC_DB
assert_ok '[[ "$rc" -ne 0 ]]' "T07b: banco ativo divergente do esperado bloqueia sync-code"

rc=0
TEST_SYNC_URI="https://aculta.org" run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset TEST_SYNC_URI
assert_ok '[[ "$rc" -ne 0 ]]' "T07c: URI de producao (aculta.org) e estritamente bloqueada (T14)"

rc=0
FAKE_DRUSH_MALFORMED=1 run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset FAKE_DRUSH_MALFORMED
assert_ok '[[ "$rc" -ne 0 ]]' "T07d: saida malformada do Drush bloqueia sync-code (fail-closed)"

# 8. Relatorio fiel de updatedb pendente e config drift no doctor
export FAKE_DRUSH_UPDB_JSON='{"mod_1001":{"module":"mod","update_id":1001}}'
export FAKE_DRUSH_CFG_JSON='{"diff_1":{"name":"diff_1","state":"Different"}}'
doc_out="$(run_sync "$fix7" doctor --format=json 2>/dev/null)"
unset FAKE_DRUSH_UPDB_JSON FAKE_DRUSH_CFG_JSON
doc_parsed="$(printf '%s' "$doc_out" | python3 -c 'import json,sys; d=json.load(sys.stdin); dc=d.get("drush_checks") or {}; print(dc.get("updatedb_pending_count"), dc.get("config_drift_count"))')"
assert_ok '[[ "$doc_parsed" == "1 1" ]]' \
  "T08: doctor captura, normaliza e relata updatedb:status e config:status como dados estruturados ($doc_parsed)"

# ==============================================================================
# Caso 9: Falha de drush cr APOS fast-forward -> estado PARTIAL, sem carimbar last_validated_sha
# ==============================================================================
setup_fixture "c9_partial"
fix9="$WORK_BASE/c9_partial"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix9" sync-code --adopt-baseline >/dev/null 2>&1
initial_sha9="$(git -C "$fix9/runtime" rev-parse HEAD)"

echo "<?php // v2 falha cr" >"$fix9/Author/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix9/Author" commit -am "Commit para falha parcial" -q
git -C "$fix9/Author" push -q origin dev
new_sha9="$(git -C "$fix9/Author" rev-parse HEAD)"

rc=0
FAKE_DRUSH_CR_FAIL=1 run_sync "$fix9" sync-code >/dev/null 2>&1 || rc=$?
unset FAKE_DRUSH_CR_FAIL
st9="$(run_sync "$fix9" status --format=json)"
assert_ok '[[ "$rc" -ne 0 && "$(git -C "$fix9/runtime" rev-parse HEAD)" == "$new_sha9" ]]' \
  "T09a: falha de drush cr apos fast-forward retorna erro sem rollback destrutivo de Git"
assert_ok '[[ "$(printf "%s" "$st9" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"overall_status\"], d[\"last_code_sync_sha\"], d[\"last_validated_sha\"])")" == "PARTIAL $new_sha9 $initial_sha9" ]]' \
  "T09b: falha de drush cr registra outcome PARTIAL, atualiza last_code_sync_sha mas preserva last_validated_sha anterior"

# Recuperacao: quando drush cr volta a funcionar, sync-code (mesmo com HEAD == origin/dev) corrige e valida!
run_sync "$fix9" sync-code >/dev/null 2>&1
st9_rec="$(run_sync "$fix9" status --format=json)"
assert_ok '[[ "$(printf "%s" "$st9_rec" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"overall_status\"], d[\"last_validated_sha\"])")" == "VALIDATED $new_sha9" ]]' \
  "T09c: reexecucao de sync-code apos falha parcial reconstroi cache pendente e promove estado para VALIDATED"

# ==============================================================================
# Caso 10: Concorrencia - duas instancias simultaneas de sync-code (flock)
# ==============================================================================
setup_fixture "c10_concurrency"
fix10="$WORK_BASE/c10_concurrency"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix10" sync-code --adopt-baseline >/dev/null 2>&1
echo "<?php // concorrencia" >"$fix10/Author/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix10/Author" commit -am "Commit concorrente" -q
git -C "$fix10/Author" push -q origin dev

# Iniciar primeira instancia com sleep no fake-drush
FAKE_DRUSH_SLEEP=1 run_sync "$fix10" sync-code >/dev/null 2>&1 &
pid1=$!
sleep 0.3
rc2=0
run_sync "$fix10" sync-code >/dev/null 2>&1 || rc2=$?
wait "$pid1" || true
assert_ok '[[ "$rc2" -ne 0 ]]' \
  "T10: segunda instancia simultanea de sync-code e bloqueada pelo lock cooperativo"

# ==============================================================================
# Caso 11 & 12: Estado persistente corrompido, symlink inesperado e redacao de segredos
# ==============================================================================
setup_fixture "c12_state"
fix12="$WORK_BASE/c12_state"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix12" sync-code --adopt-baseline >/dev/null 2>&1

# Corromper state.json
echo "{corrupted json" >"$fix12/runtime/var/runtime-sync/state.json"
rc=0
run_sync "$fix12" sync-code >/dev/null 2>&1 || rc=$?
assert_ok '[[ "$rc" -ne 0 ]]' \
  "T12a: state.json corrompido faz sync-code falhar fechado (BLOCKED)"

# Testar protecao contra symlink em state.json
rm -f "$fix12/runtime/var/runtime-sync/state.json"
ln -s /tmp/alvo-inexistente "$fix12/runtime/var/runtime-sync/state.json"
rc=0
run_sync "$fix12" status >/dev/null 2>&1 || rc=$?
assert_ok '[[ "$rc" -ne 0 ]]' \
  "T12b: symlink em state.json e detectado e recusado"

# ==============================================================================
# Caso 13: Runbook especial commerce_shipping no structural-plan (F5)
# ==============================================================================
setup_fixture "c13_commerce"
fix13="$WORK_BASE/c13_commerce"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix13" sync-code --adopt-baseline >/dev/null 2>&1

cat >"$fix13/Author/composer.lock" <<'EOF'
{"packages": [{"name": "drupal/commerce_shipping", "version": "2.x"}]}
EOF
cat >"$fix13/Author/web/modules/custom/aculta_portal/aculta_portal.post_update.php" <<'EOF'
<?php
function aculta_commerce_post_update_migrate_shipping_methods(): void {}
EOF
git -C "$fix13/Author" add .
git -C "$fix13/Author" commit -q -m "Cutover commerce_shipping"
git -C "$fix13/Author" push -q origin dev

splan13="$(run_sync "$fix13" structural-plan --refresh --format=json)"
assert_ok '[[ "$(printf "%s" "$splan13" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"special_runbooks\"][0][\"id\"], d[\"ordered_steps\"][2][\"phase\"])")" == "commerce_shipping_cutover special_cutover_commerce_shipping" ]]' \
  "T13: structural-plan detecta cutover de commerce_shipping e prescreve updatedb/uninstall ANTES de composer install"

printf '\nResumo da suite Runtime Sync v2: %d PASS, %d FAIL\n' "$PASS_COUNT" "$FAIL_COUNT"
[[ "$FAIL_COUNT" -eq 0 ]]
