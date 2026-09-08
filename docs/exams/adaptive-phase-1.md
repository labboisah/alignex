# Adaptive Phase 1: baseline, policy and containment

Implemented: 8 September 2026. Scope: Phase 1 only. The adaptive selection runtime and progressive scoring remain unimplemented; they are not enabled by these controls.

## Outcome

Phase 1 establishes a reproducible traditional-exam regression baseline, default-off pilot configuration, server-side containment, a read-only inventory command and the agreed five-context policy. It does not generate adaptive questions or apply progressive penalties.

The original [audit](../adaptive-examination-audit-2026-09-08.md) remains a historical record. This document records its Phase 1 reconciliation and the current controls.

## Current controls

[AdaptiveRolloutService](../../app/Services/AdaptiveRolloutService.php) treats either `mode=adaptive` or `exam_mode=adaptive` as adaptive for containment. Conflicting legacy values therefore cannot bypass the restriction.

| Boundary | Phase 1 behavior |
| --- | --- |
| Authorized admin draft creation | Allowed for currently valid owner/category combinations; existing validation/policies still apply |
| Create/update to scheduled or active | Rejected for adaptive exams with a validation message |
| Paper generation | Adaptive generation rejected; fixed-paper generator remains available for traditional exams |
| Candidate login/start | Unstarted adaptive attempts rejected before device binding or paper payload delivery |
| Existing candidate token | The shared session resolver blocks access to unstarted adaptive attempts through exam, answer, start and other token-based actions |
| Existing started attempts | Their current paper, deadline, mode and ordinary save/submit/recovery behavior are preserved |
| Editing exam via shared wizard after attempts start | Changing into/out of adaptive mode or editing an already adaptive exam is rejected |
| Offline package export | Adaptive-labelled exports rejected; traditional activation/authorization/package checks remain in place |
| Admin UI | Adaptive selection/details show a draft-only availability notice; server validation remains authoritative |
| Candidate audit | Blocked token/login access writes `adaptive_start_blocked` without answer keys or candidate tokens |

These are application boundary controls, not database triggers. Administrative scripts must follow the same policy. Existing professional settings/template services and privileged operational interventions still need comprehensive immutable snapshots in Phase 2; Phase 1 does not claim every historical field is immutable through every administrative action.

## Rollout settings and engine readiness

[config/adaptive.php](../../config/adaptive.php) reads:

```dotenv
ADAPTIVE_PILOT_ENABLED=false
ADAPTIVE_PILOT_OWNERS=
```

The owner allowlist uses exact `type:id` values, for example `organization:12,institution:7`. A parent organization key does not automatically allow a child school/center. Defaults are documented in [.env.example](../../.env.example); the local application's .env was not changed.

The service reports pilot enablement, exact owner membership, low-stakes category eligibility and runtime readiness. **Runtime readiness and publication remain false in Phase 1 even when an owner is allowlisted.** An environment toggle must not route candidates into the old fixed-paper implementation and call it adaptive. Later phases must wire the actual delivery strategy and require all rollout gates before enabling publication. Existing plan entitlements and policies remain additional requirements, not replaced by this allowlist.

Adaptive draft preparation is permitted without activating a live pilot. The low-stakes pilot scope is assessment/practice only; professional-category, recruitment and certification use are excluded from initial live rollout.

## Five-context policy

| Context | Current Phase 1 | Intended first pilot | Preserved boundary |
| --- | --- | --- | --- |
| Institution | Assessment drafts permitted; live adaptive blocked | Course assessment/diagnostics for assigned groups | Institution hierarchy and lecturer assigned-course scope |
| Organization | Existing permitted category drafts; live adaptive blocked | Assessment/practice for training diagnostics | Owner-scoped banks, direct/group assignment, release and report access |
| Professional school | Existing permitted category drafts; live adaptive blocked | Assessment/practice in programme/course/module/batch context | Eligibility, ordinary exam scores and certificates |
| Secondary school | Adaptive still rejected; terminal/assessment traditional flows retained | Formative assessment only after explicit owner/category support | Terminal exams remain traditional; teacher/subject/group constraints |
| CBT center | Existing permitted category drafts; live adaptive blocked | Authorized online assessment/practice | Center candidate groups, subject banks, content owner and delivery-center separation |

Current secondary topic authoring is intentionally disabled by TopicController, and current school administration menus omit arms. Future weakness tracking must initially respect the supported subject-level structure or explicitly implement topic-level support; documentation must not assume old topic/arm routes exist.

## Progressive remediation policy accepted for later implementation

