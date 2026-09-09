# Document-based question generation and adaptive recovery: implementation plan

Status: proposed; no document-generation feature is implemented by this plan.
Date: 9 September 2026.
Baseline: [adaptive examination](adaptive.md), [simple setup](adaptive-simple-setup.md), and [Phase 7](adaptive-phase-7.md).

## Objective and scope

Extend the first adaptive version so organizers can upload learning resources and the system can generate source-supported questions, identify areas needing more evidence or practice, and prepare fresh questions for subsequent levels.

Question-bank coverage is the main limitation this work addresses. Adding generated questions does not by itself establish their accuracy, difficulty calibration, or the validity of high-stakes results. The existing consequential-scoring restrictions remain separate from this work.

The first release supports formative assessment and practice using text-based single-answer multiple-choice questions. Other question formats follow after their validation and scoring rules are implemented.

## Intended experience

1. The organizer creates the exam using the existing form.
2. Under **Learning resources**, they upload files or select previously uploaded resources and link them to the exam's subjects, courses, modules, or topics.
3. They choose **Existing questions**, **Questions from resources**, or **Use both**, and the desired question count. Existing level and deduction settings remain unchanged.
4. The interface reports **Reading resources**, **Preparing questions**, **Questions ready for review**, or **Ready**. Failures explain what to fix, such as unreadable pages or insufficient coverage.
5. Initially, an authorized reviewer checks generated questions with their source passages. Approved questions enter the normal question bank. There is no extra technical exam-approval form.
6. Candidates take Level 1 normally. After completion, they see their existing marks, improvement chart, and named areas to improve.
7. The system prepares the next level from unused eligible questions, supplementing gaps with generated questions from the approved resources.
8. If preparation is incomplete, the candidate sees **Preparing your next level** with a refresh/retry state. Their next-level timer and deduction have not started.
9. **Start next level** becomes available only when a complete eligible set is ready. Confirmation starts the timer and posts the configured deduction once.

Do not require organizers to understand model prompts, retrieval indexes, snapshots, calibration, or provider configuration.

## How weakness analysis and generation work

Keep the server's recorded responses, scoring, evidence counts, and mastery rules authoritative.

- Analyze performance by the subject/module areas already supported.
- Add topic and learning-objective analysis only where questions have reliable mappings and sufficient evidence. A single wrong response is not automatically a confirmed topic weakness.
- Distinguish **Needs more practice**, **Not yet assessed**, and **Not enough evidence**. Untested material should receive coverage without being presented as a demonstrated weakness.
- Use the existing configured mastery threshold and evidence minimum. Record the analysis version used for each completed level.
- A language model may suggest objective tags, source passages, distractors, and plain-language feedback. It must not set earned marks, penalties, mastery status, eligibility, or result release.
- Reuse unused eligible bank questions first under the **Use both** setting. Generate only the missing coverage, difficulty bands, or objectives.
- Match resources to the same owner, exam scope, and topic/objective. Do not fill missing source coverage with unsupported model knowledge.
- Exclude previously issued items and close paraphrases from the candidate's subsequent levels. Track related variants as question families so changing names or numbers does not count as a genuinely fresh assessment.

Example: a candidate is weak in fractions but has sufficient evidence of strength in geometry. Retrieve fractions material, reserve eligible unused fractions questions, and generate any shortfall from that material. Preserve earned marks and calculate the next budget using the exam's configured percentage deduction. Do not generate geometry recovery questions solely to fill a quota.

A resource library can still run out of distinct, defensible questions. In that case, report the coverage gap rather than promising unlimited fresh levels.

## Architecture and proposed persistence

Use Laravel services, policies, FormRequests, queued jobs, and Inertia pages. Candidate actions remain within the existing React Router island and Laravel candidate APIs. Reuse Recharts for released learning feedback.

Use a provider-neutral question-generation interface. Select the generation/OCR provider during implementation; this plan assumes no particular external API or pricing. Network calls run in queued jobs, outside database locks and exam request transactions.

Proposed additive records:

| Record | Purpose |
| --- | --- |
| learning_resources | Owner-scoped resource identity, uploader, title, status, file metadata, and retention policy |
| learning_resource_versions | Immutable file hash, private storage location, extraction status, language, extractor version, and source version |
| learning_resource_chunks | Extracted passages with page/section references, text hashes, and retrieval metadata |
| resource_scope_links | Resource-to-subject, course, module, topic, and objective mappings |
| exam_resource_bindings | Frozen resource versions and allowed scope for an exam's preparation |
| question_generation_jobs | Purpose, request key, requested coverage, provider/model/prompt versions, status, attempts, usage, and failure reason |
| generated_question_provenance | Question version, source chunks, supporting passages, validation results, and reviewer decisions |
| question_family_links | Duplicate/variant relationships used to prevent repeated exposure |
| adaptive_weakness_profiles | Versioned completed-level evidence by topic/objective, including insufficient-evidence states |
| adaptive_level_preparations | Target progression and level, preparation state, frozen inputs, approved inventory, and reservation ownership |
| adaptive_pool_revisions | Immutable question-pool supplements bound to specific future levels |

