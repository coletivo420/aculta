# Triagem Semgrep — 2026-11-16

Escopo: revisão dos 24 achados publicados no PR #305 e nova execução das regras PHP locais nos scripts PHPStan, no módulo `aculta_messenger` e nas Stories PHP do tema `aculta420_v2`.

## Resultado por regra e classe

| Regra | Total inicial | REAL corrigido | ACEITO COM JUSTIFICATIVA | PENDENTE | Execução final |
| --- | ---: | ---: | ---: | ---: | ---: |
| `aculta-php-eval-or-dynamic-exec` | 3 | 3 | 0 | 0 | 0 |
| `aculta-php-markup-raw-output` | 5 | 5 | 0 | 0 | 0 |
| `aculta-php-static-drupal-service` | 16 | 0 | 16 | 0 | 0 |
| **Total** | **24** | **8** | **16** | **0** | **0** |

## Correções

- `scripts/phpstan-debt-guard.php`: processo executado com argumentos em lista via `proc_open()`; removida a concatenação de comando.
- `scripts/phpstan-debt-report.php`: comandos Git e PHPStan passam por `shell_exec()` com binário/argumentos escapados e saída validada.
- `PrivateMessageController::pmSettingsPage()`: removido HTML traduzido em `#markup`; texto e link agora são renderizados como elementos separados.
- Quatro Stories de `aculta420_v2`: removidos `renderInIsolation()` e `#markup` que reempacotavam a saída. Slots retornam o render array original para manter anexos e metadados de cache.

## Aceitos com justificativa

Os 16 usos estáticos estão em `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Entity/PrivateMessageThread.php`. A classe é uma entidade `ContentEntityBase` criada pelo entity storage do Drupal; a construção padrão de entidades não aceita injeção de serviços por construtor. A classe já contém TODO para extrair essas chamadas para serviços. A regra exclui somente esse caminho exato, sem excluir outros arquivos ou módulos. A exceção permanece dívida técnica a ser removida quando a lógica for extraída.

## Verificações

- Fixtures locais Semgrep: **PASS**, 22 expectativas, 0 falhas.
- Varredura final dos caminhos revisados com as regras PHP locais: **PASS**, 0 achados, 249 arquivos escaneados.
- PHP lint nos 10 arquivos PHP alterados: **PASS**.
- PHPStan nível 10 global: **PASS**, 0 erros não suprimidos.
- Guard PHPStan `check` e `diff`: **PASS**; três entradas exatas removidas após correção/narrowing.
- Bats `tests/shell/phpstan-debt-guard.bats`: **PASS**, 20 cenários.
- PHPUnit Unit focal (`AvisoRetencaoTest`, `UnpublishedPreviewHooksTest`): **PASS**, 5 testes e 13 assertions; PHPUnit reportou três deprecações.
- `php scripts/validate-aculta-portal-drupal12.php`: **PASS**, 1.236 checks.

A classificação inicial usa o SARIF do PR #305. A execução final usa Semgrep local com as regras versionadas do projeto; não inclui regras remotas.
