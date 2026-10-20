# Cartões de identidade e autoria

Status: cartões de `author_forum` integrados aos tópicos e respostas do bundle novo na linha
`aculta_user 0.4.0-dev.40`. `aculta_user` possui políticas,
links, perfil, avatar e memória histórica. `AuthorIdentityResolver`,
`AuthorIdentity` e `AuthorCardBuilder` compõem identidade, avatar cacheável e
render array neutro; ACULTA420 apresenta o cartão SDC. Ações e dados adicionais
seguem responsabilidade de consumidores autorizados, sem duplicação no Forum.

## Inventário atual

| Componente atual | Capacidade | Consumidor atual | Variante alvo | Ação |
| --- | --- | --- | --- | --- |
| `PublicProfileController` + `PublicProfilePolicy` | Perfil público, visibilidade campo a campo, foto e dados do perfil | `/u/{apelido}` | `profile_full` | Preservar e estender view-model/cache para os campos já autorizados; não tornar privado público por conveniência. |
| `PublicProfileLink` + `UsernameLinkHooks` | URL atual por apelido; perfil sem visibilidade fica sem link | Tema Username e campos de referência User | Todas as variantes atuais | Preservar como resolução canônica. |
| `AuthorIdentityResolver` + `AuthorIdentity` + `AuthorCardBuilder` | Apelido/link, avatar governado por policy, ano de conta pública nos cartões de fórum e render array com cache | Cartão neutro compartilhável | Base compartilhada das variantes User | Preservar; somente acrescentar dados aprovados e cacheáveis; não aceitar autoria legada como User. |
| `AccountBioCard` | Render da bio da própria conta | dashboard `/identidade` | não é cartão público | Preservar; separar claramente apresentação da área própria. |
| `SocialLinksCard` + `SocialNetworks` | Redes sociais com dados de Profile | dashboard e perfil público | `profile_full`, apenas com opt-in | Preservar; aplicar política de visibilidade e sanitização existentes. |
| `AvatarPool`, `UserPictureForm`, `aculta_image` | Avatar do acervo/foto da pessoa e edição | User/profile e componentes de identidade | Todas as variantes | Preservar. Qualquer nova URL/imagem passa por `aculta_image` e finalidade aprovada. |
| `AccountNames` e Profile principal | Apelido/nome público e perfis por função | Account, links, forms | Todas as variantes | Reutilizar; jamais derivar URL de perfil de UID em componente visual. |
| `LegacyAuthor`, `LegacyAuthorManager`, `LegacyAuthorAccessControlHandler` | Autor histórico sem User ativo; acesso administrativo | autoria importada e memorial | `author_growroom_historico` | `AuthorCardBuilder::buildLegacy()` entrega nome, rótulo de origem e cache sem User/login/amizade/mensagem. |
| `LegacyMemorialController` | Memorial coletivo `/u/lendashistoricas` | Growroom histórico | `author_growroom_historico` | Preservar link coletivo; sem página individual. |
| `FriendshipManager` / `FriendshipManagerInterface` | Estado e ações de amizade | perfil e Messenger | `author_forum`, `author_comment`, `profile_full` quando autorizado | Reusar API existente; ocultar ação quando inelegível. |
| `BadgeService` / `AchievementDefinitions` | Conquistas e fatos | perfil/Game | variantes autorizadas | Reusar sem conceder prémios no render e respeitar privacidade de conquistas. |
| `DiscussionCommentAuthorHooks` em `aculta_user` | Cartão compartilhado de autoria de Comment | Fórum, Revista e contextos futuros | Variante resolvida pelo host: `author_forum`, `author_comment`, `author_lms` ou `author_wiki` | Contextos desconhecidos não herdam variante. Autoria histórica usa builder separado. |
| `PublicForumTopicAuthorHooks` | Cartão da autoria User ou histórica nos tópicos novos | Node `aculta_forum_topic`, `full`/`teaser` | `author_forum` / `author_growroom_historico` | Integrado pelo `AuthorCardBuilder`; não consulta campos Profile diretamente e não converte autores históricos em User. |
| `ForumModerationHooks`, `RestrictedForumHooks`, `RestrictedForumGrants`, `ParticipationRules` | Acesso/moderação ainda ligados aos bundles antigos | Forum contrib | `author_forum` + política contextual | Refatorar/migrar conforme paridade e ownership da nova API. |
| `ActivityController` | Aba de atividade Wiki/Fórum/Revista/Comentários/conquistas | perfil/conta | `profile_compact` ou list views | Estender a nova fonte de tópico/comentário; não duplicar consulta de perfil. |
| `comment--comment-forum.html.twig` | Avatar/nome/data/resposta em cartão de mensagem | Comment contrib | `author_forum` via tema | Ainda usa saída legada; integrar ao novo builder junto do corte do Forum contrib, sem duplicar dados no tema. |
| `node--forum--full.html.twig`, `forum-list.html.twig` | Thread e listagem | Forum contrib | `author_forum` e cards de discussão | Substituir apenas com implementação do novo motor, fora desta fase. |
| `revista-publication` slot **Author box**, `node--article--full`/`node--blog--full` | Autoria editorial | Magazine/ACULTA420 | `author_editorial` | Preservar o cartão editorial existente; adaptar slot para API central quando implementada. |
| SDCs `social-links-page`, `editorial-card`, `course_card` | Perfil social, autor editorial/curso | ACULTA420 | várias | Reutilizar onde a função coincide; não declarar que já são Author Cards genéricos. |

## Ações por caso

- **Preservar:** Profile/User, política pública, links canônicos, amizades, AvatarPool, memória histórica e cartão editorial existente.
- **Estender:** serviços de identidade em `aculta_user` para compor view-model autorizado e cacheável; criar contratos de variantes.
- **Refatorar:** acessos Forum-specific hoje em `aculta_user`; comentários e atividade para o provider comum.
- **Substituir:** renderização presa a `forum`/`comment_forum` depois da implementação equivalente por contexto.
- **Remover:** somente após verificar consumidores, paridade e documentação histórica; jamais apagar cadastro/autor histórico durante retirada do módulo Forum.

## Dados a preparar

Cada cartão recebe um sujeito (User ou autoria histórica) e contexto de uso.
`aculta_user` decide quais campos podem ser entregues e retorna build/view-model
com cacheability. Identidade pública, apelido, avatar, biografia, redes sociais,
conquistas, datas e estatísticas são opcionais e sujeitos a acesso/visibilidade.
Forum não carrega Profile/User field diretamente. Preferir URLs `Url`/render arrays
preparadas pelo Portal; o tema não recebe entidades.

API conceitual e critérios: [`AUTHOR-CARD-API.md`](AUTHOR-CARD-API.md),
[`AUTHOR-CARD-VARIANTS.md`](AUTHOR-CARD-VARIANTS.md),
[`AUTHOR-PANE-PORT.md`](AUTHOR-PANE-PORT.md) e
[`PRIVACY-AND-ACCESS.md`](PRIVACY-AND-ACCESS.md).
