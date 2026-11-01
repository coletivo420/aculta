## `0.1.0-dev.1` — adaptador `wiki` do Fórum — 2026-10-22

- Novo submódulo `aculta_forum_wiki`, irmão de `aculta_forum_lms`, com o
  provider `wiki` tagueado em `aculta_forum.discussion_context_provider`.
- Uma discussão por verbete: o provider aceita apenas Node `wiki_entry` com o
  campo compartilhado `field_aculta_discussion`, usa `stableId`
  `wiki:node:{uuid}`, a URL canônica do verbete e as políticas
  `editorial_entity_access` (leitura, descoberta e notificação) e
  `editorial_opt_in`.
- Leitura compõe Entity Access do verbete com o do campo; escrita acrescenta
  `post comments` e o campo aberto; moderação usa `administer comments`.
- O adaptador não cria tópico público, não reimplementa a Wiki, não registra
  rotas e não altera o fluxo editorial.
- Em `config/sync`: a instância `node.wiki_entry.field_aculta_discussion`, o
  campo oculto no formulário do verbete, a exibição `full` do `wiki_entry` com
  `comment_default` e a entrada `aculta_forum_wiki: 0` em `core.extension.yml`.
  A configuração fica no núcleo porque o `aculta_wiki` não tem `config/install`;
  a decisão sobre o dono dessa configuração continua aberta.
- No tema ACULTA420, o SDC `aculta420:wiki-entry` ganhou o slot `discussion` e
  o `node--wiki-entry--full.html.twig` passa `content.field_aculta_discussion`;
  o Olivero renderiza a mesma exibição `full` sem alteração de tema.
- Medição (banco local, Runtime intocado):
  `tests/src/Unit` — 5 testes, 28 asserções, OK. A suíte unitária do
  `aculta_forum` continua passando (105 testes, 799 asserções).
- Nada foi habilitado, importado ou atualizado no Runtime. A ativação depende de
  importar a configuração com backup verificado.
