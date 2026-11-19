# Dívida PHPStan — ACULTA

Gerado em 2026-11-19T03:40:09+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `d0844954f010a076d6c1842d5e9ac63d1df92ca8` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 16796 |
| Dívida congelada no baseline | 16796 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 9816 |
| Produção | 9989 |
| Testes | 6807 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 212 |
| media | 15000 |
| baixa | 1694 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| scripts | 1931 |
| aculta_events | 1770 |
| aculta_messenger | 1737 |
| aculta_wiki | 1368 |
| aculta_portal | 1152 |
| aculta_lms | 1014 |
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
| aculta_forum | 186 |
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
| `offsetAccess.nonOffsetAccessible` | 4433 |
| `argument.type` | 3237 |
| `method.nonObject` | 2010 |
| `missingType.iterableValue` | 1591 |
| `cast.string` | 1545 |
| `cast.int` | 962 |
| `method.notFound` | 667 |
| `property.nonObject` | 462 |
| `return.type` | 286 |
| `foreach.nonIterable` | 259 |
| `binaryOp.invalid` | 216 |
| `offsetAccess.invalidOffset` | 125 |
| `property.notFound` | 100 |
| `encapsedStringPart.nonString` | 85 |
| `missingType.return` | 82 |
| `staticMethod.alreadyNarrowedType` | 69 |
| `offsetAccess.notFound` | 68 |
| `nullCoalesce.offset` | 67 |
| `method.alreadyNarrowedType` | 61 |
| `instanceof.alwaysTrue` | 36 |

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

