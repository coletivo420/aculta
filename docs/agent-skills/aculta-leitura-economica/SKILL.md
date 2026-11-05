---
name: aculta-leitura-economica
description: Limita a leitura e a saída de um agente ao necessário para a tarefa. Use no início de qualquer tarefa, antes de ler documentos longos ou delegar a subagente.
---

# Leitura econômica

1. Leitura de partida: `AGENTS.md`, `TODO.md` raiz e o `TODO.md` do componente. Só isso.
2. Escolha a linha do tipo de tarefa no orçamento de `docs/portal/AGENT-TOKEN-ECONOMY.md`. Mais de três documentos de partida indica escopo largo: reduza a subfase.
3. Procure antes de ler: `rg -n "termo" caminho` e leia só o trecho. Para roadmap ou README longo, liste as seções com `rg -n '^## ' arquivo` e leia a seção necessária.
4. Não leia `data/`, `references/`, `vendor/`, `docs/history/`, `web/core/`, `web/modules/contrib/` nem logs sem motivo escrito na tarefa.
5. Saída de comando verbosa: `python3 scripts/agent-output.py --lines 60 -- COMANDO`. Para revisão, `git diff --stat` antes do diff completo.
6. Subagente: passe só os caminhos-alvo, peça resposta com limite (ex.: "até 15 linhas") e use só a conclusão. Se a tarefa cabe em duas leituras, não delegue.
7. Antes de `/clear` ou de encerrar, registre o handoff com a skill `aculta-handoff` e retome a sessão só por ele.
8. Economia nunca substitui gate, teste ou revisão (item 8 de `AGENT-TOKEN-ECONOMY.md`).
