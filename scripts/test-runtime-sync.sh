#!/usr/bin/env bash
# Referências às variáveis de caso são avaliadas em strings por assert_ok().
# shellcheck disable=SC2034
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
  local msg="$1" result="$2"
  if [[ "$result" == PASS ]]; then
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
assert_ok "T01a: plan com HEAD == origin/dev e baseline UNKNOWN nao reporta falso NOOP" "$([[ "$(printf "%s" "$plan_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"outcome\"])")" != "NOOP" ]] && printf PASS || printf FAIL)"

# 1b. sync-code deve rodar drush cr mesmo com HEAD == origin/dev!
run_sync "$fix" sync-code >/dev/null 2>&1
cr_calls="$(cat "$fix/cr.count")"
st_json="$(run_sync "$fix" status --format=json)"
assert_ok "T01b: sync-code com HEAD == origin/dev e cache pendente executa drush cr exatamente 1 vez" "$([[ "$cr_calls" -eq 1 ]] && printf PASS || printf FAIL)"
assert_ok "T01c: last_validated_sha passa a registrar o SHA validado apos drush cr" "$([[ "$(printf "%s" "$st_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"last_validated_sha\"])")" == "$head_sha" ]] && printf PASS || printf FAIL)"

# 2. Executar sync-code novamente no mesmo SHA: deve retornar NOOP e NAO rodar drush cr
sync2_json="$(run_sync "$fix" sync-code --format=json)"
cr_calls_after="$(cat "$fix/cr.count")"
assert_ok "T02: segunda execucao com HEAD == origin/dev e cache ja validado retorna NOOP sem novo drush cr" "$([[ "$cr_calls_after" -eq 1 && "$(printf "%s" "$sync2_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"outcome\"])")" == "NOOP" ]] && printf PASS || printf FAIL)"

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
assert_ok "T03a: plan --refresh classifica delta remoto R1 sem modificar checkout" "$([[ "$(printf "%s" "$plan_ref_json" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"risk\"])")" == "R1" ]] && printf PASS || printf FAIL)"
assert_ok "T03b: plan --refresh preserva HEAD local inalterado" "$([[ "$(git -C "$fix/runtime" rev-parse HEAD)" == "$head_sha" ]] && printf PASS || printf FAIL)"

# 3b. sync-code faz fast-forward e roda drush cr 1 vez
run_sync "$fix" sync-code >/dev/null 2>&1
assert_ok "T03c: sync-code aplica fast-forward R1 e reconstroi cache" "$([[ "$(git -C "$fix/runtime" rev-parse HEAD)" == "$r1_sha" && "$(cat "$fix/cr.count")" -eq 2 ]] && printf PASS || printf FAIL)"

# 4. Commit exclusivamente documental (R0) apos baseline validado -> atualiza SHA e dispensa drush cr
echo "Nova linha de doc" >>"$fix/Author/docs/README.md"
git -C "$fix/Author" add docs/README.md
git -C "$fix/Author" commit -q -m "Doc only R0"
git -C "$fix/Author" push -q origin dev
r0_sha="$(git -C "$fix/Author" rev-parse HEAD)"

r0_sync_json="$(run_sync "$fix" sync-code --format=json)"
assert_ok "T04: delta exclusivamente documental (R0) com baseline valido atualiza checkout sem drush cr desnecessario" "$([[ "$(git -C "$fix/runtime" rev-parse HEAD)" == "$r0_sha" && "$(cat "$fix/cr.count")" -eq 2 ]] && printf PASS || printf FAIL)"

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
assert_ok "T05a: alteracao em composer.lock e classificada como R2 e bloqueia sync-code" "$([[ "$rc" -ne 0 && "$(printf "%s" "$st5" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"pending_structural_risk\"])")" == "R2" ]] && printf PASS || printf FAIL)"

# 5b. Alterar config/sync e .install (R3) e arquivo com newline/hook
echo "foo: bar" >"$fix5/Author/config/sync/system.site.yml"
echo "<?php function aculta_portal_update_10001() {}" >"$fix5/Author/web/modules/custom/aculta_portal/aculta_portal.install"
git -C "$fix5/Author" add .
git -C "$fix5/Author" commit -q -m "Muda config e install"
git -C "$fix5/Author" push -q origin dev

rc=0
run_sync "$fix5" sync-code >/dev/null 2>&1 || rc=$?
st5b="$(run_sync "$fix5" status --format=json)"
assert_ok "T05b: alteracao em config/sync e .install e classificada como R3 e bloqueia sync-code" "$([[ "$rc" -ne 0 && "$(printf "%s" "$st5b" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"pending_structural_risk\"])")" == "R3" ]] && printf PASS || printf FAIL)"

