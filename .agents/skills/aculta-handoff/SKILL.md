---
name: aculta-handoff
description: Registra o handoff de sessão em docs/operations/ESTADO-DA-SESSAO.md ao encerrar uma fase ou o trabalho. Use ao terminar uma fase, nunca a cada microfase.
---

# Handoff de sessão

## Ao retomar (antes de agir)

1. Leia só a primeira entrada de `docs/operations/ESTADO-DA-SESSAO.md`.
2. Confira o estado real: `git status -sb`, `git log --oneline -3` e o `HEAD` de `origin/dev`.
3. Se o estado divergir da entrada (commit ausente, arquivo alterado, branch diferente), registre a divergência antes de continuar. Não presuma que a entrada está atual.

## Ao encerrar (uma entrada no topo)

Campos, nesta ordem. Campo sem conteúdo recebe "nenhum", com o motivo:

1. **Objetivo:** o que a fase pretendia, em uma frase.
2. **Estado:** o que funciona, o que está parcial e o que não foi testado. Diga o que é parcial de forma específica.
3. **Decisões e alternativas descartadas:** o motivo de cada escolha, para não ser debatida de novo.
4. **Cuidados:** contornos e particularidades que parecem simplificáveis, com a razão de mantê-los.
5. **Commits e arquivos:** hashes e caminhos relativos, sem diff nem listas longas.
6. **Verificações:** gate, resultado e DEFERRED com o motivo. Nunca PASS sem execução.
7. **Pendências e bloqueios:** inclui o que aguarda aprovação do responsável.
8. **Próximo passo:** uma ação concreta e executável.

## Regras

- Não registre segredos, senhas, tokens nem caminhos absolutos de máquina.
- Não cole saída de comando longa. Resuma e aponte o arquivo ou o commit.
- Cada entrada deve caber em cerca de 1.200 bytes. Acima disso, corte o histórico, não o estado.
- Registre no mesmo commit da fase, ou em commit separado se houver falha de script (informe no campo Verificações).
- Limite: 3 entradas. Mova as mais antigas, sem alterar o texto, para `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`.
- Rode `php scripts/validate-agent-token-budget.php` e confirme PASS.
