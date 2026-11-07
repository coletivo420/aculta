# Confirmação da API HTMX no Drupal Core 11.5

## Resultado

**F0 concluída.** A API está presente no Core 11.5 usado no Homelab; os testes
oficiais do Core e a fixture ACULTA de diálogo/formulário passaram no banco
dedicado e em duas engines de navegador. Token CSRF válido foi aceito; token
adulterado foi rejeitado. Nenhum código de produção foi migrado.

## Inspeção do Core

- `web/core/lib/Drupal/Core/Htmx/Htmx.php` declara `Drupal\Core\Htmx\Htmx`, sem
  anotação `@internal`. O docblock descreve a classe como API para aplicar os
  controles HTMX a render arrays e contém exemplos de uso.
- `web/core/core.libraries.yml` declara `core/drupal.htmx`, dependente de
  `core/htmx`, `core/drupal`, `core/drupalSettings` e `core/loadjs`.
- `Htmx::onlyMainContent()` cria o atributo booleano
  `data-hx-drupal-only-main-content`. O teste Kernel oficial
  `web/core/tests/Drupal/KernelTests/Core/Htmx/HtmxRequestTest.php` verifica esse
  nome literalmente.
- `FormBuilder::rebuildForm()` detecta o cabeçalho HTMX e, quando o alvo não
  contém o formulário que iniciou a requisição, atualiza o `form_build_id` por
  meio de `swapOob`. A geração do token CSRF de sessão para formulários de
  usuários autenticados continua no fluxo normal do Form API. Formulários
  anônimos não recebem token de sessão, conforme a regra de cache do Core.

## Provas executadas

Ambas usaram `SIMPLETEST_DB` carregado de `secrets/simpletest-db.env`; nenhum
teste foi conectado ao banco Runtime. ChromeDriver e Chromium headless foram
usados como motor complementar, segundo `tests/browser/README.md` e
`docs/operations/NAVEGADOR-TESTES.md`.

- FunctionalJavascript do Core
  `Drupal\FunctionalJavascriptTests\Core\Htmx\HtmxDynamicFormTest`: **1 teste,
  19 assertions PASS**. Executou em sessão autenticada, fez POST HTMX do
  formulário dinâmico e confirmou a mudança de `form_build_id`. PHPUnit reportou
  55 depreciações preexistentes de Core/contrib; nenhuma falha. Os HTMLs de
  diagnóstico foram mantidos fora do repositório em `$SUITE_OUT`.
- Kernel do Core `Drupal\KernelTests\Core\Htmx\HtmxRequestTest`: **5 testes,
  60 assertions PASS**, incluindo o atributo `data-hx-drupal-only-main-content`.
  PHPUnit informou que o diretório padrão de HTML de browser tests não era
  gravável; este gate Kernel não gera captura de navegador.
- Inspeção do código confirmou o caminho de validação CSRF do Form API. A prova
  oficial anterior demonstra o POST HTMX autenticado do formulário dinâmico do
  Core. A fixture ACULTA `web/modules/custom/aculta_portal/tests/src/FunctionalJavascript/HtmxF0DialogTest.php`
  exercita a progressão completa: abrir `<dialog>`, carregar por `Htmx::get()`
  o formulário autenticado, enviar por `Htmx::post()` e confirmar resultado no
  diálogo. Com token adulterado, o Core exibe a mensagem de formulário
  desatualizado e não executa o submit handler.
- A fixture passou em Firefox headless: **1 teste, 7 assertions, 54
  depreciações**; e em Chromium headless: **1 teste, 7 assertions, 55
  depreciações**. Sem falhas. Versões nesta sessão: Firefox ESR
  153.5.1/geckodriver 0.37.1 e Chromium/ChromeDriver 156.0.8078.79. HTMLs de
  diagnóstico foram mantidos em `$SUITE_OUT`, fora do repositório.

## Reexecução humana/agente

A fixture é um módulo de teste descoberto somente durante testes Drupal; não está
habilitado no site. Rode da raiz do repositório, com o banco dedicado e com
`SIMPLETEST_BASE_URL` configurada para o servidor de desenvolvimento documentado. Inicie o
driver escolhido e configure `MINK_DRIVER_ARGS_WEBDRIVER` conforme
`web/core/phpunit.xml.dist`:

```sh
set -a && . secrets/simpletest-db.env && set +a
export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:?configure a URL de teste documentada}"
export BROWSERTEST_OUTPUT_DIRECTORY="${SUITE_OUT:?configure saída persistente fora do repositório}/htmx-f0"
export MINK_DRIVER_ARGS_WEBDRIVER="${MINK_DRIVER_ARGS_WEBDRIVER:?configure Firefox ou Chromium e o endpoint do driver}"
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/tests/src/FunctionalJavascript/HtmxF0DialogTest.php
```

O browser/driver pode ser Firefox ESR/geckodriver ou Chrome/Chromium headless
com driver compatível. Registre browser, versão, saída e depreciações. A fixture
não habilita módulo no Runtime, não altera `config/sync` nem dados do site; o
teste usa `aculta_simpletest`.

O Core grava os HTMLs de diagnóstico do FunctionalJavascript em
`web/sites/simpletest/browser_output/` durante a execução, mesmo quando
`BROWSERTEST_OUTPUT_DIRECTORY` está configurado no processo inicial. Mova os
arquivos recém-gerados `Drupal_Tests_aculta_portal_FunctionalJavascript_HtmxF0DialogTest-*`
para `$SUITE_OUT/htmx-f0/` após o teste e não os deixe no docroot; podem conter
tokens de sessão da conta sintética de teste.

## Limite e próximo passo

F0 está concluída e permite iniciar F1; F2 depende de F1a, e F3 segue a ordem de
módulos do ADR-050. F4 e F5 continuam aguardando autorização específica de Loja.
