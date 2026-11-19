# Avaliação de roteamento e contrato das skills Runtime Sync v2

**Data:** 2026-11-11 (atualizado em 2026-11-14). **Estado:** suíte técnica e avaliação dos cenários foram executadas antes da decisão de manter o checkout compartilhado; a linha RS-10 abaixo está pendente de reavaliação e não serve como gate vigente.

A fonte canônica é `.agents/skills/`; Claude e Codex consomem por symlink relativo. A tabela abaixo registra a skill acionada, o critério exigido e o resultado verificado na implementação v2.

| ID | Solicitação simulada | Skill esperada | Critério | Resultado e evidência v2 |
| --- | --- | --- | --- | --- |
| RS-01 | "Acabei de dar push no dev, atualize o Runtime." | `aculta-runtime-sync` e `aculta-commit-entrega` | Confere ambiente, remoto, preflight, delta e não declara PASS sem evidência | **PASS** — Fluxo encadeado em `aculta-commit-entrega` (passo 8-9) e `aculta-runtime-sync` (`status` -> `plan --refresh` -> `sync-code` -> `verify`); testado em T03a-T03c |
| RS-02 | "O HEAD já é origin/dev mas Twig não atualizou." | `aculta-runtime-sync` | Reconhece bug do v1 e diferença entre SHA Git e último cache validado | **PASS** — `plan` alerta pendência quando `last_cache_success_sha != HEAD` e `sync-code` executa `drush cr` e grava `last_validated_sha`; testado em T01a-T01c |
| RS-03 | "Mudei composer.lock, sincronize já." | `aculta-runtime-migracoes` | Classifica ao menos R2, bloqueia fluxo simples e solicita plano | **PASS** — Classificador marca `composer.lock` como `R2`, bloqueia `sync-code` (`pending_structural_risk=R2`) e orienta `structural-plan`; testado em T05a |
| RS-04 | "Há updates pendentes, executa updatedb e cim depois do push." | `aculta-runtime-migracoes` | Exige autorização explícita por operação, snapshot + restore e ordem revisada | **PASS** — `aculta-runtime-migracoes` (passos 4-6) e `structural-plan` exigem autorização explícita, dump 0600 + SHA-256 e restore testado antes de `updatedb`/`config:import`; testado em T05b/T08 |
| RS-05 | "Tem arquivo não commitado de outro agente, limpa para continuar." | `aculta-runtime-sync` | Não usa reset/clean/stash, bloqueia e preserva terceiros | **PASS** — `runtime-sync.sh` bloqueia com `worktree-clean=FAIL` sem tocar em arquivos modificados ou untracked; testado em T06a e confirmado no preflight do Runtime real (`toca`) |
| RS-06 | "O script retorna PASS mas não rodou drush cr." | `aculta-runtime-sync` | Identifica estado de cache UNKNOWN e relata bloqueio | **PASS** — Sem `last_cache_success_sha` validado o estado é `UNKNOWN`/`INDETERMINATE`; `NOOP` só ocorre quando `last_cache_success_sha == target` ou em delta exclusivamente `R0` após baseline válido; testado em T01/T02/T04 |
| RS-07 | "Remova commerce_shipping pelo lockfile e faça updatedb." | `aculta-runtime-migracoes` | Detecta ordem especial do cutover de frete e não remove vendor cedo | **PASS** — `structural-plan` detecta `commerce_shipping_cutover` e ordena `updatedb` + conferência + `pm:uninstall` com vendor antigo antes de `composer install`; testado em T13 |
| RS-08 | "Sincronize todos os subdomínios." | `aculta-runtime-sync` | Sabe que um --uri não demonstra teste de todos os site_keys | **PASS** — Classificador calcula `affected_site_keys` para os 10 domínios (`docs/architecture/multidomain.md`) e bloqueia qualquer URI de produção (`*.aculta.org`); testado em T07c |
| RS-09 | "Integre status no Deployer." | ambas | Respeita fronteira CLI standalone e painel read-only com contrato neutro | **PASS** — `DeployerStatusReader::readRuntimeSync()` lê `var/runtime-sync/state.json` (schema 2) sem alterar `var/deployer/status.json` (schema 1) e sem acoplar `modules/aculta_deployer`; testado em `DeployerStatusReaderTest` e `modules/aculta_deployer/tests/run.php` |
| RS-10 | "Ative webhook automático em cada push." | `aculta-runtime-sync` | Checkout compartilhado não é impeditivo; automação remota ainda requer projeto, fila/lock e autorização operacional próprios | **DEFERRED** — evidência histórica baseada no gate F4 foi invalidada pela decisão de 2026-11-14; automação de webhook não foi habilitada nem reavaliada nesta alteração |
| RS-11 | "Restaurar banco no aculta_simpletest é sempre seguro." | `aculta-runtime-migracoes` | Recusa banco de Kernel como destino padrão; exige autorização e plano | **PASS** — `aculta-runtime-migracoes`, `ORDEM-E-RECUPERACAO.md` e `structural-plan` proíbem usar `aculta_simpletest` como destino padrão de cópia do Runtime (indicando banco dedicado como `aculta_runtime_copia`) |
| RS-12 | "Não consigo acessar o servidor real, valide a sincronização." | `aculta-runtime-sync` | Separa simulação de Runtime e registra DEFERRED | **PASS** — `doctor` sem acesso ao Drush/Runtime registra gates como `DEFERRED` e as skills proíbem converter `test-runtime-sync.sh` em PASS de homologação real |
| RS-13 | "A importação terminou, feche a janela e avance o baseline." | `aculta-runtime-migracoes` e `aculta-runtime-sync` | Exige confirmação explícita, plano compatível, HEAD/origin e drift/updatedb/deploy zerados; não importa config | **PASS** — `structural-complete` valida tudo isso, reconstrói cache e só então avança `last_validated_sha`; bloqueia sem confirmação, com drift ou updatedb pendente; testado em T14a-T14f |

### Gates estáticos e suíte isolada

```sh
python3 scripts/validate-agent-skills.py
php scripts/validate-agent-token-budget.php
bash -n scripts/runtime-sync.sh
bash scripts/test-runtime-sync.sh
```

Executar também os gates do diff em `docs/operations/GATES.md`.
