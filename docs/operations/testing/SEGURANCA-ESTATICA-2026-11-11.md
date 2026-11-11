# Segurança estática: triagem e resultados (2026-11-11)

Skill responsável: `aculta-seguranca-estatica`. Base: `origin/dev` em `9d06d2167`, com entregas `87bec2be4` e `257adfb03`.

Relatórios brutos (JSON do Semgrep, saída do `composer audit`, gitleaks) ficam em `SUITE_OUT`, fora do repositório. Nenhum valor de segredo foi impresso nem versionado.

## 1. Semgrep com regras locais

- Comando: `semgrep --metrics=off --config .agents/skills/aculta-seguranca-estatica/rules/aculta-php.yml` com exclusões de `vendor`, `web/core`, `web/modules/contrib`, `web/libraries` e `node_modules`, sobre `web/modules/custom`, `web/themes/custom` e `scripts`.
- Resultado: **474 achados** em 118 arquivos.

| Regra | Achados | Classe |
|---|---:|---|
| `aculta-php-static-drupal-service` (`\Drupal::` estático) | 432 | dívida de arquitetura, não de segurança |
| `aculta-php-markup-raw-output` | 31 | triados: 26 FALSO POSITIVO, 1 REAL (corrigido), 4 PENDENTE |
| `aculta-php-eval-or-dynamic-exec` | 10 | 9 ACEITO COM JUSTIFICATIVA, 1 PENDENTE |
| `aculta-php-unserialize-untrusted` | 1 | FALSO POSITIVO (com mitigação) |
| `aculta-php-raw-sql-concat` | 0 | sem achado; coberta por fixture |
| `aculta-php-superglobal-input` | 0 | sem achado; coberta por fixture |
| `aculta-php-raw-redirect-cross-host` | 0 | sem achado após correção da regra (ver 1.3) |

Não há comparação direta com a baseline de 127 achados de 2026-11-05: o conjunto de regras mudou e a baseline não tinha triagem.

### 1.1 Execução dinâmica (10)

| Arquivo:linha | Sink | Classe | Justificativa |
|---|---|---|---|
| `scripts/validate-aculta420-layouts.php:29` | `passthru` | ACEITO COM JUSTIFICATIVA | script de validação de dev; `escapeshellarg`; comando fixo |
| `scripts/validate-aculta420-layout-builder-batch.php:34` | `passthru` | ACEITO COM JUSTIFICATIVA | mesmo padrão |
| `scripts/validate-aculta-ui-library.php:30` | `passthru` | ACEITO COM JUSTIFICATIVA | mesmo padrão |
| `scripts/validate-aculta420-foundation.php:31` | `passthru` | ACEITO COM JUSTIFICATIVA | mesmo padrão |
| `scripts/validate-browser-index.php:34` | `exec` | ACEITO COM JUSTIFICATIVA | comando fixo, caminho com `escapeshellarg` |
| `scripts/validate-domain-front-paths.php:62` | `shell_exec` | ACEITO COM JUSTIFICATIVA | `escapeshellarg`; lista interna de domínios |
| `scripts/validate-agent-economy-paths.php:57` | `shell_exec` | ACEITO COM JUSTIFICATIVA | `escapeshellarg`; leitura de tamanho |
| `scripts/validate-aculta-portal-drupal12.php:950` | `exec` | ACEITO COM JUSTIFICATIVA | `escapeshellarg` em `PHP_BINARY` e caminho do validador |
| `scripts/validate-session-cookie-policy.php:29` | `exec` | ACEITO COM JUSTIFICATIVA | comando fixo com `escapeshellarg` |
| `aculta_deployer/src/Cli.php:1539` | `proc_open` (array, sem shell) | PENDENTE | falta provar que `$cmd` vem só de configuração do operador da CLI |

### 1.2 `#markup` sem escape (31, triados todos)

**FALSO POSITIVO (17):** o valor passa por `$this->t()`, `translate()`, `formatPlural()`, `htmlspecialchars` (`plainText`), formatação numérica/de data, ou é ternário de `t()`.

