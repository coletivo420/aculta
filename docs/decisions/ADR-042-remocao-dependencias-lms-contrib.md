# ADR-042 — Remoção das dependências do LMS contrib

- **Status:** Implementada no código e no Composer em `dev`.
- **Data da atualização:** 2026-11-03.
- **Escopo:** remover dependências executáveis do Drupal LMS contrib sem remover
  a infraestrutura Group ou dependências adjacentes independentes.

## Estado atual

O `aculta_lms` é o dono das entidades de aula, atividade, resposta, progresso,
tentativa arquivada, comentários de resposta e política de arquivos. Seu
`aculta_lms.info.yml` não depende de `lms`, `lms_classes`,
`lms_answer_plugins`, `lms_answer_comments` ou `lms_file_upload` como módulos
externos. `composer.json` e `composer.lock` também não exigem `drupal/lms*`.

O módulo `group` continua sendo dependência deliberada. Cursos e memberships
usam o plugin genérico `group_membership`; isso não é uma dependência do LMS
contrib e não deve ser duplicado em `aculta_lms`. A relação específica
`lms_classes` é um plugin próprio do ACULTA, mantido para a estrutura de cursos
e turmas do fórum.

`webform_score` e `entity_print` são projetos separados e não foram
classificados como `drupal/lms`; permanecem apenas onde seus contratos próprios
forem necessários.

O assinante `UploadedAnswerRouteSubscriber` mantém compatibilidade opcional
com a rota `lms_file_upload.answer_file_download` para instalações antigas. A
rota é alterada somente quando existe; sua ausência não bloqueia instalação,
reconstrução de rotas ou testes. A autorização efetiva continua sendo
`aculta_lms.uploaded_answer_access::access`.

## Verificações de independência

- `composer.json`/`composer.lock`: sem `drupal/lms` ou `drupal/lms_file_upload`.
- `config/sync/core.extension.yml`: não habilita os módulos LMS contrib.
- `AttemptArchiveLifecycleTest` usa somente `aculta_lms`, Group e
  `aculta_file_uploader`; não importa `Drupal\Tests\lms`.
- O teste de rota cobre a ausência da rota legada e a preservação da
  autorização ACULTA quando uma instalação antiga ainda a fornece.

## Registro histórico

Planos P0–P6, medições e decisões de migração anteriores continuam preservados
nos changelogs e no histórico do repositório; não representam dependências
atuais do código.
