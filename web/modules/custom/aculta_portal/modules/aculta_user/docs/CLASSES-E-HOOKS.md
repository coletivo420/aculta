# Classes e hooks — aculta_user

Identidade, perfil, avatar, amizade, associados (marcador), autoria histórica (memorial), restrição de fóruns (F9) e moderação de denúncias. Ver também `docs/` e o `CHANGELOG.md` do módulo.

> O Runtime de teste não contém Nodes `forum`, Comments `comment_forum`, termos
> `forums` ou vínculos editoriais antigos desde a limpeza F-D33 (2026-10-20).
> Alguns guards permanecem somente até uninstall do contrib e retirada de
> consumidores; ver [MIGRATION-REMOVAL.md](../../aculta_forum/docs/MIGRATION-REMOVAL.md).

Atualizado a partir do código em 2026-10-27 (`src/`). Cada classe aparece com a primeira frase do próprio docblock; a regra e o motivo completos ficam no código. Quando uma classe mudar, atualize esta tabela na mesma entrega.

## Hooks (`#[Hook]`)

| Hook | Implementação | Arquivo |
| --- | --- | --- |
| `preprocess_username` | `UsernameLinkHooks::preprocessUsername` | `src/Hook/UsernameLinkHooks.php` |
| `preprocess_field` (campos de referência a usuário) | `UsernameLinkHooks::preprocessField` | `src/Hook/UsernameLinkHooks.php` |
| `aculta_portal_dashboard_alter` | `AccountDashboardHooks::dashboardAlter` | `src/Hook/AccountDashboardHooks.php` |
| `aculta_portal_photo_editor_alter` | `PhotoEditorHooks::photoEditorAlter` | `src/Hook/PhotoEditorHooks.php` |
| `aculta_portal_changemail_alter` | `ChangemailHooks::changemailAlter` | `src/Hook/ChangemailHooks.php` |
| `aculta_portal_settings_tabs_alter` | `SettingsTabsHooks::settingsTabsAlter` | `src/Hook/SettingsTabsHooks.php` |
| `comment_presave` | `ParticipationHooks::commentPresave` | `src/Hook/ParticipationHooks.php` |
| `cron` | `AvatarCronHooks::cron` | `src/Hook/AvatarCronHooks.php` |
| `entity_access` | `ForumModerationHooks::entityAccess` | `src/Hook/ForumModerationHooks.php` |
| `entity_presave` | `ForumModerationHooks::closeGrowroomHistoricDiscussion` | `src/Hook/ForumModerationHooks.php` |
| `form_comment_admin_overview_alter` | `ForumModerationHooks::commentAdminOverviewAlter` | `src/Hook/ForumModerationHooks.php` |
| `entity_create_access` | `ParticipationHooks::entityCreateAccess` | `src/Hook/ParticipationHooks.php` |
| `form_user_form_alter` | `PhotoEditorHooks::formUserFormAlter` | `src/Hook/PhotoEditorHooks.php` |
| `module_preuninstall` | `FriendshipLifecycleHooks::protectFriendshipData` | `src/Hook/FriendshipLifecycleHooks.php` |
| `entity_view` | `PublicForumTopicAuthorHooks::entityView` | `src/Hook/PublicForumTopicAuthorHooks.php` |
| `entity_view` (Comment) | `DiscussionCommentAuthorHooks::entityView` | `src/Hook/DiscussionCommentAuthorHooks.php` |
| `views_data_alter` | `AuthorCardViewsDataHooks::viewsDataAlter` | `src/Hook/AuthorCardViewsDataHooks.php` |
| `page_attachments` | `PhotoFlowPageHooks::pageAttachments` | `src/Hook/PhotoFlowPageHooks.php` |
| `user_delete` | `FriendshipLifecycleHooks::removeRelationshipsForDeletedMember` | `src/Hook/FriendshipLifecycleHooks.php` |
| `user_insert` | `AccountCreationHooks::userInsert` | `src/Hook/AccountCreationHooks.php` |
| `user_presave` | `AccountCreationHooks::userPresave` | `src/Hook/AccountCreationHooks.php` |
| `user_presave` | `PictureReplacementHooks::userPresave` | `src/Hook/PictureReplacementHooks.php` |
| `user_update` | `PictureReplacementHooks::userUpdate` | `src/Hook/PictureReplacementHooks.php` |

### Herança de papéis (0.4.0-dev.22–23)
- `user_load`, `user_presave`, `form_user_form_alter`, `user_role_delete` — `Hook/RoleInheritanceHooks.php`. Ver `HIERARQUIA-DE-FUNCOES.md`.

## Classes por pasta

