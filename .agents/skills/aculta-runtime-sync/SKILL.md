---
name: aculta-runtime-sync
description: Use após push em origin/dev, ao inspecionar SHA, cache, divergência ou sincronização do Runtime ACULTA de desenvolvimento; nunca faça migração estrutural automaticamente.
---

# ACULTA - sincronização cotidiana do Runtime

**Gatilho:** apos push que altera codigo servido, quando houver acesso real ao Runtime, ou a pedido para diagnosticar cache, Drush ou sincronizacao. Push apenas de docs, skills e instrucoes nao exige rebuild. Nao executar comandos de Runtime por ritual fora do ambiente comprovado. Combine com `aculta-commit-entrega` e `aculta-gates-por-escopo`.

**Fonte operacional:** `docs/operations/RUNTIME-SYNC.md`. **Arquitetura v2:** `docs/operations/RUNTIME-SYNC-PLANO-V2.md`. Leia apenas a seção relevante. Use a [matriz de risco e testes](references/CLASSIFICACAO-E-TESTES.md) se o diff não for trivial.

## Procedimento obrigatório

1. **Determine onde está.** `pwd -P`, `hostname -s`, `git status --short --branch`, `git remote -v`, `git rev-parse HEAD`, `git ls-remote origin refs/heads/dev`. Confirme manualmente remoto esperado (`coletivo420/aculta`), branch `dev`, checkout servido e ambiente `test`. O checkout compartilhado com Apache é esperado; não exija isolamento como precondição. Não altere remotes, links, settings ou permissões por conta própria.
2. **Inspecione antes de escrever.** `bash scripts/runtime-sync.sh status` e `bash scripts/runtime-sync.sh plan` (ou `plan --refresh` para buscar objetos em staging isolado fora do checkout). Compare `HEAD`, `origin/dev`, `last_cache_success_sha` e `last_validated_sha`. Ausência de baseline = **UNKNOWN** (`INDETERMINATE`), nunca PASS.
3. **Leia os sinais do Drupal.** Execute `bash scripts/runtime-sync.sh doctor --format=json` no host comprovado com URI HTTPS de desenvolvimento e banco `aculta_runtime`, tratando `updatedb:status`, `config:status` e `deploy:hook-status` como dados estruturados. Sem acesso ao Runtime, registre DEFERRED.
4. **Classifique o delta cumulativo** desde `last_validated_sha`: R0 documental; R1 código/cache; R2 dependências; R3 Drupal estrutural; R4 destrutivo/infra; RX indeterminado. Mudança de `*.install`, hooks, lockfiles, `config/sync`, settings, renames estruturais ou dados exige revisão. RX bloqueia.
5. **Execute apenas fluxo seguro.** `bash scripts/runtime-sync.sh sync-code` somente se o preflight estiver limpo, `ACULTA_SYNC_ENV=test`, `ACULTA_SYNC_EXPECTED_HOST`, `ACULTA_SYNC_EXPECTED_DB` e `ACULTA_SYNC_URI` estiverem conferidos e o delta for R0/R1. O v2 reconstrói cache mesmo quando `HEAD == origin/dev` se `last_cache_success_sha != HEAD`, e retorna `NOOP` quando já validado. NÃO use comandos manuais para contornar bloqueio.
6. **Valide e feche.** Use `verify` e confira SHA, cache, Drush, pendências e smoke dos `site_keys`; falha de `drush cr` após fast-forward deixa `PARTIAL`. Após import/updatedb autorizados, com HEAD == `origin/dev`, worktree limpo e plano compatível, rode `ACULTA_SYNC_STRUCTURAL_CONFIRM=1 … structural-complete --plan=<plano.json>`: ele não importa config nem roda updatedb, exige drift/updatedb/deploy zerados, reconstrói o cache e avança `last_validated_sha`.
7. **Registre sem segredos** modo, SHAs, gates PASS/FAIL/DEFERRED, alterações realizadas, bloqueio e próximo passo. Nunca atribua um PASS a simulação de Drush (`scripts/test-runtime-sync.sh`) quando a tarefa exigir Runtime real.

## Segurança e coordenação (F3)

- Só ambiente de desenvolvimento (`*.aculta.toca.net.br`). `drush cr` escreve em tabelas de cache e requer lock e registro.
- O checkout de desenvolvimento é o mesmo checkout servido pelo Apache, conforme `docs/architecture/environments.md`. Alterações em arquivos servidos ficam visíveis imediatamente. O preflight exige worktree limpa antes de `sync-code` para evitar aplicar um alvo sobre trabalho não commitado; o lock (`var/deployer/runtime-sync.lock`) serializa sincronizadores, mas não impede edições externas. A ausência de isolamento não é falha nem bloqueio.
- Proibidos `git reset --hard`, `git clean`, `git stash` automático, force push, Composer, `updatedb`, `config:import`, `pm:uninstall` ou SQL destrutivo na rotina cotidiana.
- Para R2-R4 ou mudança estrutural, use `bash scripts/runtime-sync.sh structural-plan` e carregue `aculta-runtime-migracoes`.
