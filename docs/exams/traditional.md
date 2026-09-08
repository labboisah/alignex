# Traditional CBT knowledge base

Reviewed: 8 September 2026. Status: implemented shared delivery workflow, with known regression and security gaps. This document describes code present in the repository; it is not a claim that every route or deployment has passed acceptance testing.

## Purpose and boundaries

Traditional CBT gives each candidate a fixed paper generated before writing begins. Papers can differ between candidates through random selection and question/option shuffling, but answers do not change the remaining question selection. Exam category describes the purpose; exam mode describes delivery; delivery mode describes online/offline/hybrid configuration. These are separate fields.

Laravel owns exam state, authorization, timing, persistence and scoring. Public/admin pages use Laravel routes and Inertia React. Only the candidate app under `/exam/*` uses React Router. MySQL is the intended operational database.

## Where traditional exams are allowed

| Owner | Categories | Participant setup |
| --- | --- | --- |
| [Institution](../entities/institution.md) | Assessment | Course paper rows and candidate groups |
| [Organization](../entities/organization.md) | Recruitment, assessment, certification, professional, practice, general | Direct candidates and/or reusable candidate groups |
| [Professional school](<../entities/professional school.md>) | Professional, certification, practice, assessment | Programme and training batch |
| [Secondary school](<../entities/secondary school.md>) | Terminal, assessment | Academic session, term, student group and subject rows |
| [CBT center](<../entities/cbt center.md>) | Recruitment, assessment, certification, professional, practice, general | Candidate groups; direct candidate selection is rejected by the current exam request |

The authoritative category/mode matrix is [ExamOwnershipRules](../../app/Support/ExamOwnershipRules.php). Context-specific request requirements are in [StoreExamRequest](../../app/Http/Requests/StoreExamRequest.php).

## Implemented administration workflow

1. Select an available operating context. Context selection affects terminology, dashboard, menus and setup options; it does not replace server authorization.
2. Prepare learners, their groups/batches, subjects or courses, question banks, and questions.
3. Create the exam through the Inertia wizard. Set its owner, category, traditional mode, title/code, schedule, duration, pass mark, status and paper rows.
4. Configure paper rows: subject or institution course, one or more banks, question count, marks setting, optional duration and difficulty distribution. Selection rules can contain topic IDs and bank lists.
5. Assign participants. The assignment service synchronizes candidates/students with exam participant records; the refresh action updates group-derived assignments.
6. Preview availability and generate candidate-specific papers. Resolve insufficient-question warnings before proceeding.
7. Operate the exam, supervise writing, review results and use available reports or certificate tools.

The configuration exposes question/option shuffling, immediate-result preference, back navigation, fullscreen, webcam, screenshots, maximum tab switches, negative marking, device binding and retake settings. A saved setting alone does not prove that every possible combination has complete enforcement.

## Data model

| Record | Responsibility |
| --- | --- |
| `Exam` | Owner/category/modes, schedule, settings, pass mark and status |
| `ExamSubject` | Paper row, count, bank reference, distribution and selection rules; institutions use course-linked setup even though the table retains this name |
| `ExamParticipant` and exam candidate association | Assigned candidate/student identity and membership |
| `CandidateExamAttempt` | Candidate's attempt, status, device information, server deadline, totals and result |
| `CandidatePaper` | Selected question, stable question order and option order |
| `CandidateAnswer` | Selected options, saved answer, flag, timestamps and awarded marks |
| `ExamAuditLog`, `ProctoringEvent` | Activity, incidents and monitoring evidence |
| `CandidatePerformanceProfile` | Descriptive topic/difficulty performance after scoring |

Owner identity uses `exam_owner_type`, `exam_owner_id` and context foreign keys. `Exam::effectiveMode()` prefers `exam_mode` over legacy `mode`. Do not assume the legacy and newer fields can be changed independently.

## Paper generation behavior

[ExamPaperGeneratorService](../../app/Services/ExamPaperGeneratorService.php) synchronizes participants, previews bank availability, and generates papers within a transaction. It stores selected question IDs and option ordering, updates attempt question/mark totals, skips attempts that already have papers, and blocks generation once the schedule or an attempt has started. Notification calls occur after the generation transaction.

Selection uses row-level banks (falling back to an exam bank), subject filtering, optional topics, and optional difficulty distribution. It currently admits draft, review and approved questions. Topic filtering is intentionally bypassed for secondary owners in the current query. Paper marks are summed from question records; do not assume the wizard's marks-per-question value overwrites every question's marks.

## Candidate journey and APIs

The app has login, instructions, write, submitted, error and disqualified screens. The server's login implementation can start an eligible attempt when the scheduled time has arrived; the instructions screen is not itself the only authority for when time begins.

