## Fechamento das fases — 2026-10-25

Os hosts de teste documentados nos resultados abaixo são registros históricos. Em
2026-10-28, o host da Revista foi renomeado no contrato para
`revista.aculta.toca.net.br`; a validação desse host aguarda Virtualmin, DNS e TLS.

- Unit do Fórum 184/1.332; Unit do Messenger 261/2.023.
- Kernel do Fórum 150/2.863 no MariaDB (42 depreciações de Core/contrib).
- Functional 6/6, 33 asserções, em site novo com o perfil standard:
  negação anônima, telas autenticadas, criação de tópico pelo formulário, lock
  da limpeza e desinstalação sem rota/configuração.
- `tests/browser/aculta-forum/follow-and-notices.py` 13/13: seguir e deixar de
  seguir, aviso único com contador, leitura pela rota canônica, filtro de não
  lidos, teclado, 500 CSS px e nenhuma conversa privada.
- `fgroup-runtime.py`: ativo com membership lê o tópico restrito; suspenso
  recebe 403.
- F-GROUP local: `provision`, `measure` (anônimo, ativo sem membership,
  suspenso e comum negados; ativo com membership liberado), `search` sob
  grants e `teardown` com `node_access` de volta a `all` (26 linhas).
- Migração e corte locais: posts 2/2, Comments legados removidos
  (`comments=0`, `posts=5`) e avisos estáveis (3→3); backup
  `aculta-local-pre-cut-20261025.sql.gz`.

## Rodadas datadas arquivadas

As rodadas de teste de 2026-10-23 e 2026-10-24, as provas de F-GROUP e as rodadas de `aculta_forum_lms` (dev.33 a dev.40) e dev.194 e dev.231 foram movidas sem alteração para `docs/history/modules/aculta_forum-TEST-MATRIX-ARQUIVO-2026-11-05.md`. Para o estado atual, use o fechamento acima e os gates em `docs/operations/GATES.md`.