#!/usr/bin/env bash
# ACULTA - sincronizacao conservadora do checkout de desenvolvimento.
# Nao executa Composer, updates, config import/export nem operacoes de banco.
set -Eeuo pipefail

usage() {
  cat <<'HELP'
Uso: bash scripts/runtime-sync.sh [plan|sync-code]

  plan        somente leitura: identifica HEAD remoto e resume o risco.
  sync-code   fast-forward do checkout dev e drush cr, apenas se nao ha
              alteracoes estruturais. Exige confirmacao explicita do host,
              URI, banco e ambiente via variaveis ACULTA_SYNC_*.

Variaveis para sync-code:
  ACULTA_SYNC_ENV=test
  ACULTA_SYNC_EXPECTED_HOST=<hostname -s conferido manualmente>
  ACULTA_SYNC_EXPECTED_DB=<banco conferido manualmente>
  ACULTA_SYNC_URI=<URI de um purpose do servidor de desenvolvimento>

Nenhum comando faz deploy de producao, importacao de config ou update de banco.
HELP
}

fail() { printf 'ERRO: %s\n' "$*" >&2; exit 1; }
notice() { printf '%s\n' "$*" >&2; }
command -v git >/dev/null || fail 'git indisponivel'
mode="${1:-plan}"
case "$mode" in plan|sync-code) ;; help|-h|--help) usage; exit 0 ;; *) usage; exit 2 ;; esac
[[ $# -le 1 ]] || fail 'argumentos extras nao sao permitidos'
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd -- "$root"
git_root="$(cd -- "$(git rev-parse --show-toplevel 2>/dev/null)" && pwd -P)" || fail 'raiz Git indisponivel'
[[ "$git_root" == "$root" ]] || fail 'script fora da raiz do checkout Git'
[[ "$(git branch --show-current)" == 'dev' ]] || fail 'o checkout deve estar na branch dev'
git remote get-url origin >/dev/null 2>&1 || fail 'remote origin ausente'
current="$(git rev-parse HEAD)"
[[ "$current" =~ ^[0-9a-f]{40}$ ]] || fail 'HEAD invalido'

# Nunca descarte alteracoes nao commitadas, inclusive de outros agentes.
dirty="$(git status --porcelain=v1 --untracked-files=normal)"
if [[ -n "$dirty" ]]; then
  notice 'Checkout tem modificacoes ou arquivos nao rastreados. Preservados, sem sincronizar.'
  if [[ "$mode" == sync-code ]]; then fail 'checkout compartilhado nao esta limpo'; fi
fi

# Apenas leitura: nao muda refs locais durante o planejamento.
remote="$(git ls-remote --exit-code origin refs/heads/dev | awk 'NR==1 {print $1}')"
[[ "$remote" =~ ^[0-9a-f]{40}$ ]] || fail 'nao foi possivel confirmar origin/dev'
notice "Runtime Git local:  $current"
notice "origin/dev remoto:  $remote"

classify() {
  local file="$1"
  case "$file" in
    composer.json|composer.lock|package.json|package-lock.json|patches-aculta.json|patches.lock.json|\
    config/sync/*|web/sites/*|drush/*|*.install|*.post_update.php|*.deploy.php|*.info.yml)
      return 0 ;;
  esac
  return 1
}

show_diff() {
  local base="$1" dest="$2" line structural=0 count=0
  notice 'Arquivos alterados entre HEAD local e o destino:'
  while IFS= read -r line; do
    [[ -n "$line" ]] || continue
    count=$((count + 1))
    if classify "$line"; then
      structural=$((structural + 1))
      printf '  [REVISAR] %s\n' "$line" >&2
    else
      printf '  [codigo]  %s\n' "$line" >&2
    fi
  done < <(git diff --no-renames --name-only --diff-filter=ACDMRT "$base" "$dest" --)
  notice "Resumo: $count arquivo(s), $structural requer(em) revisao estrutural."
  [[ "$structural" -eq 0 ]]
}

if [[ "$mode" == plan ]]; then
  if [[ "$current" == "$remote" ]]; then
    notice 'Sincronizado no Git. Nenhum arquivo precisa de fast-forward.'
  elif git cat-file -e "$remote^{commit}" 2>/dev/null; then
    if git merge-base --is-ancestor "$current" "$remote"; then
      show_diff "$current" "$remote" || true
    else
      notice 'Heads divergentes: sincronizacao automatica sera bloqueada.'
    fi
  else
    notice 'Commit remoto ausente do objeto Git local: diff detalhado depende de fetch.'
  fi
  notice 'PLAN: nenhuma alteracao de codigo, banco, configuracao ou cache.'
  exit 0
fi

command -v flock >/dev/null || fail 'flock indisponivel'
command -v hostname >/dev/null || fail 'hostname indisponivel'
[[ "${ACULTA_SYNC_ENV:-}" == test ]] || fail 'ACULTA_SYNC_ENV=test obrigatorio'
[[ -n "${ACULTA_SYNC_EXPECTED_HOST:-}" ]] || fail 'defina ACULTA_SYNC_EXPECTED_HOST explicitamente'
[[ -n "${ACULTA_SYNC_EXPECTED_DB:-}" ]] || fail 'defina ACULTA_SYNC_EXPECTED_DB explicitamente'
[[ -n "${ACULTA_SYNC_URI:-}" ]] || fail 'defina ACULTA_SYNC_URI explicitamente'
[[ "$ACULTA_SYNC_URI" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?(/.*)?$ ]] || fail 'ACULTA_SYNC_URI deve ser HTTPS sem credenciais ou parametros'
[[ "$(hostname -s)" == "$ACULTA_SYNC_EXPECTED_HOST" ]] || fail 'host nao corresponde ao declarado'
[[ -f vendor/drush/drush/drush.php ]] || fail 'Drush nao instalado; nao atualizar codigo isoladamente'
[[ -f vendor/autoload.php ]] || fail 'vendor nao instalado; nao atualizar codigo isoladamente'

# Lock fora do Git versionado; evita duas sincronizacoes concorrentes.
mkdir -p -- var/deployer
exec 9>var/deployer/runtime-sync.lock
flock -n 9 || fail 'sincronizacao ja em andamento'
[[ -z "$(git status --porcelain=v1 --untracked-files=normal)" ]] || fail 'checkout mudou durante o preflight'

# Checar o banco real, sem imprimir credenciais nem conectar a outro ambiente.
drush=(php vendor/drush/drush/drush.php "--uri=$ACULTA_SYNC_URI")
actual_db="$("${drush[@]}" status --field=db-name)" || fail 'nao foi possivel identificar banco Drupal'
[[ "$actual_db" == "$ACULTA_SYNC_EXPECTED_DB" ]] || fail 'banco ativo difere do informado; abortado'
notice 'Preflight Drush: verificacao de updates pendentes (somente leitura)'
"${drush[@]}" updatedb:status || fail 'updatedb:status falhou'
notice 'Preflight Drush: verificacao de drift (somente leitura)'
"${drush[@]}" config:status || fail 'config:status falhou'

# Fetch permitido apenas na etapa de escrita; ainda sem modificar o checkout.
git fetch --no-tags origin dev || fail 'git fetch falhou'
target="$(git rev-parse FETCH_HEAD)"
[[ "$target" =~ ^[0-9a-f]{40}$ ]] || fail 'FETCH_HEAD invalido'
[[ "$(git branch --show-current)" == dev ]] || fail 'branch mudou durante preflight'
[[ "$(git rev-parse HEAD)" == "$current" ]] || fail 'HEAD mudou durante preflight'
[[ -z "$(git status --porcelain=v1 --untracked-files=normal)" ]] || fail 'checkout deixou de estar limpo'
if [[ "$target" == "$current" ]]; then
  notice 'Git ja esta sincronizado. Nenhum cache foi alterado.'
  exit 0
fi
git merge-base --is-ancestor "$current" "$target" || fail 'origin/dev nao e fast-forward do HEAD atual'
show_diff "$current" "$target" || fail 'mudanca estrutural detectada: exigir procedimento e autorizacao separados'

# Sem reset, stash, clean, composer, cex, cim ou updb.
git merge --ff-only "$target" || fail 'fast-forward falhou; nenhuma limpeza automatica'
notice 'Codigo sincronizado. Reconstruindo caches Drupal...'
if ! "${drush[@]}" cr; then
  fail 'drush cr falhou APOS fast-forward. Nao houve rollback automatico. Corrigir antes de continuar.'
fi
[[ "$(git rev-parse HEAD)" == "$target" ]] || fail 'HEAD mudou ao final; auditar concorrencia'
notice 'Pos-sincronizacao: conferir database updates e config drift (somente leitura).'
"${drush[@]}" updatedb:status || fail 'updatedb:status pos-sync falhou'
"${drush[@]}" config:status || fail 'config:status pos-sync falhou'
notice "PASS: codigo e cache sincronizados com $target. Banco e configuracao nao foram alterados pelo script."
