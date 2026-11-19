---
name: aculta-handoff
description: Registra estado recuperavel quando uma fase fica parcial ou um agente transfere trabalho a outro; nao exige log a cada microalteracao.
---

# Handoff focado

- Para fix simples concluido no commit e no relato, handoff adicional e opcional.
- Para trabalho parcialmente concluido ou entregue a outro agente, compare sempre o registro com HEAD, remoto, diff e estado real.
- Registre objetivo, estado funcional, arquivos/commits, evidencias PASS/FAIL/DEFERRED, bloqueios e proximo passo concreto. Nao cole logs nem replique o CHANGELOG.
- Mantenha ate tres entradas recentes em `docs/operations/ESTADO-DA-SESSAO.md`; registros antigos sao historicos e nao substituem verificacao do codigo.
- Nunca armazene segredos, tokens, credenciais nem dados pessoais. Nao crie commit vazio ou apenas burocratico para encerrar tarefa ja rastreavel.
