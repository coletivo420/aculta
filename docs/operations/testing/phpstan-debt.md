# Dívida PHPStan — ACULTA

Gerado em 2026-11-10T22:11:37+00:00 por `scripts/phpstan-debt-report.php`.

**Total de erros: 3743**

## Por criticidade

| Criticidade | Erros |
| --- | ---: |
| alta | 432 |
| media | 3311 |
| baixa | 0 |

## Dez identificadores mais frequentes

| Identificador | Erros |
| --- | ---: |
| `method.notFound` | 1558 |
| `argument.type` | 330 |
| `instanceof.alwaysTrue` | 196 |
| `class.notFound` | 129 |
| `nullCoalesce.offset` | 120 |
| `staticMethod.alreadyNarrowedType` | 113 |
| `return.type` | 102 |
| `method.alreadyNarrowedType` | 97 |
| `property.notFound` | 90 |
| `method.childReturnType` | 81 |

## Módulos mais problemáticos

| Módulo | Erros |
| --- | ---: |
| aculta_lms | 430 |
| aculta_messenger | 422 |
| aculta_forum | 391 |
| aculta_events | 311 |
| aculta_user | 251 |
| aculta_portal | 248 |
| aculta_wiki | 192 |
| scripts | 157 |
| aculta_lms_h5p | 149 |
| aculta_pagamentos_mercadopago | 139 |
| aculta_commerce | 133 |
| aculta_index | 128 |
| aculta_studio | 110 |
| aculta_linkweed | 71 |
| aculta_mailer | 69 |
| aculta_mail_confirmer | 47 |
| aculta_donation | 46 |
| aculta_magazine | 39 |
| aculta_calendar | 38 |
| aculta420_v2 | 38 |


## Rodada 3 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos antes | 3750 |
| Corrigidos em aculta_lms (LearningEligibility) | 7 |
| Erros brutos ao final | 3743 |
| Baseline atual | 3743 |

Escopo: `LearningEligibility` passou a narrowing `ContentEntityInterface` nos parâmetros e checagens; aculta_lms 440 -> 430. Evidência: Unit 320/1367 PASS.

## Rodada 4 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_lms/src antes | 209 |
| Corrigidos em aculta_lms/src | 32 |
| Erros brutos em aculta_lms/src ao final | 177 |
| Erros brutos globais antes | 3743 |
| Erros brutos globais ao final | 3711 |
| Baseline atual | 3711 |

Escopo: narrowing real (instanceof/guard clauses) em `AnswerAttachmentAccessCheck`, `AnswerReviewForm`, `AnswerSubmissionForm`, `CourseCertificateManager`, `CourseCertificateIssueForm`, `CourseStructureController`, `XapiStatementsController`, `LmsForumModerationManager` e `EventClassAccess`. Nenhum `@phpstan-ignore`, nenhum cast forçado, nenhuma regra de negócio alterada. `AnswerSubmissionForm` passou a declarar `$file` antes dos ramos e a falhar explicitamente se o storage devolver entidade inesperada. Evidência: Unit 320/1367 PASS.

## Rodada 5 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_lms/src antes | 177 |
| Corrigidos em aculta_lms/src | 67 |
| Erros brutos em aculta_lms/src ao final | 110 |
| Erros brutos globais antes | 3711 |
| Erros brutos globais ao final | 3647 |
| Baseline atual | 3647 |

Escopo: `ActivityDeleteForm`, `LessonDeleteForm` e `CourseStructureMoveForm` passaram a declarar `TranslatableMarkup` (contrato real do `ConfirmFormInterface`); `CourseMaterialsForm` passou a usar `CacheableMetadata::createFromObject()` para dependências de resultado de acesso e serializa propriedades protegidas; `CourseDocumentAccessCheck` retorna `AccessResult`; `CourseStructureOrderManager` narrowing `ContentEntityInterface` e PHPDoc de entrada `array<int|string>`; `LearningNoticeRecipients` e `LearningDashboardManager` narrowing real (`FieldableEntityInterface`/`ContentEntityInterface`/`GroupRelationshipInterface`) e ternários explícitos onde `?->` era redundante. Evidência: Unit 320/1367 PASS.

