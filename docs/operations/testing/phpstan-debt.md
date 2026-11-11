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
## Rodada 18 — 2026-11-11 (aculta_mailer, módulo completo)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_mailer antes | 69 (45 em src; 5 em .install; 19 em testes) |
| Erros brutos em aculta_mailer ao final | 0 |
| Entradas de aculta_mailer no baseline ao final | 0 |
| Baseline global regenerado | 3564 erros |

Escopo: narrowing de entidades MailLog/MailTransport, assinatura correta dos ConfirmForm, validação das FieldStorageDefinition no .install, remoção de guards redundantes, filtros/callbacks alinhados aos tipos inferidos e retorno tipado do MailTemplateRenderer. MailTransportFactory agora cria SMTP via EsmtpTransportFactory/Dsn pública do Symfony, mantendo smtps na porta 465, credenciais resolvidas por segredo e configuração verify_peer. Testes existentes tiveram narrowing dos itens de fila/entidades; removido o teste tautológico que comparava o TTL à própria constante.

Resíduos no módulo: nenhum. O baseline global passou de 2797 (registro da rodada 16) para 3564 após regeneração na base atual; esse delta global inclui dívida fora do escopo e não é atribuído ao aculta_mailer. O baseline recém-gerado registra erros reais `class.notFound` dos consumidores de `DomainPurposeManager`, cuja migração para `DomainSiteManager` ainda não chegou a `origin/dev`; ficam explicitamente pendentes, sem editar esses arquivos paralelos. Evidências: PHPStan no módulo e global `[OK] No errors`; Unit 166 testes/348 assertions PASS; PHP lint dos 29 arquivos PHP alterados, YAML de serviços e índice de status PASS. A suíte Unit global 3287/17594 encontrou 4 falhas fora do escopo (ForumTopicDisplayConfigTest, BadgeEvaluationHooksTest e dois testes de aculta_index), além de 3 deprecações; o gate token budget falhou por sete violações globais preexistentes em AGENTS/skills fora do escopo.

## Rodada 17 — 2026-11-11 (aculta_wiki, src)

| Medida | Valor |
| --- | ---: |
| Erros brutos em aculta_wiki/src antes | 36 |
| Erros brutos em aculta_wiki/src ao final | 0 |
| Erros brutos globais antes | 3564 |
| Erros brutos globais ao final | 3451 |
| Baseline da Wiki (módulo completo) | 225 -> 112 |

Escopo: `WikiEditorialQueue` e `WikiContributorWorkspace` com narrowing dos resultados de storage e união `WikiProposal|Contribution`; `WikiProposalSubmissionForm` com remoção de comparações já eliminadas por `array_filter()` e retorno numérico tipado para ID de mídia. Os serviços, controllers e hooks migraram de `DomainPurposeManager` para `DomainSiteManager` e `SiteKey::Wiki`; chamadas de acesso preservam o contexto editorial e a política existente. Fixtures Unit receberam o `SiteKeyRegistry` exigido pelo contrato publicado. Nenhuma regra de negócio ou permissão foi alterada.

Resíduos: 112 erros PHPStan permanecem no módulo completo, todos em testes/.install já baselineados; Kernel de imagem contém 92 chamadas sobre `RevisionableInterface`, fora do escopo. Evidências: PHPStan em `src` (42 arquivos) e global estrito `[OK] No errors`; Unit 94 testes/455 assertions PASS. Baseline global caiu 3564 -> 3451; a redução de 113 ocorrências ficou restrita à Wiki.

## Rodada 19 — autofixes PHPStan globais — 2026-11-11

| Medida | Valor |
| --- | ---: |
| Autofixes de `--fix` | 0 (nenhum erro corrigível encontrado) |
| Autofixes de `--fix --fix-ignored` | 16 em 6 arquivos PHP |
| Ocorrências na baseline antes/depois | 2675 -> 2665 |
| Erros PHPStan globais após os autofixes | 0 (`[OK] No errors`) |

Escopo: substituição de `assertSame(TRUE/FALSE/NULL, ...)` pelas asserções PHPUnit correspondentes em testes existentes do `aculta_forum`, `aculta_lms_h5p` e `aculta_magazine`; removidas 6 entradas obsoletas (10 ocorrências) da baseline. Nenhum arquivo de produção foi alterado. As asserções permanecem estritas para os valores booleanos/nulos retornados.

Evidências: Unit dos três módulos, 31 testes/374 assertions PASS; PHP lint dos 6 arquivos PHP PASS; análise global posterior `[OK] No errors`. Dois arquivos Kernel com apenas equivalência de asserção não foram executados para evitar acesso a banco nesta rodada.


## Rodada 20 — 2026-11-11 (aculta_forum, produção)

