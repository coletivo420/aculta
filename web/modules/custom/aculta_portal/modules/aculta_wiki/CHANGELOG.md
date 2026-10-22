# Changelog — ACULTA Wiki

## Não lançado — contagem e paginação da listagem — 2026-10-22

- A view `wiki_entries` (`config/sync/views.view.wiki_entries.yml`) passa a
  contar e paginar a listagem pública. O display `page_1` (`wiki/verbetes`,
  caminho inalterado) usa pager completo de 12 itens e cabeçalho
  `@total verbetes publicados`, exibido inclusive quando não há resultados.
- Os blocos `block_changes` (5 itens) e `block_recent` mantêm o limite fixo,
  sem paginador; a troca fica restrita ao display da página.
- `url.query_args` entra nos contextos de cache da página para que `?page=N`
  não receba o cache da primeira página.
- `WikiListingConfigTest` (2 testes) trava o contrato: pager e contagem na
  página, blocos intactos. Execução: `2 testes, 13 asserções, OK`.
- Nenhuma operação de Runtime; nenhum cache limpo.

## Não lançado — testes do controlador e do formulário — 2026-10-22

- Cria testes unitários em `tests/src/Unit` para as duas classes movidas:
  `WikiControllerTest` (5 testes) e `WikiSearchFormBuilderTest` (4 testes).
  Execução: `9 testes, 34 asserções, OK`.
- Correção de robustez encontrada pelos testes: `$request->query->get('q', '')`
  lança `BadRequestException` (`Input value "q" contains a non-scalar value.`)
  quando a URL traz `q[]=...`. `WikiController::search()`,
  `WikiController::preview()` e `WikiSearchFormBuilder::build()` passam a ler
  `query->all()['q'] ?? ''` e só aceitam valor escalar; um `q[]` malformado
  deixa o campo vazio em vez de derrubar a requisição.
- Os testes rodam sem o módulo habilitado e sem banco; nenhuma operação de
  Runtime.

## Não lançado — rotas movidas — 2026-10-22

- `aculta_portal.wiki_home` (`/wiki`), `aculta_portal.wiki_search`
  (`/wiki/busca`) e `aculta_portal.wiki_search_preview` (`/wiki/busca/previa`)
  passam de `aculta_portal.routing.yml` para `aculta_wiki.routing.yml`,
  mantendo nomes de máquina, caminhos e permissões (`access content`). Nenhuma
  URL pública muda.
- Os nomes continuam com o prefixo `aculta_portal.` por serem contrato público
  citado em tema, testes e documentação; não foram renomeados.
- Enquanto o módulo `aculta_wiki` estiver desabilitado, as rotas não são
  registradas pelo Drupal. O Portal não as define mais; não há rota duplicada.
- Nenhuma operação de Runtime; nenhum cache limpo.

## Não lançado — controlador e formulário movidos — 2026-10-22

- `WikiController` e `WikiSearchFormBuilder` passam de `aculta_portal` para
  `aculta_wiki`, com os namespaces `Drupal\aculta_wiki\Controller` e
  `Drupal\aculta_wiki\Presentation` e os mesmos construtores. Nenhuma URL muda.
- O serviço `aculta_wiki.presentation.wiki_search_form` passa a ser definido em
  `aculta_wiki.services.yml`.
- O Portal cria `WikiSearchFormBuilderInterface` e injeta o serviço de forma
  opcional (`@?`). Sem o submódulo, `wiki_search` na apresentação é `NULL` e o
  restante da apresentação continua funcionando.
- As rotas do Portal apontam temporariamente para o controlador movido. A
  definição das rotas passa para `aculta_wiki.routing.yml` no commit seguinte.
- O módulo continua desabilitado; nenhuma operação de Runtime.
- `php -l` nos quatro arquivos alterados: sem erros de sintaxe.

## Não lançado — casca do submódulo — 2026-10-22

- Cria o manifesto `aculta_wiki.info.yml` (`0.1.0-dev.1`) com as dependências
  `aculta_portal`, `domain`, `views`, `node` e `content_moderation`. Sem
  dependência de `aculta_statistics`.
- Adiciona `AGENTS.md`, `README.md`, `docs/ROADMAP.md` e `docs/TEST-MATRIX.md`.
- Documento apenas: nenhum código, rota ou configuração foi movido e o módulo
  não foi habilitado. Nenhuma alteração de Runtime.
- Validação desta versão: `validate-own-module-packages` PASS (45 checagens) e
  `validate-public-slugs` PASS (380 rotas; 17 violações contribuídas na linha de
  base DT-P20).
