# Adaptive diagnostic pilot runbook

## Simplified setup and learning feedback — 9 September 2026

Choose Adaptive when creating an Assessment or Practice exam. The normal exam form now handles activation and question preparation internally; organizers do not need to approve a pilot, create snapshots, or configure a shadow engine.

The learning settings offer additional levels (on by default for new adaptive exams), maximum levels, a percentage deduction from remaining marks, an area completion target, time per level, waiting time, and the final date for further levels. Internal defaults handle selection policy, question quotas and minimum evidence. The normal exam duration and end time supply defaults. Traditional CBT retains its existing behavior.

Saving automatically checks the approved question pool and freezes its internal version. Drafts may retain readiness problems; active/scheduled exams must pass before the save succeeds. Missing questions are reported during setup. Creation and preparation run in the same transaction. Offline/hybrid organizers use **Center delivery** to assign a prepared package without another approval form.

New exams offer completed-level feedback by default, with a plain-language checkbox to turn it off. It includes per-level earned/available marks and deductions, cumulative earned marks, strengths, and areas needing practice. The current level must end before feedback is sent. Item keys, individual answer correctness and future questions remain hidden. A **Start next level** action appears when weaker areas, marks, fresh questions, time and level limits permit. Final result release remains separate.

Previous frozen snapshots retain their feedback choice; missing feedback settings stay private. Completed traditional attempts cannot be retroactively turned into adaptive levels. The locally inspected completed exam had one attempt, no adaptive state and an unready question snapshot; a fresh adaptive exam is needed to test the corrected flow. Existing responses and marks were not rewritten.

The advanced engine, audit records, delivery reservations and operational controls remain internal. This change does not enable consequential calibrated scoring.

The sections below document internal operations and the earlier pilot rollout. Any earlier manual approval/preparation instructions are superseded by the automatic exam-save workflow above.


Updated: 8 September 2026.

## Scope and delivery

Supervised diagnostic pilots are implemented for organizations, institutions, professional schools, CBT centers and secondary schools. Use assessment or practice categories. Secondary-school terminal exams remain traditional CBT. These controls do not approve certification, recruitment decisions or final grades.

Online delivery retains the Laravel simple-v1/recovery-v1 lifecycle. Offline delivery uses the separately versioned diagnostic-pilot-v1 engine and alignex.diagnostic-offline.v1 package contract. Both implement difficulty adjustment, coverage and weakness-focused recovery, but are distinct engine versions: do not assume psychometric equivalence or merge their results into one scale. Real calibration, specialist validation and consequential engine approval remain Phase 6 work. Synthetic examples validate software only.

## Deploy and prepare

1. Deploy the cloud source and run Laravel migrations, including 2026_09_08_200000_create_adaptive_pilot_delivery_tables.php. Keep the application encryption key stable.
2. Provide Node on the Laravel worker PATH, or set ADAPTIVE_PILOT_NODE to its executable path. Cloud reconciliation executes services/adaptive-pilot/replay.cjs with a bounded timeout.
3. Build the updated offline-server with npm run build and the updated candidate-app with npm run build. Source/build changes do not update installed executables; distribute your reviewed build before the cohort. No installer has been published by this change.
4. Activate the center normally. Configure its cloud URL, device identity, sync token and authorized administrator credentials. Remote cloud connections require HTTPS.
5. Create a separate adaptive assessment/practice exam, configure dates, objective questions, areas and recovery policy, assign active candidates, and prepare a ready immutable snapshot. Ensure fresh questions cover every level.
6. Open the exam's Adaptive Preparation page, then the pilot controls link. Record the purpose/cohort limits and explicitly acknowledge diagnostic-only use. Enable only the delivery modes required.

Adaptive delivery controls are stored exclusively in adaptive_pilot_controls, scoped to the exam and its exact owner. Set them while creating/editing an adaptive exam, or later on its pilot page. New records default to disabled; missing records never fall back to environment allowlists. Existing database approvals are preserved when a request omits these controls. Enabling delivery requires diagnostic acknowledgement and authorized access. Traditional exam forms do not persist adaptive controls.

Only system-wide settings remain in .env: ADAPTIVE_ENGINE_SHADOW_ENABLED, ADAPTIVE_ENGINE_URL, ADAPTIVE_ENGINE_SECRET, ADAPTIVE_PILOT_NODE and ADAPTIVE_PILOT_EMERGENCY_STOP. ADAPTIVE_PILOT_ENABLED, ADAPTIVE_PILOT_OWNERS and ADAPTIVE_PILOT_EXAMS are retired and ignored by runtime checks. Existing started levels retain their original state during a pause. All five owners require their own authorized exam; a center hosting candidates does not gain another owner's administrative access.

## Online session

Enable online starts, then use the existing /exam login. The candidate explicitly starts, receives one item, saves drafts and confirms immutable answers. Server timing and frozen policy govern completion. Recovery offers new items from weak areas, subject to the configured percentage penalty, evidence, fresh pool, cooldown, level limit and closing date. Existing online reporting and release restrictions apply.

## Offline session

