---
name: aculta-commit-entrega
description: Faz commit atômico e publica em origin/dev, no padrão do ACULTA (português, trailer Claude-Session, sem coautoria). Use ao concluir uma entrega.
---

# Commit e publicação de uma entrega

1. Confira `git status --short`. Preserve trabalho alheio.
2. Adicione só os arquivos da entrega, por caminho explícito. Nunca `git add -A`.
3. `git diff --cached --check` deve passar.
4. Mensagem em português, com o trailer `Claude-Session`. Nunca `Co-Authored-By`.
5. Commit atômico. Se a tarefa virou entregas independentes, faça um commit por entrega.
6. Publique: `git push origin dev`. Confirme com `git ls-remote origin dev`.
7. Nunca force push, nem reescreva commit já publicado. Se um commit saiu misturado, corrija em commit novo e informe no relatório.
8. Após confirmar o push, carregue `aculta-runtime-sync` (`plan` -> `sync-code` -> `verify`) para atualizar código/cache no Runtime quando o checkout estiver limpo e o delta for R0/R1. Se houver R2-R4 ou RX, rode `structural-plan`, carregue `aculta-runtime-migracoes` e registre o bloqueio sem executar updates/config por inferência.
9. O `runtime-sync.sh` v2 distingue `checkout_sha`, `last_cache_success_sha` e `last_validated_sha`: se `HEAD == origin/dev` mas o cache da revisão ainda não foi validado, `sync-code` reconstrói o cache; se o checkout estiver sujo por outros agentes, reporte `BLOCKED`/`DEFERRED` com evidência.
10. Não diga que publicou sem confirmar o push.
