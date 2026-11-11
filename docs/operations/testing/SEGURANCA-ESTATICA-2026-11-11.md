# Segurança estática: triagem e resultados (2026-11-11)

Skill responsável: `aculta-seguranca-estatica`. Base: `origin/dev` em `9d06d2167`.

Os relatórios brutos (JSON do Semgrep e saída do `composer audit`) ficam em `SUITE_OUT`, fora do repositório. Nenhum segredo foi impresso nem versionado.

## 1. Semgrep com regras locais

- Comando: `semgrep --metrics=off --config .agents/skills/aculta-seguranca-estatica/rules/aculta-php.yml --exclude vendor --exclude web/core --exclude web/modules/contrib --exclude web/libraries --exclude node_modules` sobre `web/modules/custom`, `web/themes/custom` e `scripts`.
- Resultado: **474 achados** em 118 arquivos. A baseline anterior de 127 achados não tinha triagem, e a comparação direta não é válida porque o conjunto de regras mudou.

| Regra | Achados | Classe |
|---|---:|---|
| `aculta-php-static-drupal-service` (`\Drupal::` estático) | 432 | dívida de arquitetura, não de segurança |
| `aculta-php-markup-raw-output` | 31 | triagem parcial (ver seção 1.2) |
| `aculta-php-eval-or-dynamic-exec` | 10 | triados (seção 1.1) |
| `aculta-php-unserialize-untrusted` | 1 | FALSO POSITIVO com mitigação |
| `aculta-php-raw-sql-concat` | 0 | sem achado; coberta por fixture |
| `aculta-php-raw-redirect-cross-host` | 0 | sem achado após correção da regra (ver 1.3) |
| `aculta-php-superglobal-input` | 0 | sem achado; coberta por fixture |

### 1.1 Execução dinâmica (10)

| Arquivo | Sink | Classe | Justificativa |
|---|---|---|---|
| `scripts/validate-aculta420-layouts.php:29` | `passthru` | ACEITO COM JUSTIFICATIVA | script de validação de dev; caminhos com `escapeshellarg`; comando fixo |
| `scripts/validate-aculta420-layout-builder-batch.php:34` | `passthru` | ACEITO COM JUSTIFICATIVA | mesmo padrão |
| `scripts/validate-aculta-ui-library.php:30` | `passthru` | ACEITO COM JUSTIFICATIVA | mesmo padrão |
| `scripts/validate-aculta420-foundation.php:31` | `passthru` | ACEITO COM JUSTIFICATIVA | mesmo padrão |
| `scripts/validate-browser-index.php:34` | `exec` | ACEITO COM JUSTIFICATIVA | caminho com `escapeshellarg`; comando fixo `python3 gerar-indice.py --check` |
| `scripts/validate-domain-front-paths.php:62` | `shell_exec` | ACEITO COM JUSTIFICATIVA | `host` e `caminho` passam por `escapeshellarg`; origem dos valores é lista interna de domínios |
| `scripts/validate-agent-economy-paths.php:57` | `shell_exec` | ACEITO COM JUSTIFICATIVA | caminho com `escapeshellarg`; leitura de tamanho |
| `scripts/validate-aculta-portal-drupal12.php:950` | `exec` | ACEITO COM JUSTIFICATIVA | `escapeshellarg` em PHP_BINARY e no caminho do validador |
| `scripts/validate-session-cookie-policy.php:29` | `exec` | ACEITO COM JUSTIFICATIVA | comando fixo com `escapeshellarg` |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/src/Cli.php:1539` | `proc_open` (array, sem shell) | PENDENTE | não há shell; falta confirmar que `$cmd` vem só de configuração do operador (parâmetro de CLI), e não de dado de domínio |

### 1.2 `#markup` sem escape (31; triagem parcial)

Triados:

| Arquivo:linha | Classe | Motivo |
|---|---|---|
| `aculta_portal/src/Plugin/Commerce/CheckoutPane/FiscalIdentityPane.php:72` | FALSO POSITIVO | `$this->t()` escapa `@masked` |
| `aculta_studio/src/Controller/SavedContentController.php:220` | FALSO POSITIVO | `$this->t()` escapa a data; `purpose` passa por `htmlspecialchars` |
| `aculta_portal/src/Form/EmailResendForm.php:56` | FALSO POSITIVO | texto fixo traduzido |
| `aculta_mailer/src/Form/MailImapSettingsForm.php:126` | FALSO POSITIVO | `$this->t()` com `@state` (estado calculado internamente) |
| `aculta_messenger/src/Controller/MessagesController.php:1528` | PENDENTE | `$site_alert->getMessage()` é renderizado como markup. Confirmar se o campo aceita HTML e quem tem permissão de editar. Se aceitar HTML, é risco de XSS por administrador, a classificar como ACEITO ou corrigir com filtro de formato |

