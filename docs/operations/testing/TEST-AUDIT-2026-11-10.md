# Auditoria da infraestrutura de testes — 2026-11-10

Snapshot inicial: `724a12317`. Branch de publicação: `dev`. Este documento
registra o que foi inventariado, classificado, reescrito, consolidado e
aposentado, com evidência no código e no Git. Ele é um recorte verificável, não
uma declaração de que toda a dívida foi zerada.

## 1. Método

- Inventário gerado por `scripts/audit-custom-tests.php` (somente leitura, sem
  executar os testes), gravado em `docs/operations/testing/test-catalog.json`.
- Descoberta e execução pelo `phpunit.xml` gerado por
  `scripts/phpunit-suites.php`, com guarda `--check` contra teste fora de suíte
  e suíte obrigatória vazia.
- Cada remoção exige evidência de que a classe/arquivo não tem consumidor de
  produção. Nomes `Legacy`, `Migration`, `Contract` ou `Characterization` não
  são motivo suficiente.
- Kernel e Functional exigem `SIMPLETEST_DB`; o banco dedicado
  `aculta_simpletest` foi carregado de `secrets/simpletest-db.env` via `sudo`
  apenas como variável de ambiente, sem gravar segredo em arquivo do repositório.

## 2. Inventário

| Tipo | Antes (724a12317) | Catálogo atual |
| --- | ---: | ---: |
| Unit | 652 | 669 |
| Kernel | 240 | 249 |
| Functional | 3 | 3 |
| FunctionalJavascript | 1 | 1 |
| Performance | 4 | 4 |
| Integration (aninhados) | 24 | classificados como Unit/Kernel |
| `*Test.php` totais | 927 | 929 |

O total cresceu porque outro agente adicionou testes de `site_key` em paralelo.
Minhas remoções estão na seção 4; o saldo não é indicador de qualidade.

Heurísticas no catálogo: `zero_assertions` = 0, `counts_literal_array` = 0,
`same_value_both_sides` = 0, `file_exists_only` = 0. Continuam sinalizados para
revisão humana `mock_isolated` (444), `static_config_contract` (37),
`unconditional_skip` (16) e `review_by_name` (24). `doc_comment_metadata` foi
zerado ao converter os 15 arquivos para atributos PHPUnit.
Nenhum desses sinais, isolado, autoriza remoção.

## 3. Infraestrutura PHPUnit

- `phpunit.xml` não declarava `<testsuites>`; qualquer execução sem caminho não
  descobria nada. Pior: testes em `tests/src/Integration/<domínio>/Kernel` e
  `.../Unit` não são alcançados por uma suite `*/tests/src/Kernel`.
- `scripts/phpunit-suites.php --write` emite um `<directory>` explícito por
  diretório (mínimo, porque `<directory>` é recursivo) e
  `--check` falha se um `*Test.php` ficar fora, se uma suíte obrigatória
  (`unit`, `kernel`, `functional`, `functionaljs`) esvaziar ou se o XML
  comitado divergir da árvore.
- Benchmarks ficam em `phpunit.performance.xml`, fora da rotina comum.
- Os 3 `*Test.php` em `aculta_messenger/tests/fixtures/` são fixtures de
  subprocesso declaradas como não descobertas; permanecem fora das suítes.

## 4. Classificação e destino

