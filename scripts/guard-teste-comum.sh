#!/usr/bin/env bash
# Guardrail: confere se a senha das contas de teste continua a registrada em secrets/teste_comum.env.
# Confere a conta nomeada no próprio registro (TESTE_COMUM_USER) e a conta legada
# "teste.comum" usada pelos scripts que ainda não leem o registro.
# Não altera nada. Sai com código 1 se alguma senha foi trocada ou se o registro está ausente.
set -euo pipefail
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
ARQ="$RAIZ/secrets/teste_comum.env"
DRUSH="$RAIZ/vendor/drush/drush/drush.php"
[ -f "$ARQ" ] || { echo "GUARD teste.comum: FAIL (registro ausente em secrets/teste_comum.env)"; exit 1; }
SENHA="$(sed -n 's/^TESTE_COMUM_PASSWORD=//p' "$ARQ")"
USUARIO="$(sed -n 's/^TESTE_COMUM_USER=//p' "$ARQ")"
[ -n "$SENHA" ] || { echo "GUARD teste.comum: FAIL (senha vazia no registro)"; exit 1; }
[ -n "$USUARIO" ] || { echo "GUARD teste.comum: FAIL (usuário vazio no registro)"; exit 1; }
RESULTADO="$(TESTE_COMUM_PW="$SENHA" TESTE_COMUM_NOME="$USUARIO" php "$DRUSH" php:eval '
$nomes = array_unique([getenv("TESTE_COMUM_NOME"), "teste.comum"]);
foreach ($nomes as $nome) {
  $contas = \Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name" => $nome]);
  $conta = reset($contas);
  if (!$conta) {
    echo $nome, "=ausente\n";
    continue;
  }
  echo $nome, "=", (\Drupal::service("password")->check(getenv("TESTE_COMUM_PW"), $conta->getPassword()) ? "sim" : "nao"), "\n";
}' 2>/dev/null)"
[ -n "$RESULTADO" ] || { echo "GUARD teste.comum: FAIL (não foi possível verificar as contas via Drush)"; exit 1; }
if printf '%s\n' "$RESULTADO" | grep -qv '=sim$'; then
  echo "GUARD teste.comum: FAIL (senha diferente do registro em: $(printf '%s\n' "$RESULTADO" | grep -v '=sim$' | paste -sd, -))"
  exit 1
fi
echo "GUARD teste.comum: PASS (senha confere com secrets/teste_comum.env para $(printf '%s\n' "$RESULTADO" | cut -d= -f1 | paste -sd, -))"
