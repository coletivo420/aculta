# Dívida PHPStan — ACULTA

Gerado em 2026-11-18T23:16:04+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `3375b5fc3` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 17706 |
| Dívida congelada no baseline | 17706 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 10448 |
| Produção | 10573 |
| Testes | 7133 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 212 |
| media | 15653 |
| baixa | 1841 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| scripts | 1931 |
| aculta_events | 1770 |
| aculta_messenger | 1737 |
| aculta_wiki | 1368 |
| aculta_portal | 1152 |
| aculta_forum | 1079 |
| aculta_lms | 1031 |
| aculta_lms_h5p | 928 |
| aculta_index | 915 |
| aculta_commerce | 623 |
| aculta_pagamentos_mercadopago | 594 |
| aculta_studio | 592 |
| aculta_mailer | 580 |
| aculta_linkweed | 377 |
| aculta_statistics | 351 |
| aculta_deployer | 339 |
| aculta420_v2 | 339 |
| aculta_document_viewer | 295 |
| aculta_mail_confirmer | 241 |
| aculta_calendar | 240 |
| aculta_image | 229 |
| aculta_cgu | 216 |
| aculta_file_uploader | 153 |
| aculta_magazine | 149 |
| aculta_game | 142 |
| aculta_patcher | 101 |
| aculta_gallery | 95 |
| aculta_editor | 86 |
| aculta_mcp | 42 |
| aculta_user | 10 |

## Identificadores mais frequentes

| Identificador | Ocorrências |
| --- | ---: |
| `offsetAccess.nonOffsetAccessible` | 4625 |
| `argument.type` | 3370 |
| `method.nonObject` | 2082 |
| `missingType.iterableValue` | 1725 |
| `cast.string` | 1605 |
| `cast.int` | 1051 |
| `method.notFound` | 709 |
| `property.nonObject` | 487 |
| `return.type` | 289 |
| `foreach.nonIterable` | 278 |
| `binaryOp.invalid` | 218 |
| `offsetAccess.invalidOffset` | 127 |
| `property.notFound` | 104 |
| `encapsedStringPart.nonString` | 88 |
| `missingType.return` | 82 |
| `staticMethod.alreadyNarrowedType` | 70 |
| `offsetAccess.notFound` | 68 |
| `nullCoalesce.offset` | 67 |
| `method.alreadyNarrowedType` | 61 |
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