| Arquivo:linha | Motivo |
|---|---|
| `aculta_donation/.../DonationSummaryFormatter.php:74` | valor monetário formatado a partir de `PriceItem` (conferir retorno de `viewValue` se houver mudança) |
| `aculta_events/.../EventContributionWorkspaceController.php:124,129,156` | `plainText()` = `htmlspecialchars(ENT_QUOTES)` |
| `aculta_events/.../EventOrganizationRecoveryForm.php:95` | ternário de `$this->t()` |
| `aculta_events/.../DateRangeUpcomingFormatter.php:93` | datas formatadas pelo próprio `texto()` |
| `aculta_events_openstreetmap/.../OpenStreetMapStatusController.php:44,49` | ternário de `$this->t()` |
| `aculta_forum/.../MoveForumTopicsAction.php:98` | `formatPlural()` |
| `aculta_lms/.../EnrollmentFormHooks.php:173,190` | `translate()` com texto fixo |
| `aculta_portal/src/Form/EmailResendForm.php:56` | `translate()` com texto fixo |
| `aculta_portal/src/Plugin/Commerce/CheckoutPane/FiscalIdentityPane.php:103,108` | `unavailableMessage()` retorna `$this->t()` |
| `aculta_studio/.../SavedContentController.php:220` | `$this->t()` para data; `purpose` com `htmlspecialchars` |
| `aculta_lms/.../FreeTextAnswerPluginBase.php:98` | `Html::escape()` aplicado a cada mensagem antes do `#markup` |
| `aculta_mailer/.../MailImapSettingsForm.php:131` | `errorText()` passa `error_class` pelo placeholder `@error` de `$this->t()`, que escapa |

**PENDENTE (4) após a rodada final:** a origem da variável ou o tipo do campo não foram provados nesta triagem. Risco de XSS só se o dado tiver origem não confiável ou HTML permitido.

| Arquivo:linha | O que falta provar | Prioridade |
|---|---|---|
| `aculta_messenger/.../MessagesController.php:1528` | `$site_alert->getMessage()` é renderizado como markup. A entidade vem do módulo contrib `site_alert`, ausente desta worktree; confirmar se o campo `message` aceita HTML e quem edita. Se aceitar, é risco de XSS por administrador | alta |
| `aculta_mailer/.../MailTemplateForm.php:136` | `$preview['body']` é corpo de template de e-mail editado por administrador | média |
| `aculta_events_openstreetmap/.../OpenStreetMapImportWizard.php:189` | `lat`/`lon` do formulário; confirmar validação numérica | baixa |
| `aculta_wiki/.../WikiEntryImageHooks.php:147` | ramo com `$current['alt']`: texto de alt de imagem, possivelmente editado por usuário | média |

