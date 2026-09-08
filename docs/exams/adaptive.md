# Adaptive examination knowledge base

Reviewed: 8 September 2026. Supervised online/offline diagnostic pilots are implemented for all five owner contexts, including secondary-school formative assessment/practice. Terminal exams remain traditional. Consequential assessment validation remains pending.

See [pilot setup and recovery runbook](adaptive-pilot.md) and [Phase 7 current status](adaptive-phase-7.md). Per-exam approval is required; no live cohort was automatically enabled. Earlier phase notes below describe historical increments and are superseded by the pilot runbook where they differ.

## Phase 6 engineering status

A separate authenticated FastAPI research engine now computes experimental 2PL/EAP estimates, uncertainty, constrained item proposals and stopping/classification results. Laravel supports versioned calibration imports, independent review/revocation, bounded shadow requests and immutable replay history. Candidate delivery and exact recovery scoring continue to use the existing diagnostic lifecycle.

The external engine cannot issue candidate questions or promote estimates into grades, certificates or recruitment decisions. See [Phase 6 implementation, calibration format and remaining acceptance work](adaptive-phase-6.md). A synthetic demonstration dataset and illustrative criteria have now been generated and evaluated; see the Phase 6 notes. No representative real-response dataset or specialist-approved criteria have yet been assessed.

## Phase 5 implementation status

Authorized staff now have one report per candidate progression, with retained level paths, topic coverage, evidence, raw accuracy, exact recovery budgets, stop reasons, frozen versions and audited CSV exports. Candidate release remains aggregate-only. Bound adaptive levels are excluded from traditional grades, verification and exports, and cannot drive recruitment shortlists or certificates.

New starts require the pilot flag plus exact owner and exam allowlists. Online diagnostics are supported for organizations, institutions, professional schools and CBT centers; secondary-school adaptive remains blocked. See [Phase 5 implementation, verification and pilot guidance](adaptive-phase-5.md). Earlier phase notes below describe their historical state.

## Phase 4 implementation status

The candidate router now branches into a dedicated adaptive interface for explicit start, current-item drafts and confirmations, server-driven completion, recovery/practice requests, durable retries, reconnects and multi-tab recovery. Safe instructions and frozen camera/fullscreen controls come from the server. Authorized supervisors see current-level progress and retained level history; adaptive resets are blocked and End Exam closes the progression through its ledger lifecycle.

See [Phase 4 experience, verification and Phase 5 handoff](adaptive-phase-4.md). The server still withholds answer keys, correctness and unreleased scores. Secondary-school adaptive policy and traditional CBT behavior remain unchanged.

## Phase 3 implementation status

Bound adaptive attempts now use an isolated server lifecycle: explicit start, one issued item, draft/committed responses, deterministic difficulty changes, coverage, resume, deadlines, submission and disqualification. Recovery levels target weak paper-row areas, use fresh questions and post percentage penalties atomically. Exact ledgers reconcile earned, penalty, recoverable and closed marks. Unscored practice cannot add credit. Candidate results expose only a released closed aggregate.

The regression selection passes 132 tests with 1,327 assertions; three separate MySQL concurrency tests pass with 42 assertions. See [Phase 3 implementation and Phase 4 handoff](adaptive-phase-3.md) for the API contract, supported scoring, limitations and operational checks.

## Phase 2 implementation status

The wizard now validates adaptive and optional progressive settings, including the per-exam penalty percentage. The preparation page checks owner-scoped approved pools and saves encrypted immutable question/configuration versions. Additive progression, level, area-balance, decision, attempt-state and ledger tables are available. Internal attempt binding opens a frozen budget without starting an exam or charging a penalty. See [Phase 2 implementation and handoff](adaptive-phase-2.md) for usage, exact scope and verification.

## Phase 1 implementation status

Phase 1 containment and regression reconciliation are implemented. Adaptive drafts remain configurable for valid owners, but publication, new candidate starts, paper generation and offline export are blocked until the runtime is ready. Started legacy attempts retain their existing fixed-paper behavior. The expanded baseline passes 92 tests with 1,073 assertions. See [Phase 1 implementation and handoff](adaptive-phase-1.md) for rollout settings, inventory and detailed evidence.