Pendentes: os demais 24 achados desta regra. Não foram triados nesta entrega; ficam como dívida até a próxima passagem.

### 1.3 Correção de regra quebrada

- `aculta-php-raw-redirect-cross-host` usava `pattern:` com `new \Symfony\...\RedirectResponse(...)`. No parser PHP do Semgrep essa forma **não casava**, mesmo com a barra inicial. Verificado com fixture isolada.
- A regra foi trocada para `pattern-regex` e agora tem caso positivo e negativo em `tests/rules/aculta-php.php`.
- O zero achado anterior dessa regra não era prova de ausência de problema.

### 1.4 Fixtures das regras

- Arquivo: `.agents/skills/aculta-seguranca-estatica/tests/rules/aculta-php.php`.
- Verificador: `tests/check-rule-fixtures.py`.
- Resultado: **17 expectativas, 0 falhas** após a correção da regra de redirect.
- `semgrep --test` foi tentado e falhou com erro interno do harness (`IndexError` em `semgrep/test.py`). Por isso o verificador próprio é o gate.

## 2. Dependências (`composer audit --locked`)

Resultado: **3 advisories** em 2 pacotes. Execução real.

| Pacote | Versão travada | Advisory | Severidade | Afetados | Correção |
|---|---|---|---|---|---|
| `drupal/entity_print` | 2.19.0 | CVE-2026-126736 (informação sensível inserida em ...) | moderada (segundo o título do advisory) | módulos que usam Entity Print (ver `composer.json` e `config/sync`) | atualizar para `>= 2.20.0` no mesmo minor 2.x; **PENDENTE de autorização** (altera lock) |
| `symfony/http-client` | v7.4.22 | CVE-2026-121971 (credenciais do escopo padrão enviadas a outras origens por referência de rede) | não informada pelo advisory no texto obtido | dependência transitiva de Drupal core/componentes; confirmar com `composer why` | atualizar para `>= 7.4.23` (mesmo minor); **PENDENTE de autorização** |
| `symfony/http-client` | v7.4.22 | CVE-2026-123119 (NoPrivateNetworkHttpClient, DNS rebinding) | não informada | mesmo | mesma correção: `>= 7.4.23` |

Nenhuma correção foi aplicada: a tarefa proíbe atualização global e alteração não autorizada do lock. Registro para decisão do responsável.

## 3. Segredos

- **gitleaks: MISSING.** Não instalado neste ambiente. Gate de segredos **DEFERRED**.
- Não foi feita varredura por padrão de segredo nesta entrega. Não afirme "sem segredos" por esta sessão.

## 4. Drupal Security Review (`drupal/security_review`)

- Estado declarado: `composer.json` `^3.1`, `composer.lock` 3.1.3, Config Sync habilita `security_review`.
- Execução: **DEFERRED**. Não há `vendor/` nem Drush consultável nesta worktree. Não se declara PASS sem consultar o Runtime.

## 5. Resumo

| Item | Resultado |
|---|---|
| Semgrep local | executado: 474 achados, triagem parcial (10/10 dinâmicos, 5/31 markup, 1/1 unserialize) |
| Fixtures das regras | PASS: 17/17 |
| `semgrep --test` nativo | FAIL por erro interno do harness; substituído pelo verificador próprio |
| composer audit | executado: 3 advisories, PENDENTE de correção autorizada |
| npm audit (`tests/browser`, dev e prod) | executado: 0 vulnerabilidades |
| gitleaks | DEFERRED (MISSING) |
| drupal/security_review | DEFERRED (sem Runtime/Drush consultável) |

## 6. Próximas ações

1. Triar os 24 `#markup` restantes e confirmar `MessagesController:1528` e `Cli.php:1539`.
2. Decidir sobre a correção de `entity_print` e `symfony/http-client` (lock).
3. Instalar gitleaks em versão fixada e integrá-lo ao workflow de segurança.
4. Executar `drupal/security_review` com Drush em ambiente descartável ou no Runtime, em modo somente leitura.
