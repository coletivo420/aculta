# Dívida PHPStan — ACULTA

Gerado em 2026-11-18T19:05:48+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `1dedf93b13b815bf3e344738597a02512284c6b4` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 18234 |
| Dívida congelada no baseline | 18234 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 10797 |
| Produção | 10700 |
| Testes | 7534 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 214 |
| media | 16165 |
| baixa | 1855 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| scripts | 1931 |
| aculta_events | 1770 |
| aculta_messenger | 1737 |
| aculta_wiki | 1368 |
| aculta_portal | 1206 |
| aculta_lms | 1147 |
| aculta_forum | 1094 |
| aculta_index | 1022 |
| aculta_lms_h5p | 928 |
| aculta_commerce | 733 |
| aculta_pagamentos_mercadopago | 594 |
| aculta_studio | 592 |
| aculta_mailer | 580 |
| aculta_linkweed | 377 |
| aculta_statistics | 351 |
| aculta_deployer | 339 |
| aculta420_v2 | 339 |
| aculta_document_viewer | 323 |
| aculta_calendar | 249 |
| aculta_mail_confirmer | 241 |
| aculta_cgu | 238 |
| aculta_image | 229 |
| aculta_game | 209 |
| aculta_file_uploader | 153 |
| aculta_magazine | 149 |
| aculta_patcher | 101 |
| aculta_gallery | 95 |
| aculta_editor | 86 |
| aculta_mcp | 42 |
| aculta_user | 10 |

## Identificadores mais frequentes

| Identificador | Ocorrências |
| --- | ---: |
| `offsetAccess.nonOffsetAccessible` | 4842 |
| `argument.type` | 3496 |
| `method.nonObject` | 2161 |
| `missingType.iterableValue` | 1738 |
| `cast.string` | 1636 |
| `cast.int` | 1068 |
| `method.notFound` | 720 |
| `property.nonObject` | 495 |
| `return.type` | 294 |
| `foreach.nonIterable` | 281 |
| `binaryOp.invalid` | 221 |
| `offsetAccess.invalidOffset` | 129 |
| `property.notFound` | 105 |
| `encapsedStringPart.nonString` | 88 |
| `missingType.return` | 82 |
| `staticMethod.alreadyNarrowedType` | 71 |
| `offsetAccess.notFound` | 70 |
| `nullCoalesce.offset` | 67 |
| `method.alreadyNarrowedType` | 62 |
| `missingType.generics` | 45 |

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
| `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php` | 163 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationLifecycleKernelTest.php` | 139 |
| `web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit/StudioProfileControllerTest.php` | 118 |
| `web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Kernel/ContentIndexSchemaKernelTest.php` | 109 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Kernel/ForumNoticeIdempotencyKernelTest.php` | 108 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventStructuredDataKernelTest.php` | 103 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationAdminAndModalKernelTest.php` | 100 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php` | 95 |
| `web/modules/custom/aculta_portal/modules/aculta_cgu/tests/src/Kernel/ContributionLifecycleKernelTest.php` | 92 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AttemptArchiveLifecycleTest.php` | 88 |
| `scripts/institution/phase6-account-email-test.php` | 85 |
| `scripts/validate-aculta420-foundation.php` | 82 |

## Como usar

- O total congelado é o teto histórico; qualquer erro fora dele reprova o gate.
- Ao alterar um arquivo, corrija a dívida desse arquivo antes de publicar.
- O baseline não é regenerado automaticamente; reduções exigem remover as entradas correspondentes no mesmo commit.
- Refira sempre SHA, versão do PHPStan e escopo ao comparar números.

