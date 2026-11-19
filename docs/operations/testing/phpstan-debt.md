# Dívida PHPStan — ACULTA

Gerado em 2026-11-19T01:40:20+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `82cbd7efdb82e291c5c77047cb4886c8a694b0fd` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 17533 |
| Dívida congelada no baseline | 17533 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 10332 |
| Produção | 10417 |
| Testes | 7116 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 212 |
| media | 15497 |
| baixa | 1824 |

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
| aculta_forum | 923 |
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
| `offsetAccess.nonOffsetAccessible` | 4590 |
| `argument.type` | 3337 |
| `method.nonObject` | 2059 |
| `missingType.iterableValue` | 1708 |
| `cast.string` | 1599 |
| `cast.int` | 1017 |
| `method.notFound` | 709 |
| `property.nonObject` | 474 |
| `return.type` | 288 |
| `foreach.nonIterable` | 274 |
| `binaryOp.invalid` | 218 |
| `offsetAccess.invalidOffset` | 126 |
| `property.notFound` | 100 |
| `encapsedStringPart.nonString` | 86 |
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

