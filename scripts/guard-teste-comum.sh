#!/usr/bin/env bash
# Guardrail: confere se a senha do usuário de teste teste.comum continua a registrada em secrets/teste_comum.env.
# Não altera nada. Sai com código 1 se a senha foi trocada ou se o registro está ausente.
set -euo pipefail
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
ARQ="$RAIZ/secrets/teste_comum.env"
DRUSH="$RAIZ/vendor/drush/drush/drush.php"
[ -f "$ARQ" ] || { echo "GUARD teste.comum: FAIL (registro ausente em secrets/teste_comum.env)"; exit 1; }
SENHA="$(sed -n 's/^TESTE_COMUM_PASSWORD=//p' "$ARQ")"
[ -n "$SENHA" ] || { echo "GUARD teste.comum: FAIL (senha vazia no registro)"; exit 1; }
OK="$(TESTE_COMUM_PW="$SENHA" php "$DRUSH" php:eval '$u = \Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name" => "teste.comum"]); $u = reset($u); echo ($u && \Drupal::service("password")->check(getenv("TESTE_COMUM_PW"), $u->getPassword())) ? "sim" : "nao";' 2>/dev/null | tail -1)"
if [ "$OK" = "sim" ]; then echo "GUARD teste.comum: PASS (senha confere com secrets/teste_comum.env)"; else echo "GUARD teste.comum: FAIL (senha diferente do registro; não alterar sem decisão do responsável)"; exit 1; fi
