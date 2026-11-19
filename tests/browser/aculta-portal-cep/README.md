# Testes do Portal CEP

O smoke dedicado que alterava o tema padrão foi removido. Valide o contrato do
formulário com o tema configurado e sem modificar o tema global. Para o fluxo de
endereço, consulte `tests/browser/aculta-portal-cep/test-account-address-browser.py`.

## Maps/Events — assistente OSM (somente leitura)

`maps-events-address.py` abre `/integracoes/openstreetmap/importar` com Firefox
ESR visível, consulta por leitura o elemento OSM configurado, avança até a
revisão do endereço e verifica o widget central, país BR oculto, biblioteca CEP,
autopreenchimento e reflow em 320/390/768/1440 px. O teste não clica em
“Confirmar e salvar”, não salva entidade e não envia alterações à API OSM.
POSTs Drupal limitam-se às etapas de busca/seleção/revisão; a consulta de CEP é
AJAX de leitura. Capturas mascaram o texto de inputs e vão para `SUITE_OUT`.

Ambiente: servidor de desenvolvimento, Runtime `aculta_runtime`, com checkout
no HEAD da PR. O ULI é gerado em `conta.aculta.toca.net.br`, purpose das rotas
de autenticação; o assistente abre em `aculta.toca.net.br`, pois o Portal
canonicaliza rotas administrativas para MAIN. Requer permissão `administer
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
