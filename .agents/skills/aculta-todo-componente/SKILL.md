---
name: aculta-todo-componente
description: Atualiza TODO e status apenas quando muda fase, versao, bloqueio real ou proximo marco do componente.
---

# Estado do componente

1. Confira `*.info.yml`, codigo, testes e fase vigente.
2. Atualize `TODO.md` e `docs/operations/status/<componente>.yml` quando mudar versao, marco, bloqueio ou proximo passo, nao a cada fix.
3. Retire tarefas concluidas e mantenha o TODO legivel; historico, hashes extensos e logs de CI ficam em seus locais proprios. Tamanho e orientativo, nao bloqueio arbitrario.
4. Valide somente contratos do escopo. Se a tarefa nao muda estado de fase, registre N/A sem editar TODO ou status.
