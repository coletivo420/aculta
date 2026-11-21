#!/usr/bin/env bash
# ACULTA SFC-01/02: focused checks in the existing Forum worktree.
# Never run Kernel or Browser against the shared Runtime.
set -euo pipefail

usage() {
  cat <<'USAGE'
Uso: scripts/tests/verify-forum-cgu-sfc01-sfc02.sh [--unit|--kernel|--browser|--all]
Sem argumentos: PHP lint + Unit SFC-01/02.
--kernel: SIMPLETEST_DB com nome "simpletest" e banco descartável.
--browser: ACULTA_QA_ISOLATED=yes + ACULTA_SECURITY_QA_FIXTURE isolada.
Nenhuma conta, configuração ou banco de Runtime é modificado.
USAGE
}

mode="${1:---unit}"
case "$mode" in
  --unit | --kernel | --browser | --all) ;;
  -h | --help)
    usage
    exit 0
    ;;
  *)
    usage >&2
    exit 2
    ;;
esac

repo="$(git rev-parse --show-toplevel)"
branch="$(git -C "$repo" branch --show-current)"
if [[ "$branch" != "feat/forum-paridade-discourse" ]]; then
  echo "ERRO: use a worktree existente da feat/forum-paridade-discourse (atual: $branch)." >&2
  exit 2
fi
cd "$repo"

module="web/modules/custom/aculta_portal/modules/aculta_forum"
cgu="web/modules/custom/aculta_portal/modules/aculta_cgu"
echo "ACULTA SFC-01/02 HEAD: $(git rev-parse HEAD)"

for file in \
  "$module/src/Access/CommentModerationAccess.php" \
  "$module/src/Access/TopicAdminPopupAccess.php" \
  "$module/src/Form/DiscussionCommentModerationForm.php" \
  "$module/src/Controller/TopicAdminPopupController.php" \
  "$module/tests/src/Unit/Security/CommentModerationBoundaryTest.php" \
  "$module/tests/src/Unit/Security/TopicAdminPopupBoundaryTest.php" \
  "$module/tests/src/Unit/Security/CommentModerationFormBoundaryTest.php"; do
  php -l "$file"
done

if [[ "$mode" == "--unit" || "$mode" == "--all" || "$mode" == "--kernel" ]]; then
  if [[ ! -x vendor/bin/phpunit ]]; then
    echo "ERRO: vendor/bin/phpunit ausente na worktree." >&2
    exit 2
  fi
fi

if [[ "$mode" == "--unit" || "$mode" == "--all" ]]; then
  for file in \
    "$module/tests/src/Unit/Security/CommentModerationBoundaryTest.php" \
    "$module/tests/src/Unit/Security/TopicAdminPopupBoundaryTest.php" \
    "$module/tests/src/Unit/Security/CommentModerationFormBoundaryTest.php" \
    "$cgu/tests/src/Unit/ModerationScopeMarleyMatrixTest.php"; do
    vendor/bin/phpunit --configuration phpunit.xml "$file"
  done
fi

if [[ "$mode" == "--kernel" || "$mode" == "--all" ]]; then
  db="${SIMPLETEST_DB:-}"
  if [[ -z "$db" || "${db,,}" != *simpletest* ]]; then
    echo "BLOQUEADO: SIMPLETEST_DB deve usar banco descartável com nome simpletest." >&2
    exit 2
  fi
  for file in \
    "$cgu/tests/src/Kernel/CguMarleyCombinedRolesDecisionKernelTest.php" \
    "$cgu/tests/src/Kernel/ForumCguIntegrationKernelTest.php" \
    "$module/tests/src/Kernel/RestrictedForumGroupAclKernelTest.php"; do
    vendor/bin/phpunit --configuration phpunit.xml "$file"
  done
fi

if [[ "$mode" == "--browser" || "$mode" == "--all" ]]; then
  if [[ "${ACULTA_QA_ISOLATED:-}" != "yes" ]]; then
    echo "BLOQUEADO: ACULTA_QA_ISOLATED=yes obrigatório." >&2
    exit 2
  fi
  manifest="${ACULTA_SECURITY_QA_FIXTURE:-}"
  if [[ -z "$manifest" || ! -f "$manifest" ]]; then
    echo "BLOQUEADO: fixture privada de Browser ausente." >&2
    exit 2
  fi
  node -e 'const fs=require("fs");const f=JSON.parse(fs.readFileSync(process.argv[1],"utf8"));if(f.isolated!==true)process.exit(2)' "$manifest" || {
    echo "BLOQUEADO: fixture não confirma isolated=true." >&2
    exit 2
  }
  (
    cd tests/browser
    npx --no-install playwright test --config qa-security-forum-cgu/playwright.config.mjs
  )
fi

echo "Concluído: $mode. PASS, FAIL e SKIP devem ser registrados individualmente."
