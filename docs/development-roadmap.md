# Development Roadmap

## Module Order

1. Inertia React stack setup.
2. Project rules and technical documentation.
3. Authentication, roles, permissions, policies, and middleware.
4. Organization and center management.
5. User management and invitations.
6. Subjects and topics.
7. Question banks and question authoring.
8. Question import, moderation, and approval workflow.
9. Exam creation, settings, scheduling, and paper generation.
10. Candidate registration, assignment, and access codes.
11. Candidate exam API.
12. Candidate `/exam/*` React Router interface.
13. Answer saving, timer authority, submit, auto-submit, and recovery.
14. Real-time supervisor monitoring with Reverb and Redis.
15. Anti-cheating controls and incident management.
16. Scoring, result review, result release, and reports.
17. Audit logs, exports, dashboards, and operational reports.
18. Offline center-based examination with Electron + SQLite.
19. Adaptive assessment integration with Python FastAPI.

## Delivery Rules

Each module should include:

- Backend data model where applicable.
- Laravel web routes for public/admin Inertia pages.
- Laravel API routes for asynchronous workflows.
- Controller, service, form request, policy, resource, and tests where applicable.
- Inertia UI with loading, error, empty, and success states.
- Toast notifications for user-triggered actions.
- Authorization and audit logging for sensitive actions.

## Early Milestones

### Foundation

- Confirm Laravel, Inertia, React, TypeScript, Tailwind, shadcn/ui, Radix UI, lucide-react, Recharts, Axios, and React Router are installed.
- Keep the temporary dashboard until the real admin shell is introduced.
- Do not add business tables before the database design module.

### Access Control

- Define roles and permissions.
- Add middleware and policies.
- Restrict admin routes.
- Add tests for authorized and unauthorized access.

### Exam Operations

- Build organization, center, user, subject, topic, question bank, and exam modules in order.
- Keep exam creation transactional.
- Keep paper generation deterministic, auditable, and secure.

### Candidate Delivery

- Build candidate APIs before the React Router exam UI.
- Ensure APIs never expose correct answers.
- Add answer autosave and recovery tests before proctoring controls.

### Monitoring and Results

- Add Reverb/Redis for live supervisor updates.
- Store proctoring events as append-only audit records.
- Score and release results through controlled workflows.

## Future Milestones

- Offline center app packaging, encrypted sync, and replay protection.
- Adaptive engine API contract, item selection rules, and psychometric reporting.
- Advanced analytics, exports, and audit dashboards.


## Phase 1 progress

Adaptive Phase 1 now includes default-off pilot controls, exact owner allowlist configuration, draft-only containment, preservation of started legacy attempts, a read-only inventory command and a reconciled 92-test regression baseline. The local inventory contains no adaptive-labelled exams. See [implementation evidence and Phase 2 handoff](exams/adaptive-phase-1.md). This is the historical Phase 1 baseline; see Phase 3 status below for the implemented server lifecycle.

## Adaptive delivery and progressive remediation plan

Status: Phase 5 diagnostic reports, authorized exports and exact owner/exam pilot controls are implemented. Pilots remain off by default; no live cohort was enabled. FastAPI/calibration and validated assessment are next. See [Phase 5 evidence and Phase 6 handoff](exams/adaptive-phase-5.md), and the [adaptive knowledge base](exams/adaptive.md#agreed-extension-progressive-weakness-focused-levels) for behavior and scoring, and the [detailed implementation plan](adaptive-examination-audit-2026-09-08.md#progressive-remediation-implementation-plan-agreed-extension) for backend tasks and acceptance tests.

Adaptive work includes two independently controlled capabilities: response-dependent question selection within a level, and optional weakness-focused progression across levels.

1. Establish a passing traditional CBT regression baseline and owner/category policy for all five contexts.
2. Add validated per-exam percentage penalties, level/budget/access limits, mastery/evidence settings, frozen configuration and a progression/level/area mark ledger.
3. Implement full-blueprint Level 1, fresh questions from unresolved areas in subsequent levels, atomic level starts, once-only penalties, exact scoring and server-authoritative recovery/closure.
4. Add administrative configuration, candidate level navigation/resume and supervision states while keeping traditional candidate behavior unchanged.
5. Report first-level and cumulative recovered scores separately from mastery; pilot online practice/diagnostics with result-release controls and optional unscored remediation.
6. Integrate and validate the future FastAPI engine and consequential scoring policies.
7. Extend offline delivery only after progression-ledger synchronization, duplicate protection and existing fixed-paper compatibility pass.

The penalty is a configured percentage of remaining recoverable marks. For example, 60 remaining marks with a 10% penalty yields 54 available marks, not 50. Penalties do not remove already earned marks. Explicit maximum levels and minimum budgets are required because percentage deductions alone may never exhaust the balance.

Scored closure does not imply mastery. Practice after closure cannot increase the final score. Progressive settings default off, must not change active/historical attempts, and must not affect traditional terminal exams or ordinary CBT scoring.


## Phase 6 engineering foundation

Authenticated FastAPI shadow evaluation, frozen calibration import/review/revocation, replayable research history and a reference-case validation runner are implemented. Full Phase 6 acceptance awaits the promised calibration data and specialist criteria, representative validation, operational evidence and owner approval. No live external-engine dispatch or consequential scoring is enabled. See [Phase 6 engineering and acceptance work](exams/adaptive-phase-6.md).
