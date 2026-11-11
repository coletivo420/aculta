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
8. Após confirmar o push, carregue `aculta-runtime-sync` para planejar código/cache no Runtime. Se houver R2-R4 ou RX, carregue `aculta-runtime-migracoes` e registre o bloqueio sem executar updates/config por inferência.
9. Não confunda `HEAD == origin/dev` com cache validado: o script v1 pode sair sem `drush cr` nesse caso. Registre o último SHA e evidências reais; sem elas, reporte UNKNOWN/DEFERRED.
10. Não diga que publicou sem confirmar o push.