## Meaning and present behavior

An adaptive examination should use committed candidate responses to influence which eligible question is issued next, under server-controlled coverage, length, timing and scoring rules.

AlignEx supports adaptive preparation and a separate server lifecycle for frozen adaptive attempts. The [traditional fixed-paper workflow](traditional.md) remains the live candidate experience; adaptive publication/new starts require explicit owner/exam pilot approval and remain disabled by default.

## Implemented pieces

| Piece | Implementation | Limit |
| --- | --- | --- |
| Mode storage | Exam mode plus frozen adaptive attempt state | Bound attempts dispatch separately; legacy started papers retain traditional behavior |
| Owner eligibility | Central ownership rules and exam request validation | Permission to configure is not runtime readiness |
| Setup UI | Validated difficulty, policy, length and optional progressive settings | Online diagnostic runtime is available only to approved pilot exams |
| Preparation and persistence | Readiness, frozen settings/items, progression/ledger state and attempt binding | Text-based objective items; immutable media support remains future work |
| Candidate and supervisor UI | Explicit start, current-item confirmation, durable retries, recovery messages, proctor controls and retained level history | Online only; new starts default to disabled |
| Server lifecycle and recovery | Draft/commit, persisted selection, deadlines, weak-area levels, exact penalties, practice isolation and aggregate release | Default-off controlled pilots; no validated ability scoring |
| Difficulty helper | Start medium; correct moves up, incorrect moves down, clamped at easy/hard | Simple rule-based stepping only |
| Next-question selection | Scoped immutable pool, required coverage, nearest difficulty fallback and persisted decisions | Simple rule-based objective engine, not calibrated ability estimation |
| Performance profiles | Subject/topic/difficulty counts and percentage-based mastery | Descriptive post-exam analysis, not ability estimation |
| Menus and pricing | Adaptive entries/feature flags in selected contexts | Menu visibility does not prove server-side rollout enforcement |

## How the legacy selector works

[AdaptiveQuestionSelectorService](../../app/Services/AdaptiveQuestionSelectorService.php) has three public methods:

- `startingDifficulty()`: always returns `medium`.
- `nextDifficulty(?bool $wasCorrect, string $currentDifficulty)`: moves easy to medium or medium to hard on a correct answer; hard to medium or medium to easy on an incorrect answer. Null correctness returns the default starting difficulty.
- `nextQuestion(CandidateExamAttempt $attempt, ?bool $wasCorrect)`: gets the last paper's difficulty, computes the next band, excludes already-used paper question IDs, and randomly chooses one matching item.

The query uses the exam-level bank and accepts draft, review and approved questions. It does not reproduce the fixed generator's subject-level bank lists or topic/subject rules. A missing eligible item yields null. The service does not reserve a question, append it to a paper, persist adaptive state, calculate an ability estimate, or stop/finalize an attempt.

The answer endpoint does not call this helper. Frozen adaptive attempts use the scoped, persistent `AdaptiveLifecycleService` introduced in Phase 3.

## Availability in five contexts

| Context | Current rule | Planned use, not current capability |
| --- | --- | --- |
| [Institution](../entities/institution.md) | Adaptive permitted for assessment category | Course diagnostics/placement and later validated assessments |
| [Organization](../entities/organization.md) | Adaptive permitted across its allowed categories | Skills diagnostics, later validated recruitment/certification |
| [Professional school](<../entities/professional school.md>) | Adaptive permitted | Programme/course/module practice, later validated certification |
| [Secondary school](<../entities/secondary school.md>) | Adaptive rejected, including assessment category | Possible opt-in formative assessments; terminal exams should stay traditional |
| [CBT center](<../entities/cbt center.md>) | Adaptive permitted | Online adaptive delivery first; offline adaptive requires separate work |

Changing secondary policy requires category-aware validation, permissions, UI, documentation and tests. Removing the blanket rejection alone would also affect terminal exams.

## Missing runtime capabilities

- Adaptive-specific candidate instructions, navigation and progress display.
- An approved scoring/reporting interpretation for unequal question paths.
- Integrated Python FastAPI engine, calibration workflow and validated ability/uncertainty estimates.
- Browser acceptance across all enabled contexts, offline integration and controlled deployment.
- Validated ability reporting, practice mastery models beyond descriptive counts, immutable media and approved aggregate certification policy.

