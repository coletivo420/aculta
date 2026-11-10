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