### `Access`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountModalAccess` | `src/Access/AccountModalAccess.php` | Edições de perfil (bio e redes sociais) só existem dentro do modal: abertura por AJAX (drupal_modal) ou envio do próprio formulário. |

### `Account`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountNames` | `src/Account/AccountNames.php` | Regras de apelido e de nome de usuário (plano aculta_user, seção B1 e decisões de cadastro). |

### `Associado`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AssociadoMarker` | `src/Associado/AssociadoMarker.php` | Marca e desmarca associados na tabela própria (ACULTA Associados, marcador puro, 2026-10-16). |

### `Avatar`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AvatarAssetCleanup` | `src/Avatar/AvatarAssetCleanup.php` | Limpeza de assets temporários de avatar (chamada pelo cron do aculta_user). |
| `AvatarPool` | `src/Avatar/AvatarPool.php` | Catálogo do acervo: atribui uma cópia a `user_picture` na criação/remoção/reroll (`user.data.avatar_id`), controla a escolha pendente do modal e fornece `fallbackEntry`/`previewUrl` para apresentação sem foto. |
| `AvatarResolver` | `src/Avatar/AvatarResolver.php` | Regra única de apresentação: foto pessoal quando a policy libera; avatar escolhido do acervo (`public://pictures/acervo/`) mesmo sem opt-in; senão fallback do acervo (`fallbackEntry`/`previewUrl`); senão `NULL` defensivo. Nunca usa a inicial. |

### `Badge`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AchievementDefinitions` | `src/Badge/AchievementDefinitions.php` | Catálogo configurável de critérios fechados; User preserva definições e fatos. */ |
| `BadgeCatalog` | `src/Badge/BadgeCatalog.php` | Identificadores estáveis das conquistas existentes. |
| `BadgeService` | `src/Badge/BadgeService.php` | Concede e revoga conquistas. Idempotente: não duplica concessão ativa. |

### `Bio`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountBioCard` | `src/Bio/AccountBioCard.php` | Caixa Bio da Minha conta: textarea bloqueado com a bio (ou o aviso quando vazia) e o lápis que abre o modal. |

### `Controller`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountBioSaveController` | `src/Controller/AccountBioSaveController.php` | Gravação da bio pelo modal (JSON). O JS do formulário chama esta rota, atualiza a caixa e fecha o modal. |
| `ActivityController` | `src/Controller/ActivityController.php` | Atividade da própria pessoa em Minha Conta; respostas combinam Comments e, quando instalada, a entidade revisionável do Fórum, ordenadas juntas e com acesso revalidado no purpose `forum`. |
| `AssociadosController` | `src/Controller/AssociadosController.php` | Relatório de associados (plano aculta_user, seção G2). |
| `AvatarRerollController` | `src/Controller/AvatarRerollController.php` | Sorteia outro avatar do acervo para a própria conta (botão do popup de foto). |
| `ChangemailRedirectController` | `src/Controller/ChangemailRedirectController.php` | Caminhos legados `/seguranca/email` e `/seguranca/email/{user}`: `302` para a aba E-mail da Segurança, só para a própria conta. Ver [TROCA-DE-EMAIL](TROCA-DE-EMAIL.md). |
| `LegacyMemorialController` | `src/Controller/LegacyMemorialController.php` | Memorial coletivo das autorias históricas do Fórum em /u/lendashistoricas (ACULTA Fórum, F2, F-D07). |
| `PublicProfileController` | `src/Controller/PublicProfileController.php` | Página pública do perfil em /u/<apelido>. |
| `ReportQueueController` | `src/Controller/ReportQueueController.php` | Renderiza a fila unificada no escopo Fórum; marca o container e o pager com tokens neutros `data-portal-ui` para o tema mapear a apresentação. Mantém ACL, conteúdo, links e cache no owner Portal/UGC. |
| `SocialLinkSaveController` | `src/Controller/SocialLinkSaveController.php` | Gravação do link de uma rede social pelo modal (JSON). O JS do formulário atualiza a página depois. |

### `Entity`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AcultaFriendship` | `src/Entity/AcultaFriendship.php` | Defines the private relationship record shared by a member pair. */ |
| `AcultaFriendshipAccessControlHandler` | `src/Entity/AcultaFriendshipAccessControlHandler.php` | Restricts relationship records to their two participants. */ |
| `AcultaFriendshipInterface` | `src/Entity/AcultaFriendshipInterface.php` | Stores one directional request and its state for a member pair. */ |
| `AssociadoMarcador` | `src/Entity/AssociadoMarcador.php` | Marcador de associado (decisão de 2026-10-16). |
| `AssociadoMarcadorAccessControlHandler` | `src/Entity/AssociadoMarcadorAccessControlHandler.php` | Marcador de associado: só a administração de associados acessa a tabela (ACULTA Associados, marcador puro). |
| `BadgeAward` | `src/Entity/BadgeAward.php` | Conquista concedida a uma pessoa (plano aculta_user, seção G). |
| `LegacyAuthor` | `src/Entity/LegacyAuthor.php` | Autoria histórica do Fórum (ACULTA Fórum, F2). |
| `LegacyAuthorAccessControlHandler` | `src/Entity/LegacyAuthorAccessControlHandler.php` | Acesso à autoria histórica: só quem administra as autorias legadas (ACULTA Fórum, F2). |

