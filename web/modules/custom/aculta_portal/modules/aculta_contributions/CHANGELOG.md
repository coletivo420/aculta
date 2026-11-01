# Changelog

## Complemento local — concorrência interprocesso (2026-11-01)

- Um worker PHP separado abre conexão DB independente e segura o lock de
  submissão enquanto o Kernel tenta a mesma operação. A submissão concorrente
  é negada sem persistência. Suíte Entity + Lifecycle: 14/161; worker e teste
  passam lint e PHPCS Drupal. Executado em `aculta_simpletest`, sem Runtime.

## Complemento local — autor Events e recuperação do 500 da WE SATIVA (2026-11-01)

- `event_create` e `event_update` agora têm formulários de autor; `event_update`
  exige Entity Access atual e armazena fingerprint da origem persistida. Avisos
  de submissão são privados e usam envelope mínimo Messenger.
- Suítes Kernel de lifecycle Events e fronteira de domínio passaram juntas:
  37 testes / 1.275 assertions, 24 depreciações upstream.
- O HTTP 500 local de `/wesativa` era causado pelo container compilado ainda
  referenciar `LearningRoomHooks`, removido na mudança de apresentação LMS para
  Studio. Após snapshot integral e `drush cr`, smoke anônimo retorna 403 esperado
  nas rotas protegidas e 404 nos caminhos legados. A validação autenticada
  permanece pendente.

## Complemento local — adapter inicial de Evento (2026-11-01)

- `aculta_events` registra `events_event` para criação/atualização privada;
  criação aprovada permanece rascunho, atualizações aplicam campos escolhidos e
  Datas propostas são aditivas. Estado/publicação e campos internos não entram
  na allowlist.
- Revalidação de Eventos, Datas, Organizações/Locais e conflitos coberta pela
  suíte Kernel Events: 19 testes / 629 assertions PASS, com 24 depreciações
  Core/contrib. UI de submissão, workspace e avisos específicos seguem
  pendentes; Runtime não foi alterado.

## Complemento local — resultados de Events (2026-11-02)

- Decisões editoriais são commitadas antes do enqueue de avisos. Falha no
  adapter de notificação é registrada sem reverter a alteração oficial.
- A cobertura Kernel confirma que aprovação e alteração da origem persistem
  quando a notificação falha (1 teste / 10 assertions PASS).

## 0.1.0-dev.3

- Wiki proposal submission now uses the shared contribution lifecycle after a
  fail-closed gate confirms every legacy proposal has a durable migration
  checkpoint. The adapter records Wiki decisions within the contribution
  lifecycle transaction.
- Wiki editorial consumers support both migrated Contributions and legacy
  proposals during the transition; the original Wiki entity tables remain.
- Kernel cutover/status 2 tests / 264 assertions and lifecycle Kernel 10 / 109
  assertions PASS. Runtime migration has not run.

## 0.1.0-dev.2

- The Wiki status route now resolves migrated Contributions and falls back to
  legacy proposals by stable numeric ID; Kernel migration coverage verifies
  resolution on both sides of the copy (1 test / 55 assertions). Runtime and
  the remaining Wiki consumer cutover are pending.

## 0.1.0-dev.1

- Added the private revisionable `aculta_contribution` entity and explicit
  default-deny access handler.
- Added an adapter registry that accepts only trusted tagged services and a
  lifecycle service for idempotent submissions, bounded Flood protection,
  locked decisions, optimistic revision checks, and selected-field application.
- Added a closed state machine and configurable submission Flood defaults
  (10 proposals per account per 3,600 seconds, matching the existing Wiki
  submission policy).
- Declared Core `options` for the list fields and added MariaDB Kernel coverage
  for revisions, private access, idempotency, selective application, conflicts,
  unauthorized submission, allowlist rejection, Flood limits, and rollback.
- Delegated proposal entity access and lifecycle authorization to each trusted
  domain adapter so global contribution grants cannot expose another domain's
  private proposals.
- Added Kernel coverage for feedback, an author's revised submission, and final
  withdrawal as revision history.
- Verified a competing submission is denied by a lock held through an
  independent MariaDB connection, with no proposal persisted.
- Proposal records capture the owner module and trusted active purpose; Drupal
  language is retained through the entity's `langcode` field.
- Added revisionable private `source_data` and `decision_data` maps. Adapters
  validate source context separately from applyable payload, and accepted
  revisions record selected keys plus normalized adapter choices.
- Added update 10001 to install the new context fields idempotently on an
  existing Contributions installation.
- No domain adapter, public route, Wiki data migration, Events integration,
  administrative queue, or Messenger event is included in this version.

## Wiki integration in progress

- Registered the Wiki adapter and added update 10003 for batch reconciliation
  of legacy proposal/revision identity and content. Kernel migration fixture:
  1 test / 53 assertions. Runtime update and all consumer cutover remain
  pending; see the module README and roadmap.