# ==============================================================================
# Caso 6: Worktree suja, untracked, branch errada, heads divergentes -> BLOCK sem perda
# ==============================================================================
setup_fixture "c6_dirty"
fix6="$WORK_BASE/c6_dirty"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix6" sync-code --adopt-baseline >/dev/null 2>&1

echo "trabalho de outro agente" >"$fix6/runtime/untracked-agent.txt"
rc=0
run_sync "$fix6" sync-code >/dev/null 2>&1 || rc=$?
assert_ok "T06a: arquivo untracked de outro agente bloqueia sync-code e e integralmente preservado" "$([[ "$rc" -ne 0 && -f "$fix6/runtime/untracked-agent.txt" ]] && printf PASS || printf FAIL)"
rm -f "$fix6/runtime/untracked-agent.txt"

# Heads divergentes (non-fast-forward)
echo "<?php // divergente local" >>"$fix6/runtime/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix6/runtime" commit -am "Commit divergente local" -q
echo "<?php // divergente remoto" >>"$fix6/Author/web/modules/custom/aculta_portal/src/Sample.php"
git -C "$fix6/Author" commit -am "Commit divergente remoto" -q
git -C "$fix6/Author" push -q origin dev

rc=0
run_sync "$fix6" sync-code >/dev/null 2>&1 || rc=$?
assert_ok "T06b: heads divergentes (nao fast-forward) bloqueiam sync-code" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

# ==============================================================================
# Caso 7 & 8: Host, URI (incluindo producao), DB errados, Drush malformado e pendencias Drush
# ==============================================================================
setup_fixture "c7_env_drush"
fix7="$WORK_BASE/c7_env_drush"

rc=0
TEST_SYNC_HOST="host-invalido" run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset TEST_SYNC_HOST
assert_ok "T07a: host incorreto bloqueia sync-code" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

rc=0
TEST_SYNC_DB="banco_errado" run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset TEST_SYNC_DB
assert_ok "T07b: banco ativo divergente do esperado bloqueia sync-code" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

rc=0
TEST_SYNC_URI="https://aculta.org" run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset TEST_SYNC_URI
assert_ok "T07c: URI de producao (aculta.org) e estritamente bloqueada (T14)" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

rc=0
FAKE_DRUSH_MALFORMED=1 run_sync "$fix7" sync-code >/dev/null 2>&1 || rc=$?
unset FAKE_DRUSH_MALFORMED
assert_ok "T07d: saida malformada do Drush bloqueia sync-code (fail-closed)" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

# 8. Relatorio fiel de updatedb pendente e config drift no doctor
export FAKE_DRUSH_UPDB_JSON='{"mod_1001":{"module":"mod","update_id":1001}}'
export FAKE_DRUSH_CFG_JSON='{"diff_1":{"name":"diff_1","state":"Different"}}'
doc_out="$(run_sync "$fix7" doctor --format=json 2>/dev/null)"
unset FAKE_DRUSH_UPDB_JSON FAKE_DRUSH_CFG_JSON
doc_parsed="$(printf '%s' "$doc_out" | python3 -c 'import json,sys; d=json.load(sys.stdin); dc=d.get("drush_checks") or {}; print(dc.get("updatedb_pending_count"), dc.get("config_drift_count"))')"
assert_ok "T08: doctor captura, normaliza e relata updatedb:status e config:status como dados estruturados ($doc_parsed)" "$([[ "$doc_parsed" == "1 1" ]] && printf PASS || printf FAIL)"

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
assert_ok "T09a: falha de drush cr apos fast-forward retorna erro sem rollback destrutivo de Git" "$([[ "$rc" -ne 0 && "$(git -C "$fix9/runtime" rev-parse HEAD)" == "$new_sha9" ]] && printf PASS || printf FAIL)"
assert_ok "T09b: falha de drush cr registra outcome PARTIAL, atualiza last_code_sync_sha mas preserva last_validated_sha anterior" "$([[ "$(printf "%s" "$st9" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"overall_status\"], d[\"last_code_sync_sha\"], d[\"last_validated_sha\"])")" == "PARTIAL $new_sha9 $initial_sha9" ]] && printf PASS || printf FAIL)"

