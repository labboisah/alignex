# Adaptive Phase 2: contracts, validation and additive persistence

Implemented: 8 September 2026.

Phase 2 provides the configuration, preparation UI and persistence contracts. It does not enable candidate adaptive delivery or post recovery penalties. The Phase 1 publication/start/export guards remain in force.

## Administrative workflow

1. Create or edit an adaptive draft in an eligible owner context.
2. Configure starting difficulty, the supported simple policy and minimum/maximum questions.
3. Optionally enable weakness-focused recovery. Set the penalty percentage, maximum scored levels (including Level 1), minimum available budget, mastery threshold/evidence minimum, level duration, progression closing time, cooldown and permission for unscored practice.
4. Save the draft, then open **Adaptive preparation** on the exam detail page.
5. Review pool warnings and save a versioned snapshot. Saving an unready snapshot records diagnostics; it cannot bind an attempt. Saving a snapshot does not publish an exam.

Preparation uses the existing exam update policy, FormRequest validation, Inertia routes, explicit safe readiness resource, processing/error/empty states and shared success toasts. No new candidate route is exposed.

## Configuration contract

The penalty is a percentage of remaining recoverable marks, configurable from 0 through 100 with at most two decimal places. Recovery settings are required when progressive remediation is enabled. Invalid policy names, conflicting mode fields, invalid lengths, invalid closing times and progressive negative marking are rejected.

Progressive configuration currently requires minimum and maximum questions to equal the sum of the paper-row quotas. These establish Level 1 coverage and the per-area quota basis for later weak-area levels. The later lifecycle must derive the reduced weak-area blueprint; it must not blindly repeat the full Level 1 blueprint.

Legacy drafts receive medium/simple and row-quota length defaults. Selecting traditional mode cannot enable progressive remediation.

## Pool readiness and versions

Only active banks in the exam's owner and subject/course scope contribute approved objective questions (single choice, multiple choice and true/false) with positive marks and usable answer options. Selected topic IDs filter eligibility. Multiple-choice scoring semantics are reserved for the server lifecycle.

Organization pools exclude child-entity banks. Institution pools require the selected course. Professional school and CBT center pools check their own owner and subject, plus configured course/module restrictions. Secondary school remains ineligible; its traditional workflow is unchanged.

Each paper row is a stable accounting area within a snapshot, with its own quota and frozen mark budget. Selected topics are coverage constraints; topic-level recovery balances are not yet implemented. Readiness checks fresh pool size across the configured scored-level cap, each difficulty band, selected topic coverage, evidence minimums, disjoint row pools and reconciliation to the exam mark total. These conservative checks are prerequisites, not a guarantee that every future answer path can complete.

Snapshots preserve settings, owner/category, timing, mark policy, blueprint, readiness, engine/scoring version identifiers and a fingerprint. Question text and options, including private answer keys, are copied into encrypted immutable pool records. Changes require a new version; binding rejects a stale fingerprint. Image paths are preserved, but media bytes are not versioned. Before live delivery, establish immutable media retention or exclude unsupported media.

The existing adaptive selector remains an unused prototype. It must not bypass this pool.

## Additive schema and attempt preparation

Migration: `2026_09_08_150000_create_adaptive_contract_tables.php`.

| Table | Purpose |
| --- | --- |
| adaptive_snapshots | Versioned exam configuration, blueprint and readiness |
| adaptive_pool_items | Encrypted immutable question/option versions |
| adaptive_progressions | One candidate/exam recovery budget and overall status |
| adaptive_levels | Unique level number and attempt, frozen incoming/available budget and weakness snapshot |
| adaptive_area_balances | Per-area earned, penalty, recoverable and closed balances, evidence and mastery |
| adaptive_attempt_states | Frozen adaptive mode/snapshot, step and state version |
| adaptive_decisions | Reserved selection history with unique step/request and progression-wide question reuse protection |
| adaptive_mark_entries | Append-only ledger entries with progression-scoped idempotency keys |

Marks use integer hundredths: 30.60 marks = 3060 units. Rates can be expressed as basis points when a later level is started. Phase 3 must implement half-up percentage rounding and transactional conservation checks for every posting.

The internal `AdaptiveAttemptPreparationService::bind()` locks the attempt and exam, checks assignment, a ready current snapshot, an unstarted empty attempt and absence of an existing progression. It creates Level 1, area balances, one opening ledger entry and frozen attempt state atomically. Repeating the same bind returns that state. A second attempt cannot reset the original budget. Binding neither starts the timer nor creates papers nor charges a penalty.

Snapshots, pool content, decisions and ledger records reject Eloquent updates/deletes. Identity/budget origin fields on mutable state records are frozen. Foreign keys restrict destructive deletion of referenced history. These application guards do not prevent privileged raw SQL updates. Later posting services must enforce cross-record consistency and total conservation; arbitrary direct model writes are not a supported posting API.

`AdaptiveCandidateItemResource` defines an explicit question/options-only response shape. It is tested for secrecy but is not connected to candidate endpoints. Correctness, difficulty, snapshots, hashes and ledger balances are not part of that resource.

## Verification

The additive migration was applied successfully to the local development database. Contract tests use SQLite; production MySQL concurrency and locking remain Phase 3 acceptance work.

The frontend production build passes using `npm.cmd run build -- --outDir storage/framework/testing/phase2-build`; production build files were not replaced. PHP formatting was checked with Pint.

The combined regression selection passes **109 tests with 1,182 assertions**, including 17 new adaptive contract tests. It covers authorization, configuration round-trips, invalid policies/rates, all five entity pool policies, selected topics, encrypted answer-key hiding, immutable versions, stale/started/unassigned binding rejection, budget reset prevention, ledger idempotency and question reuse constraints. The same run includes the 92-test Phase 1 traditional CBT/entity baseline.

Command:

```text
php artisan test --compact --filter='AdaptiveContractsTest|AdaptiveRolloutTest|SharedExamWorkflowTest|CandidateExamApiTest|ExamPaperGenerationTest|ResultManagementTest|InstitutionAssessmentFeatureTest|SecondarySchoolFeatureTest|ProfessionalExamFeatureTest|CbtCenterFeatureTest|OrganizationModuleTest|ExamModuleTest|ExamMonitorTest|InstitutionStructureFeatureTest|ProfessionalFacilitatorManagementTest|CorrectedEntityFoundationTest'
```

## Phase 3 handoff

Implement server-owned item issuance, answer commitment, deterministic difficulty changes, atomic/idempotent advancement, resume, deadlines, stopping and submission. Use snapshot content and frozen attempt identity throughout.

For progression, implement finalized weakness analysis, weak-area-only quotas and weights, fresh questions across all levels, exactly-once penalty/start transactions, integer rounding, ledger reconciliation, level caps, closure, unscored practice separation and aggregate result release. Every posting must preserve original = earned + penalties + recoverable + closed, both globally and by area. Preparation currently establishes this identity at opening only.

Add race tests against MySQL, including concurrent starts, duplicate commitments and expiry/submission winning over late writes. Verify that every declared decision belongs to its progression, level and snapshot. Do not enable runtime readiness until the candidate lifecycle and its acceptance gates pass.

See the [implementation plan](../adaptive-examination-audit-2026-09-08.md) and [adaptive knowledge base](adaptive.md).