Reuse existing Question, QuestionOption, Topic, question-bank review states, and owner relationships. Confirm actual table names and constraints before migrations. Add explicit owner-scoped indexes, foreign keys, and unique idempotency constraints.

Resource uploads are private. Exam managers and reviewers access source material according to policies; candidate APIs never expose answer keys, distractor explanations, reviewer notes, retrieval chunks, or upcoming questions. Any candidate study-resource links need a separate release policy and must not reveal future item explanations.

System configuration holds provider credentials, endpoints, worker settings, and global limits. Owner budgets, exam resource choices, allowed automation, and generation settings belong in the database.

## Immutable attempts and question-pool expansion

The current implementation freezes an approved pool and checks fresh-question availability for all configured levels before opening an exam. Its selector reads that frozen pool. Uploading questions afterward cannot silently extend an existing attempt.

Implement in two stages:

1. **Pre-generation:** generate and approve sufficient inventory before the current preparation service freezes the exam. This works with existing pool-readiness rules.
2. **Between-level generation:** add explicit immutable pool revisions. Preserve the original resource bindings, scoring policy, original marks, issued content, and historical snapshot. A prepared future level references its own approved pool revision.

Update AdaptivePreparationService, AdaptiveAttemptPreparationService, AdaptiveLifecycleService, candidate resources, and offline validation together when introducing revisions. Existing attempts without a revision keep their current behavior.

Before releasing between-level generation:
- Persist the exact permitted resource versions and syllabus scope.
- Bind the target level to an exact inventory and content hashes before starting it.
- Enforce question-family exclusion across the complete progression.
- Handle concurrent requests with one preparation and one start per target level.
- Treat approved inventory, policy limits, and progression deadlines as server decisions.
- Never reset a progression budget, rewrite committed answers, or change an active level's questions.

For this new delivery policy only, replace the blanket all-level inventory requirement with a ready Level 1 plus explicit future preparation states. Keep the existing requirement for legacy exams and fully offline packages until replacement behavior is tested. Changes must be versioned; no silent relaxation for existing exams.

## Implementation phases

### Phase 1 — Resource library and access controls

Deliver an Inertia resource library with upload, list, detail, assignment, progress, and error states. Start with PDF, DOCX, and TXT; handle scanned PDFs through OCR in Phase 2.

Use private storage, file type/signature checks, size/page limits, malware handling, safe filenames, and owner policies. Reject executable or unsupported content. Record permitted use and restrict resource access to authorized owners.

Exit criteria: uploads work for all five owner contexts; unauthorized reads/assignments fail; interrupted uploads can be retried; traditional exam creation is unchanged.

### Phase 2 — Extraction and searchable source material

Queue extraction and OCR. Preserve page and section references, identify unreadable regions, remove repeated headers where appropriate, and produce versioned chunks.

Show an extraction preview so organizers can correct mappings or replace unreadable files. Store extraction confidence and explicitly block inadequate sources. Use stable content hashes to avoid duplicate extraction.

Treat document text as untrusted content, including instructions embedded in a document. It cannot override the generation task, access secrets, invoke tools, or retrieve another owner's material.

Exit criteria: supported fixtures retain traceable citations; scanned, empty, corrupt, and mixed-layout files produce clear outcomes; retrieval never crosses owner or exam scope.

### Phase 3 — Source-supported question generation and review

Generate structured draft questions containing a stem, options, one answer key, a private rationale, source references, scope tags, and a proposed difficulty band.

Validate schema, option uniqueness, source support, answer ambiguity, conflicting sources, duplicated concepts, and prohibited content leakage. Automated checks are filters, not proof that an answer is correct. Initially require content review before bank approval.

Provide reviewer edit/reject/approve actions, bulk review where appropriate, and a source-passage viewer. Record edits and revalidate changed content. Question difficulty is provisional until measured using real responses.

Exit criteria: unsupported or ambiguous items cannot be published; approved items work through existing question-bank and traditional paper workflows when explicitly selected. No generated draft enters a live exam.

### Phase 4 — Simple exam integration and pre-generated adaptive pools

Add the resource choice and question-source mode to the current exam form. Automatically queue missing inventory and show coverage by subject/module/topic.

Reuse normal approved question-bank content and existing frozen adaptive preparation. An exam remains unready while generation or required review is incomplete; do not hold a creation transaction open while waiting for a provider.

Support cancellation, job retries, resource replacement before freezing, and regeneration of rejected coverage without duplicating approved items.

Exit criteria: an organizer can create a resource-assisted exam without technical setup, and candidates complete multiple levels from pre-generated inventory. Exams configured for existing questions work without a generation provider.

### Phase 5 — Evidence-based topic weakness profiles

Add reviewed objective/topic mappings and persist completed-level evidence. Keep current area-level behavior as the fallback for untagged historical questions.

Extend released feedback with topic-level practice priorities and source study sections where permitted. Label limited evidence honestly. Keep the improvement chart based on awarded marks; do not present recovery gains as independently validated ability growth.

