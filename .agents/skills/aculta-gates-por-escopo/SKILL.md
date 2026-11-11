---
name: aculta-gates-por-escopo
description: Escolhe e roda só os gates do escopo do diff do ACULTA. Use antes de concluir qualquer alteração de código ou documentação.
---

# Gates por escopo

1. Liste o diff: `git diff --stat` e `git diff --name-only`.
2. Leia `docs/operations/GATES.md` só na tabela de escopo. Não leia o arquivo inteiro.
3. Rode só os gates das linhas que casam com o diff. Gate de módulo dependente também entra.
4. Gate fora do escopo: registre como N/A com o motivo.
5. Gate que não roda no ambiente (sem `vendor/`, sem Drush, sem extensão): registre como DEFERRED com o motivo. Nunca declare PASS sem execução.
6. Comandos verbosos (PHPUnit, PHPStan, lint de projeto): `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
7. Cole no relatório a saída resumida e o status de cada gate.

## Matriz para UI, SDC e UI Suite

Escolha pelo arquivo alterado e pelas integrações realmente presentes no Composer/configuração. Os comandos abaixo são candidatos por escopo, não uma suíte para rodar inteira:

| Diff | Validações candidatas |
| --- | --- |
| `.component.yml`, Twig ou story | `validate-aculta420-sdc-schemas.php`, `validate-aculta420-v2-sdc.php`; teste Unit/Kernel focal se existir |
| CSS, tokens, tema claro/escuro ou contraste | `validate-aculta420-v2-foundations.php`, `validate-aculta420-ui-styles.php`; lint CSS do tema se aplicável |
| `aculta420_v2.ui_styles.yml` | `validate-aculta420-ui-styles.php` e schema SDC somente se contrato de componentes também mudar |
| mapa `data-portal-ui` ou módulo/tema | `validate-module-theme-boundary.php`, `validate-aculta420-mapped-classes.php` |
| UI Patterns Library, preview, acesso ou descoberta de componente | `validate-aculta-ui-library.php` e testes focais existentes do `aculta_ui_library` |
| stories/previews, modos e reflow | browser script existente em `tests/browser/`; não invente uma frente nem relaxe assertions |
| UI Icons ou `.icons.yml` | teste focal de pack existente, análise dos assets/paths e gate do tema pertinente; não há gate específico de UI Icons no inventário auditado |

**Runtime Sync e skills:** ao modificar `.agents/skills`, aliases, `AGENTS.md`, `scripts/runtime-sync.sh` ou seus runbooks, execute `python3 scripts/validate-agent-skills.py` e `php scripts/validate-agent-token-budget.php`. Se mudar o Bash, execute `bash -n scripts/runtime-sync.sh` e a suíte de Git/Drush fake quando implementada. Runtime real sem acesso é DEFERRED, não PASS.

Não existe gate de acessibilidade automatizada universal nem gate dedicado para cada integração Views/Layout Builder. Use teste existente da frente ou registre N/A/DEFERRED com motivo. Não habilite Olivero nem outro tema para validar.
