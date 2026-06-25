# AlignEx Project Rules

AlignEx is an online CBT examination platform for secondary school examinations, professional certification examinations, recruitment examinations, traditional CBT, future adaptive assessment, online examination, future offline center-based examination, real-time supervisor monitoring, anti-cheating controls, result management, and reports.

## Core Stack

- Backend: Laravel
- Public and admin UI: Inertia React pages served by Laravel web routes
- Frontend: React + TypeScript
- Candidate exam UI: React Router only inside `/exam/*`
- Styling: Tailwind CSS
- UI components: shadcn/ui conventions with Radix UI primitives
- Icons: lucide-react
- Charts: Recharts
- Database: MySQL
- Real-time: Laravel Reverb + Redis
- Candidate/API HTTP client: Axios or `fetch`, depending on local fit
- Future offline center app: Electron + SQLite
- Future adaptive engine: Python FastAPI

## Routing Rules

- Public website and admin portal must use Laravel routes in `routes/web.php` and Inertia pages.
- Admin CRUD pages must not use React Router.
- Candidate exam pages must be the only React Router island.
- Laravel must serve the candidate exam app with:

```php
Route::get('/exam/{any?}', function () {
    return Inertia::render('CandidateExam/App');
})->where('any', '.*')->name('candidate.exam');
```

- Inside `resources/js/Pages/CandidateExam/App.tsx`, React Router may handle:
  - `/exam/login`
  - `/exam/instructions`
  - `/exam/write`
  - `/exam/submitted`
  - `/exam/error`
  - `/exam/disqualified`
- Candidate exam actions must use Laravel API routes:
  - `POST /api/candidate/login`
  - `GET /api/candidate/exam`
  - `POST /api/candidate/answer`
  - `POST /api/candidate/submit`
  - `POST /api/candidate/auto-submit`
  - `POST /api/candidate/event`

## Security Rules

- Never expose correct answers, answer keys, scoring rubrics, or correctness flags to the candidate frontend.
- Candidate APIs must return only the fields required to write the exam.
- Use policies for authorization.
- Use form requests for validation.
- Use service classes for business logic.
- Use API resources for JSON response shaping.
- Keep controllers thin.
- Use database transactions for exam creation, paper generation, and exam submission.
- Log all exam-sensitive actions, including login, answer save, submission, auto-submit, tab blur, focus loss, fullscreen exit, copy/paste attempts, network reconnects, and supervisor interventions.
- Candidate exam APIs must be fast, rate-aware, and secure.
- Do not trust client timers, candidate status, submitted flags, or score calculations.
- Server state is authoritative for exam status, timing, submission, disqualification, and result release.

## Theme Colors

- Primary Green: `#0F7A3A`
- Dark Green: `#064E3B`
- Accent Orange: `#F59E0B`
- Brown Accent: `#7C4A21`
- Slate Dark: `#0F172A`
- Light Background: `#F8FAFC`
- Border: `#E2E8F0`
- Success: `#16A34A`
- Danger: `#DC2626`
- Info: `#2563EB`
- Warning: `#F59E0B`

## Roles

- Super Admin: manages platform configuration, global settings, organizations, and audit access.
- Organization Admin: manages organization users, centers, subjects, question banks, exams, candidates, results, and reports.
- Exam Manager: creates exams, configures papers, assigns candidates, schedules exam windows, and controls result release.
- Question Author: creates and maintains subjects, topics, question banks, questions, options, and imports.
- Reviewer/Moderator: reviews question quality, approves questions, and manages question readiness.
- Supervisor/Proctor: monitors live exam sessions, reviews anti-cheating events, intervenes, and disqualifies when policy allows.
- Candidate: logs in to assigned exam sessions, reads instructions, answers questions, submits exams, and views released results where permitted.
- Support/Auditor: reviews logs, incidents, and operational records without changing exam content.

## Module Order

Build in safe, ordered modules:

1. Inertia React stack setup.
2. Project rules and technical documentation.
3. Authentication, roles, permissions, policies, and middleware.
4. Organization and center management.
5. User management and invitations.
6. Subjects and topics.
7. Question banks, questions, options, imports, review workflow, and moderation.
8. Exam creation, configuration, scheduling, and paper generation.
9. Candidate registration, assignment, access codes, and eligibility.
10. Candidate exam API and `/exam/*` React Router interface.
11. Answer saving, timer authority, submit, auto-submit, and recovery.
12. Real-time supervisor monitoring with Reverb and Redis.
13. Anti-cheating controls and incident workflow.
14. Scoring, result management, review, release, and reports.
15. Audit logs, exports, operational reporting, and dashboards.
16. Offline center-based examination architecture with Electron and SQLite.
17. Adaptive assessment integration architecture with Python FastAPI.

## Implementation Standards

- Every feature module should include backend, route, controller/service, validation, policy, Inertia UI, loading state, error state, empty state, toast notifications, and tests where applicable.
- Use Laravel web routes and Inertia props for initial public/admin page data.
- Use Laravel API routes only for async operations, imports, exports, live monitoring, answer saving, proctoring events, and candidate exam actions.
- Prefer explicit database constraints, indexes, enums or constrained strings, timestamps, and audit fields.
- Tests should cover authorization, validation, sensitive data hiding, routing boundaries, and critical workflows.