No Python engine implementation was found in the audited repository. The architecture in the older system-design document describes future intent.

## Integration design to preserve traditional CBT

Keep the existing Laravel/Inertia and candidate-router boundaries. Dispatch into separate traditional and adaptive services using a mode frozen on the attempt. Traditional attempts retain complete papers, revisable answers, stored option order and marks-based scoring.

For adaptive attempts, Laravel must own identity, current question, eligibility, deadline, state transitions, result release and audit records. Compute correctness privately. Never accept client correctness/ability or send answer keys to candidates.

An answer commitment should create one durable decision, protected by an idempotency key and state version. Retries return that same decision. Once committed, an adaptive response must not be changed in a way that rewrites its question path. Traditional answer editing remains available under its existing policy.

An engine adapter can initially wrap a limited Laravel practice algorithm and later call internal FastAPI. Validate every returned item against the server's eligible pool. Engine failure must not affect traditional exams or silently change an adaptive attempt's scoring mode.

Additive tables/columns should preserve active attempts, historical results, existing migrations and offline fixed-paper compatibility. Do not migrate legacy adaptive-labelled active exams into a new algorithm implicitly.

## Results and reporting boundaries

The current scorer calculates raw marks and a raw pass threshold. Existing “Adaptive Analysis” views summarize performance by topic and difficulty using percentage thresholds. They should not be interpreted as a calibrated ability scale or proof of comparable adaptive scores.

Phase 5 reports provide progression/attempt identity, persisted item paths, coverage, evidence counts, engine/scoring versions and stop reasons. Consequential certification/recruitment decisions require approved scoring validation before rollout. Practice pilots must clearly state what their descriptive results mean.

## Agreed extension: progressive weakness-focused levels

The Phase 3 backend implements this direction behind rollout containment. An exam can optionally enable progressive remediation: Level 1 covers the full blueprint; subsequent levels use fresh questions from unresolved paper-row areas. Selected topics constrain coverage; independently budgeted topic-level recovery is not yet implemented. Within a level, the engine adjusts question difficulty.

A progression belongs to one candidate and one exam. Each level is a separate linked attempt with its own question path, timer and result. Reconnecting resumes that level. A normal full-exam retake is a different operation and must not silently reset the progression's score or penalties.

### Per-exam configuration

Proposed settings must be validated and frozen when the candidate's progression begins. Editing an exam affects future progressions, not an active candidate's ledger.

| Setting | Planned behavior |
| --- | --- |
| `progressive_remediation_enabled` | Explicit opt-in for adaptive exams; default off |
| `recovery_penalty_percent` | Required when enabled; configurable 0-100 inclusive, at most two decimal places; no hard-coded 10% policy |
| Penalty basis | Remaining recoverable marks before the next scored level; never the original total or marks already earned |
| `max_scored_levels` | Required positive integer including Level 1; prevents unlimited progression |
| `min_level_budget` | Required positive mark amount; stop when the next budget falls below it |
| `mastery_threshold_percent` | Per-exam threshold for each blueprint area; evaluate without the recovery penalty |
| `min_evidence_per_area` | Required positive question count before claiming mastery |
| Level blueprint | Required areas, coverage quotas, starting difficulty, supported item types, question limits and weighting policy |
| Level access | Duration, overall progression closing time, optional cooldown and eligibility rules |
| `allow_unscored_remediation` | Optional practice after scored progression closes; defaults off and cannot change the examination score |

For the first implementation, use non-negative objective scoring in progressive mode and reject combining it with negative marking. Traditional negative marking remains unchanged. This avoids undefined negative recovery budgets and stacked penalties.

### Percentage penalty and cumulative score

The configured percentage is deducted once from the previous level's remaining recoverable marks when a new scored level successfully starts. Level 1 has no recovery penalty.

Let `M` be the original exam total, `R` the unearned recoverable balance, `p` the configured penalty percentage, `P` the next-level penalty, `B` its available marks, and `E` marks earned in that level:

