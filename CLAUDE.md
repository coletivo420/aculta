# Claude Code - ACULTA

Regra geral: `AGENTS.md`. Revisao: `docs/operations/AGENT-REVIEW-POLICY.md`. Este documento contem somente instrucoes especificas do Claude.

- As skills compartilham fonte em `.agents/skills/` e aparecem em `.claude/skills/` por symlinks. Carregue apenas a habilidade do escopo. UI Suite: `sdc`, `ui-patterns`, `ui-styles`, `ui-icons`, `css` conforme a tarefa.
- Confirme HEAD, diff, codigo e instrucao local antes de editar. O pedido ja autoriza alteracoes tecnicas reversiveis no escopo. Pergunte somente se uma decisao essencial ou operacao sensivel exigir.
- Commit e push por fast-forward em `origin/dev`, apos gates aplicaveis. PR e merge em main somente com pedido. Trailer `Claude-Session` opcional apenas em sessao do Claude; sem coautoria ficticia.
- Navegador: reutilizar `tests/browser/INDICE.md` e registrar evidencia/motor. Nao declarar homologado sem execucao real.
- Graphify e opcional para relacoes amplas. `rg` e leitura focal sao meios normais e nao dependem de hook obrigatorio.
- Pesquisa externa permitida; nunca enviar codigo privado, dados pessoais ou credenciais a terceiros sem autorizacao.

Ponteiros: `docs/operations/AGENT-OPERATIONS.md`, `docs/operations/AGENT-POLICY-INDEX.md` e instrucoes locais de modulo/tema.
