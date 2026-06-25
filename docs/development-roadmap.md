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