| Medida | Antes | Depois |
| --- | ---: | ---: |
| Baseline global (ocorrências) | 2688 | 2592 |
| `aculta_forum` (ocorrências) | 281 | 185 |
| `aculta_forum/src` (ocorrências) | 95 | 0 |

Escopo: `aculta_forum/src`, definições de serviço e testes Unit afetados por contratos de construção. Narrowing de entidades e storages, injeção de dependências de tempo e hooks, remoção de dependências não utilizadas, correção de assinaturas Views e preservação explícita da cacheabilidade. O atributo do Drupal CKEditor documenta `plugins` como `class-string[]`, embora o valor exigido em runtime seja o identificador global JavaScript; foi mantida uma supressão PHPStan pontual, com justificativa no argumento.

Resíduos: 185 ocorrências da baseline no módulo, todas em testes; nenhuma em produção. A análise bruta confirmou que o único erro de teste no `ForumHistoricModerationQueueHooksTest` (atribuição do relacionamento da View) já existia antes desta rodada. Não houve novas entradas de baseline para erros de produção.

Evidências: Unit do Fórum 250 testes/1888 assertions PASS; Unit global 3287 testes/17654 assertions PASS (3 deprecações PHPUnit no PHP 8.5); descoberta de suítes PASS (unit=674); lint dos arquivos PHP alterados PASS; gate Portal Drupal 12 PASS (1234 verificações); PHPStan global após regenerar baseline `[OK] No errors`. Kernel não executado, para não conectar testes a banco operacional.

## Rodada 21 — 2026-11-11 (aculta_messenger, produção)

| Medida | Antes | Depois |
| --- | ---: | ---: |
| Baseline global (ocorrências) | 2592 | 2576 |
| Erros brutos em `aculta_messenger` | 276 | 260 |
| Erros brutos em produção (`src`, `.install`, post-update) | 35 | 18 |
| Erros em `aculta_messenger/.install` | 5 | 0 |

Escopo: APIs de thread migradas para `getMemberIds()`, array de mensagens consumido conforme o retorno de `getMessages()`, caminho de módulo resolvido por `extension.path.resolver` e narrowing de `ConfigEntityStorageInterface` nos updates de configuração. A checagem do worker mantém o retry quando a implementação contrib retorna `FALSE` apesar do PHPDoc declarar entidade. Fixtures Unit atualizados para as APIs atuais.

Resíduos de produção: 14 avisos de APIs `private_message` obsoletas sem substituto publicado (mapper, identificação de thread e verificação de bloqueio) e 4 parâmetros `$sandbox` mantidos no contrato de post-update. O autofix global solicitado (`--fix --fix-ignored`) foi executado no worktree e não encontrou erros corrigíveis.

Evidências: Unit do Messenger 403 testes/4387 assertions PASS; descoberta de suítes PASS (unit=674); PHP lint e `git diff --check` PASS; análise PHPStan estrita global `[OK] No errors`. Sem testes Kernel/Functional, para evitar banco operacional.

Pós-push: no topo `ca4246fb6`, `--fix --fix-ignored` global novamente não encontrou erros corrigíveis. A análise estrita detectou uma negação redundante em `scripts/inventory-ui-suite-consumers.php`; a condição foi simplificada preservando a precedência dos estados, e a análise direcionada e global voltaram a `[OK] No errors`. A baseline permaneceu em 2576 ocorrências.

Runtime de desenvolvimento: gate DEFERRED. O `plan` read-only encontrou `HEAD` igual a `origin/dev` (`ca4246fb6`), mas o checkout principal continua sujo; `sync-code` foi bloqueado pelo guard. O delta desta rodada inclui `.install`/post-update (R3) e faltam as variáveis operacionais explícitas; não rodei Drush nem limpei caches. SHA/cache validado permanece UNKNOWN.

## Microfase 22a — 2026-11-11 (`aculta_events`, produção)

| Medida | Antes | Depois |
| --- | ---: | ---: |
| Erros brutos em `aculta_events` | 182 | 157 |
| Erros em produção do módulo | 70 | 45 |
| Baseline global (ocorrências) | 2576 | 2551 |

Escopo parcial: removidos guards `instanceof` redundantes conforme os tipos PHPDoc de storage/calendário, ajustado o encadeamento de `SELECT ... FOR UPDATE` para executar no objeto de consulta, corrigidos retornos e acesso a campos de endereço/data, removido o construtor não utilizado de dois providers e atualizadas suas definições de serviço, além das assinaturas `TranslatableMarkup` do formulário de confirmação. A passada global `analyse --fix --fix-ignored` foi repetida no estado inicial desta microfase e terminou com `No fixable errors found`, sem alterações automáticas.

