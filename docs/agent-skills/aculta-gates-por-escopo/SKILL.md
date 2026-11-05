---
name: aculta-gates-por-escopo
description: Escolhe e roda só os gates do escopo do diff do ACULTA. Use antes de concluir qualquer alteração de código ou documentação.
---

# Gates por escopo

1. Liste o diff: `git diff --stat` e `git diff --name-only`.
2. Leia `docs/operations/GATES.md` só na tabela de escopo. Não leia o arquivo inteiro.
3. Rode só os gates das linhas que casam com o diff. Gate de módulo dependente também entra.
4. Gate fora do escopo: registre como N/A com o motivo.
5. Gate que não roda no ambiente (sem `vendor/`, sem Drush, sem extensão): registre como DEFERRED com o motivo. Nunca declare PASS sem execução.
6. Comandos verbosos (PHPUnit, PHPStan, lint de projeto): `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
7. Cole no relatório a saída resumida e o status de cada gate.
