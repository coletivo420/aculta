# Inventário atual do LMS contrib

**Estado:** atualizado em 2026-11-03 na branch `dev`.

Este documento distingue referências históricas de dependências executáveis.
O ACULTA não exige os módulos externos `lms`, `lms_classes`,
`lms_answer_plugins`, `lms_answer_comments` ou `lms_file_upload` para instalar,
testar ou executar seus módulos próprios.

## Dependências removidas

| Item | Estado atual | Evidência |
| --- | --- | --- |
| `drupal/lms` | Removido | Ausente de `composer.json`, `composer.lock` e `core.extension.yml`. |
| `lms_classes` | Removido | Nenhuma dependência de módulo; Group usa relações próprias do ACULTA/configuração. |
| `lms_answer_plugins` | Removido | Plugins de resposta estão em `aculta_lms`. |
| `lms_answer_comments` | Removido | Comentários usam `aculta_lms_answer_comment`. |
| `drupal/lms_file_upload` | Removido | Upload e política são fornecidos por `aculta_file_uploader`. |

## Dependências preservadas, mas não são LMS contrib

| Item | Motivo |
| --- | --- |
| `group` | Infraestrutura de cursos, turmas e memberships. O plugin `group_membership` é do Group e continua intencional. |
| `webform_score` | Projeto separado; não é `drupal/lms` nem submódulo do LMS. |
| `entity_print` | Projeto separado para impressão/PDF; não é `drupal/lms`. |
| `aculta_file_uploader` | Módulo ACULTA obrigatório para uploads do LMS próprio. |

## Compatibilidade legada

`UploadedAnswerRouteSubscriber` procura opcionalmente
`lms_file_upload.answer_file_download`. Essa referência é apenas compatibilidade
com instalações antigas: o assinante ignora a ausência da rota e, quando ela
existe, substitui a autorização pela checagem ACULTA
`aculta_lms.uploaded_answer_access::access`.

## Testes

`AttemptArchiveLifecycleTest` usa entidades `aculta_lms_*`, comentários Core,
Group e `aculta_file_uploader`. O teste instala o tipo de curso e a relação
genérica `group_membership` a partir do Config Sync para não depender de
`lms_classes`.

`UploadedAnswerRouteSubscriberTest` cobre a ausência da rota legada e a
preservação da autorização ACULTA quando a rota é fornecida por uma instalação
antiga.

## Referências históricas

Inventários, ADRs, changelogs e estudos que descrevem `drupal/lms`, entidades
`lms_*` ou uma migração ainda pendente são registros históricos. Eles não devem
ser usados como instrução operacional atual; a decisão vigente é a desta
matriz e do ADR-042 atualizado.