Exit criteria: weak, strong, untested, and insufficient-evidence cases are distinguishable; changing model output cannot change marks or mastery; profiles are reproducible from recorded evidence.

### Phase 6 — Automatic preparation between online levels

Introduce the pool revision and preparation contracts described above.

On level finalization, create one idempotent preparation job. Freeze the weakness profile, allowed sources, coverage targets, and question-family exclusions. Fill from approved inventory, generate missing coverage, validate/review under the configured policy, and freeze the resulting level inventory.

Use states such as queued, generating, awaiting review, ready, failed, and expired. Expose plain-language status through the candidate API and UI.

Candidate retries must reuse the same preparation. A failed generation or unavailable pool causes no timer start, deduction, or partial attempt. At confirmation, recheck deadline, reservation, readiness, eligibility, and budget in one transaction. Resume an already-started level after a lost response rather than creating another.

Exit criteria: concurrent clicks, provider timeouts, delayed review, changed eligibility, deadline expiry, and duplicate callbacks cannot create double penalties or inconsistent question sets.

### Phase 7 — Offline delivery and controlled replenishment

For the first offline release, generate and approve all needed inventory while the center is connected, then package it using existing signed delivery and lease controls. No model endpoint is required during a disconnected exam.

Include source/version provenance, question-family IDs, pool revision identifiers, and approved coverage in packages. Keep source text and keys out of the candidate app. The trusted center server performs local selection and scoring.

If resources run out while disconnected, preserve progress and report that another level is unavailable. Do not silently repeat questions or post a deduction.

Later, allow a signed supplement while the center is connected: bind it to the exact package, lease, progression, future level, and unused inventory. Apply it atomically before that level starts. Reject stale, duplicate, tampered, and wrong-owner supplements. Reconcile inventory, level hashes, and marks during sync.

Exit criteria: equivalent online/offline fixtures reconcile correctly; interrupted downloads and sync do not alter started levels; traditional offline packages remain compatible.

### Phase 8 — Pilot, evaluation, and controlled automation

Pilot across all five use cases. Measure source-support accuracy, ambiguity, duplicate exposure, reviewer acceptance/edit rate, coverage, extraction quality, generation latency, cost per accepted question, and level preparation failures.

Establish content-quality acceptance thresholds using a reviewed evaluation set before enabling automatic publication. Record the chosen thresholds and accountable content reviewer in the pilot configuration. Do not use model self-confidence as the acceptance gate.

Once evidence supports it, allow owners to opt into automatic publication for low-stakes practice under a validated policy. Flagged or uncertain questions still require review. High-stakes use requires separate assessment validation and calibration work.

Exit criteria: agreed quality and reliability gates pass, owners can stop generation, and existing-question delivery remains available during provider outages.

## Five use cases

| Owner context | Typical source material | Scope and pilot considerations |
| --- | --- | --- |
| CBT center | Client-provided syllabi, preparation material, training notes | Keep client resources separate; package approved questions before disconnecting; center access does not confer ownership of another client's resources |
| Professional school | Course manuals, module notes, training standards | Link to courses/modules and objectives; use authorized reviewers for technical content; certification validity remains a separate gate |
| Secondary school | Teacher notes, lesson plans, age-appropriate curriculum material | Use class/subject/topic scope and teacher review; formative practice/diagnostics first; terminal exams retain the traditional workflow |
| Institution | Course outlines, lecture notes, approved reading material | Preserve department/course/lecturer boundaries and academic review rules; check coverage across intended learning outcomes |
| Organization | Training handbooks, SOPs, onboarding resources | Scope to approved roles and training objectives; private policies stay owner-restricted; recruitment decisions require separate validation |

## Operational and regression requirements

- Owner-isolated retrieval, generation, storage, review, reporting, and package delivery.
- Resource and question versions remain traceable when a source is replaced or withdrawn. Stop future use as required; retain controlled historical evidence for issued questions under the configured retention policy.
- Apply per-owner quotas and cost limits, provider timeouts, bounded retries, idempotency, and operational alerts. Send providers only the necessary passages and anonymized learning targets, not candidate identity.
- Secure external-provider credentials on the server. Candidate bundles and offline packages must not contain them.
- Preserve traditional navigation, fixed papers, scoring, result release, and candidate assignment.
- Test generated-item key hiding, cross-owner retrieval, malicious document instructions, unsupported sources, repeated question variants, exact mark-ledger reconciliation, recovery deadlines, and offline replay.
- Record generation and review events alongside existing exam audits. Keep raw provider payloads restricted and redact logs.
- Allow generation to be disabled without disabling existing approved question banks or changing already-issued questions.

## Recommended delivery order

Implement Phases 1–4 as the first resource-assisted release: upload, extract, generate, review, and prepare questions before the exam.

Then implement Phases 5–6 for weakness-focused generation between online levels. Complete Phase 7 before claiming disconnected generation support; its initial approach is pre-generation and local selection.

Use Phase 8 pilot evidence to decide how much generation can safely become automatic. The user-facing goal remains simple: upload resources, choose learning settings, and let the system prepare the appropriate questions.
