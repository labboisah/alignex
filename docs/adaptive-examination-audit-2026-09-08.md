# Adaptive examination implementation audit

## Current diagnostic pilot update — 8 September 2026

Online/offline supervised diagnostic delivery is implemented, including all five owner contexts and secondary-school formative assessments. Terminal exams remain traditional. See [the current pilot runbook](exams/adaptive-pilot.md) and [Phase 7 implementation](exams/adaptive-phase-7.md). These supersede older statements below that offline delivery or secondary formative adaptive is entirely unavailable. The remaining consequential calibration/validation and target-hardware rollout tasks are explicitly listed there.


Date: 8 September 2026  
Repository baseline: `e8050a7b`  
Scope: Laravel backend, Inertia administration, candidate React Router app, database foundations, result reporting, offline package boundary, and existing tests. This records the original audit baseline and completion plan; subsequent implementation updates are documented below.

## Phase 1 follow-up

The findings and initial test results below are the historical audit baseline. Phase 1 containment, local inventory and regression reconciliation have now been implemented: the expanded selection passes 92 tests with 1,073 assertions. Adaptive live delivery remains disabled. See [Phase 1 implementation evidence](exams/adaptive-phase-1.md) for the complete disposition of the original 22 non-passing outcomes and the Phase 2 handoff.

## Phase 2 implementation update

Phase 2 configuration, preparation and additive persistence are implemented. The shared regression selection now passes **109 tests with 1,182 assertions**, including 17 adaptive contract tests. The local additive migration and frontend build succeeded. See [Phase 2 evidence and Phase 3 handoff](exams/adaptive-phase-2.md). Adaptive candidate delivery, penalty posting and recovery scoring remain disabled/unimplemented; the audit findings below describe the original baseline.

## Phase 3 implementation update

The isolated adaptive server lifecycle and progressive scoring are implemented. Current-item issuance, draft/commit, deterministic difficulty and coverage, idempotent advancement, recovery starts/penalties, exact area ledgers, deadlines, disqualification, practice isolation and release-aware aggregates are covered by tests. The shared pre-start content, answer/submission race and submit-score release findings were also addressed.

The final regression selection passes **132 tests / 1,327 assertions**. Separate MySQL worker tests pass **3 tests / 42 assertions**, including a repeatable-read race found and fixed during implementation. The local runtime migration and frontend build pass. See [Phase 3 evidence and Phase 4 handoff](exams/adaptive-phase-3.md). Live rollout remains disabled. The Phase 1/2 notes and audit findings below are historical and are superseded by this update where indicated.

## Phase 4 implementation update

The candidate and supervisor experience is implemented behind the existing rollout controls. The adaptive router branch supports explicit start, final confirmation, drafts, durable retries, next-level token replacement, reconnect/multi-tab recovery, server-derived completion and release-aware results. Frozen proctor requirements are used, supervisors see retained level history, and End Exam now finalizes/closes adaptive ledgers safely.

Verification: **140 backend tests / 1,392 assertions**, **3 MySQL contention tests / 42 assertions**, and **15 browser scenarios verified** (including the corrected-case rerun). See [Phase 4 verification and Phase 5 handoff](exams/adaptive-phase-4.md). Traditional CBT retains its existing writing flow, and secondary-school adaptive remains blocked. The next phase is reporting and controlled practice pilots; runtime readiness has not been enabled.

## Phase 5 implementation update

Diagnostic progression reports, scoped/audited exports, release containment and exact owner/exam pilot controls are implemented. The online diagnostic runtime is ready for explicitly approved cohorts; flags remain off by default and no live cohort was enabled. Traditional grading/export paths exclude bound adaptive levels. Recruitment shortlisting and certificate classification cannot use these pilot results.

Verification: **152 backend tests / 1,632 assertions**, followed by **20 targeted tests / 377 assertions** for the final ownership/legacy safeguards; **four browser scenarios verified** across the suite and corrected-case rerun. See [Phase 5 delivery notes, browser verification and pilot acceptance checklist](exams/adaptive-phase-5.md). Live cohort observation and assessment-owner acceptance remain operational gates. Phase 6 is the next engineering phase; earlier audit/phase statements below are historical.

## Phase 6 engineering update

The authenticated FastAPI shadow engine, frozen calibration import/review/revoke lifecycle, immutable evaluation/replay history, bounded Laravel client and reference-data validation runner are implemented. Live candidate dispatch and consequential scoring remain gated; no representative calibration dataset or specialist acceptance evidence has been supplied yet.

