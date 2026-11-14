# Dívida PHPStan — ACULTA

Gerado em 2026-11-14T19:56:58+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `e1914d1c6b929ad9898391c82985ad16775856a6` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 26534 |
| Dívida congelada no baseline | 26534 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 15478 |
| Produção | 15696 |
| Testes | 10838 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 231 |
| media | 23270 |
| baixa | 3033 |

## Evolução desde o baseline de referência

| Classificação | Entradas |
| --- | ---: |
| Dívida removida | 15 |
| Dívida mantida | 15478 |
| Nova dívida (bloqueante) | 0 |
| Contagem aumentada (bloqueante) | 0 |
| Contagem reduzida | 0 |
| Identidade de erro alterada (revisão) | 0 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| aculta_forum | 3096 |
| scripts | 2996 |
| aculta_messenger | 2073 |
| aculta_portal | 2020 |
| aculta_lms | 1946 |
| aculta_events | 1792 |
| aculta_wiki | 1461 |
| aculta_lms_h5p | 1299 |
| aculta_index | 1112 |
| aculta_user | 899 |
| aculta_commerce | 839 |
| aculta_mailer | 665 |
| aculta_studio | 622 |
| aculta_pagamentos_mercadopago | 594 |
| aculta420_v2 | 479 |
| aculta_donation | 383 |
| aculta_linkweed | 381 |
| aculta_calendar | 358 |
| aculta_statistics | 354 |
| aculta_deployer | 345 |
| aculta_magazine | 341 |
| aculta_document_viewer | 334 |
| aculta_cgu | 311 |
| aculta_mail_confirmer | 241 |
| aculta_image | 230 |
| aculta_game | 209 |
| aculta_file_uploader | 157 |
| aculta_events_openstreetmap | 105 |
| aculta_maps | 105 |
| aculta_patcher | 101 |

## Identificadores mais frequentes

| Identificador | Ocorrências |
| --- | ---: |
| `offsetAccess.nonOffsetAccessible` | 8074 |
| `argument.type` | 4640 |
| `method.nonObject` | 3002 |
| `missingType.iterableValue` | 2803 |
| `cast.string` | 2075 |
| `cast.int` | 1344 |
| `method.notFound` | 986 |
| `property.nonObject` | 666 |
| `return.type` | 369 |
| `foreach.nonIterable` | 354 |
| `binaryOp.invalid` | 299 |
| `offsetAccess.invalidOffset` | 197 |
| `missingType.return` | 138 |
| `property.notFound` | 134 |
| `encapsedStringPart.nonString` | 115 |
| `staticMethod.internalClass` | 105 |
| `staticMethod.alreadyNarrowedType` | 102 |
| `offsetAccess.notFound` | 97 |
| `method.alreadyNarrowedType` | 85 |
| `nullCoalesce.offset` | 77 |

## Arquivos com mais dívida

| Arquivo | Ocorrências |
| --- | ---: |
| `web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Unit/ForumTopicDisplayConfigTest.php` | 857 |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Kernel/WikiLeadImageLifecycleKernelTest.php` | 819 |
| `scripts/validate-ui-patterns-views-cutover.php` | 305 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php` | 270 |
| `scripts/inventory-ui-suite-consumers.php` | 237 |
| `scripts/lib/Aculta420DesignFoundationsAnalyzer.php` | 206 |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/src/Cli.php` | 195 |
| `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` | 189 |
| `scripts/validate-portal-commerce-security.php` | 177 |
| `web/modules/custom/aculta_portal/modules/aculta_index/src/Integrity/ContentIndexIntegrityChecker.php` | 177 |
| `scripts/validate-aculta420-v2-sdc.php` | 173 |
| `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php` | 169 |
| `scripts/install-institution.php` | 163 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationLifecycleKernelTest.php` | 140 |
| `web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit/StudioProfileControllerTest.php` | 118 |
| `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` | 118 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/tests/src/Kernel/aculta_H5PCourseAccessIntegrationTest.php` | 110 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Kernel/ForumNoticeIdempotencyKernelTest.php` | 110 |
| `web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Kernel/ContentIndexSchemaKernelTest.php` | 109 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` | 108 |

## Como usar

- O total congelado é o teto histórico; qualquer erro fora dele reprova o gate.
- Ao alterar um arquivo, corrija a dívida desse arquivo antes de publicar.
- O baseline não é regenerado automaticamente; reduções exigem remover as entradas correspondentes no mesmo commit.
- Refira sempre SHA, versão do PHPStan e escopo ao comparar números.

