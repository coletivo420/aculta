#!/usr/bin/env bash
# ACULTA - Runtime Sync v2 (sincronizacao conservadora do checkout de desenvolvimento).
# Nao executa Composer, updates, config import/export, uninstall nem operacoes destrutivas.
set -Eeuo pipefail

usage() {
  cat <<'HELP'
Uso: bash scripts/runtime-sync.sh <comando> [opcoes]

Comandos disponiveis:
  plan [--refresh] [--format=json]
      Somente leitura no checkout. Identifica SHA local, SHA remoto (origin/dev),
      ultimo SHA validado (incluindo cache) e resume o risco cumulativo (R0..R4, RX).
      Com --refresh, baixa objetos Git para um repositorio temporario isolado fora do
      checkout servido para permitir diff completo sem alterar refs locais.

  doctor [--format=json]
      Somente leitura. Confere ambiente, branch/ref, remoto, locks cooperativos,
      estado persistente, binarios, vendor, Drush, banco e pendencias estruturais.

  status [--format=json]
      Somente leitura. Le o estado persistido em var/runtime-sync/state.json e o
      ultimo relatorio sem executar Drush nem acessar a rede.

  sync-code [--adopt-baseline] [--format=json]
      Atualiza codigo (git fetch + merge --ff-only quando necessario) e reconstroi
      caches Drupal (drush cr) apenas se o delta cumulativo desde o ultimo SHA
      validado for R0/R1. Corrige o caso em que HEAD == origin/dev mas o cache
      ainda nao foi reconstruido/validado para esse SHA.
      --adopt-baseline permite adotar o HEAD atual como baseline inicial quando
      last_validated_sha for UNKNOWN e ACULTA_SYNC_ADOPT_BASELINE=1 estiver definido.

  verify [--format=json]
      Confere SHA aplicado, estado de cache, bootstrap Drush, pendencias de
      updatedb/config/deploy-hooks e registra relatorio auditavel.

  structural-plan [--refresh] [--format=json]
      Gera plano operacional verificavel para mudancas R2/R3/R4 (Composer,
      updatedb, config:import, deploy hooks e casos especiais como commerce_shipping),
      sem executar nenhuma alteracao no Runtime.

  structural-complete --plan=<arquivo.json> [--format=json]
      Fecha uma janela estrutural JA executada pelo responsavel. Nao importa
      config, nao roda updatedb e nao instala modulos. Exige
      ACULTA_SYNC_STRUCTURAL_CONFIRM=1, HEAD == origin/dev, worktree limpo e o
      plano JSON do structural-plan compativel. So avanca o baseline quando
      updatedb, deploy hooks e drift de config estao zerados; reconstroi o cache
      e registra a validacao do SHA.

  isolation-plan [--target-dir=<dir>] [--format=json]
      Inspeciona e planeja a separacao entre checkout de agentes e worktree servida
      pelo Apache (F4), preservando mounts persistentes e sem executar cutover.

Variaveis obrigatorias para sync-code (e opcionais para doctor/verify):
  ACULTA_SYNC_ENV=test
  ACULTA_SYNC_EXPECTED_HOST=<hostname -s conferido manualmente>
  ACULTA_SYNC_EXPECTED_DB=<banco conferido manualmente, ex.: aculta_runtime>
  ACULTA_SYNC_URI=<URI HTTPS de um site_key do servidor de desenvolvimento>

Variaveis opcionais de teste/isolamento:
  ACULTA_SYNC_ROOT=<raiz do checkout Git>
  ACULTA_SYNC_STATE_DIR=<diretorio de estado fora do Git/DocRoot; padrao: $root/var/runtime-sync>
  ACULTA_SYNC_DRUSH_BIN=<executavel Drush alternativo para testes isolados>
  ACULTA_SYNC_ALLOWED_REMOTE_REGEX=<regex de validacao da URL do remote origin>
  ACULTA_SYNC_ADOPT_BASELINE=1 (necessario junto com --adopt-baseline)

Nenhum comando faz deploy de producao, importacao de config ou update de banco.
HELP
}

fail() {
  printf 'ERRO: %s\n' "$*" >&2
  exit 1
}

notice() {
  if [[ "${FORMAT_JSON:-0}" -eq 0 ]]; then
    printf '%s\n' "$*" >&2
  fi
}

command -v git >/dev/null || fail 'git indisponivel'
command -v python3 >/dev/null || fail 'python3 indisponivel'

mode="${1:-plan}"
case "$mode" in
  plan|doctor|status|sync-code|verify|structural-plan|structural-complete|isolation-plan)
    shift || true
    ;;
  help|-h|--help)
    usage
    exit 0
    ;;
  *)
    usage >&2
    exit 2
    ;;
esac

OPT_REFRESH=0
FORMAT_JSON=0
OPT_ADOPT_BASELINE=0
OPT_TARGET_DIR=""
OPT_PLAN_FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --refresh)
      OPT_REFRESH=1
      ;;
    --format=json|--json)
      FORMAT_JSON=1
      ;;
    --adopt-baseline)
      OPT_ADOPT_BASELINE=1
      ;;
    --target-dir=*)
      OPT_TARGET_DIR="${1#--target-dir=}"
      ;;
    --plan=*)
      OPT_PLAN_FILE="${1#--plan=}"
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      fail "opcao ou argumento desconhecido para $mode: $1"
      ;;
  esac
  shift
done

if [[ "$OPT_REFRESH" -eq 1 && "$mode" != "plan" && "$mode" != "structural-plan" ]]; then
  fail "--refresh so e permitido com plan ou structural-plan"
fi
if [[ "$OPT_ADOPT_BASELINE" -eq 1 && "$mode" != "sync-code" ]]; then
  fail "--adopt-baseline so e permitido com sync-code"
fi
if [[ -n "$OPT_PLAN_FILE" && "$mode" != "structural-complete" ]]; then
  fail "--plan so e permitido com structural-complete"
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
helper_py="$script_dir/runtime-sync/helper.py"
[[ -f "$helper_py" ]] || fail "helper ausente: $helper_py"

if [[ -n "${ACULTA_SYNC_ROOT:-}" ]]; then
  root="$(cd -- "$ACULTA_SYNC_ROOT" && pwd -P)" || fail 'ACULTA_SYNC_ROOT invalido'
else
  root="$(cd -- "$script_dir/.." && pwd -P)"
fi
cd -- "$root"

git_root="$(cd -- "$(git rev-parse --show-toplevel 2>/dev/null)" && pwd -P)" || fail 'raiz Git indisponivel'
[[ "$git_root" == "$root" ]] || fail 'script fora da raiz do checkout Git'

state_dir="${ACULTA_SYNC_STATE_DIR:-$root/var/runtime-sync}"
# Garantir que state_dir nao fique dentro de web/ (DocumentRoot)
case "$state_dir" in
  "$root/web"|"$root/web/"*)
    fail 'ACULTA_SYNC_STATE_DIR nao pode ficar dentro do DocumentRoot (web/)'
    ;;
esac

run_id="$(date -u +%Y-%m-%dT%H:%M:%SZ)-$$-$RANDOM"
run_timestamp="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
tmp_dir=""
staging_repo=""
interrupted=0

cleanup() {
  local rc=$?
  if [[ -n "$staging_repo" && -d "$staging_repo" ]]; then
    rm -rf -- "$staging_repo" 2>/dev/null || true
  fi
  if [[ -n "$tmp_dir" && -d "$tmp_dir" ]]; then
    rm -rf -- "$tmp_dir" 2>/dev/null || true
  fi
  return "$rc"
}
trap cleanup EXIT

on_signal() {
  interrupted=1
  notice "SINAL recebido: interrompendo operacao com seguranca (estado preservado)."
  if [[ -n "$tmp_dir" && -d "$tmp_dir" && ( "$mode" == "sync-code" || "$mode" == "verify" || "$mode" == "structural-complete" ) ]]; then
    cat >"$tmp_dir/interrupt-run.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "mode": "$mode",
  "outcome": "${PARTIAL_AFTER_FF:-FAILED}",
  "phase": "signal-interrupted",
  "remote_sha": "${remote:-UNKNOWN}",
  "checkout_sha": "$(git rev-parse HEAD 2>/dev/null || printf UNKNOWN)",
  "last_validated_sha": "${last_validated_sha:-UNKNOWN}",
  "risk": "${delta_risk:-RX}",
  "summary": "Operacao interrompida por sinal externo (SIGINT/SIGTERM).",
  "gates": [{"id": "signal-safety", "result": "FAIL", "reason": "Interrupcao por sinal"}],
  "writes": []
}
EOF
    python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/interrupt-run.json" --update-state >/dev/null 2>&1 || true
  fi
  exit 130
}
trap on_signal INT TERM

tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/aculta-rsync.XXXXXX")"
chmod 700 "$tmp_dir"

# 1. Leitura de estado persistido
state_wrapper="$(python3 "$helper_py" read-state --repo-root "$root" --state-dir "$state_dir")"
state_status="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state_status"])')"
last_validated_sha="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("last_validated_sha") or "UNKNOWN")')"
last_cache_success_sha="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("last_cache_success_sha") or "UNKNOWN")')"
last_code_sync_sha="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("last_code_sync_sha") or "UNKNOWN")')"
state_error_msg="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("state_error") or "")')"

# Comando: status (estritamente leitura local, sem rede e sem Drush)
if [[ "$mode" == "status" ]]; then
  current_head="$(git rev-parse HEAD 2>/dev/null || printf 'UNKNOWN')"
  current_branch="$(git branch --show-current 2>/dev/null || printf 'DETACHED')"
  dirty_now="$(git status --porcelain=v1 --untracked-files=normal 2>/dev/null || true)"
  worktree_clean="true"
  [[ -z "$dirty_now" ]] || worktree_clean="false"

  status_json="$(python3 - "$state_wrapper" "$current_head" "$current_branch" "$worktree_clean" <<'PY'
import json, sys
wrapper = json.loads(sys.argv[1])
head = sys.argv[2]
branch = sys.argv[3]
clean = sys.argv[4] == "true"
st = wrapper["state"]
st_status = wrapper["state_status"]
last_val = st.get("last_validated_sha", "UNKNOWN")
cache_sha = st.get("last_cache_success_sha", "UNKNOWN")
cache_state = "VALIDATED" if (last_val != "UNKNOWN" and last_val == head and cache_sha == head) else ("STALE" if cache_sha != "UNKNOWN" else "UNKNOWN")
if st_status == "CORRUPT":
    overall = "INDETERMINATE"
elif st.get("last_operation", {}).get("outcome") == "PARTIAL":
    overall = "PARTIAL"
elif cache_state == "VALIDATED" and clean:
    overall = "VALIDATED"
else:
    overall = st.get("last_operation", {}).get("outcome") or "UNKNOWN"

out = {
    "schema_version": 2,
    "mode": "status",
    "state_status": st_status,
    "overall_status": overall,
    "branch": branch,
    "worktree_clean": clean,
    "checkout_sha": head,
    "recorded_remote_sha": st.get("remote_sha", "UNKNOWN"),
    "last_code_sync_sha": st.get("last_code_sync_sha", "UNKNOWN"),
    "last_cache_success_sha": cache_sha,
    "last_validated_sha": last_val,
    "cache_status": cache_state,
    "pending_structural_sha": st.get("pending_structural_sha"),
    "pending_structural_risk": st.get("pending_structural_risk"),
    "drift_baseline": st.get("drift_baseline"),
    "last_operation": st.get("last_operation"),
    "history_count": len(st.get("history", [])),
    "history": st.get("history", [])[:10],
    "secrets_redacted": True,
}
if st.get("state_error"):
    out["state_error"] = st["state_error"]
print(json.dumps(out, indent=2, ensure_ascii=True))
PY
)"
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    printf '%s\n' "$status_json"
  else
    printf '%s\n' "$status_json" | python3 -c '
import json, sys
d = json.load(sys.stdin)
state_status = d["state_status"]
overall_status = d["overall_status"]
print(f"Estado persistente: {state_status} (status geral: {overall_status})")
state_error = d.get("state_error")
if state_error:
    print(f"ERRO DE ESTADO:     {state_error}")
branch = d["branch"]
worktree_clean = d["worktree_clean"]
print(f"Branch / Limpo:     {branch} / {worktree_clean}")
checkout_sha = d["checkout_sha"]
print(f"Checkout SHA:       {checkout_sha}")
recorded_remote_sha = d["recorded_remote_sha"]
print(f"Remoto registrado:  {recorded_remote_sha}")
last_code_sync_sha = d["last_code_sync_sha"]
print(f"Ultimo code sync:   {last_code_sync_sha}")
last_cache_success_sha = d["last_cache_success_sha"]
cache_status = d["cache_status"]
print(f"Ultimo cache OK:    {last_cache_success_sha} ({cache_status})")
last_validated_sha = d["last_validated_sha"]
print(f"Ultimo validado:    {last_validated_sha}")
lo = d.get("last_operation") or {}
mode = lo.get("mode")
outcome = lo.get("outcome")
risk = lo.get("risk")
timestamp = lo.get("timestamp")
print(f"Ultima operacao:    mode={mode} outcome={outcome} risk={risk} em {timestamp}")
summary = lo.get("summary")
if summary:
    print(f"Resumo:             {summary}")
'
  fi
  [[ "$state_status" != "CORRUPT" ]] || exit 1
  exit 0
fi

# Comando: isolation-plan (F4)
if [[ "$mode" == "isolation-plan" ]]; then
  current_head="$(git rev-parse HEAD 2>/dev/null || printf 'UNKNOWN')"
  iso_json="$(python3 "$helper_py" isolation-plan --repo-root "$root" --target-sha "$current_head" --target-dir "$OPT_TARGET_DIR")"
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    printf '%s\n' "$iso_json"
  else
    notice "=== Plano de Isolamento de Checkout / Runtime (F4) ==="
    printf '%s\n' "$iso_json"
  fi
  exit 0
fi

# Verificacoes Git basicas para plan, doctor, sync-code, verify, structural-plan
current_branch="$(git branch --show-current 2>/dev/null || true)"
if [[ "$current_branch" != "dev" ]]; then
  fail 'o checkout deve estar na branch dev'
fi

remote_url="$(git remote get-url origin 2>/dev/null || true)"
[[ -n "$remote_url" ]] || fail 'remote origin ausente'

allowed_remote_re="${ACULTA_SYNC_ALLOWED_REMOTE_REGEX:-(github\.com[:/]coletivo420/aculta(\.git)?$|/|^file://)}"
if ! [[ "$remote_url" =~ $allowed_remote_re ]]; then
  fail 'remote origin nao corresponde a um repositorio ACULTA permitido'
fi

current="$(git rev-parse HEAD 2>/dev/null || true)"
[[ "$current" =~ ^[0-9a-f]{40}$ ]] || fail 'HEAD invalido'

# Nunca descarte alteracoes nao commitadas, inclusive de outros agentes.
dirty="$(git status --porcelain=v1 --untracked-files=normal)"
worktree_is_clean=1
if [[ -n "$dirty" ]]; then
  worktree_is_clean=0
  notice 'Checkout tem modificacoes ou arquivos nao rastreados. Preservados, sem sincronizar.'
  if [[ "$mode" == "sync-code" ]]; then
    # Registrar tentativa bloqueada se state_dir for valido
    if [[ "$state_status" != "CORRUPT" ]]; then
      cat >"$tmp_dir/dirty-block.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "BLOCKED",
  "phase": "preflight-worktree",
  "remote_sha": "UNKNOWN",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "risk": "RX",
  "summary": "Bloqueado: checkout compartilhado contem alteracoes locais ou arquivos nao rastreados.",
  "gates": [{"id": "worktree-clean", "result": "FAIL", "reason": "Arquivos modificados ou untracked presentes"}],
  "writes": []
}
EOF
      python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/dirty-block.json" --update-state >/dev/null 2>&1 || true
    fi
    fail 'checkout compartilhado nao esta limpo'
  fi
fi

