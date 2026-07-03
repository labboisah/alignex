# Database Schema

This document defines the planned normalized database schema for AlignEx. It is a design document for future Laravel migrations; do not treat it as an instruction to create every table in one migration batch. Tables should be introduced module by module with form requests, policies, resources, services, and tests.

Laravel and MySQL remain the source of truth for identity, exam configuration, candidate eligibility, timing, answer state, scoring inputs, proctoring evidence, and audit history. The candidate frontend must never receive answer keys, correctness flags, scoring rubrics, or internal proctoring risk calculations.

## Corrected Active Entity Model

The active implementation separates:

- `organizations`
- `secondary_schools`
- `professional_schools`
- `cbt_centers`

Legacy `schools` and `centers` records may still exist for older modules, but new corrected workflows should use the explicit secondary/professional/CBT center tables. `organizations` must not contain a `school_type` column.

`users` may hold context references:

- `organization_id`
- `secondary_school_id`
- `professional_school_id`
- `cbt_center_id`
- `active_context_type`
- `active_context_id`

`exams` store explicit ownership:

- `exam_owner_type`
- `exam_owner_id`
- `organization_id`
- `secondary_school_id`
- `professional_school_id`
- `cbt_center_id`
- context-specific academic or training fields

Question banks and candidates also carry corrected ownership fields so exam creation and paper generation can be scoped to the selected context.

## Design Rules

- Use `id` big integer primary keys unless a table has a clear composite uniqueness requirement.
- Use foreign keys where lifecycle ownership is clear.
- Add `created_at` and `updated_at` to mutable business tables.
- Add constrained string statuses or enums when supported by the migration convention.
- Use soft deletes only when restore/audit requirements justify them; prefer explicit `status` for operational records.
- Store sensitive tokens, access codes, and secrets as hashes.
- Use JSON columns only for flexible settings, device metadata, event metadata, and answer payloads where normalization would make the write path slower without improving query needs.
- Keep server-side timing, scoring, submission, and result release authoritative.
- Prepare offline-compatible tables with immutable IDs, sync timestamps, and append-only logs where possible.

## users

**Purpose:** Stores platform users for admin portal access, entity administrators, exam staff, supervisors, support users, and candidates when candidates need account-style identity.

**Important columns:**
- `id`
- `name`
- `email`
- `email_verified_at`
- `password`
- `role`
- `organization_id` nullable
- `center_id` nullable
- `school_id` nullable
- `status` default `active`
- `last_login_at` nullable
- `remember_token`
- timestamps

**Relationships:**
- Belongs to `organizations` through `organization_id` when the user is an organization admin or organization-scoped staff.
- Belongs to `centers` through `center_id` when the user is a center admin or center-scoped staff.
- Belongs to `schools` through `school_id` when the user is a school admin or school-scoped staff.
- Has many `exam_audit_logs` as actor.
- Has many `proctoring_events` as reviewer or supervisor when applicable.

**Indexes needed for performance:**
- Unique index on `email`.
- Index on `role`.
- Index on `status`.
- Indexes on `organization_id`, `center_id`, and `school_id`.
- Composite index on `role, status`.

**Security-sensitive fields:**
- `password`
- `remember_token`
- `email_verified_at`
- Any future MFA secrets, recovery codes, session tokens, or invitation tokens.

**Online, offline, or both:** Both. Offline packages may include a minimal signed supervisor or center user reference, but password authentication remains server-side unless a future offline credential flow is explicitly designed.

## organizations

**Purpose:** Stores NGOs, associations, companies, government bodies, recruitment teams, and other groups that want to conduct exams on the platform.

**Important columns:**
- `id`
- `name`
- `code`
- `contact_person`
- `email`
- `phone`
- `address`
- `status`
- timestamps

**Relationships:**
- Has many `users`.
- Has many `exams`.
- Has many `subjects`.
- Has many `question_banks`.
- Has many `candidates`.
- May have many `exam_audit_logs` through related exams and actors.

**Indexes needed for performance:**
- Unique index on `code`.
- Unique or regular index on `email` depending on business rules.
- Index on `status`.
- Index on `name` for admin search.

**Security-sensitive fields:**
- Contact details are business-sensitive.
- Accreditation or approval data should be visible only to authorized platform administrators.

**Online, offline, or both:** Both. Organization ownership must be present in offline exam packages to preserve tenant boundaries during sync.

## centers

**Purpose:** Stores CBT centers that partner with the platform to deliver exams using their facilities, devices, networks, and invigilators. Centers are independent delivery partners and are not owned by organizations.

