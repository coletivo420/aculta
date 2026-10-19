# Classes e hooks — aculta_user

Identidade, perfil, avatar, amizade, associados (marcador), autoria histórica (memorial), restrição de fóruns (F9) e moderação de denúncias. Ver também `docs/` e o `CHANGELOG.md` do módulo.

> As entradas Forum nesta referência descrevem a implementação atual e seus
> consumidores dos bundles `forum`/`comment_forum`. A reconstrução aprovada em
> 2026-10-19 os substituirá por contratos contextuais e cartões de autoria
> preparados por `aculta_user`; ver [AUTHOR-CARDS.md](AUTHOR-CARDS.md) e
> [MIGRATION-REMOVAL.md](../../aculta_forum/docs/MIGRATION-REMOVAL.md). Não remova nem renomeie hooks nesta
> fase documental.

Gerado a partir do código em 2026-10-17 (`src/`). Cada classe aparece com a primeira frase do próprio docblock; a regra e o motivo completos ficam no código. Quando uma classe mudar, atualize esta tabela na mesma entrega.

## Hooks (`#[Hook]`)

| Hook | Implementação | Arquivo |
| --- | --- | --- |
| `preprocess_username` | `UsernameLinkHooks::preprocessUsername` | `src/Hook/UsernameLinkHooks.php` |
| `preprocess_field` (campos de referência a usuário) | `UsernameLinkHooks::preprocessField` | `src/Hook/UsernameLinkHooks.php` |
| `aculta_portal_dashboard_alter` | `AccountDashboardHooks::dashboardAlter` | `src/Hook/AccountDashboardHooks.php` |
| `aculta_portal_photo_editor_alter` | `PhotoEditorHooks::photoEditorAlter` | `src/Hook/PhotoEditorHooks.php` |
| `aculta_portal_settings_tabs_alter` | `SettingsTabsHooks::settingsTabsAlter` | `src/Hook/SettingsTabsHooks.php` |
| `comment_presave` | `ParticipationHooks::commentPresave` | `src/Hook/ParticipationHooks.php` |
| `cron` | `AvatarCronHooks::cron` | `src/Hook/AvatarCronHooks.php` |
| `entity_access` | `ForumModerationHooks::entityAccess` | `src/Hook/ForumModerationHooks.php` |
| `entity_access` | `RestrictedForumHooks::entityAccess` | `src/Hook/RestrictedForumHooks.php` |
| `entity_create_access` | `ParticipationHooks::entityCreateAccess` | `src/Hook/ParticipationHooks.php` |
| `form_user_form_alter` | `PhotoEditorHooks::formUserFormAlter` | `src/Hook/PhotoEditorHooks.php` |
| `module_preuninstall` | `FriendshipLifecycleHooks::protectFriendshipData` | `src/Hook/FriendshipLifecycleHooks.php` |
| `node_access_records` | `RestrictedForumHooks::nodeAccessRecords` | `src/Hook/RestrictedForumHooks.php` |
| `node_grants` | `RestrictedForumHooks::nodeGrants` | `src/Hook/RestrictedForumHooks.php` |
| `node_view` | `DiscussionHooks::nodeView` | `src/Hook/DiscussionHooks.php` |
| `page_attachments` | `PhotoFlowPageHooks::pageAttachments` | `src/Hook/PhotoFlowPageHooks.php` |
| `query_node_access_alter` | `RestrictedForumHooks::queryNodeAccessAlter` | `src/Hook/RestrictedForumHooks.php` |
| `simple_sitemap_links_alter` | `RestrictedForumHooks::simpleSitemapLinksAlter` | `src/Hook/RestrictedForumHooks.php` |
| `taxonomy_term_update` | `RestrictedForumHooks::termUpdate` | `src/Hook/RestrictedForumHooks.php` |
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
| `AvatarPool` | `src/Avatar/AvatarPool.php` | Sorteia um avatar do acervo do Portal para quem não tem foto (decisão 12: o arquivo vai para user_picture). |

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
| `ActivityController` | `src/Controller/ActivityController.php` | Atividade da própria pessoa em Minha Conta (plano aculta_user, seção E2). |
| `AssociadosController` | `src/Controller/AssociadosController.php` | Relatório de associados (plano aculta_user, seção G2). |
| `AvatarRerollController` | `src/Controller/AvatarRerollController.php` | Sorteia outro avatar do acervo para a própria conta (botão do popup de foto). |
| `LegacyMemorialController` | `src/Controller/LegacyMemorialController.php` | Memorial coletivo das autorias históricas do Fórum em /u/lendashistoricas (ACULTA Fórum, F2, F-D07). |
| `PublicProfileController` | `src/Controller/PublicProfileController.php` | Página pública do perfil em /u/<apelido>. |
| `ReportQueueController` | `src/Controller/ReportQueueController.php` | Fila de denúncias do Fórum em /painel-administrativo/forum/denuncias (ACULTA Fórum, F5.1). |
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
| `RestrictedForumNotFoundSubscriber` | `src/EventSubscriber/RestrictedForumNotFoundSubscriber.php` | Responde 404 para páginas de fórum restrito sem acesso (ACULTA Fórum, F9, F-D14). |
| `SocialAuthFieldsSubscriber` | `src/EventSubscriber/SocialAuthFieldsSubscriber.php` | Conta criada por OAuth recebe o nome de usuário a partir da parte local do e-mail recebido, sem o domínio. |