| API | Current responsibility |
| --- | --- |
| `POST /api/candidate/login` | Locate active exam and assigned candidate, require generated paper, reject closed attempts, apply eligible device/professional checks, return token and payload |
| `POST /api/candidate/start` | Enforce scheduled start and initialize an unstarted attempt |
| `GET /api/candidate/exam` | Return saved paper/answers and authoritative time; finalize an expired in-progress attempt |
| `POST /api/candidate/answer` | Check writable status/deadline, require a paper question, filter options to that question, save and update progress |
| `POST /api/candidate/submit` | Finalize and score; use auto-submitted status when overdue |
| `POST /api/candidate/auto-submit` | Finalize through the automatic-submission action |
| `POST /api/candidate/event` | Log candidate events and applicable proctoring incidents |
| `POST /api/candidate/result` | Look up a completed candidate result subject to the result-availability check |

The encrypted attempt token is accepted through the bearer header or exam-token input. Server due time is based on the attempt start plus exam duration, capped by the exam end. The UI stores a local payload/token and tracks failed saves for retries. Local storage and the displayed countdown are not scoring or timing authorities.

The current payload returns the whole paper, saved answers, candidate details and selected safe settings. [CandidatePaperResource](../../app/Http/Resources/CandidatePaperResource.php) excludes correct answers, correctness flags, explanations and scoring metadata.

## Monitoring and anti-cheating

[ExamMonitorService](../../app/Services/ExamMonitorService.php) supplies summaries, attempt rows, audit feeds, event lists and broadcast events. The monitoring controller exposes monitor views, incident reports, exam ending and attempt reset actions. Broadcast integration exists; its reliability depends on deployment configuration.

The candidate flow logs login, saves, submission and reported browser events. Tab/window-blur incidents can cause server-side disqualification after the configured threshold. Fullscreen, webcam and browser-event collection provide evidence and controls, not a guarantee that cheating is impossible.

## Scoring and results

[ExamResultService](../../app/Services/ExamResultService.php) scores supported single-choice, multiple-choice and true/false answers on the server. Selected IDs must match all correct option IDs for full marks. Incorrect answers score zero unless negative marking applies. Unsupported question types return zero in this scorer.

The service sums awarded marks, computes percentage and grade, compares raw score with the exam's raw pass mark, records duration/incidents and generates a result hash. Certification eligibility and optional certificate generation are integrated. Submission uses an attempt lock and transaction.

[ResultController](../../app/Http/Controllers/ResultController.php) provides result lists, exam summaries, candidate details, marked-paper PDF, CSV/PDF summaries, verification and candidate lookup. Exports and certificate actions can be gated by plan features. Descriptive topic/difficulty analytics also exist; they do not make an exam adaptive.

## Known gaps and verification

The [8 September audit](../adaptive-examination-audit-2026-09-08.md) ran 69 selected tests: 47 passed, 19 failed and 3 errored. Paper generation, entity creation, permissions and route/navigation expectations need triage. No tests were rerun for this documentation-only change.

Shared findings to retain in development planning:

- Submission responses currently include scores without checking the result-release preference at that boundary.
- Early login can return paper content before the scheduled start.
- Answer saving checks attempt status before the transaction without the submission-style attempt lock, leaving a possible concurrent save/submission race.
- Progress percentage is stored in the same attempt field later used for result percentage.
- Draft/review questions are eligible for fixed papers; changing this policy needs deliberate regression review.
- Offline package export code exists, but online/hybrid/offline settings do not establish complete local-runtime or synchronization acceptance.

Preserve fixed papers, answer revision, navigation, scoring, option order, historical results and candidate contracts when extending [adaptive delivery](adaptive.md).

## Implementation and test map

- [ExamController](../../app/Http/Controllers/ExamController.php), [ExamPolicy](../../app/Policies/ExamPolicy.php), [exam wizard](../../resources/js/Pages/Exams/Wizard.tsx).
- [Assignment service](../../app/Services/ExamParticipantAssignmentService.php), [candidate API controller](../../app/Http/Controllers/Api/CandidateExamController.php), [session service](../../app/Services/CandidateExamSessionService.php), [candidate UI](../../resources/js/Pages/CandidateExam/App.tsx).
- [Web routes](../../routes/web.php), [API routes](../../routes/api.php), [offline package controller](../../app/Http/Controllers/Api/OfflineExamPackageController.php).
- Tests: [candidate API](../../tests/Feature/CandidateExamApiTest.php), [shared workflow](../../tests/Feature/SharedExamWorkflowTest.php), [paper generation](../../tests/Feature/ExamPaperGenerationTest.php), [results](../../tests/Feature/ResultManagementTest.php), [monitoring](../../tests/Feature/ExamMonitorTest.php).

Update this document when delivery contracts, generation rules, scoring, result release or owner eligibility change. Describe observed implementation separately from planned safeguards.