See [Phase 6 engineering evidence, input format and remaining acceptance work](exams/adaptive-phase-6.md). The Phase 6 gate is not complete: the promised data/criteria, representative validity and operational checks, owner approval and a validated live external-engine path remain outstanding.

## Assessment

**Adaptive examination is at foundation/prototype level, not a completed adaptive candidate workflow.** Administrators can select adaptive mode in several contexts, a standalone service can move between difficulty bands, and results include topic/difficulty summaries. However, paper generation and candidate delivery still use the fixed-paper workflow regardless of mode. Selecting “adaptive” does not currently establish response-dependent question delivery.

Traditional CBT supplies substantial reusable infrastructure: participants, generated papers, option ordering, candidate sessions, server deadlines, answer persistence, submission, monitoring, scoring, and reporting. These foundations should be retained. The adaptive work must introduce an explicitly separate delivery path with its own state and scoring contract.

No completion percentage is assigned: counting labels, shared infrastructure, and adaptive-specific functionality together would overstate readiness. The practical maturity is **configuration and isolated algorithm scaffolding; end-to-end adaptive delivery unimplemented in the inspected path**.

## Evidence and current capability

Paths below are relative to the repository root; line numbers refer to the audited baseline.

| Area | Current level | Evidence and limitation |
| --- | --- | --- |
| Exam mode and ownership | Implemented foundation | `app/Models/Exam.php:132` resolves `exam_mode` before legacy `mode`; `app/Support/ExamOwnershipRules.php` permits adaptive for four owner contexts, excludes secondary schools. |
| Administrative configuration | Partial | `resources/js/Pages/Exams/Wizard.tsx:391` displays start difficulty and a free-text policy. `StoreExamRequest::rules()` has no explicit validation for those two settings. The selector does not consume them. Persistence of arbitrary nested settings was not independently tested. |
| Difficulty stepping | Isolated prototype | `app/Services/AdaptiveQuestionSelectorService.php:10` starts at medium; `nextDifficulty()` moves up/down across easy, medium, hard. Repository searches found no production caller of this selector. |
| Next-question selection | Isolated, incomplete | `AdaptiveQuestionSelectorService.php:36` selects an unused random question in one exam-level bank and difficulty band. It does not persist the selection or enforce an adaptive session lifecycle. |
| Paper generation | Working fixed-paper foundation | `app/Services/ExamPaperGeneratorService.php:80` generates all questions and option orders upfront; `questionsForExam():272` has no adaptive branch. |
| Candidate API | Fixed-paper behavior | `app/Http/Controllers/Api/CandidateExamController.php:35` requires generated papers. `answer():163` saves an answer and updates completion progress; it does not select a next item. |
| Candidate payload and UI | Fixed-paper behavior | `CandidateExamPayloadResource.php:21` serializes all attempt papers and has no delivery-mode field. `resources/js/Pages/CandidateExam/App.tsx:454` builds navigation/counts from the whole question list; `goNext():584` advances a local index. |
| Answer secrecy | Useful existing foundation | `app/Http/Resources/CandidatePaperResource.php` explicitly emits question text and options without answer keys, explanations, correctness flags, or scoring metadata. Maintain this boundary for every adaptive response. |
| Adaptive persistence | Not found as a dedicated implementation | Reviewed attempt model/migrations and adaptive references show no dedicated versioned adaptive state, selection decisions, ability estimate/uncertainty, or stopping reason. Generic metadata is not evidence of a working lifecycle. |
| Scoring | Traditional marks-based | `app/Services/ExamResultService.php:15` totals marks and compares raw score to `pass_mark`, with no adaptive scoring branch. |
| Topic/difficulty reporting | Implemented descriptive analytics | `CandidatePerformanceProfileService::generate()` groups scored papers by subject/topic/difficulty and assigns mastery labels using percentage thresholds. This is not evidence of ability estimation or adaptive item delivery. |
| FastAPI engine | Planned only in inspected repository | `docs/system-design.md:77` describes a future engine. Searches found no tracked Python implementation or adaptive engine integration in the inspected source. External services/deployments were not inspected. |
| Automated adaptive coverage | Very limited | `tests/Feature/SharedExamWorkflowTest.php:154` asserts three difficulty-helper results. Other adaptive references mainly test creation/ownership rules or analytics, not response-dependent delivery. |
| Offline adaptive delivery | Not established | `OfflineExamPackageController::show()` requires generated candidate papers and packages their questions. It does not establish an adaptive runtime contract. |