**Important columns:**
- `id`
- `name`
- `code`
- `location`
- `capacity`
- `contact_person`
- `phone`
- `email`
- `status`
- timestamps

**Relationships:**
- Has many `users`.
- Has many `exam_sessions`.
- Has many `candidate_exam_attempts`.
- May be assigned to exams through future allocation tables.
- May own offline device records in a future offline module.

**Indexes needed for performance:**
- Unique index on `code`.
- Index on `status`.
- Index on `location`.
- Index on `capacity` if center matching by size becomes common.

**Security-sensitive fields:**
- Contact details, facility capacity, device trust metadata, and offline sync credentials.

**Online, offline, or both:** Both. Centers are central to the future offline delivery model.

## schools

**Purpose:** Stores secondary schools, professional schools, bootcamps, online course providers, and certification schools that manage their own examinations.

**Important columns:**
- `id`
- `name`
- `code`
- `location`
- `capacity`
- `contact_person`
- `phone`
- `email`
- `status`
- timestamps

**Relationships:**
- Has many `users`.
- Has many `exams`.
- Has many `subjects`.
- Has many `question_banks`.
- Has many `candidates`.

**Indexes needed for performance:**
- Unique index on `code`.
- Unique or regular index on `email` depending on business rules.
- Index on `status`.
- Index on `location`.

**Security-sensitive fields:**
- Contact details, candidate population data, and accreditation details when stored in related approval tables.

**Online, offline, or both:** Both. School exams may be delivered online or in center/offline workflows.

## exam_types

**Purpose:** Normalizes exam categories such as secondary school exam, professional certification, recruitment, mock test, entrance exam, or internal assessment.

**Important columns:**
- `id`
- `name`
- `code`
- `description`
- `status`
- timestamps

**Relationships:**
- Has many `exams`.

**Indexes needed for performance:**
- Unique index on `code`.
- Index on `status`.
- Index on `name`.

**Security-sensitive fields:**
- Usually none, but disabled/internal exam types should not be exposed in public candidate flows.

**Online, offline, or both:** Both.

## exams

**Purpose:** Stores exam configuration, ownership, schedule, duration, delivery mode, security settings, result release settings, and operational status.

**Important columns:**
- `id`
- `organization_id` nullable
- `school_id` nullable
- `exam_type_id`
- `title`
- `code`
- `description`
- `delivery_mode` such as `online`, `center_based`, `offline`
- `duration_minutes`
- `starts_at`
- `ends_at`
- `timezone`
- `status`
- `security_settings` JSON
- `navigation_settings` JSON
- `result_release_settings` JSON
- `created_by`
- timestamps

**Relationships:**
- Belongs to `organizations` for organization-owned exams.
- Belongs to `schools` for school-owned exams.
- Belongs to `exam_types`.
- Belongs to `users` through `created_by`.
- Has many `exam_subjects`.
- Has many `exam_sessions`.
- Has many `candidate_exam_attempts`.
- Has many `exam_audit_logs`.
- Has many `proctoring_events` through attempts or sessions.

**Indexes needed for performance:**
- Unique index on `code`.
- Indexes on `organization_id`, `school_id`, and `exam_type_id`.
- Index on `status`.
- Indexes on `starts_at` and `ends_at`.
- Composite indexes on `organization_id, status`, `school_id, status`, and `status, starts_at`.

**Security-sensitive fields:**
- `security_settings`
- `navigation_settings`
- `result_release_settings`
- Any future paper generation seed, package secret, or proctoring thresholds.

**Online, offline, or both:** Both. Offline delivery requires signed exam packages generated from this configuration.

## subjects

**Purpose:** Stores subject areas such as Mathematics, English, Biology, Cybersecurity, or Aptitude.

**Important columns:**
- `id`
- `organization_id` nullable
- `school_id` nullable
- `name`
- `code`
- `description`
- `status`
- timestamps

**Relationships:**
- Belongs to `organizations` or `schools` depending on ownership.
- Has many `topics`.
- Has many `question_banks`.
- Has many `exam_subjects`.

**Indexes needed for performance:**
- Composite unique index on `organization_id, code` where organization-owned.
- Composite unique index on `school_id, code` where school-owned.
- Index on `status`.
- Index on `name`.

**Security-sensitive fields:**
- Usually none, but draft/internal subject structures should remain admin-only.

**Online, offline, or both:** Both.

## topics

