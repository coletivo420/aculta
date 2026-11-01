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
  Contribution Entity + Lifecycle Kernel: 14/161 in the earlier run; current full
  Contributions suite passed 18 tests / 188 assertions on 2026-11-01.
- [x] Clean module install is covered by `ContributionModuleInstallKernelTest`
  (1/8): module installer, revisionable entity, base/revision storage, service
  registry and default Flood settings. A full independent site install was not
  run in Runtime; authorized updates were applied with a pre-write snapshot.
- [x] Register the Wiki adapter, validate its allowlists and domain access, and
  share approved-field application with the current editorial service. Kernel
  tests cover registration/access and the adapter's Unit covers source/list
  choices.
- [x] Prepare update 10003 with per-proposal transaction, bounded batches,
  idempotent checkpoints, and exact numeric ID/UUID/revision ID preservation.
  Kernel migration/status and fail-closed fixtures cover history/retry,
  dual-store status, ID collision and translated revision: 3 tests / 133
  assertions on `aculta_simpletest`. Runtime update 10003 ran; audit found no
  proposal/revision rows to copy, and legacy tables remain.
- [x] Switch Wiki submission to the shared lifecycle and status/withdraw/
  decision, workspace, editorial queue, and notice consumers to dual-store
  resolution. New submissions are gated until every legacy proposal has a
  durable checkpoint. Keep Content Moderation for official Wiki revisions.
- [x] Inspect Runtime proposal counts, UUID/revision totals, collisions and
  translations before running the update. On 2026-11-01 all legacy/shared
  proposal, revision, checkpoint and revision-map counts were zero; update 10003
  completed and the migration gate is complete. No content was copied. The update
  remains fail-closed for ID collisions or translated legacy revisions; legacy
  accepted-field/list-mode selections were not stored and cannot be reconstructed.
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
  recoverable outbox. Updates 10015–10016 are applied in Runtime and config sync is clean.
- [ ] `event_update` conflict/access scenarios passed locally (6/171 focused).
  Event create duplicate-title/date conflict passed 1/26; the full Events class
  passed 29/941 with this regression. Broader duplicate matrix and authenticated
  Runtime verification remain pending.
- [x] `aculta_content_admin` discovers the Events organization proposal queue
  through its native route access; no copied records or new decision grant.
- [x] Messenger stores minimal Organization/Location decision outcomes in the
  private site inbox, revalidating proposal revision, author and access. Events
  dispatches after editorial commit and retains enqueue failures in a minimal
  KeyValue outbox for cron recovery. Proposals stay out of Index.
- [x] Event submission notices and private author proposal workspace are
  implemented. Notices omit proposal payload; links revalidate owner access.
- [ ] Email/digest policy remains a product decision; notices currently stay
  in the site inbox and outside digest.
- [ ] Authenticated access verification with separate accounts remains pending.
  Wiki update 10003 ran after the authorized snapshot; its migration audit found
  no rows to copy and the gate is complete. Messenger updates 10015/10016 ran
  and config sync is clean. The local HTTP 500 was in Ganjalab and was corrected
  in `AccountCoursesReader`; WE SATIVA did not reproduce a 500.
- [x] Events lifecycle and domain-boundary Kernel suites pass together on
  `aculta_simpletest`: 37 tests / 1,275 assertions; 24 upstream deprecations.

The existing `aculta_wiki_proposal` entity remains readable during transition;
the coordinated service cutover is implemented. The Runtime update found no
legacy rows to copy and did not remove legacy tables.
No Events, UGC, Forum, ownership-claim, or internal editorial workflow is
converted into a contribution by this initial module.

Legacy entity references are classified in
`docs/architecture/INVENTARIO-ACULTA-CONTRIBUTIONS.md`: Wiki and Organization
suggestions remain owner-local for dual-read/checkpoint migration, Messenger
resolves both queued Wiki entity types, and Content Moderation remains only for
the official Wiki Node workflow. No `Workflow Participants` dependency was found.