# Recuperacao: quando drush cr volta a funcionar, sync-code (mesmo com HEAD == origin/dev) corrige e valida!
run_sync "$fix9" sync-code >/dev/null 2>&1
st9_rec="$(run_sync "$fix9" status --format=json)"
assert_ok "T09c: reexecucao de sync-code apos falha parcial reconstroi cache pendente e promove estado para VALIDATED" "$([[ "$(printf "%s" "$st9_rec" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"overall_status\"], d[\"last_validated_sha\"])")" == "VALIDATED $new_sha9" ]] && printf PASS || printf FAIL)"

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
assert_ok "T10: segunda instancia simultanea de sync-code e bloqueada pelo lock cooperativo" "$([[ "$rc2" -ne 0 ]] && printf PASS || printf FAIL)"

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
assert_ok "T12a: state.json corrompido faz sync-code falhar fechado (BLOCKED)" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

# Testar protecao contra symlink em state.json
rm -f "$fix12/runtime/var/runtime-sync/state.json"
ln -s /tmp/alvo-inexistente "$fix12/runtime/var/runtime-sync/state.json"
rc=0
run_sync "$fix12" status >/dev/null 2>&1 || rc=$?
assert_ok "T12b: symlink em state.json e detectado e recusado" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

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
assert_ok "T13: structural-plan detecta cutover de commerce_shipping e prescreve updatedb/uninstall ANTES de composer install" "$([[ "$(printf "%s" "$splan13" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"special_runbooks\"][0][\"id\"], d[\"ordered_steps\"][2][\"phase\"])")" == "commerce_shipping_cutover special_cutover_commerce_shipping" ]] && printf PASS || printf FAIL)"

# ==============================================================================
# Caso 14: structural-complete fecha a janela apenas com drift/updb/deploy zerados
# ==============================================================================
setup_fixture "c14_structural_complete"
fix14="$WORK_BASE/c14_structural_complete"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix14" sync-code --adopt-baseline >/dev/null 2>&1
base14="$(git -C "$fix14/runtime" rev-parse HEAD)"

echo "foo: bar" >"$fix14/Author/config/sync/system.site.yml"
git -C "$fix14/Author" add config/sync/system.site.yml
git -C "$fix14/Author" commit -q -m "Config R3 da janela"
git -C "$fix14/Author" push -q origin dev
target14="$(git -C "$fix14/Author" rev-parse HEAD)"

# O operador atualiza o checkout servido durante a janela (o sync-code bloqueia R3).
git -C "$fix14/runtime" pull -q --ff-only origin dev
run_sync "$fix14" structural-plan --format=json >"$fix14/plan.json"

rc=0
run_sync "$fix14" structural-complete --plan="$fix14/plan.json" >/dev/null 2>&1 || rc=$?
assert_ok "T14a: structural-complete exige ACULTA_SYNC_STRUCTURAL_CONFIRM=1" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"

rc=0
ACULTA_SYNC_STRUCTURAL_CONFIRM=1 FAKE_DRUSH_CFG_JSON='{"system.site":{"state":"Different"}}' \
  run_sync "$fix14" structural-complete --plan="$fix14/plan.json" >/dev/null 2>&1 || rc=$?
st14_drift="$(run_sync "$fix14" status --format=json)"
assert_ok "T14b: drift de config impede fechar a janela e preserva pending_structural_risk=R3" "$([[ "$rc" -ne 0 && "$(printf "%s" "$st14_drift" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"pending_structural_risk\"])")" == "R3" ]] && printf PASS || printf FAIL)"
unset FAKE_DRUSH_CFG_JSON

rc=0
ACULTA_SYNC_STRUCTURAL_CONFIRM=1 FAKE_DRUSH_UPDB_JSON='[{"module":"aculta_portal","update_id":"10001"}]' \
  run_sync "$fix14" structural-complete --plan="$fix14/plan.json" >/dev/null 2>&1 || rc=$?
assert_ok "T14c: updatedb pendente impede fechar a janela" "$([[ "$rc" -ne 0 ]] && printf PASS || printf FAIL)"
unset FAKE_DRUSH_UPDB_JSON