## Rodada 6 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_lms/src antes | 110 |
| Corrigidos em aculta_lms/src | 27 |
| Erros brutos em aculta_lms/src ao final | 83 |
| Erros brutos globais antes | 3647 |
| Erros brutos globais ao final | 3621 |
| Baseline atual | 3621 |

Escopo: `LmsActivityDiscussionResolver`, `AnswerReviewAccessCheck`, `AccountCoursesReader`, `ActivityDeadlineEventHooks` e `CourseContentEventHooks` tiveram checagens `instanceof` redundantes removidas e narrowing real aplicado nos pontos que usavam `get()`. `AnswerReviewAccessCheck::access()` passa a aceitar `?FieldableEntityInterface`. `AccountCoursesReader` mantém o tratamento de resultado booleano de hooks de acesso do Group (coberto por teste) e lê tags via `CacheableMetadata::createFromObject()`. Resíduo conhecido: PHPStan considera que `Group::access(..., TRUE)` nunca retorna bool; o teste `testBooleanAccessGrantDoesNotBreakDashboard` prova que retorna em ambiente real, então a linha fica no baseline como dependente do ambiente. Evidência: Unit 320/1367 PASS.

## Rodada 7 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_lms/src antes | 83 |
| Corrigidos em aculta_lms/src | 26 |
| Erros brutos em aculta_lms/src ao final | 57 |
| Erros brutos globais antes | 3621 |
| Erros brutos globais ao final | 3595 |
| Baseline atual | 3595 |

Escopo: os quatro `EntityCourse*Reader` passaram a narrowing `ContentEntityInterface` antes de `fieldValue()`; `AgendaEntriesProvider` remove nullsafe/`instanceof` redundantes e filtra retornos por `Event`/`EventDate`; `ClassRosterManager` constrói a lista com narrowing real; `AttemptArchiveManager` remove `instanceof` sempre verdadeiro, narrowing `AttemptArchive` e usa `getOwner()->label()`. Evidência: Unit 320/1367 PASS.

## Rodada 8 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_lms/src antes | 57 |
| Corrigidos em aculta_lms/src | 53 |
| Erros brutos em aculta_lms/src ao final | 4 |
| Erros brutos globais antes | 3595 |
| Erros brutos globais ao final | 3542 |
| Baseline atual | 3542 |

Escopo: remoção de `instanceof` redundantes em `LmsForumModerationManager`, `AnswerReviewController`, `CourseLessonController`, `ClassActivityDeadlineManager`, `EducatorFeedbackPolicy`, `CourseCompletionPublisher`, `CourseOrderHooks`, `EducatorFeedbackAuthorCardHooks`, `EnrollmentFormHooks`, `CourseEnrollmentEventHooks`, `EventAccessHooks`, `LmsCommentModerationForm`, `CourseEnrollmentPurchaseForm`, `CourseResourceBuilder` e `LearningEligibility`; narrowing real em `EventoTurmaNotificationWorker`, `AnswerLifecycleEventHooks`, `CourseOrderEnrollmentManager` e readers; `accessForReorder` recebe `ContentEntityInterface`; `PaymentStorageInterface` usado explicitamente em `CourseOrderEnrollmentManager` e `EnrollmentFormHooks` (helpers `paymentsForOrder()` com `array<mixed>` preservam o fail-closed comprovado por teste); `EventRouteAccess::access()` retorna `AccessResultInterface`; `AccountUserLoader` trata ID anônimo como `(int) < 1`; `LearningDashboardController` filtra `LessonEntity`/`ActivityEntity` antes do `usort` e passa `''` como formato de data.

Resíduos conhecidos (dependentes do ambiente, comprovados por teste): o retorno booleano de `Group::access(..., TRUE)`; `$entityTypeManager` injetado em `ClassActivityDeadlineManager` para manter o contrato do serviço e dos testes; e `DrupalDateTime::createFromFormat()` retornando `false` para entrada inválida. Evidência: Unit 320/1367 PASS.

## Rodada 9 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_lms (module+install) antes | 16 |
| Corrigidos | 16 |
| Erros brutos em aculta_lms (module+install) ao final | 0 |
| Erros brutos globais antes | 3542 |
| Erros brutos globais ao final | 3526 |
| Baseline atual | 3526 |

