# Segurança estática: linha de base — 2026-11-05

Escopo: `web/modules/custom/` e `web/themes/custom/aculta420_v2/src/`. Regras locais em `docs/agent-skills/aculta-seguranca-estatica/rules/aculta-php.yml`, Semgrep 1.183.0. O registro de regras do Semgrep foi bloqueado pelo proxy, então a biblioteca pública não entrou.

## Resultado

| Regra | Achados | Arquivos | Situação |
| --- | --- | --- | --- |
| `aculta-php-static-drupal-service` (`\Drupal::` estático) | 96 | 38 | 84 em `src/`, contra a regra do padrão Drupal 12 (seção 7). Não triados ainda. |
| `aculta-php-markup-raw-output` (`#markup` com variável) | 31 | — | Padrão: textos traduzidos em `$this->t()` com quebra de linha. Não triados ainda. |
| SQL montado por concatenação | 0 | — | Sem achado. |
| `eval`, `exec`, `shell_exec`, `system` | 0 | — | Sem achado. |
| `unserialize` | 0 | — | Sem achado. |
| Superglobal direta (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`) | 0 | — | Sem achado. |
| `RedirectResponse` cru | 0 | — | Sem achado. |

Total: 127 achados, todos de nível aviso, nenhum de nível erro.

## Limites desta execução

- Um arquivo (`aculta_messenger/src/Controller/AvisosMensagensController.php`) não foi analisado pelo parser do Semgrep, na linha 149. `php -l` confirma que o arquivo não tem erro de sintaxe. Trata-se de limite do parser, registrado para revisão.
- Triagem manual não feita: nenhum achado foi classificado como real ou falso positivo nesta linha de base. Essa é a próxima etapa.
- Biblioteca de regras pública (`p/php`) não usada, por bloqueio do proxy. A cobertura desta linha de base é limitada às regras locais acima.

## Próximo passo

Triar os 96 achados de `\Drupal::` (separar uso legado do que é novo) e os 31 de `#markup`, e registrar a classificação aqui. Nenhum achado precisa de correção antes disso.
