# ACULTA Contributions

`aculta_contributions` owns the private, revisionable lifecycle for community
proposals. Domain modules register trusted `ContributionAdapterInterface`
services; each adapter owns eligibility, source resolution, payload allowlists,
version checks, and selective application. Unknown adapter IDs fail closed.

This module does not make proposals public or indexable. It does not own domain
entities, editorial workflow, reports, ownership claims, or forum moderation.
Wiki, unclaimed Organization profiles, Locations, and Events register domain
adapters for private, allowlisted proposals.

Submission Flood defaults are configurable in `aculta_contributions.settings`:
10 submissions per account in 3,600 seconds, matching the existing Wiki policy.
The proposal entity stores an idempotency hash, not the request token, and
captures adapter owner module, server-derived purpose, and Drupal language.
Source-owned context (for example, citations and the domain's proposal subtype)
is validated and stored separately from applicable values. Each decision
revision records its selected payload keys and the adapter's normalized
application choices; that data is not inferred from a revision log.

The generic lifecycle Kernel suite also exercises lock contention across a
separate PHP process and independent DB connection. The competing submission is
denied with no persisted proposal; the combined Entity + Lifecycle suite passed
14 tests / 161 assertions on the existing `aculta_simpletest` database.

The Wiki adapter delegates approved-field application to the
existing Wiki editorial workflow. New submissions use this shared lifecycle
after every legacy proposal has a durable migration checkpoint. Wiki routes
resolve both entity stores by preserved numeric ID; status, withdrawal,
decision, workspace, editorial queue, and notice publishing support migrated
Contributions and legacy proposals during the transition.

Wiki update 10003 now prepares a batched, transactional copy with proposal and
revision reconciliation, preserving numeric IDs, UUIDs, revision IDs, authors,
timestamps, states, notes, and active idempotency hashes. Its local Kernel
fixture and dual-store status route pass 1 test / 55 assertions, including
retry without duplicate revisions and lookup on both sides of migration. The
coordinated cutover passes 2 Wiki tests / 264 assertions; lifecycle Kernel
passes 10 / 109. The update has not run in Runtime. It stops on ID collisions or
translated legacy revisions; the old entity tables remain intact. The legacy
model did not store selected approval fields or list modes, so those historical
decisions cannot be reconstructed.

Events supports private `event_create` and `event_update` proposals. Update
submission requires the Event's current Entity Access `update` result and
captures a fingerprint from the persisted source. A newly proposed Date is
optional and append-only. Submission notices use a minimal Messenger envelope;
updates 10015–10016 still require Runtime installation and verification. The
Events lifecycle and domain-boundary Kernel suites pass together with 37 tests,
1,275 assertions, and 24 upstream deprecations on `aculta_simpletest`.

The development Runtime's WE SATIVA HTTP 500 was traced to its compiled
container still referencing the removed LMS `LearningRoomHooks` class after
the presentation hook moved to Studio. A full snapshot was taken before
`drush cr`; afterward anonymous Wiki routes returned their expected 403 and
retired proposal routes returned 404. Authenticated HTTP verification remains
pending; Bootstrap theme `#form_id` warnings remain non-fatal.

## Library use

No external libraries are introduced.

See [the roadmap](docs/ROADMAP.md), [test matrix](docs/TEST-MATRIX.md), and
[changelog](CHANGELOG.md) for implementation and validation status.