# Verificar estado persistente corrompido (fail-closed)
if [[ "$state_status" == "CORRUPT" ]]; then
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    cat <<EOF
{
  "schema_version": 2,
  "run_id": "$run_id",
  "mode": "$mode",
  "outcome": "BLOCKED",
  "phase": "state-integrity",
  "checkout_sha": "$current",
  "last_validated_sha": "UNKNOWN",
  "risk": "RX",
  "summary": "Estado persistente corrompido ou inseguro: $state_error_msg",
  "gates": [{"id": "state-integrity", "result": "FAIL", "reason": "$state_error_msg"}],
  "secrets_redacted": true
}
EOF
  fi
  fail "estado persistente corrompido ou inseguro: $state_error_msg"
fi

# Obter SHA remoto de origin/dev (somente leitura das refs locais)
remote="$(git ls-remote --exit-code origin refs/heads/dev 2>/dev/null | awk 'NR==1 {print $1}' || true)"
[[ "$remote" =~ ^[0-9a-f]{40}$ ]] || fail 'nao foi possivel confirmar origin/dev'
notice "Runtime Git local:   $current"
notice "origin/dev remoto:   $remote"
notice "Ultimo SHA validado: $last_validated_sha (cache: $last_cache_success_sha)"

# Se --refresh foi solicitado em plan ou structural-plan e o commit remoto (ou base) precisar de objetos:
diff_repo="$root"
refresh_writes_note="nenhuma escrita no checkout; consulta apenas local"
if [[ "$OPT_REFRESH" -eq 1 ]]; then
  staging_repo="$(mktemp -d "${TMPDIR:-/tmp}/aculta-rsync-staging.XXXXXX")"
  chmod 700 "$staging_repo"
  git init --bare -q "$staging_repo"
  printf '%s/objects\n' "$root/.git" >"$staging_repo/objects/info/alternates"
  git --git-dir="$staging_repo" remote add origin "$remote_url"
  git --git-dir="$staging_repo" fetch --no-tags -q origin dev:refs/remotes/origin/dev || fail 'git fetch em staging isolado falhou'
  diff_repo="$staging_repo"
  refresh_writes_note="staging descartavel em diretorio temporario privado (0700) fora do checkout servido; removido ao sair"
  notice "Refresh isolado concluido em staging temporario (nenhuma ref do checkout foi alterada)."
fi

# Determinar base para classificacao cumulativa:
# - Se last_validated_sha e um SHA valido e diferente de UNKNOWN, o delta cumulativo real
#   e medido de last_validated_sha ate remote.
# - Se last_validated_sha e UNKNOWN, tambem inspecionamos current..remote para informar o operador,
#   mas mantemos o aviso de baseline UNKNOWN (exceto se --adopt-baseline autorizado ou FF a partir de current).
effective_base="$last_validated_sha"
baseline_is_unknown=0
if [[ "$effective_base" == "UNKNOWN" ]]; then
  baseline_is_unknown=1
  if [[ "$current" != "$remote" ]]; then
    # Ha commits novos entre HEAD atual e origin/dev: analisar esse intervalo
    effective_base="$current"
  elif [[ "$OPT_ADOPT_BASELINE" -eq 1 && "${ACULTA_SYNC_ADOPT_BASELINE:-0}" == "1" ]]; then
    effective_base="$current"
  fi
fi

delta_json="$(python3 "$helper_py" classify --repo-root "$diff_repo" --base-sha "$effective_base" --target-sha "$remote" --state-status "$state_status")"
delta_risk="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print(json.load(sys.stdin)["risk"])')"
delta_allow_sync="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["allow_sync_code"] else "0")')"
delta_req_cache="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["requires_cache_rebuild"] else "0")')"
delta_diff_avail="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["diff_available"] else "0")')"
delta_is_ff="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["is_fast_forward"] else "0")')"

# Se current == remote e last_validated_sha != remote, precisamos saber se o cache esta pendente!
cache_pending=0
if [[ "$last_cache_success_sha" != "$remote" || "$last_validated_sha" != "$remote" ]]; then
  cache_pending=1
fi

show_diff_summary() {
  python3 - "$delta_json" "$current" "$remote" "$last_validated_sha" "$cache_pending" >&2 <<'PY'
import json, sys
d = json.loads(sys.argv[1])
cur, rem, last_val, cache_pend = sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5] == "1"
files = d.get("files", [])
print(f"Classificacao de risco cumulativo ({d['base_sha'][:12] if len(d['base_sha'])==40 else d['base_sha']} -> {rem[:12]}): {d['risk']}")
if files:
    print("Arquivos alterados no intervalo analisado:")
    for f in files:
        tag = "[codigo] " if f["risk"] in ("R0", "R1") else "[REVISAR]"
        ren = f" (de {f['old_path']})" if f.get("old_path") else ""
        print(f"  {tag} ({f['risk']}) {f['path']}{ren}")
structural = sum(1 for f in files if f["risk"] not in ("R0", "R1"))
print(f"Resumo: {len(files)} arquivo(s), {structural} requer(em) revisao estrutural.")
for r in d.get("reasons", []):
    print(f"  Motivo: {r}")
if cur == rem and cache_pend:
    print("ATENCAO: HEAD local ja coincide com origin/dev, mas o cache/validacao deste SHA ainda esta PENDENTE.")
PY
}

