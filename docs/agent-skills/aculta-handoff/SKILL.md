---
name: aculta-handoff
description: Registra o handoff de sessão em docs/operations/ESTADO-DA-SESSAO.md ao encerrar uma fase ou o trabalho. Use ao terminar uma fase, nunca a cada microfase.
---

# Handoff de sessão

1. Abra `docs/operations/ESTADO-DA-SESSAO.md`. Leia só as entradas recentes.
2. Acrescente uma entrada no topo com: objetivo, o que foi feito, arquivos alterados, commits (hash), verificações e resultados, pendências, próximo passo.
3. Não registre segredos nem caminhos absolutos de máquina.
4. Limite: 3 entradas. Mova as mais antigas, sem alterar o texto, para `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`.
5. Rode `php scripts/validate-agent-token-budget.php` e confirme PASS.