```text
Level 1 budget = M
After Level 1: cumulative score = E1; R = M - E1

Before each additional scored level:
P = round_half_up(R * p / 100, 2)
B = max(0, R - P)

After that level:
0 <= E <= B
cumulative score = previous cumulative score + E
R = B - E

Final percentage = cumulative score / M * 100
```

Use integer hundredths of a mark and integer basis points for percentages (or exact decimal arithmetic), not binary floating-point. Round penalties once at level creation; freeze item/area weights so their sum equals the level budget exactly. Keep each ledger entry immutable and reproducible.

Example: original paper has 50 questions at 2 marks each; the exam's recovery penalty is **10%**:

| Level | Incoming balance | Penalty | Available marks | Earned | Remaining balance | Cumulative score |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| 1 | 100.00 | 0.00 | 100.00 | 40.00 | 60.00 | 40.00 |
| 2 | 60.00 | 6.00 | 54.00 | 20.00 | 34.00 | 60.00 |
| 3 | 34.00 | 3.40 | 30.60 | 10.00 | 20.60 | 70.00 |

If another level is permitted, its penalty is 2.06 and its available marks are 18.54. If `max_scored_levels = 3`, the progression closes at 70/100 with 9.40 in penalties and 20.60 unrecovered marks.

This percentage example supersedes the earlier fixed 10-mark illustration. A percentage below 100 does not reliably exhaust all marks after a finite number of levels, especially with rounding; explicit level/budget/time limits are mandatory. A 0% penalty still obeys those limits. A 100% penalty leaves no additional scored budget, so no scored level is opened.

### Weakness selection and fair allocation

1. Level 1 must cover every required blueprint area with sufficient evidence. An area not tested enough is marked `insufficient_evidence`, not mastered.
2. Finalize and score the level on the server before generating its weakness snapshot.
3. Determine each area's status from unpenalized, objective performance and evidence count. For the initial rule-based policy, use the latest completed level's evidence for the area; if that level has too few questions, keep the area unresolved. Do not average historic failures into every future mastery decision.
4. Track per-area original allocation, earned marks, penalty share and remaining recoverable balance. Apply the recovery penalty proportionally to eligible area balances, distributing rounding remainders deterministically.
5. Generate fresh, approved questions only for unresolved areas with recoverable budgets, preserving tenant, subject/course/module and topic scope. Exclude previously administered questions across the entire progression, not just the current level.
6. Freeze area budgets before the level starts. Marks earned in one area cannot recover another area's marks or exceed that area's remaining allocation. A weighted question's full score is its frozen allocation; unsupported weighting/question combinations must fail readiness checks.
7. For the initial progressive scoring policy, use a fixed question count per targeted area, with difficulty adapting within that quota. Variable-length ability-based delivery remains a later engine policy and must not silently replace this marks ledger.

A mastered area's remaining unearned marks become closed/unrecovered rather than being transferred to another topic or automatically awarded. The formula above gives the balance before these closures: subtract any newly closed area balances from R before calculating the next penalty. The worked example assumes all remaining balances are still eligible weak areas. Areas with insufficient evidence remain unresolved; budgetless areas can be offered unscored practice if configured. Record these closures so the ledger remains balanced.

If there are too few fresh questions in any required area, show an administrator-action state before opening the next level. Never silently repeat questions, widen the scope to mastered topics, or charge a penalty for a level that could not be prepared.

### Lifecycle and stopping rules

At level completion, finalize its score and weakness snapshot exactly once, then evaluate eligibility for another level. Stop scored progression when any of these applies:

- All required areas are mastered with enough evidence.
- No eligible recoverable area balance remains.
- The proposed next budget is zero or below `min_level_budget`.
- `max_scored_levels` is reached, or the progression window closes.
- Disqualification or an authorized closure prevents further writing.

Insufficient fresh questions or a temporary engine failure blocks preparation; it is not proof of mastery. No penalty is posted while merely previewing a possible level. On an authorized next-level start, atomically persist the new attempt, budget and single penalty entry. Retried starts return that same level. No extra penalty is charged for reload, lost response or resume.

