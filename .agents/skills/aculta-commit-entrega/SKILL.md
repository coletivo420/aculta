---
name: aculta-commit-entrega
description: Revisa, testa e publica uma entrega atomica em origin/dev; sincroniza Runtime apenas quando o codigo servido e o ambiente exigirem.
---

# Commit e entrega

1. Confirme `git status --short`, branch/remoto e o diff. O checkout principal fica permanentemente fixado em `dev`. Novas branches exigem seu proprio checkout/worktree isolado (`git worktree add`). Isole os próprios commits e preserve trabalho alheio. Staged apenas por caminho explícito, nunca `git add -A` indiscriminado.
2. Classifique a revisao R0-R3 conforme `docs/operations/AGENT-REVIEW-POLICY.md`. Autorrevisao e gates de escopo em toda entrega; R2/R3 exigem segunda revisao antes de publicar; R3 exige autorizacao por operacao.
3. Execute `git diff --cached --check` e revise o staged. Commit atomico em portugues, sem coautoria ficticia. Trailer de CLI e opcional e identifica apenas a sessao real.
4. Push fast-forward em `origin/dev` e confirmacao do SHA remoto (ou via PR aprovado caso haja regra de branch protection). Se o remoto avancou, integre sem sobrescrever arquivo de outro agente e refaca os testes afetados. Nunca force push, reset destrutivo ou reescrita de historico.
5. Codigo servido: testes no Runtime real compartilhado ocorrem apenas após integração em `dev` no checkout principal. Durante desenvolvimento em branch/worktree, testes devem ser simulados/isolados. Consulte `aculta-runtime-sync` no checkout principal devidamente sincronizado quando R0/R1 do sincronizador e o preflight for seguro. Apenas docs: nao reconstruir cache. R2-R4/RX: estrutural-plan e aprovacao especifica, nao executar migracoes por inferencia.
6. Relate hash confirmado, arquivos, classe, testes executados, falhas (inclusive CI skipped), pendencias e situacao do Runtime. TODO e handoff so se mudanca alterar fase ou exigir transferencia.