| Teste original | Decisão | Evidência | Substituto | Commit |
| --- | --- | --- | --- | --- |
| `aculta_portal/.../MultidomainCrossCacheTest` | APOSENTAR + REESCREVER | `assertCount(10, [...])` sobre array do próprio teste | `MultidomainRouteIsolationTest` (8 testes/22 asserções) exercita `DomainRoutePolicy`, usada por `DomainPurposeRequestSubscriber` | `65dce055d` |
| `aculta_index/.../ContentIndexProviderServiceMapTest` | REESCREVER | lia `aculta_lms_resources` inexistente e omitia `aculta_events` e `file` | descoberta dinâmica dos `*.services.yml` + container real; 16 providers e ownership único | `d5058d577` |
| `aculta_image/.../QuarantineRulesTest` | APOSENTAR | `aculta_image\Quarantine\QuarantineRules` só era usado por si; `ImageQuarantine` passa a usar a do `aculta_file_uploader` (API e 30 dias idênticos) | teste do `aculta_file_uploader` | `aa3e5cc85` |
| `aculta_image/.../ImageCleanupCoordinatorTest` | APOSENTAR | `aculta_image\ImageCleanup` sem consumidor; forms/hooks usam `aculta_file_uploader\Cleanup\ImageCleanup`, que também cobre documentos | teste do `aculta_file_uploader` | `aa3e5cc85` |
| `aculta_wiki/.../WikiDiscussionContextProviderTest` | APOSENTAR | classe do Wiki sem registro; o dono é o Fórum, registrado no tagged iterator `aculta_forum.discussion_context_provider` | `aculta_forum/.../WikiDiscussionContextProviderTest` | `aa3e5cc85` |
| `aculta_studio/.../MarleyTabScopeTest` | REESCREVER | afirmava nomes de arquivo | lê o atributo `#[MarleyTab]` consumido por `MarleyTabManager` e barra `wiki`/`lms` | `7f6587d0e` |
| `aculta_studio/.../GanjalabServiceWiringTest` | REESCREVER | comparava strings do YAML | compila a definição comitada e exercita 404 (ausente/indisponível) e 403 (acesso negado) | `7f6587d0e` |
| `aculta_studio/.../RevistaMyWorkContractTest` | REESCREVER | `str_contains` no fonte de `RevistaTab`/`MarleyListingBuilder` | `isAvailable()`, `access()` com cache contexts/tags e `sections()` (escopo `mine`, criação como ação, sidebar suprimida, moderação só para moderador) | `7f6587d0e` |
| 4 benchmarks de `aculta_index` | MOVER/CORRIGIR | opt-in era checado depois de `parent::setUp()` instalar o banco | guarda no início do `setUp()`; sem `SIMPLETEST_DB` pulam em ~3 s; isolados em `phpunit.performance.xml` | `940a1c034` |
| `scripts/tests/validate-aculta420-design-foundations-test.php` | REESCREVER | copiava `css/tokens.css` e mutava valores do tema V1 inexistente | 14 fixtures V2: baseline, literais de cor, seletor de modo, branch Twig, parser flat e segurança de caminho | `a29e8e08b` |
| `validate-aculta420-shell-contract.php` + teste + `Aculta420ShellContractAnalyzer` | APOSENTAR | analisavam `ThemeHooks.php`/`templates/page.html.twig` do V1 e instanciavam `Drupal\aculta420\Hook\ThemeHooks` | invariantes de vazamento portados para `validate-module-theme-boundary.php`; shell V2 coberto por `shell-regions` e `foundation` | `a29e8e08b` |
| `aculta_lms/.../PersonalCalendarTokensTest` | REESCREVER | `hash('abc') === hash('abc')` | formato sha256, token não fica em claro, entradas distintas geram hashes distintos | `7a48c8dd3` |
| `aculta_portal/.../AccountOtherDataFormKernelTest` | CORRIGIR | linha órfã `'aculta_file_uploader'` no data provider entregava string | provider válido; 6 testes/30 asserções PASS | `7a48c8dd3` |
| 4 testes Unit com `\Drupal::`, `LoginValidatorSwapTest` e fixture do `PdfViewerBuilder` | CORRIGIR | container ausente, `AddToCartAjax` passou a exigir `RequestStack`, botão migrou para `aculta_portal_button` | container mínimo de `string_translation`/`url_generator`; asserção do botão atualizada; fixture regenerado | `ee057a691` |

### Mantidos com justificativa

- `aculta_wiki/.../WikiSearchIndexConfigTest`: Search API está habilitado
  (`core.extension.yml`), o índice `aculta_conteudo` existe e
  `SearchPageController::INDEX_ID` o consome. É contrato de configuração ativo,
  não resíduo.
- `aculta_forum/.../ForumTopicDisplayConfigTest` (709 linhas): cobre contratos
  de configuração do Fórum; separar responsabilidades é desejável, mas a
  divisão não foi feita nesta rodada para não misturar refatoração de teste com
  mudança de contrato.
- Testes de pagamento/checkout/Mercado Pago, acesso, ownership e moderação:
  preservados integralmente.

### `aculta_activity` (Fase 8)

Classificação: **em migração**. Evidência: o módulo ainda tem provider de índice
e hooks, o padrão `pathauto.pattern.aculta_activity.yml` está no Config Sync e o
bundle `activity` está no índice de busca; ao mesmo tempo, `aculta_events`
contém `ActivityRetirementHooks`, `ActivityRetiredRouteSubscriber` e
`ActivityLocationMigrator`. Não há evidência suficiente para remover o módulo
sem plano de migração de instalações existentes, então ele permanece.

