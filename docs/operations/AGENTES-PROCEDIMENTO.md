# Procedimento de trabalho dos agentes

Este procedimento complementa o `AGENTS.md` da raiz. Em caso de conflito,
prevalece a regra geral mais recente do responsável.

## Branch e publicação
- Faça o trabalho autorizado diretamente na branch `dev` e envie commits para
  `origin/dev`. Não crie branches, worktrees de escrita ou PRs.
- Antes de editar, confira `git status --short --branch` e preserve mudanças
  paralelas. Não sobrescreva trabalho de outras pessoas.
- Faça commits pequenos, limitados à tarefa. Publique por fast-forward; nunca
  force push ou reescreva histórico publicado.
- Se `dev` avançar, integre as mudanças sem descartar trabalho e valide antes
  de publicar novamente.

## Runtime e dados
- Não faça deploy, alterações de produção ou operações persistentes no Runtime
  sem autorização explícita.
- Antes de qualquer operação autorizada, confirme host, diretório, URI e banco
  e consulte `docs/architecture/environments.md`.
- Os bancos existentes são `aculta_runtime` e `aculta_simpletest`. Nunca crie
  bancos, não execute `site:install` em banco com tabelas e não altere o host
  `toca`.
- Não execute `pm:uninstall`, `DROP`, `updatedb` ou importação de configuração
  sem autorização explícita. Prefira verificações somente leitura.

## Validação e registro
- Execute as verificações pertinentes ao escopo e relate somente o que foi
  realmente executado. Para documentação, confira links e `git diff --check`.
- Registre ações operacionais no histórico indicado pelo responsável. Não
  acrescente snapshots ao histórico por rotina.
- Ao concluir, informe arquivos alterados, commit enviado, verificações e
  pendências.

Consulte as instruções locais do módulo ou tema afetado para contratos
específicos.