### `EventSubscriber`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `SocialAuthFieldsSubscriber` | `src/EventSubscriber/SocialAuthFieldsSubscriber.php` | Conta criada por OAuth recebe o nome de usuário a partir da parte local do e-mail recebido, sem o domínio. |

### `Form`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountBioForm` | `src/Form/AccountBioForm.php` | Modal de edição da bio (campo field_bio do perfil participante). |
| `ChangemailForm` | `src/Form/ChangemailForm.php` | Pedido de troca de e-mail na aba E-mail da Segurança. Confere a senha atual (com limite de tentativas), recusa e-mail igual ao atual e deixa a confirmação ao aculta_mail_confirmer_user. Ver [TROCA-DE-EMAIL](TROCA-DE-EMAIL.md). |
| `AssociadoSituacaoForm` | `src/Form/AssociadoSituacaoForm.php` | Administrador define a situação de associado (ativo, suspenso, desligado) ou recusa um pedido. Só administrador. |
| `AssociadoRequestForm` | `src/Form/AssociadoRequestForm.php` | Pessoa com e-mail confirmado pede adesão em `/associado`. Não concede nada. |
| `AvatarCropForm` | `src/Form/AvatarCropForm.php` | Popup de recorte da foto personalizada. |
| `FriendshipActionForm` | `src/Form/FriendshipActionForm.php` | Presents the current member's allowed action for a profile relationship. */ |
| `ParticipationSettingsForm` | `src/Form/ParticipationSettingsForm.php` | Painel de regras de participação (plano aculta_user, seção E7). |
| `PhotoEditorGuard` | `src/Form/PhotoEditorGuard.php` | Regras do popup de foto quando a foto atual vem do acervo do sistema (não é foto enviada pela pessoa). |
| `ProfileVisibilityForm` | `src/Form/ProfileVisibilityForm.php` | Minha conta > Configurações > Privacidade. |
| `SocialLinkForm` | `src/Form/SocialLinkForm.php` | Modal de edição do link de uma rede social (Minha conta). Salva pela rota JSON aculta_user.redes_save. |
| `UserPictureForm` | `src/Form/UserPictureForm.php` | Formulário de foto do popup do painel, em rota própria. |

### `Forum`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |

### `Identity`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AuthorIdentity` | `src/Identity/AuthorIdentity.php` | View-model escalar mínimo com cache tags/contexts da identidade pública. |
| `AuthorIdentityResolver` | `src/Identity/AuthorIdentityResolver.php` | Resolve identidade pública, variante editorial com fallback de nome, dados editoriais autorizados e `resolveFallback()` (nome de exibição + acervo) para quem não tem perfil público. |
| `PublicMemberIdentityInterface` | `src/Identity/PublicMemberIdentityInterface.php` | Expõe só apelido permitido pela política pública; consumidores não usam o nome interno da conta como fallback. |
| `AuthorCardBuilder` | `src/Identity/AuthorCardBuilder.php` | Gera render array neutro de cartão; `build()` omite sem identidade pública, `buildFallback()` monta nome + acervo para roster/feedback e `buildLegacy*()` monta a autoria histórica. |
| `AuthorCardVariantResolver` | `src/Identity/AuthorCardVariantResolver.php` | Resolve variant de Comment pelo bundle canônico do host (`revista`, Fórum, LMS ou Wiki); contextos desconhecidos não herdam cartão. |
| `AuthorCardThemeHooks` | `src/Hook/AuthorCardThemeHooks.php` | Declara hook neutro do cartão e mantém fallback sem estilo. |

`DiscussionCommentAuthorHooks::entityView` usa `CommentInterface::getCommentedEntity()`
e o resolver de variantes. Fórum e respostas usam `author_forum`; Revista usa
`author_comment`; os bundles previstos de LMS e Wiki usam `author_lms` e
`author_wiki`. Ver `AUTHOR-CARD-VARIANTS.md`.