Escopo: `aculta_lms.module` passou a narrowing `ContentEntityInterface` nos hooks de evento e no alter do sitemap; `aculta_lms.install` valida `FieldStorageDefinitionInterface` antes de `installFieldStorageDefinition()` (5 updates) e usa `$phase` de forma significativa em `hook_requirements()`. Evidência: Unit 320/1367 PASS; `php -l` sem erros nos dois arquivos.

## Rodada 10 — 2026-11-10 (aculta_messenger)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_messenger antes | 422 |
| Corrigidos | 84 |
| Erros brutos em aculta_messenger ao final | 338 |
| Erros brutos globais antes | 3526 |
| Erros brutos globais ao final | 3442 |
| Baseline atual | 3442 |

Escopo: `LearningNoticeManager` (35 -> 1), `EventsContributionNoticeManager` (16 -> 3), `WikiNoticeManager` (14 -> 2), `MagazineNoticeManager` (11 -> 2) e `PrivateMessageNotificationWorker` (14 -> 1). O `EventsContributionNoticeManager` importava `Drupal\aculta_contributions\Entity\Contribution`, classe inexistente; o tipo correto é `Drupal\aculta_cgu\Entity\Contribution`, corrigido. `record()` dos managers passou a validar o retorno de `create()` como `MessageInterface`; `resolveMessages()` aceita `EntityInterface[]` e ignora registros que não são mensagens; parâmetros `$sourceUuid` não usados foram removidos; `resolve*` recebem `ContentEntityInterface`/`OwnerInterface` conforme o método chamado; retornos de digest documentados como `TranslatableMarkup` (ou convertidos para string quando o contrato exige string).

Resíduos conhecidos: mutação de array capturado por referência em closures (PHPStan perde o tipo), `PrivateMessageServiceInterface::getThreadFromMessage()` que pode devolver nulo em runtime apesar da assinatura, e `DrupalDateTime::createFromFormat()` retornando `false` para entrada inválida. Evidência: Unit 403/4378 PASS no módulo.

## Rodada 11 — 2026-11-10 (correções automáticas no aculta_messenger)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_messenger/src antes | 59 |
| Corrigidos | 51 |
| Erros brutos em aculta_messenger/src ao final | 8 (todos dependentes de contrato externo) |
| Erros brutos globais antes | 3442 |
| Erros brutos globais ao final | 3359 |
| Baseline atual | 3359 |

Escopo: remoção de `instanceof SynchronizableInterface`/`method_exists()` redundantes; `CacheableMetadata::createFromObject()` para cache tags de resultados de acesso; `SiteAlertStorageInterface` explícito para `getCacheMaxAge()`; `loadRevision()` com `RevisionableStorageInterface`; `PrivateMessageInterface` no contador de não lidas; `ArrayObject` substitui arrays capturados por referência nos callbacks de `EligibleNoticePage::collect()`; helpers `record()` validam `MessageInterface`; propriedades de DI não usadas removidas (`AvisosMensagensController`, `MessengerBarHooks`, `PrivateMessageReplyHooks`) com o teste `PersistentNoticeAccessTest` ajustado; `ProfileRelationshipController` deixa de passar argumentos extras ao `FormBuilderInterface::buildForm()` (o alvo e a URL agora viajam no `FormState`), corrigindo um bug funcional real.

Resíduos: docblock de `PrivateMessageThreadInterface::getMessages()` declara `EntityReferenceFieldItemListInterface` mas a implementação retorna array (bug de documentação do contrib, não editado); `FormStateInterface::hasAnyErrors()` mutado por método auxiliar; `DrupalDateTime::createFromFormat()` retornando `false`; `PrivateMessageServiceInterface::getThreadFromMessage()` devolvendo nulo em runtime; e `ConfigFactoryInterface::get()` nulo em mocks de teste. Evidência: Unit 403/4378 PASS.

## Rodada 12 — 2026-11-10 (aculta_forum, produção)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_forum antes | 391 |
| Erros brutos em aculta_forum/src antes | ~250 |
| Erros brutos em aculta_forum/src ao final | 95 |
| Erros brutos em aculta_forum (módulo) ao final | 282 |
| Erros brutos globais antes | 3359 |
| Erros brutos globais ao final | 3253 |
| Baseline atual | 3253 |

