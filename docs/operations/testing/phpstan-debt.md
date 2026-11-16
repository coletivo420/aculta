# Dívida PHPStan — ACULTA

Gerado em 2026-11-16T10:22:01+00:00 por `scripts/phpstan-debt-report.php`.

| Item | Valor |
| --- | --- |
| Commit | `5226b10024b3194ea49321a0b2a769705fe03ae6` |
| PHP | 8.4.27 |
| PHPStan | PHPStan - PHP Static Analysis Tool 2.3.5 |
| Nível | 10 |
| Escopo | web/modules/custom, web/themes/custom, scripts |
| Inconsistências de baseline | 0 |

## Totais

| Medida | Ocorrências |
| --- | ---: |
| Total bruto no nível 10 | 21966 |
| Dívida congelada no baseline | 21966 |
| Erros não suprimidos | 0 |
| Entradas no baseline | 13129 |
| Produção | 13976 |
| Testes | 7990 |

## Criticidade funcional

| Criticidade | Ocorrências |
| --- | ---: |
| alta | 221 |
| media | 19350 |
| baixa | 2395 |

## Evolução desde o baseline de referência

| Classificação | Entradas |
| --- | ---: |
| Dívida removida | 0 |
| Dívida mantida | 13129 |
| Nova dívida (bloqueante) | 0 |
| Contagem aumentada (bloqueante) | 0 |
| Contagem reduzida | 0 |
| Identidade de erro alterada (revisão) | 0 |

## Dívida por módulo (prioridade de redução)

| Módulo | Ocorrências |
| --- | ---: |
| scripts | 2995 |
| aculta_portal | 1993 |
| aculta_messenger | 1830 |
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
| aculta420_v2 | 479 |
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
| `offsetAccess.nonOffsetAccessible` | 6364 |
| `argument.type` | 4054 |
| `method.nonObject` | 2427 |
| `missingType.iterableValue` | 2221 |
| `cast.string` | 1846 |
| `cast.int` | 1145 |
| `method.notFound` | 785 |
| `property.nonObject` | 562 |
| `return.type` | 347 |
| `foreach.nonIterable` | 319 |
| `binaryOp.invalid` | 277 |
| `offsetAccess.invalidOffset` | 177 |
| `missingType.return` | 119 |
| `property.notFound` | 113 |
| `staticMethod.internalClass` | 105 |
| `encapsedStringPart.nonString` | 97 |
| `offsetAccess.notFound` | 88 |
| `staticMethod.alreadyNarrowedType` | 76 |
| `method.alreadyNarrowedType` | 74 |
| `nullCoalesce.offset` | 71 |

## Arquivos com mais dívida

| Arquivo | Ocorrências |
| --- | ---: |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Kernel/WikiLeadImageLifecycleKernelTest.php` | 819 |
| `scripts/validate-ui-patterns-views-cutover.php` | 305 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php` | 270 |
| `scripts/inventory-ui-suite-consumers.php` | 237 |
| `scripts/lib/Aculta420DesignFoundationsAnalyzer.php` | 206 |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/src/Cli.php` | 191 |
| `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` | 189 |
| `scripts/validate-portal-commerce-security.php` | 177 |
| `web/modules/custom/aculta_portal/modules/aculta_index/src/Integrity/ContentIndexIntegrityChecker.php` | 177 |
| `scripts/validate-aculta420-v2-sdc.php` | 173 |
| `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php` | 169 |
| `scripts/install-institution.php` | 163 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationLifecycleKernelTest.php` | 140 |
| `web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit/StudioProfileControllerTest.php` | 118 |
| `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` | 118 |
| `web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Kernel/ContentIndexSchemaKernelTest.php` | 109 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Kernel/ForumNoticeIdempotencyKernelTest.php` | 108 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventStructuredDataKernelTest.php` | 104 |
| `web/modules/custom/aculta_portal/modules/aculta_events/tests/src/Kernel/EventOrganizationAdminAndModalKernelTest.php` | 100 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php` | 95 |

## Como usar

- Baseline reemitido em 2026-11-16 a partir de uma análise bruta nível 10: 13.129 entradas e 21.966 ocorrências; nenhuma supressão obsoleta ou dívida nova foi identificada.
- O total congelado é o teto histórico; qualquer erro fora dele reprova o gate.
- Ao alterar um arquivo, corrija a dívida desse arquivo antes de publicar.
- O baseline não é regenerado automaticamente; reduções exigem remover as entradas correspondentes no mesmo commit.
- Refira sempre SHA, versão do PHPStan e escopo ao comparar números.