## Prioritized findings

### F1 — High: adaptive mode currently follows fixed-paper delivery

The generator has no mode dispatch, login requires existing papers, the resource returns the complete paper, and answer saving never invokes the selector. Consequently, successful adaptive exam creation is not proof that an adaptive examination can be administered.

**Required action:** introduce explicit mode dispatch and an adaptive lifecycle. Until its acceptance gates pass, gate new adaptive publication/start behind a server-side rollout control. Inventory existing adaptive-labelled exams first: never silently convert an active attempt to another algorithm or mode.

### F2 — High: the standalone selector is unsafe to connect unchanged

`AdaptiveQuestionSelectorService::nextQuestion()` uses only `exam.question_bank_id`, difficulty, unused IDs, and draft/review/approved status. In contrast, the fixed generator supports subject-level bank lists, subject filtering, and topic rules (`ExamPaperGeneratorService.php:313`).

Connecting the selector directly could ignore configured subject/topic coverage, miss questions in subject-level banks, choose unreviewed content, or return null when a band is exhausted. It has no durable reservation, cross-request duplicate protection, or explicit ownership/eligibility recheck. This is a latent integration risk, not a demonstrated cross-tenant exploit in the currently disconnected service.

**Required action:** select only from a server-built, owner-authorized and versioned adaptive pool. Enforce subject/topic blueprint constraints, question type support, approval policy, no repeats, and an explicit pool-exhaustion policy. Keep stricter adaptive approval rules isolated initially: the current fixed generator also accepts draft/review content, so changing shared status rules requires separate CBT regression review.

### F3 — High: no atomic adaptive answer-and-advance state

The answer endpoint checks writability and expiry before its transaction and does not lock/recheck the attempt inside that transaction. Submission locks the attempt in `finalizeAttempt():501`. By inspection, a concurrent save/submission can therefore race; concurrency behavior was not reproduced in this audit.

Adaptive advancement additionally needs one committed answer to produce exactly one durable next-question decision. The current `updateOrCreate` answer model allows rewriting earlier paper answers and has no adaptive step token or idempotency key.

**Required action:** lock/recheck status and deadline inside answer transactions; introduce request idempotency and monotonically increasing state versions. For adaptive mode, distinguish draft saves from irreversible “confirm and continue,” and enforce committed-answer immutability on the server. Preserve traditional answer revision/navigation behavior.

### F4 — High: current scoring cannot be treated as validated adaptive scoring

`ExamResultService` uses raw marks, percentage, and a raw pass threshold. There is no mode-specific ability scale, uncertainty, model version, or adaptive classification rule. Existing descriptive “mastery” labels also lack an evidence-count qualification.

**Required action:** keep raw score reporting for CBT. Define separately what a low-stakes difficulty-stepping pilot reports, and what a later calibrated adaptive assessment reports. Do not treat different candidate paths as interchangeable certification/recruitment results without an approved validation process. Record administered-item count, scoring version, decision basis, and stopping reason.

### F5 — High: shared result-release bypass in submission responses

`CandidateExamController::submit()` and `autoSubmit()` return `score` and `total_marks` unconditionally after finalization. They do not inspect `show_result_immediately` or another release decision at this response boundary. Hiding a score in the UI does not remove it from the response.

**Required action:** use the authoritative release decision for all candidate-facing result responses. Add tests for withheld and released results in both modes, including automatic submission. This is an existing shared issue, not introduced by adaptive selection.

### F6 — Medium: adaptive configuration and feature access are incomplete

The start difficulty is hard-coded to medium; the UI's adaptive policy is free text; neither adaptive setting has a dedicated request rule or selector integration. Adaptive pricing/navigation flags exist, but no mode-specific adaptive entitlement check was found in the inspected exam policy/request/routes. Menu visibility is insufficient to enforce rollout eligibility.

**Required action:** validate a closed set of supported policies, bounded lengths, starting rules, and stopping settings; persist a configuration snapshot per attempt. Enforce owner/category permission, tenant eligibility, and rollout/plan entitlement server-side. Resolve conflicting legacy/new mode inputs explicitly.

### F7 — Medium: “Adaptive Analysis” wording overstates the analytics