Escopo (somente `src/`, testes fora desta rodada): `ForumCommentMigrationAudit` (19 -> 0) com narrowing de `FlaggingInterface`/`ContentEntityInterface` e `array_key_exists` nos mapas de marcadores; `ForumAttachmentPublication` (9 -> 0) e `ForumGalleryPublication` (8 -> 1) com remoção de `instanceof` redundante, `EntityPublishedInterface` para galerias e `RevisionableStorageInterface` para revisões; `ForumAttachmentCleanup` (8 -> 1); `LmsDiscussionContextProvider` (8 -> 0) com dependência de grupo anexada via `andIf()`; `ForumPublicationTimeHooks` (7 -> 0); `ForumMarkAllReadForm` (6 -> 0) com retorno `TranslatableMarkup`; `ForumPostRevisionHistoryController` (5 -> 0), `ForumPostSubmissionForm` (5 -> 0), `ForumFlaggingMigrationContext` (5 -> 0) e sources de migração `ForumComment`/`ForumCommentFlagging`; remoção de guards `getStorage() === NULL`; `create(): static` nos controllers/formulários; narrowing de `MediaInterface`/`FileInterface`; `AccessResult` explícito onde a interface não expõe `addCacheableDependency()`/`getReason()`.

Resíduos na produção: rechecagens defensivas deduplicadas pelo PHPStan (`hasContentReference()`/`hasLiveReference()`), tipagem de `getAggregateQuery()->execute()`, chamadas `\Drupal::` em classes de hook (exigem refatoração de DI), plugins de Views com `QueryPluginBase` genérico e acessos a propriedades mágicas de FieldItem. Evidência: Unit 250/1876 PASS.

## Rodada 13 — 2026-11-10 (aculta_events, produção)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_events antes | 311 |
| Erros brutos em aculta_events/src ao final | 70 |
| Erros brutos globais antes | 3253 |
| Erros brutos globais ao final | 3124 |
| Baseline atual | 3124 |

Escopo (somente `src/` e `aculta_events.install`): `aculta_events.install` 11 -> 0 com helper `_aculta_events_install_storage_definition()` validando `FieldStorageDefinitionInterface`; `EventLocationAccessControlHandler` (14 -> 0) e `EventOrganizationAccessControlHandler` (8 -> 0) e `EventAccessControlHandler` (5 -> 0) passaram a implementar `EntityHandlerInterface` com DI real (`OrganizationAccessService`, `EventPermissionPolicy`), retorno `AccessResultInterface` e propriedades protegidas; `EventCalendarItemProvider` 12 -> 0; `EventPageController` 10 -> 0 (AuthorIdentityResolver com `UserInterface`, endereços via `AddressInterface`, VirtualLocation simplificado); `EventOrganizerDashboardController` 9 -> 0 e `EventOrganizationSuggestionAdminController` 7 -> 0 com narrowing de `ContentEntityInterface`/`EntityOwnerInterface`; `EventDateOccurrenceForm`, `EventLocationModalWidget`, `EventContributionAdapter`, `LocationContributionAdapter`, `LocationPageController`, `EventDateListBuilder`, `EventDateOccurrences`, `EventContributionAdminController` e `EventEditorialPresenter` zerados; entidades `Event`/`EventOrganization` com `getOwner()`/`getOwnerId()` tipados e `getCoOrganizerIds()` via `referencedEntities()`; nova rota de membros usa o serviço `aculta_events.organization_members_access`.

Resíduos: plugins de Views com `QueryPluginBase` genérico, formulários com `$this->entity` tipado como `EntityInterface`, acessos a propriedades mágicas de FieldItem restantes e chamadas `\Drupal::` em hooks. Evidência: Unit 30/62 PASS; YAML de serviços e rotas validado.

## Rodada 14 — 2026-11-11 (aculta_user, produção)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_user antes | 251 |
| Erros brutos em aculta_user/src e .install ao final | 0 (94 restantes apenas em testes) |
| Erros brutos globais antes | 3124 |
| Erros brutos globais ao final | 2963 |
| Baseline atual | 2963 |

