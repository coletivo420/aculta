# Dívida PHPStan — ACULTA

Gerado em 2026-11-16T12:25:43+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `c22415dd1992b04a2e118c9d3f6f4b77509a93cc` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 20971 |
| Dívida congelada no baseline | 20971 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 12760 |
| Produção | 12984 |
| Testes | 7987 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 221 |
| media | 18362 |
| baixa | 2388 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| scripts | 2003 |
| aculta_portal | 1993 |
| aculta_messenger | 1828 |
| aculta_events | 1783 |
| aculta_wiki | 1457 |
| aculta_forum | 1248 |
| aculta_lms | 1147 |
| aculta_index | 1022 |
| aculta_lms_h5p | 929 |
| aculta_commerce | 799 |
| aculta_mailer | 652 |
| aculta_studio | 613 |
| aculta_pagamentos_mercadopago | 594 |
| aculta420_v2 | 478 |
| aculta_donation | 378 |
| aculta_linkweed | 377 |
| aculta_statistics | 351 |
| aculta_deployer | 339 |
| aculta_document_viewer | 324 |
| aculta_calendar | 311 |
| aculta_cgu | 310 |
| aculta_mail_confirmer | 241 |
| aculta_image | 229 |
| aculta_game | 209 |
| aculta_file_uploader | 153 |
| aculta_magazine | 149 |
| aculta_user | 114 |
| aculta_maps | 105 |
| aculta_events_openstreetmap | 104 |
| aculta_patcher | 101 |

## Identificadores mais frequentes

| Identificador | Ocorrências |
| --- | ---: |
| `offsetAccess.nonOffsetAccessible` | 5774 |
| `argument.type` | 3942 |
| `method.nonObject` | 2337 |
| `missingType.iterableValue` | 2214 |
| `cast.string` | 1844 |
| `cast.int` | 1144 |
| `method.notFound` | 765 |
| `property.nonObject` | 553 |
| `return.type` | 346 |
| `foreach.nonIterable` | 311 |
| `binaryOp.invalid` | 255 |
| `offsetAccess.invalidOffset` | 155 |
| `missingType.return` | 119 |
| `property.notFound` | 113 |
| `encapsedStringPart.nonString` | 96 |
| `offsetAccess.notFound` | 86 |
| `staticMethod.alreadyNarrowedType` | 76 |
| `method.alreadyNarrowedType` | 73 |
| `nullCoalesce.offset` | 71 |
| `instanceof.alwaysTrue` | 53 |

## Arquivos com mais dívida

| Arquivo | Ocorrências |
| --- | ---: |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Kernel/WikiLeadImageLifecycleKernelTest.php` | 819 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php` | 270 |
| `scripts/inventory-ui-suite-consumers.php` | 237 |
| `scripts/lib/Aculta420DesignFoundationsAnalyzer.php` | 206 |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/src/Cli.php` | 191 |
| `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` | 189 |
| `web/modules/custom/aculta_portal/modules/aculta_index/src/Integrity/ContentIndexIntegrityChecker.php` | 177 |
| `scripts/validate-portal-commerce-security.php` | 176 |
| `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php` | 169 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationLifecycleKernelTest.php` | 140 |
| `web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit/StudioProfileControllerTest.php` | 118 |
| `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` | 118 |
| `web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Kernel/ContentIndexSchemaKernelTest.php` | 109 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Kernel/ForumNoticeIdempotencyKernelTest.php` | 108 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventStructuredDataKernelTest.php` | 104 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationAdminAndModalKernelTest.php` | 100 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php` | 95 |
| `web/modules/custom/aculta_portal/modules/aculta_cgu/tests/src/Kernel/ContributionLifecycleKernelTest.php` | 92 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AttemptArchiveLifecycleTest.php` | 88 |
| `scripts/institution/phase6-account-email-test.php` | 85 |

## Como usar

- O total congelado é o teto histórico; qualquer erro fora dele reprova o gate.
- Ao alterar um arquivo, corrija a dívida desse arquivo antes de publicar.
- O baseline não é regenerado automaticamente; reduções exigem remover as entradas correspondentes no mesmo commit.
- Refira sempre SHA, versão do PHPStan e escopo ao comparar números.