`resources/js/Pages/Results/Exam.tsx:40` labels descriptive topic/difficulty summaries “Exam Adaptive Analysis.” The underlying profile service operates on scored papers irrespective of adaptive delivery. It deletes/replaces profiles by candidate and exam, so it also does not preserve an attempt-specific adaptive history.

**Required action:** use descriptive performance-analysis wording for the existing summaries, and add separate attempt/version-specific adaptive reports when available. Mark sparse topic evidence as insufficient instead of confidently assigning mastery from too few items.

### F8 — Medium: candidate questions are returned before the scheduled start

The early-login branch returns `CandidateExamPayloadResource` even while the attempt is not started; that resource includes all papers. The existing scheduled-start test even reads a question from that early response. Blocking answer saves does not prevent early reading of question content.

**Required action:** return waiting-room metadata before the authorized start, then issue content only after server authorization. Treat this as an explicit shared security fix with updated traditional CBT tests, not an incidental adaptive payload change.

## Requirements for all five use cases

These are proposed target behaviors, distinguished from current rules. They do not authorize a silent change to existing exam types or academic workflows.

| Use case | Current adaptive position | Proposed adaptive scope | Compatibility requirements |
| --- | --- | --- | --- |
| CBT center | Mode permitted; creation covered by tests; delivery remains fixed | Initially online practice/diagnostics; later hosted adaptive assessments for authorized clients | Separate content owner from delivery center; constrain proctor access; preserve center assignment and traditional/offline paper delivery; explicit reconnect and local-server policy. |
| Professional school | Mode permitted; creation tests exist | Course/module diagnostics and practice first; certification only after scoring validation | Preserve programme, course, module, training batch, eligibility/payment, attempt limits, and certificate rules. Do not issue certificates solely from an unvalidated adaptive pilot score. |
| Secondary school | Explicitly traditional-only in ownership rules and request validation; rejection tests exist | Introduce opt-in adaptive formative assessments under the assessment category if this product policy is approved; terminal exams remain traditional | Preserve academic session, term, class/group, subject, teacher scope, report cards, and existing terminal marks. Assessment-only permission requires coordinated backend, UI, documentation, and test changes. |
| Institutions | Ownership rules permit adaptive for assessment category; no explicit adaptive reference found in institution feature tests | Course assessments, placement/diagnostics, and remediation; graded adoption only after validation | Preserve institution/programme/course/cohort scope, facilitator authorization, academic records and grade conversion. Add explicit institution adaptive creation and delivery tests. |
| Organizations | Mode permitted; creation tests cover adaptive assessment | Training diagnostics and skills assessment first; recruitment/certification after validated decision rules | Preserve tenant boundaries, reusable candidate groups, owner-controlled release, auditor scope, recruitment reports, and comparable decision criteria. |

Secondary-school support is the most important product-policy change: merely removing the current adaptive rejection would also relax protections on terminal exams. Add category-specific permission and tests instead.

## Architecture that protects traditional CBT

1. **Keep the existing routes and routing boundary.** Admin pages remain Laravel/Inertia; only `/exam/*` uses React Router. Keep the required candidate endpoints, adding backward-compatible adaptive payload fields or an explicitly versioned adaptive contract where necessary.
2. **Dispatch by a frozen attempt delivery mode.** A traditional strategy retains complete generated papers, option ordering, navigation, answer updates, marks, and reports. An adaptive strategy handles eligible pools, current item, answer commitment, next selection, and stopping. Resolve the mode at attempt initialization; later exam edits must not change an active attempt.
3. **Add isolated adaptive records.** Proposed records: versioned exam configuration, approved pool/item versions, attempt state, and selection/response decisions. Record attempt ID, step number, item version, decision/model version, state version, idempotency key, timestamps, and stop reason. Add unique constraints on step and request identity; enforce item-repeat policy. Ability/uncertainty fields are needed only for an engine that actually computes them.
4. **Make advancement authoritative and recoverable.** Validate token, owner eligibility, current item, deadline and status; score the committed answer privately; persist response, new state and next decision atomically. A repeated request returns the same decision. A reload resumes the issued item, not a fresh random selection. Never accept client correctness or ability inputs.
5. **Use a server-side engine adapter.** An initial Laravel rule-based engine can support a limited practice pilot. A later Python FastAPI engine follows the project's planned architecture. Laravel owns identity, eligibility, timing, selected-item verification, persistence, finalization and release. The browser never calls FastAPI directly.
6. **Handle remote calls without long database locks.** Persist/version the expected state, request a deterministic engine decision with an idempotency key, then lock and verify the state version before accepting the response. Reject stale/foreign/ineligible item IDs. Define service authentication, short timeouts, retry limits, and decision audit records.
7. **Isolate failures.** Engine failure must not affect traditional exams. Adaptive attempts should resume a previously persisted decision or enter a controlled recovery state under the configured time policy. Never silently switch an in-progress adaptive attempt to traditional scoring or another engine.
8. **Separate progress from score.** The existing answer workflow writes completion percentage into `attempt.percentage`, which scoring later replaces with a score percentage. Introduce an explicit adaptive progress contract; unknown final length must not be presented as a fixed total or mastery estimate.
9. **Preserve deployed data.** Use additive migrations and explicit backfills after inventorying legacy/new mode discrepancies. Default existing traditional attempts to the legacy delivery behavior. Freeze historical results and active papers; no bulk recalculation as part of adaptive rollout.

