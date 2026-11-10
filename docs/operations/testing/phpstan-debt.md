# Dívida PHPStan — ACULTA

Gerado em 2026-11-10T18:31:09+00:00 por `scripts/phpstan-debt-report.php`.

**Total de erros: 3788**

## Por criticidade

| Criticidade | Erros |
| --- | ---: |
| alta | 432 |
| media | 3356 |
| baixa | 0 |

## Dez identificadores mais frequentes

| Identificador | Erros |
| --- | ---: |
| `method.notFound` | 1598 |
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
| aculta_messenger | 452 |
| aculta_lms | 438 |
| aculta_forum | 391 |
| aculta_events | 311 |
| aculta_user | 251 |
| aculta_portal | 248 |
| aculta_wiki | 192 |
| scripts | 159 |
| aculta_lms_h5p | 149 |
| aculta_pagamentos_mercadopago | 139 |
| aculta_commerce | 133 |
| aculta_index | 128 |
| aculta_studio | 110 |
| aculta_linkweed | 71 |
| aculta_mailer | 69 |
| aculta_mail_confirmer | 47 |
| aculta_donation | 46 |
| aculta420_v2 | 43 |
| aculta_magazine | 39 |
| aculta_calendar | 38 |


## Rodada 1 — 2026-11-10

| Medida | Valor |
| --- | ---: |
| Erros brutos no início | 3827 |
| Corrigidos antes do baseline (strict_types + ignores obsoletos) | 4 |
| Baseline inicial revisado | 3823 |
| Corrigidos nesta rodada (aculta_mailer) | 34 |
| Erros brutos ao final | 3788 |
| Baseline atual | 3788 |

Escopo da rodada: `aculta_mailer` caiu de 103 para 69 erros. Todos os
`EntityInterface::get()`/`set()` foram resolvidos com narrowing explícito
(`assert($log instanceof ContentEntityInterface)`) em controllers, formulário,
política e relatório; nenhuma regra de negócio ou permissão foi alterada.
Evidência: Unit 167 testes/351 asserções PASS; Kernel 46 testes/214 asserções
PASS com `SIMPLETEST_DB`.

Próximos alvos por volume: `aculta_messenger` (452), `aculta_lms` (438),
`aculta_forum` (391), `aculta_events` (311).