### `Form`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountBioForm` | `src/Form/AccountBioForm.php` | Modal de edição da bio (campo field_bio do perfil participante). |
| `AssociadoMarkForm` | `src/Form/AssociadoMarkForm.php` | Marca ou desmarca uma conta como associado (marcador puro, F-D19). Não concede nenhum acesso no site. |
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
| `RestrictedForumGrants` | `src/Forum/RestrictedForumGrants.php` | Visibilidade dos fóruns restritos (ACULTA Fórum, F9, F-D14). |

### `Identity`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AuthorIdentity` | `src/Identity/AuthorIdentity.php` | View-model escalar mínimo com cache tags/contexts da identidade pública. |
| `AuthorIdentityResolver` | `src/Identity/AuthorIdentityResolver.php` | Resolve apelido e link canônico para variantes User sem expor a entidade. |
| `AuthorCardBuilder` | `src/Identity/AuthorCardBuilder.php` | Gera render array neutro de cartão, com cache e ações fornecidas por consumidor autorizado. |
| `AuthorCardThemeHooks` | `src/Hook/AuthorCardThemeHooks.php` | Declara hook neutro do cartão e mantém fallback sem estilo. |

### `Hook`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountCreationHooks` | `src/Hook/AccountCreationHooks.php` | Criação de conta: perfil participante com apelido inicial, e avatar do acervo quando não há foto. |
| `AccountDashboardHooks` | `src/Hook/AccountDashboardHooks.php` | Caixa de bio na visão geral da Minha conta. Substitui o resumo de Cursos (aculta_portal). |
| `AvatarCronHooks` | `src/Hook/AvatarCronHooks.php` | Cron do aculta_user: limpeza de assets temporários de avatar (ver AvatarAssetCleanup). |
| `DiscussionHooks` | `src/Hook/DiscussionHooks.php` | Discussão vinculada: trecho dos últimos comentários do tópico de Fórum e link para a discussão completa. |
| `ForumModerationHooks` | `src/Hook/ForumModerationHooks.php` | Moderação do Fórum pelo content_editor (ACULTA Fórum, F9, decisões de 2026-10-16). |
| `FriendshipLifecycleHooks` | `src/Hook/FriendshipLifecycleHooks.php` | Maintains friendship data through account deletion and module lifecycle. */ |
| `ParticipationHooks` | `src/Hook/ParticipationHooks.php` | Modera comentários iniciais no Forum legado e nos contextos públicos compartilhados; exclui LMS e aplica bloqueio/limite configurável de criação de tópico. |
| `PhotoEditorHooks` | `src/Hook/PhotoEditorHooks.php` | Editor de foto do painel (ponto de extensão aculta_portal_photo_editor e formulário do painel). |
| `PhotoFlowPageHooks` | `src/Hook/PhotoFlowPageHooks.php` | Anexa o fluxo do popup de foto (aculta_user/photo-flow) na página do painel e informa a foto salva ao script. |
| `PictureReplacementHooks` | `src/Hook/PictureReplacementHooks.php` | Apaga a foto anterior enviada pela pessoa quando ela troca ou remove a foto (economia de disco). |
| `RestrictedForumHooks` | `src/Hook/RestrictedForumHooks.php` | Fóruns restritos (ACULTA Fórum, F9, F-D14). |
| `SettingsTabsHooks` | `src/Hook/SettingsTabsHooks.php` | Aba "Privacidade" em Minha conta > Configurações (o Portal monta as abas e chama o alter). |

### `Legacy`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `LegacyAuthorManager` | `src/Legacy/LegacyAuthorManager.php` | Cria ou atualiza uma autoria histórica do Fórum (ACULTA Fórum, F2). |

### `Moderation`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ReportQueue` | `src/Moderation/ReportQueue.php` | Fila de denúncias do Fórum (ACULTA Fórum, F5.1). |

### `Social`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `FriendshipManager` | `src/Social/FriendshipManager.php` | Implements the ACULTA member friendship workflow. */ |
| `FriendshipManagerInterface` | `src/Social/FriendshipManagerInterface.php` | Public API for member friendship requests. */ |
| `FriendshipStatus` | `src/Social/FriendshipStatus.php` | States for an ACULTA member relationship. */ |
| `SocialLinksCard` | `src/Social/SocialLinksCard.php` | Caixa "Redes sociais" da Minha conta, embaixo da bio: uma linha por rede, com ícone, valor atual e lápis. |
| `SocialNetworks` | `src/Social/SocialNetworks.php` | Redes sociais do perfil: Instagram, Facebook e Reddit. |

### `src`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ParticipationRules` | `src/ParticipationRules.php` | Contagens via EntityQuery e regras de participação para Forum legado e comentários públicos compartilhados; LMS é excluído. |
| `PublicProfilePolicy` | `src/PublicProfilePolicy.php` | Decide, campo a campo, o que do perfil participante pode aparecer em público. |
| `PublicProfileLink` | `src/PublicProfileLink.php` | Única regra de URL canônica do perfil público; atualiza a cache metadata fornecida pelo consumidor. |
| `PublicProfileLinkInterface` | `src/PublicProfileLinkInterface.php` | Contrato para resolver e testar links de perfil sem substituir a implementação canônica. |

### Role/
- `HerancaDePapeis.php` — mapa derivado de `aculta_portal.role_hierarchy`; herdados; papéis não gravados; remoção de papel excluído.

### EventSubscriber/
- `RoleInheritanceCurrentUserSubscriber.php` — estende o usuário logado (REQUEST, prioridade 299).