cr_before14="$(cat "$fix14/cr.count")"
ACULTA_SYNC_STRUCTURAL_CONFIRM=1 run_sync "$fix14" structural-complete --plan="$fix14/plan.json" >/dev/null 2>&1
st14="$(run_sync "$fix14" status --format=json)"
assert_ok "T14d: janela fechada avanca baseline, cache e limpa a pendencia estrutural" "$([[ "$(printf "%s" "$st14" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[\"last_validated_sha\"], d[\"last_cache_success_sha\"], d[\"pending_structural_sha\"], d[\"pending_structural_risk\"])")" == "$target14 $target14 None None" ]] && printf PASS || printf FAIL)"
assert_ok "T14e: structural-complete reconstroi o cache uma vez" "$([[ "$(cat "$fix14/cr.count")" -eq $((cr_before14 + 1)) ]] && printf PASS || printf FAIL)"

plan14_after="$(run_sync "$fix14" plan --format=json)"
assert_ok "T14f: apos fechar a janela, o delta cumulativo deixa de ser R3" "$([[ "$(printf "%s" "$plan14_after" | python3 -c "import json,sys; print(json.load(sys.stdin)[\"risk\"])")" != "R3" ]] && printf PASS || printf FAIL)"

# ==============================================================================
# Caso 15: deltas grandes não são passados como argumento de processo (E2BIG)
# ==============================================================================
setup_fixture "c15_large_delta"
fix15="$WORK_BASE/c15_large_delta"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix15" sync-code --adopt-baseline >/dev/null 2>&1
base15="$(git -C "$fix15/runtime" rev-parse HEAD)"
mkdir -p "$fix15/runtime/docs/bulk"
for n in $(seq 1 1400); do
  printf 'fixture %s\n' "$n" >"$fix15/runtime/docs/bulk/item-$n.md"
done
git -C "$fix15/runtime" add docs/bulk
git -C "$fix15/runtime" commit -q -m "Fixture de delta grande"
git -C "$fix15/runtime" push -q origin dev
target15="$(git -C "$fix15/runtime" rev-parse HEAD)"
delta_bytes15="$(python3 "$HELPER_PY" classify --repo-root "$fix15/runtime" --base-sha "$base15" --target-sha "$target15" --state-status OK | wc -c)"
assert_ok "T15a: fixture cobre JSON de classificação maior que ARG_MAX de um argumento (>131072; atual=$delta_bytes15 bytes)" "$([[ "$delta_bytes15" -gt 131072 ]] && printf PASS || printf FAIL)"
doctor15="$(run_sync "$fix15" doctor --format=json 2>/dev/null)"
doctor15_parsed="$(printf '%s' "$doctor15" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["mode"],d["checkout_sha"],d["remote_sha"],d["risk"])')"
assert_ok "T15b: doctor analisa delta grande sem E2BIG ($doctor15_parsed)" "$([[ "$doctor15_parsed" == "doctor $target15 $target15 R0" ]] && printf PASS || printf FAIL)"
plan15="$(run_sync "$fix15" plan --format=json 2>/dev/null)"
plan15_parsed="$(printf '%s' "$plan15" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["mode"],d["checkout_sha"],d["remote_sha"],d["risk"],d["delta"]["file_count"])')"
assert_ok "T15c: plan JSON conserva o delta grande sem E2BIG ($plan15_parsed)" "$([[ "$plan15_parsed" == "plan $target15 $target15 R0 1400" ]] && printf PASS || printf FAIL)"

# ==============================================================================
# Caso 16: allowlist R0 explícita para metadados de ferramentas locais
# ==============================================================================
setup_fixture "c16_tooling_metadata"
fix16="$WORK_BASE/c16_tooling_metadata"
ACULTA_SYNC_ADOPT_BASELINE=1 run_sync "$fix16" sync-code --adopt-baseline >/dev/null 2>&1
printf '\n/reports/css-audit/\n' >>"$fix16/runtime/.gitignore"
cat >"$fix16/runtime/.stylelintrc.json" <<'EOF'
{"extends": ["stylelint-config-drupal"]}
EOF
git -C "$fix16/runtime" add .gitignore .stylelintrc.json
git -C "$fix16/runtime" commit -q -m "Configura ferramentas CSS locais"
git -C "$fix16/runtime" push -q origin dev
plan16="$(run_sync "$fix16" plan --format=json 2>/dev/null)"
plan16_parsed="$(printf '%s' "$plan16" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["risk"],d["delta"]["counts_by_risk"]["R0"],d["delta"]["file_count"])')"
assert_ok "T16: .gitignore e .stylelintrc.json são R0 por regra explícita ($plan16_parsed)" "$([[ "$plan16_parsed" == "R0 2 2" ]] && printf PASS || printf FAIL)"

printf '\nResumo da suite Runtime Sync v2: %d PASS, %d FAIL\n' "$PASS_COUNT" "$FAIL_COUNT"
[[ "$FAIL_COUNT" -eq 0 ]]