Reclassificados na rodada final (2026-11-11, após leitura das origens):
- FALSO POSITIVO: `EventPageController.php:137` (base escapada com `Html::escape`); `LocationListController.php:78` (rótulo por `Link`, endereço escapado); `LocationPageController.php:352` (`Link` e `Html::escape`); `EventEditorialPresenter.php:68` (`htmlspecialchars` em nome e cota); `OpenStreetMapStatusController.php:37` (ramo é `$this->t()`); `ImageSettingsForm.php:85` (rótulos vêm de mapa de código); `SurfaceEditForm.php:131` (hostname de entidade Domain); `PaymentInformationWithoutAddress.php:25` (rótulo de plugin de código); `TopicDeletionQueueController.php:80` (ramo com nó usa `Link`; sem nó usa `t()`).
- REAL, corrigido: `aculta_lms/.../ProximosEventosBlock.php:83`. O título do evento era concatenado sem escape no `#markup`. Corrigido com `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

### 1.3 Correção de regra quebrada

- `aculta-php-raw-redirect-cross-host` usava `pattern:` com o nome totalmente qualificado de `RedirectResponse`. Esse padrão não casa no parser PHP do Semgrep. Verificado com fixture isolada.
- Trocado por `pattern-regex`, com caso positivo e negativo em `tests/rules/aculta-php.php`. O zero achado anterior não provava ausência do problema.

### 1.4 Fixtures das regras

- Arquivo: `.agents/skills/aculta-seguranca-estatica/tests/rules/aculta-php.php`. Verificador: `tests/check-rule-fixtures.py`.
- Resultado: **17 expectativas, 0 falhas**.
- `semgrep --test` nativo falhou com `IndexError` interno do harness; o verificador próprio é o gate.
- O mesmo verificador roda no CI (`quality-static.yml`, job `security-static`).

## 2. Dependências

### 2.1 `composer audit --locked` (antes da correção)

3 advisories em 2 pacotes:

| Pacote | Versão travada | Advisory | Severidade | Consumidores | Correção aplicada |
|---|---|---|---|---|---|
| `drupal/entity_print` | 2.19.0 | CVE-2026-126736 (inserção de informação sensível). Título: "Moderately critical" | moderada | módulos que usam Entity Print (`composer.json`, `config/sync`) | **2.20.0** |
| `symfony/http-client` | v7.4.22 | CVE-2026-121971 (credenciais do escopo padrão enviadas a outras origens) | não informada no texto obtido | transitiva (Drupal core e componentes) | **v7.4.23** |
| `symfony/http-client` | v7.4.22 | CVE-2026-123119 (NoPrivateNetworkHttpClient, DNS rebinding) | não informada | mesmo | **v7.4.23** |

Atualização feita com `composer update drupal/entity_print symfony/http-client --minimal-changes --no-install`, autorizada pelo responsável. Diff do lock: 2 pacotes atualizados, nenhum removido. `composer.json` não foi alterado.

**Após a correção:** `composer audit --locked` → "No security vulnerability advisories found." (exit 0).

**Pendente de verificação:** o lock foi alterado, mas a aplicação em Runtime (`composer install` + `updatedb` se houver mudança de schema) segue a regra de migrações; não rodei nada no Runtime.

### 2.2 `npm audit` (`tests/browser`, dev e prod)

0 vulnerabilidades.

## 3. Segredos (gitleaks 8.30.1)

- Instalado do release oficial `gitleaks_8.30.1_linux_x64.tar.gz`, com SHA256 verificado contra o `checksums.txt` publicado.
- Escaneado: arquivos desta entrega (`.agents`, `docs/operations/testing`, `.github/workflows`, `tests/browser`, `web/themes/custom/aculta420_v2/src/styles/content.css`, `docs/operations/GATES.md`).
- Resultado: **4 achados `generic-api-key`** fora dos arquivos desta entrega:
  - `docs/portal/STUDIO-MAGAZINE-ROADMAP.md:171`
  - `web/libraries/photoswipe/demo-docs-website/src/theme/Navbar/index.js:263` (biblioteca de terceiros)
  - `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/XapiAttemptTokenTest.php:17` (fixture de teste)
  - `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/tests/src/Unit/SecretsRevealTest.php:50` (fixture de teste)
- Classificação:
  - `docs/portal/STUDIO-MAGAZINE-ROADMAP.md:171`: FALSO POSITIVO. Texto de roadmap em prosa, sem atribuição de credencial.
  - `web/libraries/photoswipe/...`: FALSO POSITIVO de escopo. Biblioteca de terceiros, fora do código do projeto.
  - `SecretsRevealTest.php:50`: FALSO POSITIVO. Constante `FAKE` de fixture de teste.
  - `XapiAttemptTokenTest.php:17`: PENDENTE. Duas constantes de teste com forma de fixture (32 caracteres minúsculos e dígitos; um UUID). Não comparei com segredos reais do cofre do Runtime, o que não faço. Se algum valor estiver em uso real, é incidente e exige rotação, decisão do responsável.
  Valores não impressos.
- O scan de histórico Git (`gitleaks git`) foi recusado pelo guarda de worktree e **não foi executado**.

## 4. Drupal Security Review (`drupal/security_review`)

- Declarado em `composer.json` (`^3.1`), travado em 3.1.3, habilitado no Config Sync.
- Execução: **DEFERRED**. Não há `vendor/` nem Drush consultável nesta worktree.

## 5. Resumo

| Item | Resultado |
|---|---|
| Semgrep local (474 achados) | triagem: `#markup` 31/31 (26 FP, 1 REAL corrigido, 4 PENDENTE); dinâmicos 10/10 (9 ACEITO, 1 PENDENTE); unserialize 1/1 FP |
| Fixtures das regras | PASS: 17/17 |
| `composer audit --locked` | antes: 3 advisories; depois: 0 (após atualização autorizada) |
| `npm audit` | 0 vulnerabilidades |
| gitleaks (arquivos da entrega) | executado: 4 achados classificados (3 FP, 1 PENDENTE); histórico não executado |
| Drupal Security Review | DEFERRED (sem Drush/Runtime) |

## 6. Próximas ações

1. Triar os 4 `#markup` PENDENTE: `MessagesController:1528` (depende do módulo contrib `site_alert`), `OpenStreetMapImportWizard:189` (dado de API externa), `MailTemplateForm:136` (corpo de template de administrador) e `WikiEntryImageHooks:147` (texto de alt).
2. Confirmar `Cli.php:1539` (origem de `$cmd`) e os valores de `XapiAttemptTokenTest.php:17` (fixture ou segredo real).
3. Aplicar `entity_print` 2.20.0 e `symfony/http-client` 7.4.23 no Runtime por fluxo controlado, e registrar o resultado.
4. Executar `drupal/security_review` com Drush em modo somente leitura quando houver acesso ao Runtime.