### `Hook`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountCreationHooks` | `src/Hook/AccountCreationHooks.php` | Criação de conta: perfil participante com apelido inicial, e avatar do acervo quando não há foto. |
| `AccountDashboardHooks` | `src/Hook/AccountDashboardHooks.php` | Caixa de bio na visão geral da Minha conta. Substitui o resumo de Cursos (aculta_portal). |
| `AvatarCronHooks` | `src/Hook/AvatarCronHooks.php` | Cron do aculta_user: limpeza de assets temporários de avatar (ver AvatarAssetCleanup). |
| `ForumModerationHooks` | `src/Hook/ForumModerationHooks.php` | Moderação confirmada de conteúdo ativo; congela Nodes ACULTA importados com autoria Growroom histórica e mantém Comments contextuais fora da fila geral, validando seleções em lote. |
| `FriendshipLifecycleHooks` | `src/Hook/FriendshipLifecycleHooks.php` | Maintains friendship data through account deletion and module lifecycle. */ |
| `ParticipationHooks` | `src/Hook/ParticipationHooks.php` | Modera comentários iniciais nos contextos públicos compartilhados, exclui LMS, bloqueia novos Nodes do bundle legado e aplica limite configurável de criação de tópico. |
| `PhotoEditorHooks` | `src/Hook/PhotoEditorHooks.php` | Editor de foto do painel (ponto de extensão aculta_portal_photo_editor e formulário do painel). |
| `PhotoFlowPageHooks` | `src/Hook/PhotoFlowPageHooks.php` | Anexa o fluxo do popup de foto (aculta_user/photo-flow) na página do painel e informa a foto salva ao script. |
| `PictureReplacementHooks` | `src/Hook/PictureReplacementHooks.php` | Apaga a foto anterior enviada pela pessoa quando ela troca ou remove (economia de disco); ao remover a foto pessoal, devolve a conta ao acervo. Nunca apaga cópia do acervo. |
| `SettingsTabsHooks` | `src/Hook/SettingsTabsHooks.php` | Aba "Privacidade" em Minha conta > Configurações (o Portal monta as abas e chama o alter). |

### `Legacy`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `LegacyAuthorManager` | `src/Legacy/LegacyAuthorManager.php` | Cria ou atualiza uma autoria histórica do Fórum (ACULTA Fórum, F2). |

### `Moderation`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ReportQueue` | `src/Moderation/ReportQueue.php` | Fila exclusiva de denúncias do Fórum público; carrega Node, Comment e `aculta_forum_post` em lotes, valida purpose do tópico e exclui conteúdo histórico e contextos externos sem dependência de `aculta_forum` (dev.66). |

### `Social`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `FriendshipManager` | `src/Social/FriendshipManager.php` | Implements the ACULTA member friendship workflow. */ |
| `FriendshipManagerInterface` | `src/Social/FriendshipManagerInterface.php` | Public API for member friendship requests. */ |
| `FriendshipStatus` | `src/Social/FriendshipStatus.php` | States for an ACULTA member relationship. */ |
| `SocialLinksCard` | `src/Social/SocialLinksCard.php` | Caixa "Redes sociais" da Minha conta, embaixo da bio: uma linha por rede, com ícone, valor atual e lápis. |
| `SocialNetworks` | `src/Social/SocialNetworks.php` | Redes sociais do perfil: Instagram, Facebook e Reddit. |
| `SocialNetworksInterface` | `src/Social/SocialNetworksInterface.php` | Contrato de leitura das redes; as URLs existentes são revalidadas por esquema e host permitido. |

### `Plugin`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AuthorCard` | `src/Plugin/views/field/AuthorCard.php` | Campo de Views `aculta_user_author_card`: renderiza o cartão por linha, carrega a conta pelo `uid` quando o cache da query não traz `_entity` e aceita variante/tamanho. |

### `src`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ParticipationRules` | `src/ParticipationRules.php` | Contagens via EntityQuery e regras de participação para comentários públicos compartilhados; LMS é excluído. |
| `PublicProfilePolicy` | `src/PublicProfilePolicy.php` | Decide, campo a campo, o que do perfil participante pode aparecer em público. |
| `PublicProfileLink` | `src/PublicProfileLink.php` | Única regra de URL canônica do perfil público; atualiza a cache metadata fornecida pelo consumidor. |
| `PublicProfileLinkInterface` | `src/PublicProfileLinkInterface.php` | Contrato para resolver e testar links de perfil sem substituir a implementação canônica. |

### Role/
- `HerancaDePapeis.php` — mapa derivado de `aculta_portal.role_hierarchy`; herdados; papéis não gravados; remoção de papel excluído.

### EventSubscriber/
- `RoleInheritanceCurrentUserSubscriber.php` — estende o usuário logado (REQUEST, prioridade 299).
