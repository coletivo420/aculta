# ARQ-08 - Fechamento no agente local (2026-10-29)

**Estado da execução:** 2026-10-29, contra Drupal 11.4.10 no Runtime de desenvolvimento (`aculta_runtime`, host `aculta.toca.net.br`), Firefox ESR visível (`DISPLAY=:0`) e o banco Kernel dedicado `aculta_simpletest`. O escopo continua restrito ao bundle `revista`: nenhum bundle, campo, entidade editorial, workflow ou editor paralelo foi criado.

## Evidências executadas

| Gate | Comando | Resultado |
| --- | --- | --- |
| PHP lint | `php -l` em todos os PHP de `aculta_magazine` | PASS (0 erros) |
| PHPCS | `phpcs --standard=Drupal,DrupalPractice` no módulo | 0 erros, 1 aviso pré-existente (`aculta_magazine.install`) |
| Unit | PHPUnit `tests/src/Unit` | 31 testes / 121 asserções PASS |
| Kernel | `RevistaEditorialAccessKernelTest` em `aculta_simpletest` | 12 testes / 500 asserções PASS |
| Navegador | `tests/browser/revista-editorial/flow.py` (Firefox ESR) | 28 verificações PASS em execução contínua; demais etapas PASS em execuções focais (ver nota) |

**Nota sobre o navegador:** o script completo não fechou uma execução única. Em 2026-10-29 o checkout servido era trocado por outros agentes durante a execução e a máquina chegou a 13 geckodrivers concorrentes; houve `HTTP 500`/timeout no login e o widget de mídia deixou de renderizar em recargas. Cada verificação abaixo foi executada e aprovada em execução contínua (28 checks) ou em probe focal com o mesmo Firefox ESR e o mesmo Runtime. O script foi mantido com retentativas e deve ser reexecutado em janela estável para um PASS único.

## ARQ-08A - PASS

Formulário nativo (`node/add/revista`): criação com título, chamada, resumo, corpo (CKEditor 5), autoria e categoria editoriais, imagem via media library e seletor de moderação; envio inválido gera erros do Form API (principal e secundário) sem gravar node; recarga limpa do formulário; edição salvando nova revisão; erro em `field_references` abre o grupo "Propriedades secundárias" e mantém o erro visível; pré-visualização pela rota `entity.node.preview`; histórico de revisões; transições `draft → review → published → archived → draft`; acesso negado a anônimo e a `email_confirmed` sem papel editorial; envio do formulário de edição com JavaScript desabilitado, preservando a moderação e a mídia. Nenhum editor paralelo foi criado.

## ARQ-08B - PASS

- **Full display e SDC `aculta420:revista-publication`: PASS.** Chamada, resumo, imagem, categoria, autoria, cartão do autor, 5 links de compartilhamento, metatag description, og:image, canonical e JSON-LD renderizam na publicação no propósito `magazine` (`revista.aculta.toca.net.br`). Sem overflow horizontal medido em 1280 px e no mínimo de 500 px do Firefox ESR (a janela não reduz a 320 px neste ambiente).
- **Listagem (`revista_sessoes`, view mode `teaser`): PASS.** Com autorização do responsável para avançar pelo tema, o display `teaser` passou a expor `field_media_image`, `field_subtitle` e `field_summary`; o ACULTA420 ganhou `templates/node--revista--teaser.html.twig`, que reutiliza o SDC compartilhado `aculta420:card` (sem SDC alternativo, conforme `docs/component-catalog.md`) e a library `aculta420/revista-listing` (CSS mobile-first). O cartão renderiza capa 16:9, categoria, título, chamada, resumo e link de leitura, sem overflow em 1280 px e 500 px reais. Gates do tema: fundação 349 checks PASS, SDC schema 54 componentes PASS, shell 11 checks PASS.
- **Caso sem imagem:** os campos são obrigatórios no bundle; `RevistaContentHooks::metatagsAlter()` só atua em nodes legados sem imagem e está coberto por Unit.

