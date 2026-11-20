# Dívida PHPStan — ACULTA

Gerado em 2026-11-20T11:15:32+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `55f022ad32f051c1483c492b1aaf6eb2b90af8af` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 15204 |
| Dívida congelada no baseline | 15204 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 8992 |
| Produção | 9427 |
| Testes | 5777 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 197 |
| media | 13478 |
| baixa | 1529 |

## Evolução desde o baseline de referência

| Classificação | Entradas |
| --- | ---: |
| Dívida removida | 203 |
| Dívida mantida | 8992 |
| Nova dívida (bloqueante) | 0 |
| Contagem aumentada (bloqueante) | 0 |
| Contagem reduzida | 14 |
| Identidade de erro alterada (revisão) | 0 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| scripts | 1667 |
| aculta_events | 1528 |
| aculta_wiki | 1353 |
| aculta_portal | 1120 |
| aculta_messenger | 1052 |
| aculta_lms | 975 |
| aculta_lms_h5p | 923 |
| aculta_index | 912 |
| aculta_commerce | 620 |
| aculta_pagamentos_mercadopago | 594 |
| aculta_mailer | 580 |
| aculta_studio | 552 |
| aculta_linkweed | 377 |
| aculta_statistics | 344 |
| aculta_deployer | 339 |
| aculta420_v2 | 328 |
| aculta_document_viewer | 292 |
| aculta_mail_confirmer | 241 |
| aculta_calendar | 239 |
| aculta_image | 227 |
| aculta_cgu | 202 |
| aculta_file_uploader | 153 |
| aculta_magazine | 142 |
| aculta_game | 130 |
| aculta_gallery | 95 |
| aculta_patcher | 90 |
| aculta_editor | 86 |
| aculta_mcp | 42 |
| aculta_mcp_client | 1 |

## Identificadores mais frequentes

| Identificador | Ocorrências |
| --- | ---: |
| `offsetAccess.nonOffsetAccessible` | 3905 |
| `argument.type` | 2992 |
| `method.nonObject` | 1728 |
| `cast.string` | 1438 |
| `missingType.iterableValue` | 1437 |
| `cast.int` | 821 |
| `method.notFound` | 617 |
| `property.nonObject` | 420 |
| `return.type` | 263 |
| `foreach.nonIterable` | 249 |
| `binaryOp.invalid` | 193 |
| `offsetAccess.invalidOffset` | 118 |
| `property.notFound` | 94 |
| `encapsedStringPart.nonString` | 85 |
| `missingType.return` | 78 |
| `nullCoalesce.offset` | 64 |
| `offsetAccess.notFound` | 63 |
| `method.alreadyNarrowedType` | 57 |
| `staticMethod.alreadyNarrowedType` | 53 |
| `instanceof.alwaysTrue` | 35 |

## Arquivos com mais dívida

| Arquivo | Ocorrências |
| --- | ---: |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Kernel/WikiLeadImageLifecycleKernelTest.php` | 819 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php` | 270 |
| `scripts/lib/Aculta420DesignFoundationsAnalyzer.php` | 206 |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/src/Cli.php` | 191 |
| `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` | 189 |
| `web/modules/custom/aculta_portal/modules/aculta_index/src/Integrity/ContentIndexIntegrityChecker.php` | 177 |
| `scripts/validate-portal-commerce-security.php` | 176 |
| `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php` | 158 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationLifecycleKernelTest.php` | 139 |
| `web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit/StudioProfileControllerTest.php` | 118 |
| `web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Kernel/ContentIndexSchemaKernelTest.php` | 109 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationAdminAndModalKernelTest.php` | 100 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php` | 95 |
| `web/modules/custom/aculta_portal/modules/aculta_cgu/tests/src/Kernel/ContributionLifecycleKernelTest.php` | 92 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AttemptArchiveLifecycleTest.php` | 88 |
| `scripts/institution/phase6-account-email-test.php` | 85 |
| `scripts/validate-aculta420-foundation.php` | 82 |
| `web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Kernel/ContentIndexRebuilderKernelTest.php` | 81 |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php` | 80 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldWidget/aculta_H5PEditorWidget.php` | 80 |

## Como usar

- O total congelado é o teto histórico; qualquer erro fora dele reprova o gate.
- Ao alterar um arquivo, corrija a dívida desse arquivo antes de publicar.
- O baseline não é regenerado automaticamente; reduções exigem remover as entradas correspondentes no mesmo commit.
- Refira sempre SHA, versão do PHPStan e escopo ao comparar números.

