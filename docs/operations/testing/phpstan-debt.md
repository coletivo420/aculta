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
