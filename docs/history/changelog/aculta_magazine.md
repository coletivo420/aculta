Histórico arquivado do CHANGELOG.md de aculta_magazine. Não é fonte de decisão vigente: ver docs/decisions/ e docs/architecture/aculta_magazine.md.

## 0.1.0-dev.12 — área administrativa unificada da Revista — 2026-10-19

- Criada listagem exclusiva de publicações `article` e `blog` em
  `/painel-administrativo/conteudo/revista`.
- Comentários editoriais ficam incorporados à mesma página como Views block,
  filtrados por `field_aculta_discussion` e pelos bundles da Revista.
- Acesso de conteúdo usa `access content overview`; moderação exige
  `administer comments`; filtros de bundles são fixos e a área fica limitada ao
  purpose `main`.
- RUNTIME STATUS: PARTIAL; smoke HTTP e renderização da View no servidor de
  teste pendentes.