**Purpose:** Stores topic taxonomy under subjects for question classification, paper generation, reporting, and adaptive readiness.

**Important columns:**
- `id`
- `subject_id`
- `parent_id` nullable
- `name`
- `code`
- `description`
- `status`
- timestamps

**Relationships:**
- Belongs to `subjects`.
- May belong to another `topics` row through `parent_id`.
- Has many child `topics`.
- Has many `questions`.

**Indexes needed for performance:**
- Composite unique index on `subject_id, code`.
- Index on `subject_id`.
- Index on `parent_id`.
- Index on `status`.

**Security-sensitive fields:**
- Usually none.

**Online, offline, or both:** Both.

## question_banks

**Purpose:** Groups questions into managed pools for authoring, moderation, selection, import, and reuse.

**Important columns:**
- `id`
- `organization_id` nullable
- `school_id` nullable
- `subject_id`
- `name`
- `code`
- `description`
- `status`
- `created_by`
- timestamps

**Relationships:**
- Belongs to `organizations` or `schools`.
- Belongs to `subjects`.
- Belongs to `users` through `created_by`.
- Has many `questions`.

**Indexes needed for performance:**
- Composite unique index on `organization_id, code`.
- Composite unique index on `school_id, code`.
- Index on `subject_id`.
- Index on `status`.
- Index on `created_by`.

**Security-sensitive fields:**
- Draft question pools, moderation state, and internal bank composition are exam-sensitive.

**Online, offline, or both:** Both. Offline packages receive only selected and approved questions, not full banks unless explicitly authorized.

## questions

**Purpose:** Stores authored question items, stem content, type, difficulty, mark value, moderation status, and scoring metadata.

**Important columns:**
- `id`
- `question_bank_id`
- `subject_id`
- `topic_id` nullable
- `created_by`
- `reviewed_by` nullable
- `question_type` such as `single_choice`, `multiple_choice`, `true_false`, `essay`
- `stem`
- `explanation` nullable
- `difficulty`
- `marks`
- `negative_marks` nullable
- `status`
- `scoring_metadata` JSON nullable
- `reviewed_at` nullable
- timestamps

**Relationships:**
- Belongs to `question_banks`.
- Belongs to `subjects`.
- Belongs to `topics`.
- Belongs to `users` through `created_by` and `reviewed_by`.
- Has many `question_options`.
- Has many `candidate_answers`.

**Indexes needed for performance:**
- Indexes on `question_bank_id`, `subject_id`, and `topic_id`.
- Index on `question_type`.
- Index on `difficulty`.
- Index on `status`.
- Composite index on `question_bank_id, status`.
- Composite index on `subject_id, topic_id, status`.

**Security-sensitive fields:**
- `explanation`
- `scoring_metadata`
- Any rubric, correct-answer hints, moderation notes, or psychometric metadata.
- Question content is sensitive before and during active exams.

**Online, offline, or both:** Both. Offline packages must encrypt question payloads and include only items selected for the exam.

## question_options

**Purpose:** Stores objective answer options for questions.

**Important columns:**
- `id`
- `question_id`
- `label`
- `option_text`
- `display_order`
- `is_correct`
- `score_weight` nullable
- timestamps

**Relationships:**
- Belongs to `questions`.
- May be referenced by `candidate_answers` through selected option IDs stored in JSON or a future answer-option pivot.

**Indexes needed for performance:**
- Index on `question_id`.
- Composite unique index on `question_id, label`.
- Composite index on `question_id, display_order`.

**Security-sensitive fields:**
- `is_correct`
- `score_weight`
- Any correctness explanation or option-level scoring metadata.

**Online, offline, or both:** Both. Candidate APIs must omit `is_correct` and scoring fields.

## candidates

**Purpose:** Stores candidate identity, exam ownership scope, eligibility metadata, and contact details.

**Important columns:**
- `id`
- `organization_id` nullable
- `school_id` nullable
- `user_id` nullable
- `candidate_number`
- `first_name`
- `last_name`
- `email` nullable
- `phone` nullable
- `date_of_birth` nullable
- `metadata` JSON nullable
- `status`
- timestamps

**Relationships:**
- Belongs to `organizations` or `schools`.
- Optionally belongs to `users`.
- Has many `candidate_exam_attempts`.
- Has many `candidate_answers` through attempts.

**Indexes needed for performance:**
- Composite unique index on `organization_id, candidate_number`.
- Composite unique index on `school_id, candidate_number`.
- Index on `user_id`.
- Index on `email`.
- Index on `status`.
- Indexes on `organization_id` and `school_id`.

