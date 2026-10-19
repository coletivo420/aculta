# Pesquisa e evidências — ACULTA Forum

Revisão: 2026-10-19. Base local: `origin/main` `d4a0d66e`. Este documento
distingue documentação upstream, código instalado e decisões alvo. APIs Drupal
e dependências devem ser rechecadas no momento de cada implementação.

Para a ação contextual da View, Drupal Core 11.x documenta `hook_views_pre_render`
como ponto de inserir `attachment_before`/`attachment_after` antes da renderização;
`AccessManagerInterface::checkNamedRoute(..., TRUE)` fornece o resultado de acesso
cacheável. Código instalado verificado em `views.api.php`, `ViewExecutable.php`,
`AccessManagerInterface.php` e `CacheableMetadata.php`. Referências: [Views pre-render](https://api.drupal.org/api/drupal/core%21modules%21views%21views.api.php/function/hook_views_pre_render/11.x), [AccessManagerInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessManagerInterface.php/interface/AccessManagerInterface/11.x).

O filtro categórico usa o plugin Views `taxonomy_index_tid`; Core 11 suporta
campos `entity_reference` direcionados a `taxonomy_term` e restringe as opções
por vocabulário. A integração e a atualização da tabela `taxonomy_index` foram
verificadas em `TaxonomyViewsHooks.php` e `TaxonomyHooks.php`. Referências:
[plugin TaxonomyIndexTid](https://api.drupal.org/api/drupal/core%21modules%21taxonomy%21src%21Plugin%21views%21filter%21TaxonomyIndexTid.php/11.x),
[integração de Views Taxonomy](https://api.drupal.org/api/drupal/core%21modules%21taxonomy%21src%21Hook%21TaxonomyViewsHooks.php/class/TaxonomyViewsHooks/11.x).

## Estado real do repositório

| Evidência | Constatação | Efeito para o projeto |
| --- | --- | --- |
| `composer show drupal/forum` e `composer.lock` | `drupal/forum` 1.1.3; exige Core `~11.3 || ~12` e `drupal/history:^1` | Pacote contrib está instalado; não remover até fase própria. |
| `composer.json` e `config/sync/core.extension.yml` | Forum é dependência Composer e extensão habilitada na configuração exportada | Mudanças em config/Composer são persistentes e fora de dev.1. |
| `web/modules/contrib/forum` | ForumManager, ForumIndexStorage, controllers, forms, hooks, blocks, routes, validation e config optional | Inventariar APIs/efeitos de uninstall; Forum contém responsabilidades e side effects, não só templates. |
| Config Sync | Node `forum`, taxonomy `forums`, Node field `taxonomy_forums`, Comment bundle/field `comment_forum`, displays, flags e View `aculta_forum_topics` | Conhecer dependências e references antes de remover configuração/dados. |
| `aculta_magazine` | `LegacyPublicationTopicLookup`, `ForumSectionResolver`, `PublicationTypeConverter`, `ForumTopicHooks`, `ForumOpeningPost` preservam/leem vínculos históricos de tópico | F-D25 substitui a discussão por Comment no Node editorial. API de sincronização e backfill removidos em 0.1.0-dev.11; lookup restante é somente leitura até remoção segura dos vínculos. |
| `aculta_user` | participation, `RestrictedForumGrants`, hooks de access/moderação/apresentação, DiscussionHooks e ActivityController referem bundles atuais | Migrar responsabilidades para contexto sem retirar identidade, moderação ou privacidade. |
| `aculta_messenger` | `MessageSubscribeEventHooks` e Aviso/retention atendem eventos de fórum | Reutilizar o único transporte e atualizar produtores/consumidores com idempotência/access. |
| Busca | Search API instalada; config atual não seleciona o Forum nem Comment como fonte pública | Reavaliar índices por contexto, não presumir que a busca cobre tópicos hoje. |
| ACULTA420 | Templates `forum-list`, `node--forum--full`, `comment--comment-forum`; `forum-index.css`, `discussion.css` | Apresentação atual do contrib será substituída pelo tema a seu tempo; identidade é de `aculta_user`. |
| `aculta_user` cards | `PublicProfilePolicy`, `PublicProfileController`, `PublicProfileLink`, `AccountBioCard`, `SocialLinksCard`, `AvatarPool`, `LegacyAuthor*`, amizade/conquistas | Há blocos funcionais reutilizáveis, mas não um AuthorCardBuilder unificado. Ver inventário próprio. |

O código instalado é fonte de verdade para o estado descrito. Os status de Runtime
e de conteúdo não foram consultados nesta fase, portanto nada aqui afirma que a
configuração exportada é idêntica ao servidor.

## Advanced Forum histórico

Advanced Forum 7.x-2.8 é uma referência Drupal 7 histórica que extendia Core
Forum, Views/CTools, Node/Comment e Author Pane. A página do projeto lista
dependências e alerta que linha moderna está sem stable suportada. A feature
inventory upstream cita: overview com busca, marcar tudo lido, ícones e novos
posts; subfóruns/containers, contagens e última atividade; View de tópicos,
fixados, pager, popularidade/views e legends; thread com autor, número/permalink,
respostas encadeadas, next/previous, preview, citações/ações; Views de ativos,
novos, próprios e sem resposta; estatísticas; seis styles (Naked, Blue Lagoon,
Boxy e versões stacked). A lista completa de portabilidade e destino está em
[`ADVANCED-FORUM-PORT.md`](ADVANCED-FORUM-PORT.md).

O pacote `advanced_forum-7.x-2.8.tar.gz` foi inspecionado sem instalação ou
execução (SHA-256 `e6ab783a0562830363370c269c41c3af81e77daf80fe1cd6533c0f29a9a46edd`).
Os arquivos `styles/naked/advanced-forum.naked.author-pane.tpl.php`,
`styles/naked/advanced-forum.naked.post.tpl.php`, `includes/mark-read.inc`,
`includes/views/advanced_forum_*.view` e `advanced_forum.module` confirmam que o
Author Pane histórico podia exibir nome, avatar, online/last seen, título,
badges, data de entrada, total de posts, pontos de gamificação, contato,
mensagem privada, relações e amizade; o template ainda tinha seção administrativa
com IP e Fasttoggle. O post tratava first-new, reply-to, unpublished, permalink,
edição, assinatura, ações e jump-to-top. A ação de marcar lido escrevia
diretamente na tabela `history` do D7. Esses detalhes informam o inventário e as
exclusões de privacidade; IP/controles administrativos e pontos de terceiros não
são campos de cartão público.

Essas capacidades são analisadas como requisitos, não código fonte para executar.
Não portamos PHPTemplate, procedural hooks, configuração de D7, estilos/imagens
históricas nem APIs obsoletas. Variantes de estilo são recompostas com SDC,
Bootstrap, tokens, light/dark e acessibilidade do projeto.

## Melhores práticas/contratos Drupal atuais consultados

| Fonte primária | Evidência consultada | Consequência |
| --- | --- | --- |
| [Comment: administrar comentários em entidades](https://www.drupal.org/docs/8/core/modules/comment/administering-a-content-types-comment-settings) | Comments podem se vincular a entity types por field; field storage é configurado por entidade hospedeira | O candidato comum usa Nodes em todos os contexts, mas storage/bundles devem passar testes Kernel. |
| [Comment API 11.x](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentInterface.php/interface/CommentInterface/11.x) | Comment é entidade de conteúdo do Core, com owner/translation/revision; respeitar contratos nativos | Usar Comment, não uma resposta custom. |
| [Views / Core](https://www.drupal.org/docs/8/core/modules/views) | Views fornece listas configuráveis com query, filtros, sorts e displays | Usar Views, mantendo access/domain/context filtros imutáveis e revisados. |
| [Rotas Drupal](https://www.drupal.org/docs/drupal-apis/routing-system/structure-of-routes) e [DefaultHtmlRouteProvider 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Routing%21DefaultHtmlRouteProvider.php/function/DefaultHtmlRouteProvider%3A%3AgetAddFormRoute/11) | `_entity_form` usa handler de formulário da entidade e `_entity_create_access` usa formato `entity_type:{bundle_parameter}`; confirmado no Core instalado | `/criar-topico` delega formulário e autorização ao Node/Core; não duplica Form API nem concede acesso por papel. |
| [Cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays) e [cache contexts](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-contexts) | Access e cacheability são parte do resultado renderizado; resultados precisam tags/contexts/max-age corretos | Cartões, comentários, aggregates e contexto privado carregam metadados de cache. |
| [Group docs](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/group) | Group gerencia collections de conteúdo/usuários e acesso com membership/roles de grupo | LMS revalida membership/Group access real, sem role global estudante. |
| [Group project/version matrix](https://www.drupal.org/project/group) | Em 2026 releases 3.x suportam Drupal 11; 4.x alpha exige 11.4+ e muda linha de compatibilidade | Confirmar versão instalada/API; não migrar Group ou habilitar adapter sem decisão. |
| [Drupal 11+ standards local](../../../../../../../docs/portal/DRUPAL-11-STANDARDS.md) | DI, `#[Hook]` quando suportado, EntityQuery access explícito, render/access/cache | Normativo do projeto e aplicável a toda fase funcional. |

Pesquisa obrigatória em Drupal.org e Core instalado será repetida na fase de
implementação. A documentação pública não prova a configuração particular do
ACULTA nem substitui inspeção de `web/core`, contrib e testes.

## Referências Advanced Forum

- [Projeto Advanced Forum](https://www.drupal.org/project/advanced_forum): propósito, linha 7.x e dependências históricas.
- [Feature inventory do projeto](https://www.drupal.org/node/841056): funções de listagens, categorias, thread, views e Author Pane.
- [Styling your forum](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-archive/contrib-modules-for-building-the-site-functionality/social-networking-and-collaboration/community-building-and-social-networking-61): seis combinações de estilo e templates históricos.
- [Releases](https://www.drupal.org/project/advanced_forum/releases): 7.x-2.8 é security update/unsupported; ramo 8.x-1.x é desenvolvimento, não baseline para dependência.
- [Pacote-fonte 7.x-2.8](https://ftp.drupal.org/files/projects/advanced_forum-7.x-2.8.tar.gz): snapshot inspecionado para Author Pane, post template, Views e leitura; SHA-256 registrado acima.

## Decisões ainda sujeitas a prova técnica

- Se um único bundle Comment e `field_aculta_discussion` servem todos os Nodes/contextos sem efeitos colaterais em admin, access, history e moderation.
- Como combinar Group access e node access em Views/Search API, com isolamento observável nos casos A/B.
- Mecanismo de leitura/não lidos: avaliar History e entidade/source local; ainda não há decisão de novo storage.
- Modelo de contagens/visualizações e necessidade de agregação persistida/cache.
- Relevância e risco de assinatura, online, shadow topic, views count e next/previous.
- Dependências exatas de Comment fields e uninstall validators na versão instalada.

Cada item deve ser resolvido com fonte upstream e teste no Core/contrib instalado,
sem afirmar que não confirmado. A fase dev.1 registra pendências em vez de
codificar hipótese.
