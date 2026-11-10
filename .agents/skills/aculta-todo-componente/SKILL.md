---
name: aculta-todo-componente
description: Atualiza o TODO.md de um módulo, submódulo ou tema e o status YAML quando uma fase termina. Use ao concluir uma fase em um componente.
---

# Atualizar o TODO do componente

1. Leia o `TODO.md` ao lado do `info.yml` do componente.
2. Atualize a versão (igual à do `info.yml`), o estado e o próximo passo. Remova o que foi concluído.
3. Não acrescente histórico: o TODO tem no máximo 2.000 bytes.
4. Atualize `docs/operations/status/<componente>.yml` no mesmo commit, quando o arquivo existir.
5. Rode `php scripts/validate-agent-token-budget.php` e confirme PASS.