**Security-sensitive fields:**
- Personally identifiable information: name, email, phone, date of birth, metadata.
- Candidate numbers and future identity verification data.

**Online, offline, or both:** Both. Offline packages should include minimal candidate data required for check-in and exam delivery.

## exam_sessions

**Purpose:** Stores scheduled or live exam delivery sessions. A session may represent an exam window, center delivery slot, or supervised runtime grouping.

**Important columns:**
- `id`
- `exam_id`
- `center_id` nullable
- `name`
- `starts_at`
- `ends_at`
- `capacity` nullable
- `status`
- `settings` JSON nullable
- timestamps

**Relationships:**
- Belongs to `exams`.
- Belongs to `centers` when center-based.
- Has many `candidate_exam_attempts`.
- Has many `exam_audit_logs`.
- Has many `proctoring_events`.

**Indexes needed for performance:**
- Index on `exam_id`.
- Index on `center_id`.
- Index on `status`.
- Indexes on `starts_at` and `ends_at`.
- Composite index on `exam_id, status`.
- Composite index on `center_id, starts_at`.

**Security-sensitive fields:**
- Session settings, center assignment, and capacity planning.
- Any future offline package identifiers or session unlock metadata.

**Online, offline, or both:** Both.

## exam_subjects

**Purpose:** Defines the subjects included in an exam and their timing, marks, ordering, and question selection rules.

**Important columns:**
- `id`
- `exam_id`
- `subject_id`
- `display_order`
- `duration_minutes` nullable
- `total_marks`
- `question_count`
- `selection_rules` JSON nullable
- `instructions` text nullable
- timestamps

**Relationships:**
- Belongs to `exams`.
- Belongs to `subjects`.
- Has many `candidate_answers` through exam, subject, and question relationships.

**Indexes needed for performance:**
- Composite unique index on `exam_id, subject_id`.
- Composite index on `exam_id, display_order`.
- Index on `subject_id`.

**Security-sensitive fields:**
- `selection_rules`
- Internal instructions or paper generation constraints.

**Online, offline, or both:** Both.

## candidate_exam_attempts

**Purpose:** Stores a candidate's assigned and runtime attempt for an exam. This is the authoritative record for login, start, timing, submission, disqualification, scoring, and result release.

**Important columns:**
- `id`
- `candidate_id`
- `exam_id`
- `exam_session_id` nullable
- `center_id` nullable
- `access_code_hash`
- `attempt_number`
- `status`
- `started_at` nullable
- `server_due_at` nullable
- `submitted_at` nullable
- `auto_submitted_at` nullable
- `disqualified_at` nullable
- `disqualification_reason` nullable
- `score` nullable
- `result_status` nullable
- `device_fingerprint_hash` nullable
- `ip_address` nullable
- `user_agent` nullable
- timestamps

**Relationships:**
- Belongs to `candidates`.
- Belongs to `exams`.
- Belongs to `exam_sessions`.
- Belongs to `centers`.
- Has many `candidate_answers`.
- Has many `exam_audit_logs`.
- Has many `proctoring_events`.

**Indexes needed for performance:**
- Composite unique index on `candidate_id, exam_id, attempt_number`.
- Index on `exam_id`.
- Index on `exam_session_id`.
- Index on `center_id`.
- Index on `status`.
- Index on `server_due_at`.
- Index on `submitted_at`.
- Composite index on `exam_id, status`.
- Composite index on `exam_session_id, status`.

**Security-sensitive fields:**
- `access_code_hash`
- `device_fingerprint_hash`
- `ip_address`
- `user_agent`
- Score and disqualification data.
- Any future identity verification evidence.

**Online, offline, or both:** Both. Offline attempts must sync with replay protection and conflict detection.

## candidate_answers

**Purpose:** Stores server-authoritative candidate responses per question with autosave and submission state.

**Important columns:**
- `id`
- `candidate_exam_attempt_id`
- `question_id`
- `subject_id`
- `answer_payload` JSON nullable
- `selected_option_ids` JSON nullable
- `answer_text` long text nullable
- `is_flagged`
- `saved_at`
- `submitted_at` nullable
- `score_awarded` nullable
- `scored_at` nullable
- `scored_by` nullable
- timestamps

**Relationships:**
- Belongs to `candidate_exam_attempts`.
- Belongs to `questions`.
- Belongs to `subjects`.
- Belongs to `users` through `scored_by` when manual review is performed.