# Funcao auxiliar para rodar checagens de Drush quando disponivel e configurado
run_drush_inspection() {
  local require_env="$1"
  DRUSH_OK=0
  DRUSH_SUMMARY_JSON=""
  DRUSH_ACTUAL_DB=""

  if [[ "$require_env" -eq 1 ]]; then
    command -v hostname >/dev/null || fail 'hostname indisponivel'
    [[ "${ACULTA_SYNC_ENV:-}" == "test" ]] || fail 'ACULTA_SYNC_ENV=test obrigatorio'
    [[ -n "${ACULTA_SYNC_EXPECTED_HOST:-}" ]] || fail 'defina ACULTA_SYNC_EXPECTED_HOST explicitamente'
    [[ -n "${ACULTA_SYNC_EXPECTED_DB:-}" ]] || fail 'defina ACULTA_SYNC_EXPECTED_DB explicitamente'
    [[ -n "${ACULTA_SYNC_URI:-}" ]] || fail 'defina ACULTA_SYNC_URI explicitamente'
    [[ "$ACULTA_SYNC_URI" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?(/.*)?$ ]] || fail 'ACULTA_SYNC_URI deve ser HTTPS sem credenciais ou parametros'
    # Proibir dominios de producao (.aculta.org)
    if [[ "$ACULTA_SYNC_URI" =~ \.aculta\.org(/|$|:) || "$ACULTA_SYNC_URI" =~ ^https://aculta\.org(/|$|:) ]]; then
      fail 'ACULTA_SYNC_URI aponta para dominio de producao (.aculta.org); operacao bloqueada'
    fi
    [[ "$(hostname -s)" == "$ACULTA_SYNC_EXPECTED_HOST" ]] || fail 'host nao corresponde ao declarado'
  else
    # Em modo doctor/verify/structural-plan sem variaveis explicitas, verificar se podemos consultar em modo leitura
    if [[ -n "${ACULTA_SYNC_ENV:-}" && "${ACULTA_SYNC_ENV}" != "test" ]]; then
      fail 'ACULTA_SYNC_ENV deve ser test'
    fi
    if [[ -n "${ACULTA_SYNC_EXPECTED_HOST:-}" && "$(hostname -s)" != "$ACULTA_SYNC_EXPECTED_HOST" ]]; then
      fail 'host nao corresponde ao declarado em ACULTA_SYNC_EXPECTED_HOST'
    fi
    if [[ -n "${ACULTA_SYNC_URI:-}" ]]; then
      [[ "$ACULTA_SYNC_URI" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?(/.*)?$ ]] || fail 'ACULTA_SYNC_URI invalida'
      if [[ "$ACULTA_SYNC_URI" =~ \.aculta\.org(/|$|:) || "$ACULTA_SYNC_URI" =~ ^https://aculta\.org(/|$|:) ]]; then
        fail 'ACULTA_SYNC_URI aponta para dominio de producao (.aculta.org); operacao bloqueada'
      fi
    fi
  fi

  local uri="${ACULTA_SYNC_URI:-https://aculta.toca.net.br}"
  if [[ -n "${ACULTA_SYNC_DRUSH_BIN:-}" ]]; then
    [[ -x "$ACULTA_SYNC_DRUSH_BIN" ]] || fail 'ACULTA_SYNC_DRUSH_BIN nao executavel'
    drush_cmd=("$ACULTA_SYNC_DRUSH_BIN" "--uri=$uri")
  else
    if [[ ! -f vendor/drush/drush/drush.php ]]; then
      if [[ "$require_env" -eq 0 ]]; then return 1; fi
      fail 'Drush nao instalado; nao atualizar codigo isoladamente'
    fi
    if [[ ! -f vendor/autoload.php ]]; then
      if [[ "$require_env" -eq 0 ]]; then return 1; fi
      fail 'vendor nao instalado; nao atualizar codigo isoladamente'
    fi
    drush_cmd=(php vendor/drush/drush/drush.php "--uri=$uri")
  fi

  DRUSH_ACTUAL_DB="$("${drush_cmd[@]}" status --field=db-name 2>/dev/null | tr -d '\r' | tail -n 1 || true)"
  if [[ -z "$DRUSH_ACTUAL_DB" ]]; then
    if [[ "$require_env" -eq 0 ]]; then return 1; fi
    fail 'nao foi possivel identificar banco Drupal'
  fi
  if [[ -n "${ACULTA_SYNC_EXPECTED_DB:-}" && "$DRUSH_ACTUAL_DB" != "$ACULTA_SYNC_EXPECTED_DB" ]]; then
    fail 'banco ativo difere do informado; abortado'
  fi

  notice 'Preflight Drush: verificacao de updates pendentes (somente leitura)'
  local updb_rc=0
  "${drush_cmd[@]}" updatedb:status --format=json >"$tmp_dir/updb.out" 2>&1 || updb_rc=$?
  if [[ "$updb_rc" -ne 0 && "$require_env" -eq 1 ]]; then
    fail 'updatedb:status falhou'
  fi

  notice 'Preflight Drush: verificacao de drift (somente leitura)'
  local cfg_rc=0
  "${drush_cmd[@]}" config:status --format=json >"$tmp_dir/cfg.out" 2>&1 || cfg_rc=$?
  if [[ "$cfg_rc" -ne 0 && "$require_env" -eq 1 ]]; then
    fail 'config:status falhou'
  fi

  local dep_rc=0
  "${drush_cmd[@]}" deploy:hook-status --format=json >"$tmp_dir/dep.out" 2>&1 || dep_rc=$?

  DRUSH_SUMMARY_JSON="$(python3 "$helper_py" parse-drush \
    --updb-rc "$updb_rc" --updb-file "$tmp_dir/updb.out" \
    --cfg-rc "$cfg_rc" --cfg-file "$tmp_dir/cfg.out" \
    --deploy-rc "$dep_rc" --deploy-file "$tmp_dir/dep.out")"

  local parsed_ok
  parsed_ok="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["ok"] else "0")')"
  if [[ "$parsed_ok" != "1" ]]; then
    if [[ "$require_env" -eq 0 ]]; then
      return 1
    fi
    fail 'saida do Drush malformada ou erro em updatedb:status / config:status'
  fi

  DRUSH_OK=1
  return 0
}

# Comando: plan
if [[ "$mode" == "plan" ]]; then
  plan_outcome="PLANNED"
  if [[ "$current" != "$remote" ]]; then
    if ! git --git-dir="${diff_repo}/.git" cat-file -e "$remote^{commit}" 2>/dev/null && ! git --git-dir="$diff_repo" cat-file -e "$remote^{commit}" 2>/dev/null; then
      notice 'Commit remoto ausente do objeto Git local: diff detalhado depende de plan --refresh ou fetch.'
      plan_outcome="INDETERMINATE"
    elif ! python3 -c "import json,sys; sys.exit(0 if json.loads(sys.argv[1])['is_fast_forward'] else 1)" "$delta_json"; then
      notice 'Heads divergentes: sincronizacao automatica sera bloqueada.'
      plan_outcome="BLOCKED"
    else
      show_diff_summary
      if [[ "$delta_allow_sync" == "1" && "$worktree_is_clean" -eq 1 ]]; then
        plan_outcome="READY"
      else
        plan_outcome="BLOCKED"
      fi
    fi
  else
    notice 'Sincronizado no Git. Nenhum arquivo precisa de fast-forward.'
    show_diff_summary
    if [[ "$cache_pending" -eq 0 && "$worktree_is_clean" -eq 1 ]]; then
      plan_outcome="NOOP"
    elif [[ "$delta_allow_sync" == "1" && "$worktree_is_clean" -eq 1 ]]; then
      plan_outcome="READY"
    elif [[ "$baseline_is_unknown" -eq 1 ]]; then
      plan_outcome="INDETERMINATE"
    else
      plan_outcome="BLOCKED"
    fi
  fi
  notice 'PLAN: nenhuma alteracao de codigo, banco, configuracao ou cache.'

  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    python3 - "$run_id" "$run_timestamp" "$plan_outcome" "$remote" "$current" "$last_validated_sha" "$last_cache_success_sha" "$worktree_is_clean" "$cache_pending" "$refresh_writes_note" "$delta_json" <<'PY'
import json, sys
run_id, ts, outcome, rem, cur, last_val, last_cache, clean, cache_pend, writes_note, delta_raw = sys.argv[1:12]
delta = json.loads(delta_raw)
gates = [
    {"id": "git-branch-dev", "result": "PASS", "reason": "Branch ativa e dev"},
    {"id": "worktree-clean", "result": "PASS" if clean == "1" else "FAIL", "reason": "Worktree limpo" if clean == "1" else "Modificacoes ou untracked presentes"},
    {"id": "diff-available", "result": "PASS" if delta["diff_available"] else "FAIL", "reason": "Objetos Git presentes" if delta["diff_available"] else "Commit ausente localmente"},
    {"id": "baseline-known", "result": "PASS" if last_val != "UNKNOWN" else "FAIL", "reason": f"last_validated_sha={last_val}"},
    {"id": "risk-allow-sync-code", "result": "PASS" if delta["allow_sync_code"] else "FAIL", "reason": f"Risco classificado: {delta['risk']}"},
]
out = {
    "schema_version": 2,
    "run_id": run_id,
    "timestamp": ts,
    "mode": "plan",
    "outcome": outcome,
    "phase": "risk-classification",
    "remote_sha": rem,
    "checkout_sha": cur,
    "last_validated_sha": None if last_val == "UNKNOWN" else last_val,
    "last_cache_success_sha": None if last_cache == "UNKNOWN" else last_cache,
    "cache_rebuild_needed": cache_pend == "1" and (delta["requires_cache_rebuild"] or last_cache == "UNKNOWN"),
    "risk": delta["risk"],
    "delta": delta,
    "gates": gates,
    "writes": [],
    "staging_note": writes_note,
    "secrets_redacted": True,
}
print(json.dumps(out, indent=2, ensure_ascii=True))
PY
  fi
  exit 0
fi

# Comando: structural-plan (F5)
if [[ "$mode" == "structural-plan" ]]; then
  if run_drush_inspection 0; then
    printf '%s' "$DRUSH_SUMMARY_JSON" >"$tmp_dir/drush-summary.json"
    splan_out="$(python3 "$helper_py" structural-plan --repo-root "$diff_repo" --base-sha "$effective_base" --target-sha "$remote" --state-status "$state_status" --drush-json-file "$tmp_dir/drush-summary.json")"
  else
    splan_out="$(python3 "$helper_py" structural-plan --repo-root "$diff_repo" --base-sha "$effective_base" --target-sha "$remote" --state-status "$state_status")"
  fi
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    printf '%s\n' "$splan_out"
  else
    notice "=== Plano Operacional Estrutural Supervisionado (F5) ==="
    show_diff_summary
    printf '%s\n' "$splan_out"
  fi
  exit 0
fi

# Comando: structural-complete (F5) - fecha a janela ja executada pelo responsavel.
if [[ "$mode" == "structural-complete" ]]; then
  [[ "${ACULTA_SYNC_STRUCTURAL_CONFIRM:-0}" == "1" ]] \
    || fail 'defina ACULTA_SYNC_STRUCTURAL_CONFIRM=1 depois de concluir a janela autorizada'
  [[ -n "$OPT_PLAN_FILE" ]] || fail 'informe --plan=<arquivo.json gerado por structural-plan>'
  [[ -f "$OPT_PLAN_FILE" ]] || fail 'arquivo do plano nao encontrado'
  [[ "$worktree_is_clean" -eq 1 ]] || fail 'checkout nao esta limpo'
  [[ "$current" == "$remote" ]] || fail 'HEAD difere de origin/dev; atualize o checkout na janela'
  [[ "$last_validated_sha" =~ ^[0-9a-f]{40}$ ]] || fail 'baseline validado ausente'
  git merge-base --is-ancestor "$last_validated_sha" "$current" \
    || fail 'baseline validado nao e ancestral de HEAD'

  plan_check="$(python3 -I - "$OPT_PLAN_FILE" "$last_validated_sha" "$current" <<'PY'
import json, sys
path, base, target = sys.argv[1], sys.argv[2], sys.argv[3]
try:
    plan = json.load(open(path, encoding="utf-8"))
except Exception as exc:
    print("err|plano ilegivel: " + str(exc)); raise SystemExit(0)
errors = []
if plan.get("schema_version") != 2:
    errors.append("schema_version diferente de 2")
if plan.get("base_sha") != base:
    errors.append("base_sha diverge do last_validated_sha")
if plan.get("target_sha") != target:
    errors.append("target_sha diverge do HEAD")
if plan.get("risk") not in ("R2", "R3", "R4"):
    errors.append("plano nao e R2/R3/R4 (use sync-code para R0/R1)")
if plan.get("autonomous_execution") is not False:
    errors.append("plano nao declara autonomous_execution=false")
if errors:
    print("err|" + "; ".join(errors)); raise SystemExit(0)
print("ok|" + str(plan.get("risk")))
PY
)"
  [[ "$plan_check" == ok\|* ]] || fail "${plan_check#err|}"
  plan_risk="${plan_check#ok|}"

  command -v flock >/dev/null || fail 'flock indisponivel'
  mkdir -p -- var/deployer
  exec 9>var/deployer/runtime-sync.lock
  flock -n 9 || fail 'sincronizacao ja em andamento'

  run_drush_inspection 1
  updb_pending_count="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["updatedb_pending_count"])')"
  deploy_pending_count="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["deploy_hook_pending_count"])')"
  config_drift_count="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["config_drift_count"])')"

  if [[ "$updb_pending_count" -ne 0 || "$deploy_pending_count" -ne 0 || "$config_drift_count" -ne 0 ]]; then
    cat >"$tmp_dir/structural-incomplete.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "structural-complete",
  "outcome": "BLOCKED",
  "phase": "post-window-verification",
  "remote_sha": "$current",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "pending_structural_sha": "$current",
  "pending_structural_risk": "$plan_risk",
  "risk": "$plan_risk",
  "summary": "Janela ainda nao concluida: updatedb=$updb_pending_count, deploy_hooks=$deploy_pending_count, config_drift=$config_drift_count.",
  "drift_summary": $DRUSH_SUMMARY_JSON,
  "gates": [
    {"id": "updatedb-clean", "result": "$( [[ "$updb_pending_count" -eq 0 ]] && printf PASS || printf FAIL )", "reason": "$updb_pending_count update(s)"},
    {"id": "deploy-hooks-clean", "result": "$( [[ "$deploy_pending_count" -eq 0 ]] && printf PASS || printf FAIL )", "reason": "$deploy_pending_count hook(s)"},
    {"id": "config-drift-clean", "result": "$( [[ "$config_drift_count" -eq 0 ]] && printf PASS || printf FAIL )", "reason": "$config_drift_count item(ns)"}
  ],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
    python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/structural-incomplete.json" --update-state >/dev/null 2>&1 || true
    fail "janela incompleta: updatedb=$updb_pending_count deploy=$deploy_pending_count config_drift=$config_drift_count"
  fi

  notice 'Janela reconhecida sem pendencias; reconstruindo cache para o SHA alvo.'
  if ! "${drush_cmd[@]}" cr; then
    cat >"$tmp_dir/structural-cr-fail.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "structural-complete",
  "outcome": "PARTIAL",
  "phase": "cache-rebuild",
  "remote_sha": "$current",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "pending_structural_sha": "$current",
  "pending_structural_risk": "$plan_risk",
  "risk": "$plan_risk",
  "summary": "drush cr falhou ao fechar a janela; baseline preservado.",
  "gates": [{"id": "drush-cache-rebuild", "result": "FAIL", "reason": "drush cr falhou"}],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
    python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/structural-cr-fail.json" --update-state >/dev/null 2>&1 || true
    fail 'drush cr falhou; estado PARTIAL, baseline preservado'
  fi

  run_drush_inspection 1
  updb_after="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["updatedb_pending_count"])')"
  deploy_after="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["deploy_hook_pending_count"])')"
  drift_after="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["config_drift_count"])')"
  [[ "$updb_after" -eq 0 && "$deploy_after" -eq 0 && "$drift_after" -eq 0 ]] \
    || fail "pendencias surgiram apos drush cr: updatedb=$updb_after deploy=$deploy_after drift=$drift_after"

  cat >"$tmp_dir/structural-complete-run.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "structural-complete",
  "outcome": "VALIDATED",
  "phase": "complete",
  "remote_sha": "$current",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "new_last_code_sync_sha": "$current",
  "new_last_cache_success_sha": "$current",
  "new_last_validated_sha": "$current",
  "pending_structural_sha": null,
  "pending_structural_risk": null,
  "risk": "$plan_risk",
  "summary": "PASS: janela estrutural fechada; drift, updatedb e deploy hooks zerados em $current.",
  "drift_summary": $DRUSH_SUMMARY_JSON,
  "gates": [
    {"id": "plan-match", "result": "PASS", "reason": "plano $plan_risk compativel com baseline e HEAD"},
    {"id": "worktree-clean", "result": "PASS", "reason": "Checkout limpo e HEAD == origin/dev"},
    {"id": "updatedb-clean", "result": "PASS", "reason": "0 update(s) pendente(s)"},
    {"id": "deploy-hooks-clean", "result": "PASS", "reason": "0 deploy hook(s) pendente(s)"},
    {"id": "config-drift-clean", "result": "PASS", "reason": "0 item(ns) de drift"},
    {"id": "drush-cache-rebuild", "result": "PASS", "reason": "drush cr executado com sucesso"}
  ],
  "writes": ["drush:cr", "var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
  python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/structural-complete-run.json" --update-state >/dev/null
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    cat "$state_dir/latest.json"
  else
    notice "STRUCTURAL-COMPLETE: VALIDATED (SHA: $current; risco da janela: $plan_risk)"
  fi
  exit 0
fi

# Comando: doctor (F2/F3)
if [[ "$mode" == "doctor" ]]; then
  # Checar se lock de sync esta ocupado sem bloquear permanentemente
  mkdir -p -- var/deployer
  lock_busy=0
  if [[ -f "$root/var/deployer/runtime-sync.lock" ]] && command -v flock >/dev/null; then
    if ! flock -n "$root/var/deployer/runtime-sync.lock" true 2>/dev/null; then
      lock_busy=1
    fi
  fi

  drush_gate="DEFERRED"
  drush_reason="Drush nao inspecionado ou ambiente sem variaveis/conexao"
  DRUSH_OK=0
  DRUSH_SUMMARY_JSON=""
  DRUSH_ACTUAL_DB=""

  if [[ -n "${ACULTA_SYNC_ENV:-}" && "${ACULTA_SYNC_ENV}" != "test" ]]; then
    drush_gate="FAIL"
    drush_reason="ACULTA_SYNC_ENV deve ser test"
  elif [[ -n "${ACULTA_SYNC_EXPECTED_HOST:-}" && "$(hostname -s)" != "$ACULTA_SYNC_EXPECTED_HOST" ]]; then
    drush_gate="FAIL"
    drush_reason="Host nao corresponde ao declarado em ACULTA_SYNC_EXPECTED_HOST"
  elif [[ -n "${ACULTA_SYNC_URI:-}" ]] && ( [[ "$ACULTA_SYNC_URI" =~ \.aculta\.org(/|$|:) || "$ACULTA_SYNC_URI" =~ ^https://aculta\.org(/|$|:) ]] ); then
    drush_gate="FAIL"
    drush_reason="ACULTA_SYNC_URI aponta para dominio de producao (.aculta.org)"
  else
    uri="${ACULTA_SYNC_URI:-https://aculta.toca.net.br}"
    drush_cmd=()
    if [[ -n "${ACULTA_SYNC_DRUSH_BIN:-}" && -x "$ACULTA_SYNC_DRUSH_BIN" ]]; then
      drush_cmd=("$ACULTA_SYNC_DRUSH_BIN" "--uri=$uri")
    elif [[ -f vendor/drush/drush/drush.php && -f vendor/autoload.php ]]; then
      drush_cmd=(php vendor/drush/drush/drush.php "--uri=$uri")
    fi

    if [[ "${#drush_cmd[@]}" -gt 0 ]]; then
      DRUSH_ACTUAL_DB="$("${drush_cmd[@]}" status --field=db-name 2>/dev/null | tr -d '\r' | tail -n 1 || true)"
      if [[ -n "$DRUSH_ACTUAL_DB" ]]; then
        if [[ -n "${ACULTA_SYNC_EXPECTED_DB:-}" && "$DRUSH_ACTUAL_DB" != "$ACULTA_SYNC_EXPECTED_DB" ]]; then
          drush_gate="FAIL"
          drush_reason="Banco ativo difere do esperado em ACULTA_SYNC_EXPECTED_DB"
        else
          updb_rc=0
          "${drush_cmd[@]}" updatedb:status --format=json >"$tmp_dir/updb.out" 2>&1 || updb_rc=$?
          cfg_rc=0
          "${drush_cmd[@]}" config:status --format=json >"$tmp_dir/cfg.out" 2>&1 || cfg_rc=$?
          dep_rc=0
          "${drush_cmd[@]}" deploy:hook-status --format=json >"$tmp_dir/dep.out" 2>&1 || dep_rc=$?
          DRUSH_SUMMARY_JSON="$(python3 "$helper_py" parse-drush \
            --updb-rc "$updb_rc" --updb-file "$tmp_dir/updb.out" \
            --cfg-rc "$cfg_rc" --cfg-file "$tmp_dir/cfg.out" \
            --deploy-rc "$dep_rc" --deploy-file "$tmp_dir/dep.out")"
          parsed_ok="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["ok"] else "0")')"
          if [[ "$parsed_ok" == "1" ]]; then
            DRUSH_OK=1
            drush_gate="PASS"
            drush_reason="Drush operacional e banco conectado"
          else
            drush_gate="FAIL"
            drush_reason="Saida do Drush malformada ou erro em updatedb/config:status"
          fi
        fi
      elif [[ -n "${ACULTA_SYNC_EXPECTED_DB:-}" || -n "${ACULTA_SYNC_URI:-}" ]]; then
        drush_gate="FAIL"
        drush_reason="Nao foi possivel identificar banco Drupal via Drush"
      fi
    fi
  fi

  printf '%s' "$DRUSH_SUMMARY_JSON" >"$tmp_dir/doctor-drush.json"
  [[ -s "$tmp_dir/doctor-drush.json" ]] || printf '{}' >"$tmp_dir/doctor-drush.json"
  doctor_json="$(python3 - "$run_id" "$run_timestamp" "$remote" "$current" "$last_validated_sha" "$last_cache_success_sha" "$worktree_is_clean" "$lock_busy" "$drush_gate" "$drush_reason" "$delta_json" "$tmp_dir/doctor-drush.json" <<'PY'
import json, sys
run_id, ts, rem, cur, last_val, last_cache, clean, lock_busy, d_gate, d_reason, delta_raw, drush_file = sys.argv[1:13]
delta = json.loads(delta_raw)
try:
    drush = json.loads(open(drush_file, encoding="utf-8").read())
except Exception:
    drush = {}

updb_cnt = drush.get("updatedb_pending_count")
cfg_cnt = drush.get("config_drift_count")
dep_cnt = drush.get("deploy_hook_pending_count")

gates = [
    {"id": "git-branch-dev", "result": "PASS", "reason": "Branch dev ativa"},
    {"id": "worktree-clean", "result": "PASS" if clean == "1" else "FAIL", "reason": "Worktree limpo" if clean == "1" else "Checkout possui modificacoes/untracked de agentes"},
    {"id": "sync-lock-free", "result": "FAIL" if lock_busy == "1" else "PASS", "reason": "Outro runtime-sync em execucao" if lock_busy == "1" else "Lock cooperativo livre"},
    {"id": "baseline-known", "result": "PASS" if last_val != "UNKNOWN" else "FAIL", "reason": f"last_validated_sha={last_val}"},
    {"id": "drush-bootstrap", "result": d_gate, "reason": d_reason},
    {"id": "updatedb-clean", "result": ("PASS" if updb_cnt == 0 else "FAIL") if updb_cnt is not None else "DEFERRED", "reason": f"{updb_cnt} update(s) pendente(s)" if updb_cnt is not None else "Nao medido"},
    {"id": "config-drift", "result": ("PASS" if cfg_cnt == 0 else "FAIL") if cfg_cnt is not None else "DEFERRED", "reason": f"{cfg_cnt} item(ns) com drift" if cfg_cnt is not None else "Nao medido"},
    {"id": "deploy-hooks-clean", "result": ("PASS" if dep_cnt == 0 else "FAIL") if dep_cnt is not None else "DEFERRED", "reason": f"{dep_cnt} deploy hook(s) pendente(s)" if dep_cnt is not None else "Nao medido"},
    {"id": "checkout-isolation-f4", "result": "FAIL", "reason": "Checkout compartilhado com Apache; edicoes de agentes ficam visiveis antes do sync-code ate cutover F4"},
]

outcome = "VALIDATED"
if d_gate == "FAIL" or lock_busy == "1":
    outcome = "BLOCKED"
elif last_val == "UNKNOWN":
    outcome = "INDETERMINATE"
elif clean != "1" or (updb_cnt or 0) > 0 or delta["risk"] not in ("R0", "R1"):
    outcome = "BLOCKED"

out = {
    "schema_version": 2,
    "run_id": run_id,
    "timestamp": ts,
    "mode": "doctor",
    "outcome": outcome,
    "phase": "environment-audit",
    "remote_sha": rem,
    "checkout_sha": cur,
    "last_validated_sha": None if last_val == "UNKNOWN" else last_val,
    "last_cache_success_sha": None if last_cache == "UNKNOWN" else last_cache,
    "risk": delta["risk"],
    "drush_checks": drush if drush else None,
    "gates": gates,
    "writes": [],
    "secrets_redacted": True,
}
print(json.dumps(out, indent=2, ensure_ascii=True))
PY
)"
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    printf '%s\n' "$doctor_json"
  else
    notice "=== Diagnostico do Runtime (doctor) ==="
    printf '%s\n' "$doctor_json" | python3 -c '
import json, sys
d = json.load(sys.stdin)
outcome = d["outcome"]
risk = d["risk"]
print(f"Resultado: {outcome} | Risco: {risk}")
for g in d["gates"]:
    result = g["result"]
    gate_id = g["id"]
    reason = g["reason"]
    print(f"  [{result}] {gate_id}: {reason}")
'
  fi
  exit 0
fi

# Comando: verify (F2)
if [[ "$mode" == "verify" ]]; then
  run_drush_inspection 1
  printf '%s' "$DRUSH_SUMMARY_JSON" >"$tmp_dir/verify-drush.json"
  verify_outcome="VALIDATED"
  if [[ "$worktree_is_clean" -ne 1 || "$current" != "$remote" || "$last_cache_success_sha" != "$current" ]]; then
    verify_outcome="BLOCKED"
  fi
  cat >"$tmp_dir/verify-run.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "verify",
  "outcome": "$verify_outcome",
  "phase": "post-verification",
  "remote_sha": "$remote",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "new_last_validated_sha": "$( [[ "$verify_outcome" == "VALIDATED" ]] && printf '%s' "$current" || printf 'UNKNOWN' )",
  "risk": "$delta_risk",
  "summary": "Verificacao concluida com resultado $verify_outcome.",
  "drift_summary": $DRUSH_SUMMARY_JSON,
  "gates": [
    {"id": "git-sha-match", "result": "$( [[ "$current" == "$remote" ]] && printf PASS || printf FAIL )", "reason": "HEAD == origin/dev"},
    {"id": "cache-validated-for-sha", "result": "$( [[ "$last_cache_success_sha" == "$current" ]] && printf PASS || printf FAIL )", "reason": "last_cache_success_sha=$last_cache_success_sha"},
    {"id": "drush-status", "result": "PASS", "reason": "Banco e bootstrap verificados"}
  ],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
  python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/verify-run.json" --update-state >/dev/null
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    cat "$state_dir/latest.json"
  else
    notice "VERIFY: $verify_outcome (SHA: $current)"
  fi
  [[ "$verify_outcome" == "VALIDATED" ]] || exit 1
  exit 0
fi

# Comando: sync-code (F1/F2/F3)
command -v flock >/dev/null || fail 'flock indisponivel'

# Lock cooperativo fora do Git versionado; evita duas sincronizacoes concorrentes.
# Ordem unica de locks documentada (F3): 1) var/deployer/runtime-sync.lock -> 2) var/runtime-sync/state.lock (interno).
mkdir -p -- var/deployer
exec 9>var/deployer/runtime-sync.lock
if ! flock -n 9; then
  fail 'sincronizacao ja em andamento'
fi

run_drush_inspection 1

# Revalidar que o checkout continua limpo apos adquirir o lock
[[ -z "$(git status --porcelain=v1 --untracked-files=normal)" ]] || fail 'checkout mudou durante o preflight'

# Reler estado persistido apos adquirir o lock (outro processo pode ter acabado de sincronizar e reconstruir o cache!)
state_wrapper="$(python3 "$helper_py" read-state --repo-root "$root" --state-dir "$state_dir")"
state_status="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state_status"])')"
[[ "$state_status" != "CORRUPT" ]] || fail 'estado persistente corrompido detectado apos lock'
last_validated_sha="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("last_validated_sha") or "UNKNOWN")')"
last_cache_success_sha="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("last_cache_success_sha") or "UNKNOWN")')"
last_code_sync_sha="$(printf '%s' "$state_wrapper" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["state"].get("last_code_sync_sha") or "UNKNOWN")')"

# Verificar se ha updates de banco ou deploy hooks pendentes no Runtime antes de prosseguir
updb_pending_count="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["updatedb_pending_count"])')"
deploy_pending_count="$(printf '%s' "$DRUSH_SUMMARY_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["deploy_hook_pending_count"])')"

# Fetch permitido apenas na etapa de escrita; ainda sem modificar o checkout.
# Debounce natural (F3): git fetch obtem o SHA mais recente elegivel em origin/dev no momento do lock.
git fetch --no-tags origin dev || fail 'git fetch falhou'
target="$(git rev-parse FETCH_HEAD)"
[[ "$target" =~ ^[0-9a-f]{40}$ ]] || fail 'FETCH_HEAD invalido'
[[ "$(git branch --show-current)" == "dev" ]] || fail 'branch mudou durante preflight'

current_now="$(git rev-parse HEAD)"
[[ "$current_now" == "$current" ]] || fail 'HEAD mudou durante preflight'
[[ -z "$(git status --porcelain=v1 --untracked-files=normal)" ]] || fail 'checkout deixou de estar limpo'

# Determinar base de comparacao para a sincronizacao:
# 1) Se last_validated_sha e conhecido (SHA-40), o delta cumulativo e last_validated_sha..target.
# 2) Se last_validated_sha e UNKNOWN:
#    - Se target != current: podemos avaliar current..target para fast-forward quando current e ancestral de target.
#    - Se target == current: o codigo ja estava nesse SHA, mas o cache/baseline e UNKNOWN!
#      Nesse caso (F1 bug fix), se --adopt-baseline + ACULTA_SYNC_ADOPT_BASELINE=1 foram passados (ou se estivermos
#      sincronizando pela primeira vez sem mudancas estruturais no commit HEAD), reconstruimos o cache!
#      Para garantir que `sync-code` resolva o bug `HEAD == origin/dev` quando o agente acabou de fazer commit/push
#      de codigo R0/R1 no checkout servido: se last_validated_sha == UNKNOWN e current == target, inspecionamos
#      HEAD~1..HEAD (quando HEAD~1 existir) a menos que --adopt-baseline tenha sido solicitado.
sync_base="$last_validated_sha"
if [[ "$sync_base" == "UNKNOWN" ]]; then
  if [[ "$target" != "$current" ]]; then
    sync_base="$current"
  elif [[ "$OPT_ADOPT_BASELINE" -eq 1 && "${ACULTA_SYNC_ADOPT_BASELINE:-0}" == "1" ]]; then
    sync_base="$current"
  elif git rev-parse --verify -q "${current}~1^{commit}" >/dev/null 2>&1; then
    sync_base="$(git rev-parse "${current}~1")"
  else
    sync_base="$current"
  fi
fi

# Verificar ancestralidade do checkout atual para o target
if [[ "$current" != "$target" ]]; then
  git merge-base --is-ancestor "$current" "$target" || fail 'origin/dev nao e fast-forward do HEAD atual'
fi
if [[ "$sync_base" != "$target" ]]; then
  git merge-base --is-ancestor "$sync_base" "$target" || fail 'target nao e fast-forward do baseline validado'
fi

delta_json="$(python3 "$helper_py" classify --repo-root "$root" --base-sha "$sync_base" --target-sha "$target" --state-status "$state_status")"
delta_risk="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print(json.load(sys.stdin)["risk"])')"
delta_allow_sync="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["allow_sync_code"] else "0")')"
delta_req_cache="$(printf '%s' "$delta_json" | python3 -c 'import json,sys; print("1" if json.load(sys.stdin)["requires_cache_rebuild"] else "0")')"

# Caso 1: HEAD == target E o cache/validacao ja estao comprovadamente no SHA target => NOOP idempotente!
if [[ "$target" == "$current" && "$last_cache_success_sha" == "$target" && "$last_validated_sha" == "$target" ]]; then
  notice "NOOP: Git e cache Drupal ja estao comprovadamente sincronizados e validados em $target."
  cat >"$tmp_dir/noop-run.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "NOOP",
  "phase": "complete",
  "remote_sha": "$target",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "new_last_code_sync_sha": "$target",
  "new_last_cache_success_sha": "$target",
  "new_last_validated_sha": "$target",
  "risk": "R0",
  "summary": "NOOP: codigo e cache ja validados para o SHA atual.",
  "drift_summary": $DRUSH_SUMMARY_JSON,
  "gates": [
    {"id": "worktree-clean", "result": "PASS", "reason": "Worktree limpo"},
    {"id": "cache-already-validated", "result": "PASS", "reason": "last_cache_success_sha == target"}
  ],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
  python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/noop-run.json" --update-state >/dev/null
  if [[ "$FORMAT_JSON" -eq 1 ]]; then
    cat "$state_dir/latest.json"
  fi
  exit 0
fi

# Exibir resumo do diff e verificar bloqueios estruturais (R2, R3, R4, RX)
show_diff_summary
if [[ "$delta_allow_sync" != "1" ]]; then
  cat >"$tmp_dir/struct-block.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "BLOCKED",
  "phase": "risk-classification",
  "remote_sha": "$target",
  "checkout_sha": "$current",
  "last_validated_sha": "$last_validated_sha",
  "pending_structural_sha": "$target",
  "pending_structural_risk": "$delta_risk",
  "risk": "$delta_risk",
  "summary": "Bloqueado: mudanca estrutural ($delta_risk) detectada entre $sync_base e $target.",
  "drift_summary": $DRUSH_SUMMARY_JSON,
  "gates": [
    {"id": "worktree-clean", "result": "PASS", "reason": "Worktree limpo"},
    {"id": "risk-allow-sync-code", "result": "FAIL", "reason": "Risco $delta_risk requer structural-plan e janela autorizada"}
  ],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
  python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/struct-block.json" --update-state >/dev/null
  fail 'mudanca estrutural detectada: exigir procedimento e autorizacao separados'
fi

# Executar fast-forward se o checkout ainda estiver atras de target
did_ff=0
if [[ "$current" != "$target" ]]; then
  git merge --ff-only "$target" || fail 'fast-forward falhou; nenhuma limpeza automatica'
  did_ff=1
  PARTIAL_AFTER_FF="PARTIAL"
fi

# Decidir se o rebuild de cache (drush cr) e necessario:
# - Se last_cache_success_sha == UNKNOWN ou last_validated_sha == UNKNOWN: SEMPRE reconstruir cache no primeiro sync/adocao.
# - Se delta_req_cache == 1 (R1): SEMPRE reconstruir cache.
# - Se delta_risk == R0 (somente docs/testes) E last_cache_success_sha == sync_base (ja havia baseline de cache valido):
#   podemos pular drush cr com justificativa documentada!
need_cr=1
cr_skip_reason=""
if [[ "$delta_risk" == "R0" && "$delta_req_cache" == "0" && "$last_cache_success_sha" == "$sync_base" && "$last_validated_sha" == "$sync_base" ]]; then
  need_cr=0
  cr_skip_reason="Delta contem exclusivamente arquivos R0 (documentacao/testes) e baseline de cache anterior estava validado."
fi

uri="${ACULTA_SYNC_URI}"
if [[ -n "${ACULTA_SYNC_DRUSH_BIN:-}" ]]; then
  drush_cmd=("$ACULTA_SYNC_DRUSH_BIN" "--uri=$uri")
else
  drush_cmd=(php vendor/drush/drush/drush.php "--uri=$uri")
fi

if [[ "$need_cr" -eq 1 ]]; then
  notice 'Codigo sincronizado. Reconstruindo caches Drupal...'
  if ! "${drush_cmd[@]}" cr; then
    cat >"$tmp_dir/partial-fail.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "PARTIAL",
  "phase": "cache-rebuild",
  "remote_sha": "$target",
  "checkout_sha": "$(git rev-parse HEAD 2>/dev/null || printf '%s' "$target")",
  "last_validated_sha": "$last_validated_sha",
  "new_last_code_sync_sha": "$target",
  "risk": "$delta_risk",
  "summary": "FALHA PARCIAL: codigo em $target, mas drush cr falhou. Cache NAO validado; nao houve rollback automatico.",
  "drift_summary": $DRUSH_SUMMARY_JSON,
  "gates": [
    {"id": "git-fast-forward", "result": "PASS", "reason": "Checkout em $target"},
    {"id": "drush-cache-rebuild", "result": "FAIL", "reason": "drush cr retornou erro"}
  ],
  "writes": ["git:HEAD", "var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
    python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/partial-fail.json" --update-state >/dev/null || true
    fail 'drush cr falhou APOS fast-forward. Nao houve rollback automatico. Corrigir antes de continuar.'
  fi
else
  notice "Cache rebuild dispensado com seguranca (R0): $cr_skip_reason"
fi

# Conferir concorrencia pos-escrita: HEAD e worktree devem continuar intactos
if [[ "$(git rev-parse HEAD)" != "$target" ]] || [[ -n "$(git status --porcelain=v1 --untracked-files=normal)" ]]; then
  cat >"$tmp_dir/concurrency-fail.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "PARTIAL",
  "phase": "post-concurrency-audit",
  "remote_sha": "$target",
  "checkout_sha": "$(git rev-parse HEAD 2>/dev/null || printf UNKNOWN)",
  "last_validated_sha": "$last_validated_sha",
  "new_last_code_sync_sha": "$target",
  "risk": "$delta_risk",
  "summary": "Concorrencia detectada: HEAD ou worktree foi modificado por outro processo durante a sincronizacao.",
  "gates": [
    {"id": "post-concurrency-check", "result": "FAIL", "reason": "Checkout alterado durante a operacao"}
  ],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
  python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/concurrency-fail.json" --update-state >/dev/null || true
  fail 'HEAD ou worktree mudou ao final; auditar concorrencia'
fi

notice 'Pos-sincronizacao: conferir database updates e config drift (somente leitura).'
post_updb_rc=0
"${drush_cmd[@]}" updatedb:status --format=json >"$tmp_dir/post_updb.out" 2>&1 || post_updb_rc=$?
[[ "$post_updb_rc" -eq 0 ]] || fail 'updatedb:status pos-sync falhou'

post_cfg_rc=0
"${drush_cmd[@]}" config:status --format=json >"$tmp_dir/post_cfg.out" 2>&1 || post_cfg_rc=$?
[[ "$post_cfg_rc" -eq 0 ]] || fail 'config:status pos-sync falhou'

post_dep_rc=0
"${drush_cmd[@]}" deploy:hook-status --format=json >"$tmp_dir/post_dep.out" 2>&1 || post_dep_rc=$?

POST_DRUSH_JSON="$(python3 "$helper_py" parse-drush \
  --updb-rc "$post_updb_rc" --updb-file "$tmp_dir/post_updb.out" \
  --cfg-rc "$post_cfg_rc" --cfg-file "$tmp_dir/post_cfg.out" \
  --deploy-rc "$post_dep_rc" --deploy-file "$tmp_dir/post_dep.out")"

post_updb_cnt="$(printf '%s' "$POST_DRUSH_JSON" | python3 -c 'import json,sys; print(json.load(sys.stdin)["updatedb_pending_count"])')"
if [[ "$post_updb_cnt" -gt "$updb_pending_count" ]]; then
  cat >"$tmp_dir/post-updb- emerged.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "PARTIAL",
  "phase": "post-validation",
  "remote_sha": "$target",
  "checkout_sha": "$target",
  "last_validated_sha": "$last_validated_sha",
  "new_last_code_sync_sha": "$target",
  "new_last_cache_success_sha": "$target",
  "pending_structural_sha": "$target",
  "pending_structural_risk": "R3",
  "risk": "R3",
  "summary": "Novos database updates pendentes surgiram apos atualizar o codigo ($updb_pending_count -> $post_updb_cnt).",
  "drift_summary": $POST_DRUSH_JSON,
  "gates": [
    {"id": "post-updatedb-no-new-pending", "result": "FAIL", "reason": "Surgiram updates pendentes apos sync"}
  ],
  "writes": ["git:HEAD", "drush:cr", "var/runtime-sync/state.json"]
}
EOF
  python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/post-updb- emerged.json" --update-state >/dev/null || true
  fail 'novos updates de banco surgiram apos a sincronizacao; homologacao bloqueada'
fi

cat >"$tmp_dir/success-run.json" <<EOF
{
  "run_id": "$run_id",
  "timestamp": "$run_timestamp",
  "mode": "sync-code",
  "outcome": "VALIDATED",
  "phase": "complete",
  "remote_sha": "$target",
  "checkout_sha": "$target",
  "last_validated_sha": "$target",
  "new_last_code_sync_sha": "$target",
  "new_last_cache_success_sha": "$target",
  "new_last_validated_sha": "$target",
  "pending_structural_sha": null,
  "pending_structural_risk": null,
  "risk": "$delta_risk",
  "summary": "PASS: codigo e cache sincronizados e validados em $target.",
  "drift_summary": $POST_DRUSH_JSON,
  "gates": [
    {"id": "worktree-clean", "result": "PASS", "reason": "Worktree limpo antes e depois"},
    {"id": "risk-allow-sync-code", "result": "PASS", "reason": "Delta classificado como $delta_risk"},
    {"id": "drush-cache-rebuild", "result": "$( [[ "$need_cr" -eq 1 ]] && printf PASS || printf SKIP )", "reason": "$( [[ "$need_cr" -eq 1 ]] && printf 'drush cr executado com sucesso' || printf '%s' "$cr_skip_reason" )"},
    {"id": "post-drush-checks", "result": "PASS", "reason": "updatedb:status e config:status pos-sync conferidos"}
  ],
  "writes": ["var/runtime-sync/state.json", "var/runtime-sync/latest.json"]
}
EOF
python3 "$helper_py" record-run --repo-root "$root" --state-dir "$state_dir" --run-json "$tmp_dir/success-run.json" --update-state >/dev/null

if [[ "$FORMAT_JSON" -eq 1 ]]; then
  cat "$state_dir/latest.json"
else
  notice "PASS: codigo e cache sincronizados com $target. Banco e configuracao nao foram alterados pelo script."
fi
