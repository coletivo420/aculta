# F0 — confirmação da API htmx no Drupal 11.5

Data: 2026-11-05  
Branch: `htmx/f0-confirmacao`  
Estado: `DRAFT BLOCKED`

## Fontes confirmadas

- `web/core/lib/Drupal/Core/Htmx/Htmx.php` declara `Drupal\Core\Htmx\Htmx` como
  classe pública, sem anotação `@internal`.
- A classe gera atributos `data-hx-*`. `Htmx::post()` e os demais builders
  escrevem atributos `data-hx-*` no render array.
- `Htmx::onlyMainContent()` gera `data-hx-drupal-only-main-content`, e não
  `hx-drupal-only-main-content` diretamente no HTML.
- `web/core/core.libraries.yml` define `core/drupal.htmx`, dependente de
  `core/htmx`, `core/drupal`, `core/drupalSettings` e `core/loadjs`.
- `FormBuilder` reconhece o cabeçalho `HX-Request` e, quando o alvo não é o
  formulário inteiro, usa `Htmx::swapOob('outerHTML:input[name="form_build_id"…')`
  para atualizar o `form_build_id` no formulário que originou a requisição.
- A proteção CSRF continua sendo aplicada pelo fluxo normal do Form API para
  usuários autenticados, com token baseado no formulário; a requisição htmx
  não cria uma exceção a esse fluxo.
- `HtmxRequestInfoTrait::isHtmxRequest()` identifica a requisição pela presença
  do cabeçalho `HX-Request`.
- O renderer `drupal_htmx` envolve o conteúdo em um documento mínimo com
  `noindex`; não é uma resposta HTML completa do tema.

## Verificações executadas

### PASS

```text
vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/core/tests/Drupal/Tests/Core/Htmx/HtmxAttributesTest.php \
  web/core/tests/Drupal/Tests/Core/Htmx/HtmxRequestInfoTest.php

OK (56 tests, 124 assertions)
```

### PASS adicional

Com `SIMPLETEST_DB` carregado do arquivo local de segredos, o teste Kernel
passou:

```text
vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/core/tests/Drupal/KernelTests/Core/Htmx/HtmxRequestTest.php

OK (5 tests, 60 assertions)
```

### DEFERRED / bloqueio

```text
vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/core/tests/Drupal/KernelTests/Core/Htmx/HtmxRequestTest.php

ERROR: There is no database connection. Provide SIMPLETEST_DB.
```

A prova mínima de navegador (Firefox ESR), com uma rota de fragmento e um
formulário dentro de `<dialog>`, ainda não foi executada. O teste Javascript do
Core foi tentado, mas requer `SIMPLETEST_BASE_URL` e não foi apontado para um
servidor de teste seguro nesta sessão. Nenhum código de produção foi alterado
nesta fase.

## Decisão de fase

F0 permanece `DRAFT BLOCKED` somente pela prova de navegador pendente. F1, F2 e
F3 não começam enquanto F0 não for validada.