**Indexes needed for performance:**
- Composite unique index on `candidate_exam_attempt_id, question_id`.
- Index on `question_id`.
- Index on `subject_id`.
- Index on `saved_at`.
- Index on `submitted_at`.
- Index on `scored_by`.

**Security-sensitive fields:**
- `answer_payload`
- `selected_option_ids`
- `answer_text`
- `score_awarded`
- Manual scoring metadata.

**Online, offline, or both:** Both. Offline answers must be signed or checksummed during sync.

## exam_audit_logs

**Purpose:** Append-only audit trail for exam-sensitive actions and administrative decisions.

**Important columns:**
- `id`
- `exam_id` nullable
- `exam_session_id` nullable
- `candidate_exam_attempt_id` nullable
- `actor_user_id` nullable
- `actor_type`
- `event_type`
- `description`
- `metadata` JSON nullable
- `ip_address` nullable
- `user_agent` nullable
- `occurred_at`
- timestamps

**Relationships:**
- Belongs to `exams`.
- Belongs to `exam_sessions`.
- Belongs to `candidate_exam_attempts`.
- Belongs to `users` through `actor_user_id`.

**Indexes needed for performance:**
- Index on `exam_id`.
- Index on `exam_session_id`.
- Index on `candidate_exam_attempt_id`.
- Index on `actor_user_id`.
- Index on `event_type`.
- Index on `occurred_at`.
- Composite index on `exam_id, occurred_at`.
- Composite index on `candidate_exam_attempt_id, occurred_at`.

**Security-sensitive fields:**
- `metadata`
- `ip_address`
- `user_agent`
- Any captured incident details, intervention notes, or integrity signals.

**Online, offline, or both:** Both. Offline audit logs should be append-only locally and synced without deletion.

## proctoring_events

**Purpose:** Stores proctoring and anti-cheating events generated by the candidate app, supervisor actions, server checks, or future offline client.

**Important columns:**
- `id`
- `exam_id`
- `exam_session_id` nullable
- `candidate_exam_attempt_id`
- `candidate_id`
- `center_id` nullable
- `event_type`
- `severity`
- `source`
- `payload` JSON nullable
- `occurred_at`
- `reviewed_by` nullable
- `reviewed_at` nullable
- `resolution_status` nullable
- `resolution_notes` nullable
- timestamps

**Relationships:**
- Belongs to `exams`.
- Belongs to `exam_sessions`.
- Belongs to `candidate_exam_attempts`.
- Belongs to `candidates`.
- Belongs to `centers`.
- Belongs to `users` through `reviewed_by`.

**Indexes needed for performance:**
- Index on `exam_id`.
- Index on `exam_session_id`.
- Index on `candidate_exam_attempt_id`.
- Index on `candidate_id`.
- Index on `center_id`.
- Index on `event_type`.
- Index on `severity`.
- Index on `occurred_at`.
- Composite index on `exam_session_id, occurred_at`.
- Composite index on `candidate_exam_attempt_id, occurred_at`.
- Composite index on `exam_id, severity, resolution_status`.

**Security-sensitive fields:**
- `payload`
- `resolution_notes`
- `reviewed_by`
- Any screenshots, webcam references, device information, focus-loss details, fullscreen exits, copy/paste attempts, and supervisor intervention metadata.

**Online, offline, or both:** Both. Offline proctoring events must include trusted local timestamps and sync metadata.

## Migration Order Recommendation

1. Identity and tenant foundations: `users`, `organizations`, `centers`, `schools`.
2. Exam taxonomy: `exam_types`, `subjects`, `topics`.
3. Question authoring: `question_banks`, `questions`, `question_options`.
4. Exam setup: `exams`, `exam_subjects`, `exam_sessions`.
5. Candidate workflow: `candidates`, `candidate_exam_attempts`, `candidate_answers`.
6. Integrity and monitoring: `exam_audit_logs`, `proctoring_events`.

## Candidate API Resource Rules

- Never serialize `question_options.is_correct`.
- Never serialize `question_options.score_weight`.
- Never serialize `questions.scoring_metadata`.
- Never serialize answer keys, explanations, or rubrics to `/exam/*`.
- Candidate answer APIs should return only save/submission status, server timestamps, and the next safe action.

## Offline Sync Notes

The tables marked `Both` should eventually support offline package generation and sync. Future migrations may add `uuid`, `sync_batch_id`, `synced_at`, `source_device_id`, or checksum columns where needed. Do not add those fields until the offline center module is being designed.
