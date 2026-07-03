# Online Platform Test Checklist

## Routing

- Public pages render from Laravel web routes.
- Admin pages render from Laravel web routes and Inertia.
- React Router appears only in the candidate exam app.
- Candidate API routes are in `routes/api.php`.

## Context Dashboards

- Organization dashboard shows organization metrics, charts, recent activity, and quick actions.
- Secondary school dashboard shows students, classes, arms, subjects, active session, active term, terminal exams, results, and report card placeholders.
- Professional school dashboard shows candidates/trainees, programmes, courses, modules, batches, professional/adaptive/certification exams, results, and certificates.
- CBT center dashboard shows candidates, question banks, exams, capacity, results, and center actions.

## Sidebar and Terminology

- Organization context shows organization modules.
- Secondary context shows academic modules and hides professional/CBT-only modules.
- Professional context shows programme/course/module/certificate modules and hides secondary modules.
- CBT context shows candidates/question bank/exam modules and hides school modules.
- Labels use `resources/js/lib/terminology.ts`.

## Exam Workflow

- Organization recruitment, assessment, certification, and adaptive exam creation works.
- Organization exams require candidates and question bank.
- Secondary terminal traditional exam creation works.
- Secondary adaptive and recruitment exam creation are rejected.
- Professional traditional, adaptive, and certification exams work.
- CBT traditional and adaptive exams work.
- Paper generation stores question order and option order.
- Candidate frontend does not receive correct answers.
- Server-side result calculation and certificate eligibility work.

## Candidate Exam

- `/exam/login` authenticates candidate.
- `/exam/instructions` loads after login.
- `/exam/write` loads candidate paper.
- Answer saving works.
- Timer and auto-submit use server state.
- Submit writes result server-side.
- Proctoring events are logged.
- Disqualification blocks further answer saves.

## Commands

```bash
php artisan test
npm run build
php artisan route:list
php artisan migrate:status
```