A started level uses its frozen deadline and normal server submission/auto-submission rules. Abandonment/expiry finalizes it under that policy; it does not refund the penalty or grant a free replacement. Advancing after a failed level remains subject to remaining budgets and configured level/access limits. Authorized incident corrections require an explicit audited adjustment, never an implicit reset.

### Score, mastery and reporting

Keep three independent outcomes:

- **Cumulative examination score:** sum of earned marks across scored levels, bounded by the original exam total.
- **Mastery status:** tested/mastered/weak/insufficient-evidence per module or topic, with evidence counts.
- **Progression status:** active, blocked, completed or closed, with an explicit reason.

The accounting invariant is:

```text
Original total = cumulative earned + posted penalties
               + still-recoverable marks + closed/unrecovered marks
```

When progression closes, transfer unused recoverable marks into closed/unrecovered marks. No closure creates earned marks or an artificial penalty.

Exhausted marks or a maximum-level stop does not prove mastery. Optional unscored remediation may continue until mastery or the practice/access limits are reached, but it cannot alter examination scores, certificates or already released results.

Report Level 1 baseline, each level's incoming budget/penalty/earned marks, cumulative score, unresolved areas and closure reason separately. Identify recovered scores explicitly; do not present them as first-attempt performance or a calibrated ability estimate.

All level scores and the aggregate score obey the server's result-release policy. Weakness summaries may be shown when that policy permits, without disclosing item-level correctness or keys. If numeric scores are withheld, numeric recovery budgets/penalties must also be withheld because they can reveal the earlier score.

### Context boundaries

Professional schools can use levels for course/module remediation; institutions for course assessments; organizations for training diagnostics; CBT centers for authorized hosted or center-owned assessments. Each retains its existing ownership and learner assignment rules.

Secondary-school formative progression remains dependent on explicitly enabling adaptive assessment-category support. Traditional terminal exams remain unchanged. Recruitment/certification progression requires an approved decision policy and validation; recovery scoring must not automatically trigger existing certificates from an individual level.

## Completion sequence

| Phase | Deliverable | Acceptance gate |
| --- | --- | --- |
| 1 | Regression baseline, owner/category and progressive scoring policy, inventory, rollout controls | Existing CBT available; no silent conversion of active attempts |
| 2 | Validated percentage/level settings, frozen configuration, pools and progression ledger | Round-trip settings and tenant/content constraints pass |
| 3 | Atomic answer/advance and level starts, weakness selection, percentage penalties, resume and stopping | Different response paths work; retries cannot advance twice |
| 4 | Adaptive candidate/supervisor UI, level history and release-aware budget display | Browser/reconnect tests pass while traditional navigation remains intact |
| 5 | Level and cumulative score/mastery reporting, optional unscored remediation and online pilots | Honest score interpretation and release controls across enabled owners |
| 6 | FastAPI integration and validated assessment model | Approved measurement, decision, security and operational criteria |
| 7 | Offline adaptive extension and staged rollout | Replay/sync/recovery pass without breaking fixed-paper packages |

See the [full audit and phase plan](../adaptive-examination-audit-2026-09-08.md) for findings, architecture, acceptance criteria and detailed regression matrix.

## Evidence and maintenance

[SharedExamWorkflowTest](../../tests/Feature/SharedExamWorkflowTest.php) tests three helper results; creation tests cover some permitted/rejected contexts. They do not test an end-to-end adaptive examination.

The prior audit selection reported 47 passes, 19 failures and 3 errors across 69 tests. Do not describe adaptive creation or traditional compatibility as fully verified until that baseline is reconciled.

Key sources: [ownership rules](../../app/Support/ExamOwnershipRules.php), [request validation](../../app/Http/Requests/StoreExamRequest.php), [wizard](../../resources/js/Pages/Exams/Wizard.tsx), [generator](../../app/Services/ExamPaperGeneratorService.php), [candidate API](../../app/Http/Controllers/Api/CandidateExamController.php), [payload](../../app/Http/Resources/CandidateExamPayloadResource.php), [performance profiles](../../app/Services/CandidatePerformanceProfileService.php).

Update implemented/missing status whenever adaptive behavior changes, and link the tests demonstrating the new behavior. Keep plans labelled as plans.
