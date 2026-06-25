# Database Schema

This document describes the planned AlignEx entities. Tables should be introduced module by module, not all at once.

## Identity and Access

- `users`: admin, staff, supervisor, author, reviewer, and support accounts.
- `roles`: role names such as Super Admin, Organization Admin, Exam Manager, Question Author, Reviewer, Supervisor, Support.
- `permissions`: granular actions such as `exams.create`, `questions.review`, `results.release`.
- `role_user` or package-managed equivalents: assigns roles to users.
- `organizations`: tenant or institution records.
- `centers`: physical or virtual exam centers tied to organizations.

## Academic and Question Content

- `subjects`: subject areas within an organization.
- `topics`: topic taxonomy under subjects.
- `question_banks`: grouped pools of questions.
- `questions`: question stem, type, difficulty, marks, moderation status, and metadata.
- `question_options`: options for objective questions. Correctness fields must never be serialized to candidate APIs.
- `question_media`: optional images, audio, attachments, or passages.
- `question_reviews`: moderation decisions, reviewer notes, and approval history.
- `question_imports`: import jobs, source files, validation results, and row-level errors.

## Exams and Papers

- `exams`: title, category, delivery mode, duration, schedule, status, settings, and organization.
- `exam_sections`: optional sections, subjects, instructions, marks, and timing rules.
- `exam_rules`: security, randomization, display, navigation, and submission settings.
- `exam_paper_templates`: blueprint for question selection.
- `exam_papers`: generated candidate or cohort paper records.
- `exam_paper_questions`: ordered selected questions for a generated paper.

## Candidates and Sessions

- `candidates`: candidate identity and organization ownership.
- `exam_candidates`: candidate assignment, eligibility, access code hash, status, and center.
- `exam_sessions`: candidate runtime session, start time, end time, status, device metadata, and submission timestamps.
- `candidate_answers`: saved answer payloads per question.
- `candidate_answer_versions`: optional autosave history for recovery and audit.
- `exam_events`: proctoring and exam-sensitive event stream.

## Results and Reports

- `results`: final score, grade, status, release state, and review state.
- `result_items`: per-question scoring detail for authorized admin views only.
- `result_reviews`: manual review and correction workflow.
- `result_releases`: release batches and release metadata.
- `report_exports`: generated reports, filters, file paths, and actor metadata.

## Offline Future Entities

- `offline_centers`: center device authorization and sync settings.
- `offline_devices`: registered machines, keys, and trust status.
- `offline_packages`: encrypted exam packages, expiry, and checksum.
- `offline_sync_batches`: upload/download sync records.
- `offline_conflicts`: conflict records requiring review.

## Adaptive Future Entities

- `adaptive_profiles`: candidate ability estimates and engine metadata.
- `adaptive_sessions`: adaptive runtime state and external engine correlation IDs.
- `adaptive_item_events`: item selection, response, and ability update events.

## Data Rules

- Use foreign keys where lifecycle ownership is clear.
- Use indexes for lookup fields such as organization, exam, candidate, session, status, and schedule fields.
- Use transactions for exam creation, paper generation, answer submission, scoring, and result release.
- Store access codes securely; prefer hashes for reusable or sensitive codes.
- Keep answer keys and scoring details out of candidate-facing resources.