## Progressive remediation implementation plan (agreed extension)

The product scope now includes optional weakness-focused levels with a **percentage penalty configurable per exam**. This section extends the implementation plan; it does not change the historical audit findings or imply the feature already exists.

The normative behavior and worked example are in [Adaptive knowledge base: progressive levels](exams/adaptive.md#agreed-extension-progressive-weakness-focused-levels). Penalties apply to the remaining recoverable marks, not the original total. With 100 initial marks, 40 earned in Level 1 and a 10% penalty, Level 2 offers 54 marks. Earning 20 leaves 34; Level 3 offers 30.60. Earning 10 produces a cumulative 70/100 and 20.60 still unrecovered before closure.

### Backend and persistence deliverables

- Add validated opt-in progressive settings: penalty percentage (0-100), maximum scored levels, minimum budget, mastery threshold, evidence minimum, per-area question quotas, level timing/access and optional unscored remediation. Freeze the configuration and original blueprint when progression starts.
- Add a progression record linked to exam, candidate/participant, owner, original total, policy version, aggregate score, recoverable/closed balances, status and closure reason.
- Link each level to its own candidate attempt, previous finalized level and immutable weakness snapshot. Preserve the ordinary attempt history; never overwrite Level 1 to represent Level 2.
- Persist per-area original allocation, earned balance, penalties, recoverable/closed balances, evidence count and mastery state. Persist per-level incoming budget, percentage, penalty, available budget, earned score, item weights, deadlines and timestamps.
- Add an append-only mark ledger with exact decimal/integer arithmetic and unique level/penalty identities. Enforce one next level per progression/level number and one penalty per successful level start. Separate posted penalties from closed unused marks.
- Introduce focused progression, weakness-analysis and recovery-scoring services alongside the mode-specific delivery engine. Traditional ExamResultService behavior remains isolated. Generic performance profiles currently replaced by candidate/exam cannot be the authoritative progression history.
- Use a policy/FormRequest/resource boundary for any next-level action. Recheck assignment, owner access, finalized predecessor, server time, disqualification, level cap, fresh-pool readiness and state version. Browser-submitted scores, penalties, weaknesses or next-level numbers are not authoritative.
- Keep recovery scoring non-negative initially; reject negative-marking combinations only for progressive mode. Use fixed per-area item quotas with frozen score weights; keep variable-length ability scoring a separately versioned policy.
- Scope idempotency to progression, level and operation. Commit next-level attempt creation and penalty posting in one transaction. A failed preparation, preview, reconnect or repeated request must not spend marks.
- Finalize the overall result separately from each level. Gate certificates and consequential decisions on the authorized aggregate-result policy, not the completion hook for an individual level.

### Delivery additions to the existing seven phases

| Phase | Additional progressive deliverable | Additional acceptance gate |
| --- | --- | --- |
| 1: baseline/policy | Establish percentage-of-remaining basis, scored/practice boundaries, per-area mastery policy and finite level/access limits across all five contexts | Approved formula/example; secondary terminal exams stay traditional; settings explicitly opt-in |
| 2: contracts/data | Per-exam configuration, progression/level links, exact mark ledger, weakness snapshots and frozen weights; publish-time pool readiness preview | Settings round-trip; invalid percentages/caps/negative marking rejected; ledger cannot exceed original total |
| 3: server lifecycle | Level 1 full coverage, finalized weakness analysis, fresh weak-area questions, once-only next-level penalty/start, aggregate scoring, timeout/closure | Concurrent/retried starts create one level/penalty; no cross-area mark recovery; stopped/blocked levels cannot mutate results |
| 4: UI/monitoring | Admin policy editor and penalty preview; candidate level instructions/resume/eligibility state; proctor level history and stop reasons | Score-release-aware display; no implicit new level on refresh; no new penalty for network recovery |
| 5: reports/pilots | Level 1 baseline plus recovered score, area evidence/mastery, penalty ledger, closed balances, optional unscored practice and aggregate release | Reports reconcile to original total; practice never increases score; individual levels do not issue aggregate certificates |
| 6: engine/validation | Verify selection/scoring across repeated exposure and recovery paths; version engine and policy independently | Progressive raw score is not labelled calibrated ability; validated decision policy before consequential use |
| 7: offline/rollout | Signed progression/version/ledger transfer, local authority, idempotent synchronization and conflict handling | Duplicate sync cannot create another level, penalty or earned credit; traditional packages remain compatible |

### Accounting and lifecycle acceptance tests

1. Percentage example: 100 -> earn 40 -> remaining 60 -> penalty 6 -> budget 54 -> earn 20 -> remaining 34 -> penalty 3.40 -> budget 30.60 -> earn 10. Assert cumulative 70, penalties 9.40 and recoverable 20.60; at a three-level cap, move 20.60 to closed/unrecovered.
2. A further eligible level from 20.60 has penalty 2.06 and budget 18.54. This is not the former fixed ten-mark calculation.
3. Test 0%, 100%, invalid negative/over-100 rates, configured decimal precision, half-up rounding, tiny budgets, zero original total rejection, level cap and progression deadline.
4. Test per-area proportional penalty allocation and deterministic rounding remainders. Sum all frozen item/area weights to the level budget; prevent earned marks beyond an area's allocation and any transfer of an excluded mastered area's budget to another topic.
5. Test full initial coverage, fresh-item exclusion across all levels, latest-level mastery evidence, insufficient evidence, all-mastered closure and areas with no score budget but unresolved learning needs.
6. Test exhausted banks and engine failures before level start: no penalty, no fabricated mastery and no silent repeats. A retry after committed start returns the identical attempt/ledger.
7. Race next-level starts from separate devices; race finalization/start, expiry and disqualification. Confirm one immutable predecessor snapshot, one new level, one posted penalty and authoritative status.
8. Verify closed unused marks versus penalties, aggregate score <= original total, non-negative budgets and original total = earned + penalties + recoverable + closed/unrecovered.
9. Test abandonment/timeout after start, authorized incident adjustments, access-window closure, optional unscored remediation and prevention of full-retake budget-reset bypasses.
10. Test result withholding on every level and aggregate endpoint, including budget/penalty fields that could reveal a hidden earlier score. Summary weaknesses must not reveal item-level correctness.
11. Verify certificate/result hooks do not publish intermediate levels as the final outcome; recovered scores remain identified separately from first-level baseline.
12. Run owner/participant/teacher/facilitator/hosted-center tests and the traditional regression suite. Percentage settings must have no effect on traditional exams.

### Release and rollback

A controlled online pilot requires both the existing Phases 1-5 gates and their progressive additions above. Use separate rollout controls for adaptive selection and progressive levels so one does not imply the other is ready.

Disable new progressive starts when rolling back, while preserving the agreed recovery/closure path for existing levels. Do not delete ledger entries, refund penalties implicitly, rewrite the original total, re-score historical CBT attempts or change an active progression's percentage.

## Completion phases and acceptance gates

Phases are dependency-ordered. Effort estimates should follow Phase 1 because engine ambition, bank readiness, and offline requirements are not yet established.

### Phase 1 — Baseline, policy and containment

Deliver the five-context capability matrix, a documented low-stakes pilot scope, traditional regression baseline, feature flag/tenant allowlist, and inventory of existing adaptive-labelled exams. Define how active versus not-started legacy adaptive-labelled attempts are handled. Specify secondary formative support while retaining traditional terminal exams.

**Gate:** all existing traditional workflows remain available; new adaptive publication/start is controlled server-side; no active attempt is migrated implicitly. Product owners accept the owner/category matrix and the pilot's reporting limits.

### Phase 2 — Contracts, validation and additive persistence

Implement mode-specific validated settings and policies, frozen attempt mode/configuration, adaptive pool and decision records, eligibility queries, immutable item versions, unique constraints, and candidate payload contracts. Include standard loading/error/empty states and toasts in the Inertia setup UI.

**Gate:** authorized configuration round-trips; invalid policies/lengths/conflicting modes are rejected; no cross-owner or out-of-blueprint items enter a pool; traditional migrations/data/contracts remain compatible. A bank readiness preview identifies missing topic/difficulty coverage before publication.

### Phase 3 — Server adaptive lifecycle

Implement initial-item issuance, draft versus committed answers, server-side correctness evaluation, deterministic difficulty policy, atomic/idempotent advancement, resume, explicit minimum/maximum lengths, content quotas, exhaustion behavior, timeout, submit and disqualification. Scope the first engine to question types that can be scored reliably by the server.

Address the shared answer/submission race, result-release response, and early content-delivery findings as separately reviewed fixes with regression tests.

**Gate:** two candidates with different committed responses can receive different eligible next items; retries/reloads preserve each path; concurrent requests cannot advance twice; no future items/keys/correctness fields reach candidates; expiry/submission wins over late writes.

### Phase 4 — Candidate and supervisor experience

Status: implemented behind rollout containment. See [Phase 4 delivery notes and verification](exams/adaptive-phase-4.md).

Add mode-specific instructions and an adaptive writing view inside the existing router island. Provide “confirm and continue,” no editing of committed items, server-derived progress/stopping messages, accessible keyboard behavior, saving/retry/error states, reconnect recovery, and monitored selection/stop incidents. Use wording that explains variable length without exposing scoring internals.

**Gate:** end-to-end browser tests pass for all enabled contexts, refresh/disconnect, duplicate clicks, multi-tab use, time expiry and proctor intervention. Traditional navigation, flags, bulk paper display and existing timing behavior pass their regression suite.

### Phase 5 — Reporting and controlled practice pilots

Status: software implemented; live cohort acceptance remains an operational gate. See [Phase 5 reports, verification and pilot guidance](exams/adaptive-phase-5.md). Defaults remain disabled; no live cohort was enabled.

Add attempt-specific item-path history, topic coverage, evidence counts, raw practice performance, stop reason, engine/configuration version and authorized exports. Exercise release controls and keep traditional exports/grades stable. Pilot online practice/diagnostics per enabled owner context; implement the secondary assessment policy here if accepted earlier.

**Gate:** reports accurately distinguish descriptive practice results from validated adaptive scores; reviewer/auditor access is scoped; no automatic recruitment/certification decision depends on an unvalidated pilot result. Every enabled context has creation-to-result tests.

### Phase 6 — FastAPI and validated adaptive assessment

Status: engineering foundation implemented in shadow mode; full acceptance remains pending. See [Phase 6 implementation and validation requirements](exams/adaptive-phase-6.md).

Implement the internal engine contract, calibrated item data lifecycle, ability/uncertainty computation where selected, content/exposure controls, stopping/classification rules, replayability, and versioned result interpretation. Assign a qualified assessment specialist to item calibration and scoring validation; engineering tests alone are insufficient acceptance for this phase.

**Gate:** agreed accuracy, classification, content coverage, fairness, security, throughput and availability criteria pass on representative evaluation data. Recruitment/certification owners approve the decision rules before consequential use. Engine outage tests prove traditional CBT remains functional.

### Phase 7 — Offline center adaptation and staged rollout

**Foundation implemented (8 September 2026):** cloud and offline server capability guards, additive fixed-paper manifest versioning, five-context package compatibility tests, aggregate operations reporting and rollout pause/resume regression coverage. See [Phase 7 notes](exams/adaptive-phase-7.md). **The full gate remains open:** adaptive offline runtime, signed packages, local/cloud replay, reconciliation, live selection metrics and supervised recovery are not yet implemented or validated. Phase 6 calibration/assessment acceptance is still pending.

Treat adaptive offline delivery as a separate extension to the existing package boundary. Define supported local engine/runtime, approved signed pool/configuration packages, server-held scoring secrets, tamper-evident decisions, local deadline authority, recovery and synchronization conflict rules. Preserve existing fixed-paper package compatibility; unsupported adaptive offline imports must fail explicitly.

Roll out by tenant/context using monitored cohorts. Disable new adaptive starts when a rollout fails while retaining a defined recovery path for active adaptive attempts. Never delete state or change an active attempt's scoring mode as rollback.

**Gate:** local/cloud replay and result reconciliation pass; traditional offline packages and online CBT regressions pass; supervisors can recover interrupted sessions; monitoring tracks selection latency, duplicate decisions, pool exhaustion, errors and completion by engine/context.

## Required test matrix

| Test group | Minimum coverage |
| --- | --- |
| Traditional regression | Creation in all five contexts; ownership/validation; participant assignment; fixed-paper generation; bank/topic selection; option shuffling; candidate login/start; answer edits; navigation; expiry; submit; negative marking; release; reports/certificates where applicable. |
| Adaptive selection | Start setting honored; up/down/boundary behavior; approved pool; subject/topic quotas; subject-level bank lists; no repeated items; unsupported types; exhausted bands/pools; deterministic recovery; min/max/stopping rules. |
| Concurrency and recovery | Duplicate idempotency key; parallel answer requests; stale state version; save versus submit/disqualification/expiry; crash after persistence before response; refresh; network reconnect; remote engine timeout/stale response. Run locking checks on isolated MySQL, not only SQLite. |
| Candidate data security | No keys, correctness, explanations or internal ability state; no future questions; no pre-start item access; withheld results stay absent across all candidate endpoints; candidate-supplied correctness ignored/rejected. |
| Authorization and context | Five owner types; content owner versus hosted center; teachers/facilitators; candidate assignment; inaccessible banks; server-side feature eligibility; secondary terminal rejection and approved formative allowance. |
| Reporting and operations | Attempt history survives retakes; versions and stop reasons retained; progress separated from score; protected exports; release controls; adaptive outage leaves traditional requests unaffected. |

## Verification record and limits

Static review covered the files referenced above and repository-wide searches for adaptive/engine references. The existing test command is recorded below; see Executed test results for the outcome.

```text
php artisan test --compact --filter='SharedExamWorkflowTest|CandidateExamApiTest|ExamPaperGenerationTest|ResultManagementTest|InstitutionAssessmentFeatureTest|SecondarySchoolFeatureTest|ProfessionalExamFeatureTest|CbtCenterFeatureTest|OrganizationModuleTest'
```

`phpunit.xml` configures SQLite `:memory:`, array cache/session, null broadcasting, synchronous queue, and notification dry-run settings. These tests do not establish production MySQL locking behavior, real Reverb/Redis delivery, browser interaction, external engine availability, operational capacity, or assessment validity. No production database, external deployment, psychometric dataset, or offline executable was audited. No new functionality or audit-specific test suite was introduced.

**Release recommendation:** retain traditional CBT as the established delivery path. Complete Phases 1–5 for a clearly labelled, controlled online adaptive practice/diagnostic pilot; require Phase 6 before consequential adaptive scoring and Phase 7 before claiming adaptive offline-center support.


### Executed test results

69 tests ran: **47 passed, 19 failures, 3 errors**, with 570 assertions in approximately 58 seconds reported by the runner (exit code 1). Application source was unchanged. A clean traditional regression baseline has not been established. References above to creation tests indicate coverage exists, not that every test passes.

- Paper generation: expected 3 available questions but received 0; the candidate resource secrecy test errored because no attempt existed. The resource allowlist was inspected statically, but this failing test does not verify it.
- CBT centers: candidate, bank, and adaptive recruitment creation assertions found empty tables; another exam test errored on a missing model.
- Professional schools: adaptive hierarchy and mixed traditional/adaptive/certification creation assertions found no exams; a candidate course association differed; certificate access returned 403.
- Secondary schools: administration routes returned 404, teacher access returned 403, navigation differed, an imported class-arm association differed, and a student-group test errored.
- Organizations/results: validation error keys and menu labels differed; a result/export test returned 403.

The runner reported no failures/errors for CandidateExamApiTest, SharedExamWorkflowTest, or InstitutionAssessmentFeatureTest in this selection. Their tested flows do not establish a complete adaptive candidate path.

Failure root causes were not repaired or fully diagnosed during this documentation-only audit. Some may be stale fixtures/expectations; others may be application defects. **Phase 1 must triage all 22 non-passing outcomes, fix or reconcile them with intended behavior, and establish a passing baseline before adaptive integration.**