## ARQ-08C - PARTIAL

- **Provider e acesso: PASS.** `MagazineContentIndexProvider` é descoberto no `aculta_index` real (`magazine → node/revista`); projeção do node real com `publishedAt`, `owner_uid` e relação `editorial_image`; `isIndexable()` exige revisão padrão; `getAccessPurpose()` = `magazine`. O filtro de acesso autorizou o node publicado para anônimo e editor, com contexts `domain`, `user.node_grants:view`, `user.permissions`, tags do node, max-age `-1`; a consulta do índice fixa max-age `0`.
- **Views e listagens: PASS com ressalvas.** `page_editorial` responde 200, filtra `status=1` e categoria e não lista o rascunho; a frente da Revista (`/`) renderiza `revista_sessoes`. `/noticias` é alias do node 9 e a listagem daquela página vem de um bloco, não do display `page_noticias`; `/revista` redireciona ao root do propósito. São decisões de conteúdo/rota, não defeitos do Magazine.
- **Catálogo do índice: BLOCKED.** A geração ativa não tem entradas `node/revista` e os itens de `aculta_index_update` são concluídos sem gravação quando não há geração gravável. Backfill/ativação de nova geração é gate operacional do ARQ-03 e exige autorização própria; **nenhum rebuild foi executado**. Não foi criado índice nem catálogo paralelo.

## ARQ-08D - PASS (com achado)

- `ArticleAuthorsHooks` e `PostPresentation`: caixa de autoria, cartão do autor e cinco links de compartilhamento com URL absoluta do host da Revista; SEO com description, og:image, canonical e JSON-LD `NewsArticle` verificados.
- **Discussão:** submissão real pelo formulário (CKEditor 5) por conta `email_confirmed`; o comentário fica pendente ("aguardando uma verificação dos administradores") e invisível ao anônimo; o moderador com `administer comments` vê os pendentes. Comentário do editor também fica pendente porque o papel `content_editor` não possui `skip comment approval` (ver achado). Anexos e galeria da discussão aparecem no formulário; o upload não foi exercitado (pertence ao `aculta_forum`) - DEFERRED.
- **Achado de política:** `content_editor` é isento da regra do primeiro comentário (`papeis_isentos`), mas o Core mantém seus comentários pendentes por falta de `skip comment approval`. Decisão pendente: conceder a permissão ao papel ou documentar a moderação entre editores.

## Achados fora do Magazine (handoff)

1. **Media library (Core 11.4.10):** o botão visível "Inserir selecionado(s)" fica fora do `<form>` (jQuery UI) e o POST chega sem `media_library_select_form[...]`; `MediaLibrarySelectForm::viewsFormValidate()` lança `array_filter(): Argument #1 must be of type array, null given`. Evidência no watchdog (`wid` 254268, 254280, 254581). O teste usa o caminho AJAX equivalente, documentado no README da pasta.
2. **`/noticias`** é alias de node 9 e sombreia o display `page_noticias` - decisão de conteúdo/rota.
3. **`skip comment approval` ausente em `content_editor`** - decisão de política de moderação.

## Limites desta rodada

- Nenhum rebuild/ativação do catálogo do índice; nenhuma importação global de configuração; nenhuma alteração em `aculta_events`, `aculta_calendar`, `aculta_maps` ou `aculta_studio`. No tema, a alteração autorizada ficou restrita ao cartão da listagem (`node--revista--teaser`, library `revista-listing`) e à exposição dos campos no display `teaser`.
- Nenhum bundle, campo, entidade ou workflow novo.
- Contas e conteúdo de teste com prefixo `agente-teste-`/`[AGENTE ARQ-08]` foram criados para a homologação e removidos ao fim da sessão, conforme `docs/portal/GUARDRAILS.md`.
- A execução contínua do script de navegador deve ser repetida em janela sem concorrência para registrar o PASS único; as verificações já executadas estão descritas acima.
