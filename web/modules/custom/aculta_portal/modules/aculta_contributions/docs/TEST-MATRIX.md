# Test matrix

| Contract | Test | Status |
| --- | --- | --- |
| Adapter IDs are trusted, unique, and unknown IDs fail closed | `ContributionAdapterRegistryTest` (Unit) | Passed locally |
| Review decisions cannot reopen final states | `ContributionStateMachineTest` (Unit) | Passed locally |
| Revision persistence, payload, and private access | `ContributionEntityKernelTest` (Kernel) | Passed: 1 test / 16 assertions |
| Adapter-validated private source context is separate and revisionable | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Accepted field selection is recorded with decision revision | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Missing context fields are restored by an idempotent entity schema update | `ContributionEntityKernelTest` (Kernel) | Passed |
| Entity access is delegated to the source adapter; unknown adapter access fails closed | `ContributionEntityKernelTest` (Kernel), registry Unit | Passed |
| Idempotent submission, no write before approval, and selected-field application | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Stale-source conflict, permission denial, allowlist rejection | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Configurable Flood threshold and transaction rollback | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Review feedback, author resubmission, and final withdrawal revision | `ContributionLifecycleKernelTest` (Kernel) | Passed: 1 test |
| Lock contention between independent database backends/connections | `ContributionLifecycleKernelTest` (Kernel) | Passed: second submission denied, no proposal persisted |
| Lock contention across OS processes with independent DB connections | `ContributionLifecycleKernelTest::testOsProcessContentionStopsSubmission` + `ContributionLockWorker.php` | Passed: 1 test / 13 assertions; the competing proposal was denied and not persisted. Full Entity + Lifecycle Kernel: 14 tests / 161 assertions on HEAD `50d01ac2c`, existing `aculta_simpletest`; later commits were LMS-only. |
| Wiki adapter allowlists, purpose/access policy, and list-mode choices | `WikiContributionAdapterTest` (Unit), Wiki proposal Kernel | Passed: 3/5 Unit; adapter registration/access focal 1/223 |
| Wiki data migration preserves proposal/revision identity and retries safely | `WikiLeadImageLifecycleKernelTest::testWikiContributionMigrationPreservesRevisionHistory` (Kernel) | Passed locally: 1 test / 53 assertions; Runtime not updated |
| OS-level multiprocess lock behavior | Separate process harness | Pending |
| Wiki migration, Events policy, admin collection, Messenger privacy, Index exclusion | Domain integration tests pending | Pending |
| Events Organization/Location proposals: private submit, protected fields, selective apply, stale-source conflict, approval-only Location creation | `EventOrganizationLifecycleKernelTest` | Passed in combined regression: Events/Contributions 27 tests / 658 assertions; 24 upstream deprecations |
| Content Admin exposes organization proposal queue only through Events native route access | `ModeratableCollectionAccessKernelTest` (Kernel), `ModeratableCollectionRegistryTest` (Unit) | Kernel 4/56 and Unit 1/4 PASS |
| Content Admin discovers Event proposal queue only through Events native route access; review form accepts only event_create/event_update allowlisted fields | `ModeratableCollectionRegistryTest` (Unit), `EventOrganizationLifecycleKernelTest::testEventCreateContributionWaitsForApprovalAndCreatesDates` | Unit 1/8; full Events Kernel 21/691 PASS; 24 Core/contrib deprecations |
| Notice failure does not undo a committed source change or proposal approval | `ContributionLifecycleKernelTest::testNoticeFailureDoesNotRollBackDecisionOrSource` (Kernel) | Passed: 1 test / 10 assertions |
| Events outcome enqueue failure remains recoverable from a minimal outbox | `OrganizationContributionEventQueueTest` (Unit) | Passed: 1 test / 12 assertions |
| Events Organization/Location/Event decisions use minimal Messenger envelopes; consumer rejects unsafe metadata | `EventsContributionNoticeConsumerTest` (Unit), queue/worker/event Units, `EventOrganizationLifecycleKernelTest` | Unit 11/32; full Kernel PASS 2026-11-02: 23/753; 24 Core/contrib deprecations. Payload/rationale/address excluded; Runtime update 10015 remains pending |
| Event create stays private until approval, creates draft for proposal author, rejects publication status, revalidates author create capability; update applies selected fields/appends Dates, revalidates fresh author Entity Access and detects stale origin | `EventOrganizationLifecycleKernelTest` Event Contributions scenarios | PASS, full class rerun 2026-11-02: 23 tests / 753 assertions; 24 Core/contrib deprecations |
| Event apply revalidates claimed Organization membership and proposed Date Location visibility after submission | `EventOrganizationLifecycleKernelTest::testEventUpdateContributionRevalidatesOrganizationMembership`, `::testEventUpdateContributionRevalidatesDateLocationAccess` | PASS, full class rerun 2026-11-02: 23 tests / 753 assertions; 24 Core/contrib deprecations |
| Events-domain author workspace lists own Organization, Location, and Event proposals; another account cannot read details | `EventOrganizationLifecycleKernelTest` | PASS, full class rerun 2026-11-02: 23 tests / 753 assertions; includes 3 focused regressions (111 assertions) |
| Event contribution workspace routes remain only in Eventos purpose and uncached; dashboard links to the workspace | `EventDomainBoundaryKernelTest::testOrganizerRoutesBelongOnlyToEventsPurpose`, `::testOrganizerDashboardBuildsWithAccessResultObject` | PASS, full class rerun 2026-11-02: 10 tests / 369 assertions; 18 Core/contrib deprecations |
| WE SATIVA 500 on development Runtime | Read-only watchdog + anonymous HTTP smoke before/after cache rebuild | Reproduced HTTP 500 on `https://wiki420.aculta.toca.net.br/wesativa`; watchdog identified stale compiled-container reference to removed `Drupal\\aculta_lms\\Hook\\LearningRoomHooks` (file absent in checkout). After a full pre-write snapshot and `drush cr`, `/wesativa`, `/wesativa/inicio`, and `/wesativa/verbetes/novo` return expected anonymous 403; legacy proposal paths return 404. Missing-class fatal no longer appears. Bootstrap theme warnings for `#form_id` remain; authenticated response not revalidated. |
| Events Kernel lifecycle and domain-boundary suites on `dev` HEAD `dd6ba130c` | `EventOrganizationLifecycleKernelTest` + `EventDomainBoundaryKernelTest`, with `SIMPLETEST_DB` sourced from the protected local env file and checked to target `aculta_simpletest` | Passed: 37 tests / 1,275 assertions; 24 upstream deprecations. |
| Contributions core Unit suite on `dev` HEAD `dd6ba130c` | `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_contributions/tests/src/Unit` | Passed: 3 tests / 19 assertions. |

Unit command:

```sh
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_contributions/tests/src/Unit
```

Kernel command must use the repository's existing `aculta_simpletest` through
`SIMPLETEST_DB`; do not point it at `aculta_runtime` or create a database.
Historical combined Contributions/Events run passed 27 Kernel tests / 658
assertions with 24 Core/contrib deprecations. Focused Messenger/queue Units
passed 9 tests / 27 assertions. The updated Events suite passed at the recorded
HEAD above using only the existing `aculta_simpletest` database.
