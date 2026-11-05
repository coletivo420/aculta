# Confirmação da API HTMX no Drupal Core 11.5

## Resultado

**F0 parcialmente verificada; BLOCKED para aceite completo.** A API está presente
no Core 11.5 usado no Homelab e passou testes oficiais do Core em MariaDB de
teste dedicado e Chromium headless. Falta uma prova de integração ACULTA que
abra um popup `<dialog>`, carregue nele um formulário Drupal via HTMX e o envie.
Não iniciar F1/F2/F3 antes dessa prova e do aceite integral de F0 (ADR-050).

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
  funcional acima demonstra um POST HTMX autenticado aceito pelo formulário de
  teste do Core, mas não é um teste negativo de token inválido.

## Limite e próximo passo

Essas provas não incluem um popup ACULTA em `<dialog>`, nem demonstram a
progressão completa de abrir o popup, obter o fragmento, enviar o formulário e
ver o resultado dentro do diálogo. Portanto F0 permanece bloqueada nesse
critério; I2, I3 e I4 aguardam F0. F4 e F5 continuam aguardando autorização
específica de Loja.
