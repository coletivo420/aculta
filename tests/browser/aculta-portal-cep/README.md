# ACULTA Portal CEP — Firefox com Olivero

Smoke autenticado de **Minha conta → Endereço** usando o tema Core Olivero.
Confirma o tema realmente carregado, os campos de endereço, o preenchimento
assíncrono do CEP, o bloqueio dos campos preenchidos e o reflow em 1280, 390 e
320 px. Os PNGs vão para `SUITE_OUT`; a suíte não submete o formulário.

## Ambiente e pré-requisitos

- Servidor de desenvolvimento `conta.aculta.toca.net.br`, checkout
  `/home/piradopirata/projetos/aculta`, Runtime `aculta_runtime`.
- Firefox ESR visível com `DISPLAY=:0` e geckodriver. O perfil temporário fica
  sob `SUITE_OUT/profiles` e é removido por `wdlib.py`.
- Uma conta descartável autenticada e um link ULI de uso único, salvo em
  `$SUITE_OUT/uli.txt`. Não imprima nem versione esse link.
- `olivero` precisa estar habilitado durante o teste. Como o teste altera o
  tema padrão e habilita Olivero temporariamente, restaure a configuração no
  mesmo shell com `trap`:

```sh
set -eu
DRUSH='/home/piradopirata/projetos/aculta/vendor/drush/drush/drush.php'
URI='https://conta.aculta.toca.net.br'
ORIGINAL_THEME="$(php "$DRUSH" -l "$URI" config:get system.theme default --format=string)"
OLIVERO_ENABLED="$(php "$DRUSH" -l "$URI" config:get core.extension theme.olivero --format=string)"
restore_theme() {
  php "$DRUSH" -l "$URI" config:set system.theme default "$ORIGINAL_THEME" -y >/dev/null
  if [ "$OLIVERO_ENABLED" = '0' ]; then
    php "$DRUSH" -l "$URI" theme:uninstall olivero -y >/dev/null
  fi
  php "$DRUSH" -l "$URI" cr >/dev/null
}
trap restore_theme EXIT
php "$DRUSH" -l "$URI" theme:enable olivero -y >/dev/null
php "$DRUSH" -l "$URI" config:set system.theme default olivero -y >/dev/null
php "$DRUSH" -l "$URI" cr >/dev/null
DISPLAY=:0 SUITE_OUT="$SUITE_OUT" ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles" \
  python3 -I tests/browser/aculta-portal-cep/olivero.py "$SUITE_OUT/uli.txt"
```

The account is a fixture: remove the temporary account and its `authenticated`
Commerce profile after the run. This smoke edits no saved address and does not
alter Config Sync. It covers the account page only; the separate existing
`scripts/test-account-address-browser.py` exercises address submission under
ACULTA420.

## Maps/Events — assistente OSM (somente leitura)

`maps-events-address.py` abre `/integracoes/openstreetmap/importar` com Firefox
ESR visível, consulta por leitura o elemento OSM configurado, avança até a
revisão do endereço e verifica o widget central, país BR oculto, biblioteca CEP,
autopreenchimento e reflow em 320/390/768/1440 px. O teste não clica em
“Confirmar e salvar”, não salva entidade e não envia alterações à API OSM.
POSTs Drupal limitam-se às etapas de busca/seleção/revisão; a consulta de CEP é
AJAX de leitura. Capturas mascaram o texto de inputs e vão para `SUITE_OUT`.

Ambiente: servidor de desenvolvimento `eventos.aculta.toca.net.br`, Runtime
`aculta_runtime`, com checkout no HEAD da PR. Requer permissão `administer
aculta events`, `DISPLAY=:0`, Firefox ESR, geckodriver, a conta de teste
indicada por `OSM_ADDRESS_TEST_UID` e um elemento OSM consultável pela API ativa.
O link ULI é gerado pelo Drush para cada execução e nunca é gravado no repositório.

Variáveis: `SUITE_OUT` (obrigatória), `ACULTA_PROJECT_ROOT` (raiz do checkout),
`OSM_ADDRESS_BASE_URL`, `OSM_ADDRESS_DRUSH_URI`, `OSM_ADDRESS_TEST_UID`,
`OSM_ADDRESS_TEST_TYPE` e `OSM_ADDRESS_TEST_ID`. Execução:

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT" ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
  python3 -I tests/browser/aculta-portal-cep/maps-events-address.py
```

Limites: valida o assistente de importação OSM, não um formulário de criação
direta/edição de local nem um formulário próprio de `aculta_maps`. Também não
altera ou valida o armazenamento de entidades.
