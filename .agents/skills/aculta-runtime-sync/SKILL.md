---
name: aculta-runtime-sync
description: Use após push em origin/dev, ao inspecionar SHA, cache, divergência ou sincronização do Runtime ACULTA de desenvolvimento; nunca faça migração estrutural automaticamente.
---

# ACULTA - sincronização cotidiana do Runtime

**Gatilho:** depois de cada push de agente, ao diagnosticar Runtime desatualizado ou quando a tarefa envolver Git, cache, Drush ou coordenação de checkouts. Combine com `aculta-commit-entrega` e `aculta-gates-por-escopo`.

**Fonte operacional:** `docs/operations/RUNTIME-SYNC.md`. **Arquitetura futura:** `docs/operations/RUNTIME-SYNC-PLANO-V2.md`. Leia apenas a seção relevante. Use a [matriz de risco e testes](references/CLASSIFICACAO-E-TESTES.md) se o diff não for trivial.

## Procedimento obrigatório

1. **Determine onde está.** `pwd -P`, `hostname -s`, `git status --short --branch`, `git remote -v`, `git rev-parse HEAD`, `git ls-remote origin refs/heads/dev`. Confirme manualmente remoto esperado, branch, checkout servido e ambiente `test`. Não altere remotes, worktrees, links, settings ou permissões por conta própria.
2. **Inspecione antes de escrever.** `bash scripts/runtime-sync.sh plan`. Compare `HEAD`, `origin/dev` e o **último SHA comprovadamente validado**, incluindo cache. O script v1 não registra esse último SHA: ausência do baseline = **UNKNOWN**, não PASS.
3. **Leia os sinais do Drupal.** No host comprovado e com URI HTTPS de desenvolvimento fornecida na configuração, valide banco `aculta_runtime`, Drush e saídas de `updatedb:status` e `config:status` como dados, não só código de saída. Se não houver acesso ao Runtime, registre DEFERRED.
4. **Classifique o delta cumulativo** desde o último SHA validado: R0 documental; R1 código/cache; R2 dependências; R3 Drupal estrutural; R4 destrutivo/infra; RX indeterminado. Mudança de `*.install`, hooks, lockfiles, `config/sync`, settings, renames ou dados exige revisão. RX bloqueia.
5. **Execute apenas fluxo seguro.** `bash scripts/runtime-sync.sh sync-code` somente se o preflight estiver completo, as variáveis explícitas `ACULTA_SYNC_ENV`, `ACULTA_SYNC_EXPECTED_HOST`, `ACULTA_SYNC_EXPECTED_DB`, `ACULTA_SYNC_URI` forem conferidas e o fluxo R0/R1 for permitido. O sincronizador atual bloqueia worktree sujo e mudanças estruturais. NÃO use comandos manuais para contornar o bloqueio.
6. **Valide o resultado real.** Confira SHA, cache, saúde Drush, pendências e smoke dos hosts afetados. `HEAD == origin/dev` NÃO prova cache atualizado: o script v1 retorna cedo nesse caso, sem `drush cr`. Se o baseline de cache for desconhecido, informe que a validação está pendente; não declare sincronizado.
7. **Registre sem segredos** modo, SHAs, gates PASS/FAIL/DEFERRED, alterações realizadas, bloqueio e próximo passo. Nunca atribua um PASS a simulação de Drush ou a teste local quando a tarefa exige Runtime real.

## Segurança e fronteiras

- Só ambiente de desenvolvimento. `drush cr` escreve em tabelas de cache e não é operação absolutamente read-only.
- `flock` do script evita apenas outro sincronizador, **não outros agentes**. O checkout editado pelos agentes ainda é o docroot Apache: código pode estar visível antes do sync. Coordene pausa de escritas; para isolamento real, siga a F4 do plano v2.
- Proibidos `git reset --hard`, `git clean`, `git stash` automático, force push, Composer, `updatedb`, `config:import`, `pm:uninstall` ou SQL destrutivo como parte da sincronização cotidiana.
- Para R2-R4 ou mudança estrutural suspeita, carregue `aculta-runtime-migracoes`. Ordem explícita do responsável é necessária para modificações de banco/configuração.
- Os comandos `doctor`, `status`, `plan --refresh`, `structural-plan` e o relatório v2 **ainda são planejados**; não os apresente como implementados.
