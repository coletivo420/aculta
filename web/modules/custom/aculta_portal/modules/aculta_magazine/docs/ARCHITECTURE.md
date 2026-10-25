# ACULTA Magazine — arquitetura

**Versão MAG 0.5-A · 2026-10-22**. Documento normativo do submódulo `aculta_magazine`.
Complementa [AUDIT-MAG-0.5-A.md](AUDIT-MAG-0.5-A.md), [DEPENDENCY-MATRIX.md](DEPENDENCY-MATRIX.md) e o contrato
editorial do Studio em [STUDIO-INTEGRATION-CONTRACT.md](STUDIO-INTEGRATION-CONTRACT.md).

## 1. Fronteira de ownership

O Magazine é dono de **conteúdo editorial de Revista**. Ele não é dono de:

| Área | Dono | O Magazine apenas consome |
| --- | --- | --- |
| Navegação, “Meu trabalho”, criação, edição, acompanhamento no GanjaLab | `aculta_studio` | plugins `StudioTab` e listagens |
| Identidade, autoria, coautoria, perfis e política de primeira contribuição | `aculta_user` | `author_card_builder`, permissões compartilhadas |
| Discussão contextual e moderação de comentários | `aculta_forum` | `field_aculta_discussion`, provider de contexto |
| Domínio, propósito, URL canônica e tokens de site | `aculta_portal` | `DomainPurposeManager`, `TokenHooks` |
| Componentes visuais, tokens, WCAG | `aculta420` | SDC `revista-publication` |
| Tratamento e formatação de imagem | `aculta_image` | filtro `aculta_imagem_modelo` |
| Galerias incorporadas | `aculta_gallery` | Media Gallery no CKEditor 5 |

Regra: o Magazine **nunca** duplica storage, permissões, navegação ou CSS de outro dono. Não existe tabela,
field storage ou serviço de agregação próprio do Magazine além dos dois serviços listados em §5.

## 2. Modelo de conteúdo

- Um único bundle: `revista` (`RevistaPublications::CANONICAL_BUNDLE`). `article` e `blog` estão aposentados;
  a migração concluída está registrada em `MIGRATION-ARTICLE-BLOG-TO-REVISTA.md`; o utilitário executável foi removido.
- `new_revision: TRUE`, `preview_mode: 1`, `display_submitted: FALSE`.
- Campos próprios: chamada (`field_subtitle`), resumo (`field_summary`), imagem editorial (`field_media_image`,
  obrigatória), créditos, referências (link), atividade relacionada, autor editorial e categoria editorial
  (taxonomias), etiquetas, publicado em (`field_published_at`), metatag, domínio de origem e discussão.
- `MagazineContentIndexProvider` descreve a identidade por tradução da revisão padrão, publicação e domínio,
  além das relações explicitamente selecionadas `related_activity` e `editorial_image`. A projeção não copia
  corpo, resumo, autoria, workflow detalhado ou conteúdo do arquivo; o catálogo não é API de acesso.
- `body` usa o formato `revista_editorial` (HTML editorial completo; o filtro `filter_html` está desligado, a
  sanitização depende do CKEditor 5 e das permissões de formato).
- Displays: `default` (administrativo), `full` (tema, via SDC), `teaser` (listagens). Campos virtuais do Entity
  Print ficam ocultos; a dependência de `entity_print` é opcional e não está declarada no `.info.yml`.

## 3. Fluxo editorial

Workflow `revista` (Content Moderation), estados e transições **exatamente** como configurados:

| Transição | De | Para | Rótulo | Peso |
| --- | --- | --- | --- | --- |
| `create_new_draft` | `draft`, `published` | `draft` | Salvar rascunho | 0 |
| `publish` | `draft`, `published` | `published` | Publicar | 1 |
| `archive` | `published` | `archived` | Arquivar | 2 |
| `draft_to_review` | `draft` | `review` | Enviar para revisão | 2 |
| `archived_draft` | `archived` | `draft` | Restaurar rascunho | 3 |
| `review_to_draft` | `review` | `draft` | Devolver para correção | 3 |
| `archived_published` | `archived` | `published` | Restaurar publicação | 4 |
| `review_to_published` | `review` | `published` | Publicar | 4 |

- Publicar direto do rascunho é permitido por `publish` (`draft → published`), sem passar por `review`.
- Não existe estado nem transição “rejeitado”. Não inventar.
- Transições e permissões são exclusivas de `content_editor`; `authenticated` não possui nenhuma.
- Publicação é sempre uma transição manual; `field_published_at` é metadado editorial, não agendador.
- A revisão padrão após publicar é a `published`; rascunhos e revisões de revisão não são públicos.

## 4. Hooks e integração Form API

| Classe | Hook | Responsabilidade |
| --- | --- | --- |
| `RevistaFormHooks` | `form_alter` em `node_revista_form` / `node_revista_edit_form` | agrupa propriedades secundárias em `details` fechado (`#weight 90`), anexa `aculta_magazine/revista-form` |
| `ArticleAuthorsHooks` | `node_view` | injeta `article_authors` no display `full` publicado, no propósito `magazine` |
| `PublicationDiscussionHooks` | `node_load` | aplica o default de discussão em memória quando o campo vem ausente |

Os hooks declarados usam atributos `#[Hook(...)]` (Drupal 11). Nenhum usa `\Drupal::` estático.

O Magazine é dono dos campos, validações e do agrupamento. O Studio pode apresentar o formulário nativo e o
feedback de validação; não pode criar um segundo editor com validação própria.

## 5. Serviços