## 5. Descoberta e CI

- `scripts/phpunit-suites.php --check` falha em regressão de descoberta.
- `.github/workflows/php-quality.yml` passa a rodar em `dev` e `main`, com jobs
  `test-discovery` e `phpunit-unit` (JUnit como artefato). O job Unit não usa
  banco.
- PHPStan não usa mais `|| true`. `scripts/phpstan-ratchet.php` lê
  `phpstan-baseline-count.txt` (3829 erros no snapshot) e falha apenas se a
  contagem crescer; a dívida existente é reduzida aos poucos, sem baseline de
  megabytes e sem mascarar regressão.

## 6. Execução verificável

| Verificação | Resultado |
| --- | --- |
| `php scripts/phpunit-suites.php --check` | PASS — unit=669, kernel=249, functional=3, functionaljs=1, performance=4 |
| Suíte Unit completa (excluindo apenas os dois arquivos em renomeação concorrente) | **PASS — 3271 testes, 17535 asserções, 0 erros, 0 falhas, 0 deprecations** |
| `phpunit --testsuite kernel --filter AccountOtherDataFormKernelTest` (com `SIMPLETEST_DB`) | PASS — 6 testes, 30 asserções |
| `phpunit --filter FaqEditorialSafetyKernelTest` (com `SIMPLETEST_DB`) | PASS — 3 testes, 45 asserções |
| `phpunit -c phpunit.performance.xml` sem banco | PASS — 4 pulados em ~3 s |
| `phpunit` nos 15 arquivos convertidos de doc-comment | PASS — 48 testes, 269 asserções |
| `php scripts/phpstan-ratchet.php` | PASS — 3829 erros ≤ baseline 3829 |
| `php scripts/tests/validate-aculta420-design-foundations-test.php` | PASS — 14 fixtures |
| `php scripts/validate-module-theme-boundary.php` | PASS — 7884 verificações |
| `php scripts/audit-browser-scripts.php` | 535 arquivos classificados; 9 entrypoints, 49 contratos de rota, 171 scripts, 225 evidências, 18 candidatos a revisão |
| `php scripts/validate-aculta420-design-foundations.php` (tema real) | FAIL pré-existente — 5/1271: literais de cor em `ui-library-admin.css`, `surface.css`, `surface-accent.css`, `surface-panel.css` (arquivos não alterados nesta rodada) |

## 6.1. Browser

`docs/operations/testing/browser-inventory.json` classifica cada arquivo de
`tests/browser/` por purpose/front, papel, consumidores e destino. O executor
Playwright continua sendo apenas `playwright.routes.spec.mjs` (contratos
`routes.json`) e `playwright-runner.mjs` (smoke por URL); os scripts Python de
revisão manual não entram na suíte executável. Os 18 scripts sem consumidor
referenciado estão marcados como `revisar: candidato a arquivo` no inventário,
para revisão humana — não foram apagados porque podem ser evidência.

## 7. Pendências

| ID | Situação | Descrição | Próxima ação |
| --- | --- | --- | --- |
| P2 | aberto | `validate-aculta420-design-foundations.php` falha no tema real (5/1271) por literais de cor em CSS existente | Substituir literais por tokens semânticos ou justificar exceção |
| P6 | aberto | `ForumTopicDisplayConfigTest` com 709 linhas e múltiplas responsabilidades (form/display, listagens, admin, domain/pathauto) | Separar em classes por responsabilidade com uma base compartilhada, mantendo a suíte verde |
| P7 | aberto | Dois arquivos `ContentPurposeResolver*Test` em renomeação concorrente para `ContentSiteKeyResolver*Test` ainda coexistem na árvore de trabalho | Concluir a renomeação do outro agente; a suíte completa já passa excluindo o par duplicado |
| P8 | aberto | 18 scripts Python/MJS do browser sem consumidor referenciado | Revisar caso a caso; arquivar ou converter em entrypoint documentado |

## 8. Recuperação

Arquivos removidos estão no histórico Git e podem ser restaurados com
`git log --diff-filter=D -- <caminho>` e `git checkout <commit>^ -- <caminho>`.
O WIP concorrente (UI Skins, remoção de `drupal/gin`) foi isolado em
`stash@{0}` com a mensagem
`wip-preservado-2026-11-10-site-key-ui-skins` antes do fast-forward para
`origin/dev`.