1. In pilot controls, enter the assigned center activation ID and select unstarted candidates. Reserve a package of at most 200 candidates. Existing starts, previous reservations, stale snapshots and unsupported settings are rejected.
2. Reservation freezes the exam against subsequent edits and prevents those candidates starting online. It is not a downloadable fixed paper. Keep the package ID and confidential candidate access codes.
3. On the center server open /adaptive.html, sign in as supervisor and import the package ID. Import validates its HMAC, device binding, version and validity window before writing SQLite. New imports start paused.
4. Verify the assigned candidates and center clock, then enable starts locally. Candidates use the adaptive pilot entry in the updated candidate app, or the center's /adaptive.html page, their registration and access code.
5. SQLite persists commands before acknowledgement. A lost response can be retried with the same command ID. Drafts survive page refresh; committed answers cannot be changed. The center controls deadlines and records focus/copy/paste/reconnect events. Configured tab-switch limits can disqualify.
6. Weakness recovery uses fresh questions and exact integer mark accounting. Example: 60 recoverable marks with a 10% penalty gives a 54-mark next level. A percentage penalty is applied to the remaining recoverable budget at each eligible recovery start, not as a fixed 10 marks.
7. The supervisor can pause new starts, end or disqualify a session, and reset a candidate's device binding. Reset invalidates the prior token and preserves the progression, answers and deadline.
8. Completion is provisional. When connected, use supervisor synchronization. Laravel replays the ordered transcript against the stored original package and checks the final state hash. Matching closed sessions become verified; replay mismatches are quarantined. Identical accepted uploads are idempotent and conflicting replacements are rejected.
9. Refresh the cloud pilot page to review per-area earned, penalty and closed balances. Verified diagnostic results remain separate from official scores, grades, certificates and recruitment shortlists.

Supported offline items are text single-choice, multiple-choice and true/false. Required webcam/fullscreen, payments, negative marking and unscored remediation are explicitly rejected for this pilot. Use a separately configured supervised exam. The fresh-question pool must fit the portable contract limits; export validates it before reserving candidates.

## Operations and recovery

Run php artisan adaptive:operations --hours=24, optionally with --owner=secondary_school:OWNER_ID (or another exact owner key). It reports online cohorts/stops, shadow operations and offline reservation/verification/quarantine counts. It does not claim measured live-selection latency, duplicate-request counts or production capacity.

Pause online starts and new offline reservations in the exam controls. ADAPTIVE_PILOT_EMERGENCY_STOP=true blocks cloud publication/new starts and new package reservations after deployment configuration refresh. Started online levels retain their frozen state. A disconnected center cannot receive a cloud kill switch: pause starts or end affected sessions locally.

Back up the center SQLite database and original activation configuration together before the pilot and before any maintenance. Local package, state and transcript encryption derives from the sync token. Preserve that token, device identity and cloud application key while sessions are outstanding. Never rotate credentials by deleting state. Restore to the original configured center and reconcile before any transfer. Clock rollback blocks commands; correct the clock without deleting the clock journal or extending recorded deadlines.

A quarantine is a review task, not permission to overwrite an accepted result. Preserve the database, package ID, lease ID, error, transcript and center audit history. Do not switch a reserved candidate to cloud delivery, delete their lease or issue another progression to recover lost marks.

## Security and remaining acceptance

HMAC authenticates packages with the existing center secret; it is not an asymmetric issuer signature. Keys and future items stay on the trusted center server, encrypted at rest, and candidate responses expose only the current permitted item. Supervisors and the center machine remain trusted. Cloud replay detects divergence from the original rules; it does not establish that a trusted center honestly observed a candidate.

Traditional fixed-paper endpoints/imports continue rejecting adaptive packages. Their existing contracts and candidate workflow remain separate. Retain center-local audit history; answer/incident commands are replayed in cloud, while local login/device administration history stays in the center database.

Before an actual cohort, verify the deployed builds, activation, backups, clock, LAN access and one supervised end-to-end rehearsal on the target hardware. Installer distribution, hardware capacity benchmarking and real assessment validation remain operational acceptance tasks. Do not describe these software tests as high-stakes readiness.

## Verification recorded for this implementation

- Laravel: 48 policy/contracts/reporting/pilot/operations tests (476 assertions), then 58 shared/secondary/lifecycle/offline-boundary tests (438 assertions), all passing. The final engine immutability change was followed by a successful rerun of all 10 pilot delivery tests (68 assertions).
- Portable engine: 5 passing tests, including late-event rejection after completion and identical final-command retries.
- Offline server: 22 passing SQLite/API/import tests after the final engine rebuild.
- Portal browser: 4 passing checks covering pilot controls, secondary adaptive recovery, and organization/secondary traditional navigation.
- Offline browser: supervised pilot login, draft restoration, lost-response retry, weakness recovery and completion passed.
- Portal production/browser builds, offline-server main/renderer builds and candidate-app builds passed. Both offline renderer TypeScript checks passed.
- Local additive migration, read-only operations command, PHP formatting and cross-repository whitespace checks passed. Cloud and center engine source copies were kept identical.

Reproduce from the cloud repository:

~~~text
php artisan test --compact --filter='AdaptiveContractsTest|AdaptiveReportingTest|AdaptivePilotDeliveryTest|AdaptiveOperationsTest|AdaptiveRolloutTest'
php artisan test --compact --filter='SharedExamWorkflowTest|SecondarySchoolFeatureTest|AdaptiveLifecycleTest|AdaptiveOfflineBoundaryTest'
node --test services/adaptive-pilot/kernel.test.cjs
npm.cmd run build:browser
npx.cmd playwright test --grep 'diagnostic pilot controls|secondary_school: explicit start|traditional paper navigation'
node tests/Browser/offline-pilot-smoke.cjs
~~~

Reproduce from offline-server:

~~~text
npm.cmd run build
npx.cmd tsc --noEmit -p tsconfig.renderer.json
node scripts/test-packages.mjs
~~~

Reproduce from candidate-app:

~~~text
npm.cmd run build
npx.cmd tsc --noEmit
~~~

These are development and isolated integration checks. They do not substitute for target-hardware rehearsal, installed-release distribution or assessment validity evidence.

Deployment note: if another installation still relies on environment-only approvals, save the equivalent approvals through its existing pilot page before upgrading. After this change, an exam without a matching database record cannot start, regardless of old environment values. The local configuration transition found no environment-only approvals to transfer.