| Serviço | Classe | Dependências | Função |
| --- | --- | --- | --- |
| `aculta_magazine.post_presentation` | `Presentation\PostPresentation` | `date.formatter`, `entity_type.manager`, `aculta_portal.domain_purpose` | cabeçalho de publicação (autor/data/hora) e ações de compartilhamento; cacheável |
| `aculta_magazine.content_index_provider` | `ContentIndex\MagazineContentIndexProvider` | contrato neutro do Portal | projeção allowlist de metadados da revisão padrão e relações explícitas; descoberto pelo `aculta_index` opcional |

Não há event subscriber, plugin manager, controller ou rota própria: as rotas administrativas vêm de Views e as rotas de conteúdo são as do Core.

## 6. Configuração

- `config/install/`: duas Views administrativas e o bloco de comentários.
- `config/sync`: tipo de conteúdo, workflow, displays, campos, Metatag, Pathauto, Simple Sitemap, busca indexada.
- Não há `config/optional`. O módulo **não** executa importação de configuração em update hooks.
- Divergências conhecidas entre Sync e Runtime: ver §4 de `AUDIT-MAG-0.5-A.md`.

## 7. Apresentação e tema

- `node--revista--full.html.twig` do tema ACULTA420 consome `aculta420:revista-publication`
  (`status: experimental`) e passa `meta`, `authors`, `discussion` pelos slots.
- O Magazine não possui template, CSS ou biblioteca de apresentação próprios além de `revista-form.js`.
- O tema ainda mantém templates de `article`/`blog`; são obsoletos e pertencem ao ACULTA420.

## 8. Integração com o Studio

O Studio consome o Magazine por três vias reais, sem acoplamento de storage:

1. `RevistaTab` (`StudioTab`): área, seções e ações; permissões verificadas via `AccessResult`.
2. `StudioListingBuilder`: listagem `scope: mine` com `accessCheck(TRUE)` e checagem por revisão.
3. Rotas do Core: `node.add` (purpose `magazine`) e `entity.node.edit_form`.

O contrato detalhado, incluindo atores, estados e o que o GanjaLab pode ou não consumir, está em
`STUDIO-INTEGRATION-CONTRACT.md` e `EDITORIAL-ACCESS-MATRIX.md`. O Magazine não implementa navegação de Studio,
cabeçalho, formulário próprio nem fila de moderação.

## 9. Invariantes

1. Um único bundle editorial: `revista`.
2. Nenhuma listagem de conteúdo sem Entity Access; proibido `accessCheck(FALSE)` para alimentar o Studio.
3. Nenhuma permissão genérica substituindo Entity/Revision Access.
4. Nenhum estado ou transição fora do workflow configurado.
5. Nenhuma duplicação de autoria/coautoria, discussão ou domínio: consumir o dono.
6. Nenhum componente visual fora do ACULTA420.
7. Configuração do Magazine só muda com evidência; importação global é proibida.
8. A projeção de catálogo não inclui corpo, resumo, autoria, arquivos ou workflow detalhado; toda exposição revalida acesso do Node original.

## 10. Testes

| Tipo | Arquivo | Cobre |
| --- | --- | --- |
| Unit | `RevistaPublicationsTest` | reconhecimento estrito do bundle |
| Unit | `PublicationDiscussionHooksTest` | default de discussão e limites |
| Unit | `MagazineAdminViewsConfigTest` | Views administrativas e `config/install` |
| Unit | `LegacyForumUpdatesTest` | hooks históricos 10001–10003 são no-op para conteúdo novo |

A matriz de autorização editorial (atores × estados × ações) é coberta em 0.5-B por Kernel/Unit adicionais; testes
estáticos não substituem validação de access do Drupal.

## 11. Integração com avisos do ACULTA Messenger

O Magazine tem um único bundle `revista`, revisão editorial no workflow (`draft`, `review`, `published`, `archived`), autor em `node.uid` e coautores em `co_authors`. A publicação pode resultar de `publish` (`draft` → `published`) ou `review_to_published` (`review` → `published`). Comentários não definem a audiência de avisos editoriais.

Decisão de produto aprovada em 2026-10-25: avisos podem alcançar participantes editoriais ou pessoas que seguem uma publicação, conforme o fato ocorrido. A administração do Messenger definirá os eventos editoriais habilitados e os canais permitidos. O aviso no site é obrigatório; e-mail segue a preferência da conta e a política administrativa compartilhada. Os fatos candidatos são envio de revisão, resultado editorial e publicação/republicação. Não avisar em salvamento comum, rascunho, comentário ou arquivamento, salvo habilitação explícita de um evento editorial pela administração. O autor/ator não recebe aviso de sua própria ação; autoria e coautoria continuam sendo resolvidas pelos campos atuais.

A audiência de publicação inclui seguidores ativos da publicação; a audiência de eventos editoriais inclui somente autores/coautores ou editores elegíveis para aquele fato. A assinatura expressa deverá usar o mecanismo de Flag/Entity já instalado, sem conceder acesso ao conteúdo. Seguir nunca cria conversa, thread ou membership. As regras de acesso, revisão, Domain/Purpose e estado publicado são revalidadas ao emitir, abrir e entregar cada aviso.

`aculta_magazine` publica fatos de workflow após sua persistência e mantém a ownership editorial. `aculta_messenger` é dono do adapter, Message, deduplicação, central, preferências, entrega e revalidação de acesso; `aculta_mailer` permanece dono do transporte. A categoria compartilhada `revista` deve estar configurada antes da entrega de e-mail. Não criar storage de avisos, caixa ou transporte no Magazine. A implementação desta integração e do acompanhamento de publicações ainda está pendente; este contrato substitui a decisão anterior que adiava destinatários por falta de definição de produto.
