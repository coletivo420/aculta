---
name: aculta-gates-por-escopo
description: Escolhe e roda só os gates do escopo do diff do ACULTA. Use antes de concluir qualquer alteração de código ou documentação.
---

# Gates por escopo

1. Liste o diff: `git diff --stat` e `git diff --name-only`. Classifique R0-R3 em `docs/operations/AGENT-REVIEW-POLICY.md`; as classes nao sao as categorias operacionais do Runtime Sync.
2. Leia `docs/operations/GATES.md` só na tabela de escopo. Não leia o arquivo inteiro.
3. Rode só os gates das linhas que casam com o diff. Gate de módulo dependente também entra.
4. Gate fora do escopo: N/A quando relevante ao relato; nao listar centenas de gates irrelevantes.
5. Gate que não roda no ambiente (sem `vendor/`, sem Drush, sem extensão): registre como DEFERRED com o motivo. Nunca declare PASS sem execução.
6. Comandos verbosos (PHPUnit, PHPStan, lint de projeto): `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
7. Registre resultado e evidencia dos gates efetivamente selecionados. Distinga falha anterior de regressao introduzida; nao converter skipped em PASS.

## Matriz por tipo de arquivo

A matriz completa (PHP, Twig, CSS, JavaScript, SDC, UI Patterns, UI Styles, UI Skins, UI Icons, HTMX, rotas, controllers, serviços, formulários, pagamentos, uploads, autenticação, Runtime Sync, shell, GitHub Actions e skills) está em `docs/operations/GATES.md`, seção "Matriz por tipo de arquivo". Use-a para escolher os gates do diff; não rode a suíte inteira para mudança documental.

Skills de apoio por escopo: segurança (`aculta-seguranca-estatica`), acessibilidade (`aculta-acessibilidade`), regressão visual (`aculta-regressao-visual`), shell e workflows (`aculta-shell-qualidade`).

## Matriz para UI, SDC e UI Suite

Escolha pelo arquivo alterado e pelas integrações realmente presentes no Composer/configuração. Os comandos abaixo são candidatos por escopo, não uma suíte para rodar inteira:

| Diff | Validações candidatas |
| --- | --- |
| `.component.yml`, Twig ou story | `validate-aculta420-sdc-schemas.php`, `validate-aculta420-v2-sdc.php`; teste Unit/Kernel focal se existir |
| CSS, tokens, tema claro/escuro ou contraste | `validate-aculta420-v2-foundations.php`, `validate-aculta420-ui-styles.php`; lint CSS do tema se aplicável |
| `aculta420_v2.ui_styles.yml` | `validate-aculta420-ui-styles.php` e schema SDC somente se contrato de componentes também mudar |
| mapa `data-portal-ui` ou módulo/tema | `validate-module-theme-boundary.php`, `validate-aculta420-mapped-classes.php` |
| UI Patterns Library, SDC, preview ou descoberta de componente | `validate-aculta420-sdc-library-contract.php`, schema SDC e testes focais dos consumidores alterados; Runtime/browser quando aplicável |
| stories/previews, modos e reflow | browser script existente em `tests/browser/`; não invente uma frente nem relaxe assertions |
| UI Icons ou `.icons.yml` | teste focal de pack existente, análise dos assets/paths e gate do tema pertinente; não há gate específico de UI Icons no inventário auditado |

**Governanca dos agentes:** alteracoes em `AGENTS.md`, `CLAUDE.md`, `.agents/skills`, `.claude/settings.json`, `.codex/hooks.json` e runbooks pedem `python3 scripts/validate-agent-skills.py`, `php scripts/validate-agent-token-budget.php` e revisao dos contratos. **Runtime Sync:** so se alterar `scripts/runtime-sync.sh`, `scripts/runtime-sync/**` ou classificador operacional, execute tambem `bash -n scripts/runtime-sync.sh` e `bash scripts/test-runtime-sync.sh`. Se alterar a integração de leitura com o Deployer, rode `php web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php` e `DeployerStatusReaderTest`. Runtime real bloqueado ou sem acesso é DEFERRED/BLOCKED, não PASS.

Não existe gate de acessibilidade automatizada universal nem gate dedicado para cada integração Views/Layout Builder. Use teste existente da frente ou registre N/A/DEFERRED com motivo.
