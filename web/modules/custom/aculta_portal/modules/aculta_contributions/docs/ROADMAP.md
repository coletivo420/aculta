# Roadmap

- [x] Core private revisioned proposal model, adapter registry, fail-closed
  access, idempotent submission, configurable Flood protection, proposal
  revisions/withdrawal, decisions, locks, base-version conflicts, and selective
  applier contract. Kernel covers persistence, transactions, access, rollback,
  stale-source conflicts, author revision after feedback, withdrawal, separate
  source context, and recorded field selection/application choices. Update
  10001 restores context fields idempotently on an existing installation.
- [x] Lock contention across independent database backends/connections is
  covered by Kernel; the competing submission is denied without persistence.
- [x] OS-level multiprocess harness starts a separate PHP process with its own
  DB connection; the competing submission is denied without persistence.
  Contribution Entity + Lifecycle Kernel: 14 tests / 161 assertions on HEAD
  `50d01ac2c`; subsequent commits were LMS-only.
- [ ] Fresh-install/update rehearsal remains pending beyond existing entity
  schema and idempotent-update Kernel fixtures; no install/update rehearsal was
  performed in Runtime.
- [x] Register the Wiki adapter, validate its allowlists and domain access, and
  share approved-field application with the current editorial service. Kernel
  tests cover registration/access and the adapter's Unit covers source/list
  choices.
- [x] Prepare update 10003 with per-proposal transaction, bounded batches,
  idempotent checkpoints, and exact numeric ID/UUID/revision ID preservation.
  Kernel migration/status fixture: 1 test / 55 assertions, including retry and
  status-route resolution before and after the copy.
- [x] Switch Wiki submission to the shared lifecycle and status/withdraw/
  decision, workspace, editorial queue, and notice consumers to dual-store
  resolution. New submissions are gated until every legacy proposal has a
  durable checkpoint. Keep Content Moderation for official Wiki revisions.
- [ ] Inspect Runtime proposal counts, UUID/revision totals, collisions and
  translations before running the update. The update fails closed on ID
  collisions or translated legacy revisions. Legacy accepted-field/list-mode
  selections were not stored and cannot be reconstructed.
- [x] Events Organization proposals cover unclaimed-profile updates and
  unclaimed creation, with reviewer-selected fields and dual-read legacy queue.
  Claims and updates to claimed Organizations remain in their existing owners.
- [x] Events Location proposals cover private creation/update, central BR
  address validation, protected fields, duplicate guard and revision conflicts.
  Approved creation remains draft and is not linked to an Event.
- [x] Initial Events adapter handles `event_create` / `event_update`, an
  editorial allowlist, owner/purpose access, locked fingerprint application,
  Organization/Location revalidation and additive Dates. Create approval
  produces a draft owned by the proposer; selected update fields only.
- [x] Author interfaces submit private `event_create` and `event_update`
  proposals; update requires current Entity Access and stores the persisted
  source fingerprint. A newly proposed Date is optional and append-only.
- [x] Event submission notices use a minimal private Messenger envelope and
  recoverable outbox. Updates 10015–10016 are implemented but not installed in
  Runtime.
- [ ] Full duplicate and related-access revocation matrix, and Runtime
  verification remain pending.
- [x] `aculta_content_admin` discovers the Events organization proposal queue
  through its native route access; no copied records or new decision grant.
- [x] Messenger stores minimal Organization/Location decision outcomes in the
  private site inbox, revalidating proposal revision, author and access. Events
  dispatches after editorial commit and retains enqueue failures in a minimal
  KeyValue outbox for cron recovery. Proposals stay out of Index.
- [x] Event submission notices and private author proposal workspace are
  implemented. Notices omit proposal payload; links revalidate owner access.
- [ ] Email/digest policy and Runtime installation of Messenger updates
  10015–10016 remain pending.
- [ ] Runtime Wiki migration/reconciliation, migration counts, rollback evidence,
  and access verification with separate accounts remain pending. Runtime
  enabled `aculta_contributions` after a pre-write snapshot on 2026-11-01 to
  repair the missing entity type that caused HTTP 500 in authenticated WE
  SATIVA paths; no Wiki proposal migration, updatedb, or config import ran.
- [x] Events lifecycle and domain-boundary Kernel suites pass together on
  `aculta_simpletest`: 37 tests / 1,275 assertions; 24 upstream deprecations.

The existing `aculta_wiki_proposal` entity remains readable during transition;
the coordinated service cutover is implemented, but the Runtime update and
real-data reconciliation have not run. The update does not remove legacy tables.
No Events, UGC, Forum, ownership-claim, or internal editorial workflow is
converted into a contribution by this initial module.