The detailed contract is in [adaptive.md](adaptive.md#agreed-extension-progressive-weakness-focused-levels).

- Optional per-exam progression; disabled unless explicitly configured and later supported by the runtime.
- Level 1 covers the full blueprint; subsequent levels use fresh questions from unresolved areas.
- Percentage penalties apply to remaining recoverable marks, with a configurable rate and exact decimal rounding.
- Example: 40/100 in Level 1 leaves 60; a 10% penalty leaves 54 for Level 2. Earning 20 leaves 34; the next budget is 30.60; earning 10 gives a cumulative 70/100.
- Preserve per-area recovery allocations; never recover one area's lost marks by answering another area's questions.
- Maximum scored levels, minimum budget, evidence minimums and server access/deadline rules are mandatory.
- Scored closure, passing score and mastery are separate outcomes. Optional unscored remediation cannot increase the final score.
- No progressive tables, penalty calculations, mastery engine, certificate changes or new retake behavior are implemented in Phase 1.

## Inventory and legacy handling

Run from a trusted application console:

```text
php artisan adaptive:inventory
php artisan adaptive:inventory --json
```

[AdaptiveInventoryCommand](../../app/Console/Commands/AdaptiveInventoryCommand.php) reports exam IDs, owner keys, status, legacy/new mode conflicts, attempt counts, unstarted attempts to block and started in-progress attempts to preserve. It excludes soft-deleted records under the model's normal scope and emits no candidate identities, access codes or answer content. It does not modify exams, attempts, settings or results.

Local inventory on 8 September 2026: **0 adaptive-labelled exams**. This is evidence for the configured local database only; run the command separately before any deployed rollout.

Legacy policy:

1. Unstarted adaptive-labelled exams/attempts stay in place but cannot start or export as adaptive.
2. Started legacy attempts may continue their existing fixed paper. They do not gain an adaptive engine or recovery levels.
3. Historical submissions/results remain intact. No migration, mode conversion, score recalculation or penalty backfill runs automatically.
4. An administrator may explicitly reconfigure an unstarted draft as traditional if that is the intended examination; active/historical papers cannot be converted through the shared exam editor.
5. Turning the pilot flag off does not interrupt started legacy attempts.

## Regression reconciliation

The original 69-test selection reproduced exactly 47 passes, 19 failures and 3 errors before changes. Its 22 non-passing outcomes were reconciled as follows:

| Original outcomes | Finding and correction |
| --- | --- |
| CBT candidate/import (1) | Fixtures omitted the now-required owner-scoped candidate group; updated payload and import assignment |
| CBT bank creation (1) | Current bank UI/request requires a center subject; updated fixture and subject association assertion |
| CBT exam creation and adaptive recruitment draft (2) | Current exam contract requires groups and subject-matching banks; updated fixtures and kept adaptive creation draft-only |
| CBT sidebar (1) | Menu is nested; assertions now inspect exam children |
| Paper generation preview and secrecy test (2) | Exam fixture did not select its question bank; linked exam/paper row to the bank so generation and secrecy assertions actually run |
| Organization missing prerequisites/sidebar (2) | Bank validation is row-scoped; menu has Questions/Exams children; updated explicit expectations |
| Professional candidate hierarchy (1) | Candidate registration derives programme from batch and does not persist the old direct course input; assert the current association |
| Professional exam setup (2) | Added required batch and subject-matching bank; adaptive cases remain drafts, traditional cases scheduled |
| Professional certificates and result exports (2) | Created explicit plan entitlements in relevant fixtures; retained real feature middleware and existing 403 behavior for unentitled users |
| Secondary teacher and sidebar (2) | Added explicit plan entitlements where the test exercises paid teacher/report capabilities; assert current Admin menu without arms |
| Secondary subject/topic setup (1) | Topic authoring explicitly returns 404 for secondary context; assert no topic creation while retaining subject/bank success |
| Secondary current/legacy navigation and administration updates (3) | Reconciled retired corrected arms routes and current menus; preserve existing arm rows and assert rejected updates/deletes |
| Student import (1) | Actual implementation defect: validated class-arm context was discarded when creating students; now persists that already-authorized value |
| Student group workflow (1) | IDs in fixtures must be strings under the current request contract; group/exam/certificate assertions now execute |

Test reconciliation did not relax production authorization, expand topic access, restore retired routes, remove assertions about candidate secrecy, or skip tests.

## Validation

The expanded selection passed **92 tests, 1,073 assertions** after the Phase 1 changes:

```text
php artisan test --compact --filter='AdaptiveRolloutTest|SharedExamWorkflowTest|CandidateExamApiTest|ExamPaperGenerationTest|ResultManagementTest|InstitutionAssessmentFeatureTest|SecondarySchoolFeatureTest|ProfessionalExamFeatureTest|CbtCenterFeatureTest|OrganizationModuleTest|ExamModuleTest|ExamMonitorTest|InstitutionStructureFeatureTest|ProfessionalFacilitatorManagementTest|CorrectedEntityFoundationTest'
```

The seven new [AdaptiveRolloutTest](../../tests/Feature/AdaptiveRolloutTest.php) cases cover adaptive draft/publication control, exact owner keys, secondary exclusions, unstarted login/token blocking, preserved legacy answer/submission, generation with conflicting mode fields, started-mode conversion rejection and read-only inventory.

Frontend compilation also passed with npm.cmd run build -- --outDir storage/framework/testing/phase1-build (3,540 modules); tracked production assets were not replaced. PHP changes were formatted with Pint, and git diff --check passed.

This is a scoped regression baseline, not a full application/security/load audit. SQLite tests do not prove MySQL concurrency or operational Reverb/Redis/offline behavior. The earlier audit's score-release, pre-start traditional paper exposure and answer/submission race findings remain scheduled for their explicit shared-workflow fixes.

## Handoff to Phase 2

Implement validated adaptive/progressive configuration, immutable attempt/progression snapshots, eligible pool/item versions and additive state/ledger records. Preserve this baseline and add new contract tests. Do not set runtime readiness true until the server lifecycle and acceptance gates are implemented.

Before a live pilot: review deployed inventory, verify real owner/plan eligibility, exercise MySQL races and browser recovery, validate withholding of scores/derived budgets, and pass every pilot gate in the [implementation plan](../adaptive-examination-audit-2026-09-08.md).
