# Test matrix

| Contract | Test | Status |
| --- | --- | --- |
| Adapter IDs are trusted, unique, and unknown IDs fail closed | `ContributionAdapterRegistryTest` (Unit) | Passed locally |
| Review decisions cannot reopen final states | `ContributionStateMachineTest` (Unit) | Passed locally |
| Clean module install creates the revisionable entity tables and installs default config/services | `ContributionModuleInstallKernelTest` | Passed: 1 test / 8 assertions |
| Revision persistence, payload, and private access | `ContributionEntityKernelTest` (Kernel) | Passed in current full suite: 18 tests / 188 assertions |
| Adapter-validated private source context is separate and revisionable | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Accepted field selection is recorded with decision revision | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Missing context fields are restored by an idempotent entity schema update | `ContributionEntityKernelTest` (Kernel) | Passed |
| Entity access is delegated to the source adapter; unknown adapter access fails closed | `ContributionEntityKernelTest` (Kernel), registry Unit | Passed |
| Idempotent submission, no write before approval, and selected-field application | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Stale-source conflict, permission denial, allowlist rejection | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Configurable Flood threshold and transaction rollback | `ContributionLifecycleKernelTest` (Kernel) | Passed |
| Review feedback, author resubmission, and final withdrawal revision | `ContributionLifecycleKernelTest` (Kernel) | Passed: 1 test |
| Lock contention between independent database backends/connections | `ContributionLifecycleKernelTest` (Kernel) | Passed: second submission denied, no proposal persisted |
| Lock contention across OS processes with independent DB connections | `ContributionLifecycleKernelTest::testOsProcessContentionStopsSubmission` + `ContributionLockWorker.php` | Passed; competing proposal was denied and not persisted. Current full Contributions suite passed after adding the clean-install fixture. |
| Wiki adapter allowlists, purpose/access policy, and list-mode choices | `WikiContributionAdapterTest` (Unit), Wiki proposal Kernel | Passed: 3/5 Unit; adapter registration/access focal 1/223 |
| Wiki data migration preserves proposal/revision identity and retries safely | `WikiLeadImageLifecycleKernelTest::testWikiContributionMigrationPreservesRevisionHistory` (Kernel) | Kernel passed 1/53; Runtime update 10003 applied, audit found zero rows to copy; migration gate complete |
| Wiki migration, Events policy, admin collection, Messenger privacy, Index exclusion | Wiki/Events/Messenger Kernel and Unit suites listed above | Local coverage is present; authenticated Runtime journeys and Index projection after approval remain pending |
| Events Organization/Location proposals: private submit, protected fields, selective apply, stale-source conflict, approval-only Location creation | `EventOrganizationLifecycleKernelTest` | Current full class passed 29 tests / 941 assertions; 24 upstream deprecations |
| Content Admin exposes organization proposal queue only through Events native route access | `ModeratableCollectionAccessKernelTest` (Kernel), `ModeratableCollectionRegistryTest` (Unit) | Kernel 4/56 and Unit 1/4 PASS |
| Content Admin discovers Event proposal queue only through Events native route access; review form accepts only event_create/event_update allowlisted fields | `ModeratableCollectionRegistryTest` (Unit), `EventOrganizationLifecycleKernelTest::testEventCreateContributionWaitsForApprovalAndCreatesDates` | Unit 1/8; historical focused Events Kernel 21/691 PASS; 24 Core/contrib deprecations |
| Notice failure does not undo a committed source change or proposal approval | `ContributionLifecycleKernelTest::testNoticeFailureDoesNotRollBackDecisionOrSource` (Kernel) | Passed: 1 test / 10 assertions |
| Events outcome enqueue failure remains recoverable from a minimal outbox | `OrganizationContributionEventQueueTest` (Unit) | Passed: 1 test / 12 assertions |
| Events Organization/Location/Event decisions use minimal Messenger envelopes; consumer rejects unsafe metadata | `EventsContributionNoticeConsumerTest` (Unit), queue/worker/event Units, `EventOrganizationLifecycleKernelTest` | Unit 11/32; Events full class currently 29/941; 24 Core/contrib deprecations. Payload/rationale/address excluded; Runtime updates 10015–10016 applied and config sync clean |
| Event create stays private until approval, creates draft for proposal author, rejects publication status, revalidates author create capability; update applies selected fields/appends Dates, revalidates fresh author Entity Access and detects stale origin | `EventOrganizationLifecycleKernelTest` Event Contributions scenarios | PASS, full Events class 29 tests / 941 assertions; 24 Core/contrib deprecations |
| Event create rejects an exact title/start-time duplicate without creating a second Event | `EventOrganizationLifecycleKernelTest::testEventCreateContributionRejectsDuplicateTitleAndDate` | Passed: 1 test / 26 assertions; full Events class 29/941 |
| Event apply revalidates claimed Organization membership and proposed Date Location visibility after submission | `EventOrganizationLifecycleKernelTest::testEventUpdateContributionRevalidatesOrganizationMembership`, `::testEventUpdateContributionRevalidatesDateLocationAccess` | PASS, full Events class 29 tests / 941 assertions; 24 Core/contrib deprecations |
| Events-domain author workspace lists own Organization, Location, and Event proposals; another account cannot read details | `EventOrganizationLifecycleKernelTest` | PASS, full Events class 29 tests / 941 assertions; includes focused regressions |
| Event contribution workspace routes remain only in Eventos purpose and uncached; dashboard links to the workspace | `EventDomainBoundaryKernelTest::testOrganizerRoutesBelongOnlyToEventsPurpose`, `::testOrganizerDashboardBuildsWithAccessResultObject` | PASS, recorded Events domain-boundary class 10 tests / 369 assertions; 18 Core/contrib deprecations |
| Local development HTTP 500 and WE SATIVA check | Watchdog, focused Kernel and HTTP smoke | Current 500 was `/ganjalab`: `AccountCoursesReader` called `isAllowed()` on boolean `true`; correction passed 2 tests / 19 assertions and UID 1 provider path was exercised. `/wesativa` did not reproduce a 500; its anonymous protected route returned 403. Authenticated WE SATIVA access remains pending. |
| Events Kernel lifecycle and domain-boundary suites on `dev` HEAD `dd6ba130c` | `EventOrganizationLifecycleKernelTest` + `EventDomainBoundaryKernelTest`, with `SIMPLETEST_DB` sourced from the protected local env file and checked to target `aculta_simpletest` | Passed: 37 tests / 1,275 assertions; 24 upstream deprecations. |
| Contributions core full suite on the `dev` checkout based on `5a5973573` | `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_contributions/tests/src` with existing `SIMPLETEST_DB` | Passed 18 tests / 188 assertions (2026-11-01). |

Unit command:

```sh
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_contributions/tests/src/Unit
```

Kernel command must use the repository's existing `aculta_simpletest` through
`SIMPLETEST_DB`; do not point it at `aculta_runtime` or create a database.
Historical focused Events, Messenger and queue checks are retained above with their run counts. Current core Contributions suite passed against the existing `aculta_simpletest`; the Events full-class result is 29/941. No test used `aculta_runtime`.