Resíduos: a microfase não finaliza `aculta_events`; permanecem 45 erros de produção e 112 em testes. Entre os resíduos de produção estão unions de entidades de contribuição, revisão com storage genérico, assinaturas Form API, injeção nos formulários modais e APIs dos plugins Views. Evidências: Unit 30 testes/62 assertions PASS; lint nos 14 arquivos PHP alterados PASS; gate Portal Drupal 12 PASS (1234 verificações); análise PHPStan global estrita `[OK] No errors`; baseline regenerado localmente e `git diff --check` PASS.

## Microfase 22b — 2026-11-11 (`aculta_events`, produção)

| Medida | Antes | Depois |
| --- | ---: | ---: |
| Erros brutos em `aculta_events` | 157 | 112 |
| Erros em produção do módulo | 45 | 0 |
| Baseline global (ocorrências) | 2551 | 2500 |

Escopo: estreitamento de entidades de Evento/Local/Organização/Contribution/UgcReport, storage revisionável e tipo da consulta Views SQL; retorno de validação dos formulários de entidade conforme `ContentEntityFormInterface`; renderização do card Views pela API do renderer; campos de endereço lidos via `getValue()`; dependências não usadas removidas do migrador; `SELECT ... FOR UPDATE` mantido e executado no objeto da consulta. Os formulários modais recebem `RendererInterface` por DI, e fixtures Kernel foram ajustadas aos construtores.

Resíduos: zero erro bruto em `src`; permanecem 112 erros, todos em testes. A regeneração global retirou 45 ocorrências de Eventos e seis entradas antigas de scripts que já não aparecem na análise atual; nenhum script foi alterado. Evidências: análise bruta do módulo (157 -> 112, produção 45 -> 0), análise global estrita `[OK] No errors`, Unit do módulo 30 testes/62 assertions PASS, descoberta de suítes PASS (unit=674), lint de 19 arquivos PHP PASS, YAML de serviços PASS, gate Portal Drupal 12 PASS (1234 verificações). A suíte Unit global foi executada, mas terminou com fatal aos 1708/3286 testes porque `aculta_messenger_schema()` foi declarada pelos dois checkouts (`round19` e principal); nenhum Kernel foi executado para evitar banco.

## Microfase 22c — 2026-11-11 (`aculta_events`, Unit)

| Medida | Antes | Depois |
| --- | ---: | ---: |
| Erros brutos em `aculta_events` | 112 | 111 |
| Erros brutos nos testes Unit de Eventos | 1 | 0 |
| Baseline global (ocorrências) | 2500 | 2499 |

Escopo: `OrganizationContributionEventQueueTest` passou a manter o estado do outbox em `ArrayObject`, cuja API de coleção preserva o contrato dinâmico dos callbacks do mock; a asserção ainda verifica o payload armazenado e a remoção após recuperação da fila. O teste protege a recuperação durável quando `QueueInterface::createItem()` falha na primeira tentativa. Os 111 erros brutos restantes estão todos em testes Kernel.

Evidências: Unit do módulo 30 testes/62 assertions PASS; PHP lint do teste PASS; análise bruta de Eventos 112 -> 111; análise global estrita `[OK] No errors`; gate Portal Drupal 12 PASS (1234 checks); `git diff --check` PASS. Kernel não executado: conexão isolada de banco não comprovada nesta rodada.

## Microfase 22d — 2026-11-11 (`aculta_events`, Kernel de ciclo de vida)

| Medida | Antes | Depois |
| --- | ---: | ---: |
| Erros brutos em `aculta_events` | 111 | 35 |
| Erros em `EventOrganizationLifecycleKernelTest.php` | 76 | 0 |
| Baseline global (ocorrências) | 2499 | 2423 |

Escopo: os retornos genéricos de storage no Kernel de ciclo de vida são validados por classe concreta em `requireEntity()`, preservando erro explícito quando fixture/entidade esperada não existe. Os itens de fila são estreitados com `assertIsObject()`, a asserção sobre o campo de transferência usa `isEmpty()`, e os `assertTrue(TRUE)` nos catches foram removidos porque `fail()` já faz a ausência da exceção falhar.

Resíduos: 35 erros brutos em outros testes Kernel de Eventos; nenhum erro em `src` ou Unit. Evidências: arquivo focal (76 -> 0), módulo bruto (111 -> 35), baseline global (2499 -> 2423), análise global estrita `[OK] No errors`, Unit do módulo 30 testes/62 assertions PASS, descoberta PHPUnit PASS (kernel=250, unit=674), lint PHP e gate Portal Drupal 12 (1234 checks) PASS. Kernel focal DEFERRED: `SIMPLETEST_DB` está unset e o bootstrap Drupal recusa executar Kernel sem uma URL de teste; não configurei conexão por inferência.
