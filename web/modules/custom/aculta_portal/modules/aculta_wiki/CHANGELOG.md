# Changelog — ACULTA Wiki

## Não lançado — upload da imagem de destaque — 2026-10-22

- O `field_wiki_image` deixa de ser um autocomplete de mídia no formulário do
  verbete. `WikiEntryImageHooks` (`form_node_wiki_entry_form_alter`) oculta o
  widget central e abre a aba **"Imagem de destaque"** dentro das abas verticais
  `advanced`: um `managed_file` em `public://wiki/imagens`, o campo de descrição
  (até 512 caracteres, como no Fórum), o resumo da imagem atual e a opção de
  removê-la.
- A aba consome o **consumidor `wiki` da política central**
  (`aculta_portal.user_upload_policy`): `UserUploadSettingsForm` ganhou a aba
  "Wiki", `config/sync/aculta_portal.user_upload.yml` ganhou `consumers.wiki`
  herdando a política global e `aculta_portal.schema.yml` ganhou o mapeamento.
  Extensões, tamanho e inspeção da imagem continuam vindo do mesmo serviço que
  o Fórum usa.
- `WikiLeadImageUploader` valida na submissão (um arquivo por envio, descrição
  obrigatória, imagem real dentro da política) e, antes de salvar o verbete, cria
  uma mídia `image` publicada — título = nome do arquivo, `field_media_image`
  com `alt` — e aponta `field_wiki_image` para ela.
- Decisão: **aba em vez de diálogo modal**. Um diálogo não teria como gravar no
  campo de autocomplete de um nó ainda não salvo sem repetir a mecânica AJAX da
  Media Library; a aba reusa o padrão do diálogo do Fórum sem esse acoplamento.
- Decisão: **substituir não apaga**. A mídia anterior fica no banco porque
  revisões publicadas podem apontar para ela; a limpeza fica para uma frente
  própria.
- A aba define `#tree => TRUE` explicitamente: `<details>` não sintetiza essa
  propriedade e, sem ela, os valores não chegariam sob `wiki_lead_image`.
- O uploader marca o arquivo como permanente antes de criar a mídia, porque o
  envio não passa pelo autocomplete que registraria o uso do arquivo e ele
  seria coletado como temporário.
- A aba só aparece quando a política do consumidor tem alguma extensão de
  imagem habilitada; caso contrário o widget central continua visível para não
  travar o campo.
- `aculta_wiki.info.yml` passa a declarar `drupal:file` e `drupal:media`.
- Testes: `WikiLeadImageUploaderTest` (10), `WikiEntryImageHooksTest` (6) e
  `WikiLeadImagePolicyTest` (3). Suíte do submódulo: `32 testes, 158 asserções,
  OK`. PHPCS `Drupal,DrupalPractice` limpo nos arquivos novos.
- Nenhuma operação de Runtime; nenhum cache limpo. A validação em navegador
  (ACULTA420 e Olivero) fica para depois da importação de configuração.

## Não lançado — imagem e campos no card da listagem — 2026-10-22

- O view mode `teaser` do `wiki_entry`
  (`config/sync/core.entity_view_display.node.wiki_entry.teaser.yml`) passa a
  renderizar `field_wiki_image` pelo mesmo formatador da página do verbete
  (`entity_reference_entity_view`, view mode `default`, sem link); o campo sai
  de `hidden`. A ordem do card fica imagem (peso 0), resumo (1) e categorias
  (2). Nenhum view mode de mídia novo e nenhuma configuração adicional.
- O tema exibe a imagem no card: `node--wiki-entry--teaser.html.twig` calcula
  `has_image` e repassa o campo ao SDC `aculta420:wiki-entry`, que já tinha os
  slots `image`; `wiki-entry.css` recorta em 16:9 (`aspect-ratio` +
  `object-fit: cover`) e a story `wiki-entry.teaser.story.yml` cobre o estado
  com imagem, resumo e categorias.
- Nenhuma URL muda (`/wiki`, `/wiki/verbetes`, `/wiki/busca`,
  `/wiki/busca/previa`). Os blocos `block_changes` e `block_recent` não foram
  tocados.
- `WikiTeaserConfigTest` (2 testes) trava o contrato: imagem sem link via
  entidade de mídia, ordem dos campos e `body`/relacionados/referências
  ocultos. Execução junto da suíte do módulo: `13 testes, 59 asserções, OK`.
  Validador de SDC: `PASS (41 components, 25 include calls, 610 checks)`.
- Nenhuma operação de Runtime; nenhum cache limpo. A renderização em navegador
  (ACULTA420 e Olivero) fica para depois da importação de configuração.

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