Escopo: `OwnershipClaimService` 42 -> 0 com helper `requireContentEntity()` e narrowing em `canClaim`/`decideClaim`; `OrganizationAccessService` 15 -> 0; `ReportQueue` 10 -> 0 (`FlaggingInterface`); `ForumModerationHooks` 7 -> 0; `aculta_user.install` 6 -> 0 (`_aculta_user_install_storage_definition()`); `ParticipationEditWindow` 6 -> 0; `PhotoEditorHooks` 5 -> 0 (DI morta removida, `EntityFormInterface`); `UserPictureForm`, `AccountCreationHooks`, `DiscussionCommentAuthorHooks`, `AvatarAssetCleanup`, `AvatarResolver`, `BadgeService`, `ActivityController`, `LegacyMemorialController`, `PeopleListController`, `PublicProfileController`, handlers de acesso, `AvatarCropForm`, `RoleInheritanceHooks`, `UsernameLinkHooks`, `AuthorCard` (render agora retorna markup), `AuthorCardBuilder`, `LegacyAuthorManager`, `PublicProfilePolicy`, `ConnectionQueryService`, `FriendshipManager`, `SocialNetworks`, `AccountNames` e `NicknameRegistry` (docblock do `rebuild()`).

Mudança transversal: `PerfilDoPapel::principal()/obter()` passaram a aceitar `EntityStorageInterface` e validar `ProfileStorageInterface`, eliminando ~15 erros em aculta_user, aculta_portal e aculta_events. Evidência: Unit 160/618 PASS; `aculta_user/src` e `.install` em zero.

## Rodada 15 — 2026-11-11 (aculta_portal, produção)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_portal/src antes | 149 |
| Erros brutos em aculta_portal/src ao final | 44 |
| Erros brutos globais antes | 2963 |
| Erros brutos globais ao final | 2857 |
| Baseline atual | 2857 |

Escopo: `BillingAddressPane` 16 -> 0 (ProfileStorageInterface/ProfileInterface), `AccountAddressForm` 13 -> 0, `PortalController` 11 -> 0 (OrderInterface, SocialAuth, transporte, DI morta), `SupportController` 9 -> 0, `SubscriptionActionForm` 8 -> 0 (`TranslatableMarkup`, `PaymentGatewayInterface`), `PortalFormCallbacks` 7 -> 0 (`EntityFormInterface`), além de `TurnstileToggle`, `MediaContentIndexProvider`, `AdminEmailConfirmForm`, `SalesPaymentCancelForm`, `PortalHooks`, `TokenHooks`, `ContentCleanupPreview`, `EmailConfirmationRequester`, `SocialAuthProfileSubscriber`, `ContentIndexOwnerResolver`, `ContentIndexProjection`, `CheckoutStepResolver`, `FiscalIdentityPolicy`, `AccountCoursesManager`, `ShopController`, `SurfaceConfigReader` e `PortalRequirementsController`.

Evidência: Unit 390/1361 PASS; `phpstan analyse` em `[OK] No errors`.

## Rodada 16 — 2026-11-11 (aculta_wiki, produção — parcial)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_wiki/src antes | ~110 |
| Erros brutos em aculta_wiki/src ao final | 36 |
| Erros brutos globais antes | 2857 |
| Erros brutos globais ao final | 2797 |
| Baseline atual | 2797 |

Escopo: `WikiContributionMigration` 29 -> 0 (`RevisionableStorageInterface`, `Contribution`), `WikiEditorialNoticePublisher` 6 -> 0, `WikiEntryImageHooks` 6 -> 0 (DI de `file_url_generator`, `CacheableMetadata`), providers de Content Index, `WikiLeadImageCleanup`, `WikiController`, `WikiProposalStatusController`, `WikiReviewHooks`, `WikiProposalDecision` e `WikiLeadImageUploader` (selects com lock divididos em etapas). Evidência: Unit 94/455 PASS; YAML de serviços validado.

Restante no módulo: `WikiEditorialQueue` (13), `WikiContributorWorkspace` (8), `WikiProposalSubmissionForm` (7) e avulsos — exigem narrowing de uniões `Contribution|WikiProposal` e reescrita de laços de paginação.
