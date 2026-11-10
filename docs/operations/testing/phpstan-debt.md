# Dívida PHPStan — ACULTA

Gerado em 2026-11-10T19:40:23+00:00 por `scripts/phpstan-debt-report.php`.

**Total de erros: 3750**

## Por criticidade

| Criticidade | Erros |
| --- | ---: |
| alta | 432 |
| media | 3318 |
| baixa | 0 |

## Dez identificadores mais frequentes

| Identificador | Erros |
| --- | ---: |
| `method.notFound` | 1566 |
| `argument.type` | 330 |
| `instanceof.alwaysTrue` | 196 |
| `class.notFound` | 129 |
| `nullCoalesce.offset` | 119 |
| `staticMethod.alreadyNarrowedType` | 113 |
| `return.type` | 102 |
| `method.alreadyNarrowedType` | 97 |
| `property.notFound` | 90 |
| `method.childReturnType` | 81 |

## Módulos mais problemáticos

| Módulo | Erros |
| --- | ---: |
| aculta_lms | 438 |
| aculta_messenger | 422 |
| aculta_forum | 391 |
| aculta_events | 311 |
| aculta_user | 251 |
| aculta_portal | 248 |
| aculta_wiki | 192 |
| scripts | 156 |
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


## Rodada 2 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos antes da rodada | 3788 |
| Corrigidos em aculta_messenger | 38 |
| Erros brutos ao final | 3750 |
| Baseline atual | 3750 |

Escopo: `AvisoService` e `PrivateMessageNotificationWorker` passaram a narrowing
`ContentEntityInterface` (assert) e `MessageInterface` (@var) em vez de operar
sobre `EntityInterface`. Testes Kernel do messenger ganharam as dependências
ausentes (`file`, `token`, `image`, `crop`, `image_widget_crop`), o que levou a
suíte de 56 erros para 0: 97 testes, 1940 asserções PASS. Unit: 495/5327 PASS.
