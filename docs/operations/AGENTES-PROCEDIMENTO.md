# Procedimento de trabalho dos agentes

Este procedimento complementa o `AGENTS.md` da raiz. Em caso de conflito,
prevalece a regra geral mais recente do responsável.

## Branch e publicação
- Faça o trabalho autorizado diretamente na branch `dev` da worktree compartilhada e envie commits para
  `origin/dev`. Todos os agentes trabalham na mesma worktree: isole os próprios commits (stage só por caminho explícito) e preserve alterações de outros agentes, sem reset, stash, checkout ou clean que descartem trabalho alheio. Worktree separada não é obrigatória. PRs e merge em main somente com pedido.
- Antes de editar, confira `git status --short --branch` e preserve mudanças
  paralelas. Não sobrescreva trabalho de outras pessoas.
- Faça commits pequenos, limitados à tarefa. Publique por fast-forward; nunca
  force push ou reescreva histórico publicado.
- Se `dev` avançar, integre as mudanças sem descartar trabalho e valide antes
  de publicar novamente.

## Runtime e dados
- Nao faca deploy de producao ou operacoes estruturais/persistentes sem autorizacao especifica. Sincronizacao de codigo/cache em dev segue o preflight do Runtime Sync.
- Antes de qualquer operação autorizada, confirme host, diretório, URI e banco
  e consulte `docs/architecture/environments.md`.
- Os bancos existentes são `aculta_runtime` e `aculta_simpletest`. Nunca crie
  bancos, não execute `site:install` em banco com tabelas e não altere o host
  `toca`.
- Não execute `pm:uninstall`, `DROP`, `updatedb` ou importação de configuração
  sem autorização explícita. Prefira verificações somente leitura.

## Validação e registro
- Classifique R0-R3 conforme `docs/operations/AGENT-REVIEW-POLICY.md`, execute gates do escopo e relate somente testes realmente executados. Para documentacao, confira links e `git diff --check`.
- Registre ações operacionais no histórico indicado pelo responsável. Não
  acrescente snapshots ao histórico por rotina.
- Ao concluir, informe arquivos alterados, commit enviado, verificações e
  pendências.

Consulte as instruções locais do módulo ou tema afetado para contratos
específicos.