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
| Wiki migration, Events policy, admin collection, Messenger privacy, Index exclusion | Wiki/Events/Messenger Kernel and Unit suites listed above | Local coverage is present, including private-proposal/draft exclusion and official Event projection scheduling; authenticated Runtime journeys and queue processing remain pending |
| Events Organization/Location proposals: private submit, protected fields, selective apply, stale-source conflict, approval-only Location creation | `EventOrganizationLifecycleKernelTest` | Current full class on HEAD `559fc5c5e` passed 30 tests / 973 assertions; 24 upstream deprecations |
| Content Admin exposes organization proposal queue only through Events native route access | `ModeratableCollectionAccessKernelTest` (Kernel), `ModeratableCollectionRegistryTest` (Unit) | Kernel 4/56 and Unit 1/4 PASS |
| Content Admin discovers Event proposal queue only through Events native route access; review form accepts only event_create/event_update allowlisted fields | `ModeratableCollectionRegistryTest` (Unit), `EventOrganizationLifecycleKernelTest::testEventCreateContributionWaitsForApprovalAndCreatesDates` | Unit 1/8; historical focused Events Kernel 21/691 PASS; 24 Core/contrib deprecations |
| Notice failure does not undo a committed source change or proposal approval | `ContributionLifecycleKernelTest::testNoticeFailureDoesNotRollBackDecisionOrSource` (Kernel) | Passed: 1 test / 10 assertions |
| Events outcome enqueue failure remains recoverable from a minimal outbox | `OrganizationContributionEventQueueTest` (Unit) | Passed: 1 test / 12 assertions |
| Events Organization/Location/Event decisions use minimal Messenger envelopes; consumer rejects unsafe metadata | `EventsContributionNoticeConsumerTest` (Unit), queue/worker/event Units, `EventOrganizationLifecycleKernelTest` | Messenger Unit 402 tests / 4,374 assertions PASS, including Events worker opt-out 1/15; Events full class on HEAD `559fc5c5e` is 30/973. Payload/rationale/address excluded. Runtime updates 10015–10016 applied; new 10017/config remains pending |
| Events proposal email options default to site-only; member can choose direct or daily digest | `AvisoPreferenciasTest::testEventsContributionEmailChoicesDefaultToSiteOnly`; `EventsContributionNoticeEmailWorkerTest::testCurrentOptOutCancelsQueuedEmail` | Preference policy and opt-out PASS; SMTP, delivery/digest Kernel, and Runtime update 10017 remain pending |
| Messenger/Events full Kernel suites in this checkout | Kernel directories on `aculta_simpletest` | Incomplete: Messenger-wide run stalled after five tests; Event lifecycle run stalled after 14 progress dots and was interrupted. No pass is claimed. |
| Event create stays private until approval, creates draft for proposal author, rejects publication status, revalidates author create capability; update applies selected fields/appends Dates, revalidates fresh author Entity Access and detects stale origin | `EventOrganizationLifecycleKernelTest` Event Contributions scenarios | PASS, full Events class 30 tests / 973 assertions; 24 Core/contrib deprecations |
| Event create rejects a case-insensitive duplicate title at the same date without creating a second Event | `EventOrganizationLifecycleKernelTest::testEventCreateContributionRejectsDuplicateTitleAndDate` | Passed: 1 test / 26 assertions; full Events class 30/973 on HEAD `559fc5c5e` |
| Event apply revalidates claimed Organization membership and proposed Date Location visibility after submission | `EventOrganizationLifecycleKernelTest::testEventUpdateContributionRevalidatesOrganizationMembership`, `::testEventUpdateContributionRevalidatesDateLocationAccess` | PASS, full Events class 30 tests / 973 assertions; 24 Core/contrib deprecations |
| Events-domain author workspace lists own Organization, Location, and Event proposals; another account cannot read details | `EventOrganizationLifecycleKernelTest` | PASS, full Events class 30 tests / 973 assertions; includes focused regressions |
| Event contribution workspace routes remain only in Eventos purpose and uncached; dashboard links to the workspace | `EventDomainBoundaryKernelTest::testOrganizerRoutesBelongOnlyToEventsPurpose`, `::testOrganizerDashboardBuildsWithAccessResultObject` | PASS, recorded Events domain-boundary class 10 tests / 369 assertions; 18 Core/contrib deprecations |
| Local development HTTP 500 and WE SATIVA check | Focused Kernel, Drush container resolution, and canonical HTTPS GETs | The recorded `/ganjalab` 500 was `AccountCoursesReader` calling `isAllowed()` on boolean `true`; correction passed 2 tests / 19 assertions. On HEAD `7ae74db91`, both H5P upload services resolve, `drush cr` passes, and anonymous GETs to `/ganjalab` and `/wesativa` return 403, no 500. Authenticated route behavior and real H5P upload remain unverified. |
| Events Kernel lifecycle and domain-boundary suites on `dev` HEAD `dd6ba130c` | `EventOrganizationLifecycleKernelTest` + `EventDomainBoundaryKernelTest`, with `SIMPLETEST_DB` sourced from the protected local env file and checked to target `aculta_simpletest` | Passed: 37 tests / 1,275 assertions; 24 upstream deprecations. |
| Contributions core full suite on `dev` HEAD `559fc5c5e` | `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_contributions/tests/src` with existing `SIMPLETEST_DB` | Passed 18 tests / 188 assertions (2026-11-01). |
| Private Event Contribution and approved draft stay out of Index; official publication queues projection | `EventOrganizationLifecycleKernelTest::testEventCreateContributionWaitsForApprovalAndCreatesDates` | Passed on HEAD `559fc5c5e`: 1 test / 52 assertions; no provider/projection for proposal or draft, published Event schedules `project` work |
| Sensitive Location address/precision update revalidates Events linked through Dates and fails on changed source | `EventOrganizationLifecycleKernelTest::testLocationAddressContributionDetectsChangedLinkedEvent` | Passed on `559fc5c5e`: 1 test / 26 assertions; the proposal conflicts and Location address remains unchanged |


Unit command:

```sh
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_contributions/tests/src/Unit
```

Kernel command must use the repository's existing `aculta_simpletest` through
`SIMPLETEST_DB`; do not point it at `aculta_runtime` or create a database.
Historical focused Events, Messenger and queue checks are retained above with their run counts. Current core Contributions suite passed against the existing `aculta_simpletest`; Events full class passed 30/973, and the new Index focal passed 1/52. No test used `aculta_runtime`.

