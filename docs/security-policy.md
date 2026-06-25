# Security Policy

## Core Principles

- Server state is authoritative.
- Candidate clients are untrusted.
- Correct answers must never be exposed to candidate frontend code, page props, API responses, local storage, logs, or browser-readable payloads.
- Every sensitive action must be authorized, validated, logged, and tested.

## Authorization

- Use Laravel policies for model-level authorization.
- Use middleware for route-level access control.
- Use role and permission checks for admin workflows.
- Separate candidate session authentication from admin user authentication.
- Supervisors may monitor only exams they are authorized to supervise.

## Validation

- Use form requests for all write actions.
- Validate candidate access codes, session status, exam windows, question eligibility, and answer payloads.
- Validate imports before persistence.
- Reject client-submitted scores, correct flags, durations, or authoritative statuses.

## Candidate Exam Security

- Candidate APIs must require a secure candidate session token.
- Candidate API responses must include only exam metadata, question text, allowed options, and candidate progress fields.
- Do not send `is_correct`, answer keys, explanations, scoring rubrics, or internal moderation fields.
- Use server-side timing for start, expiry, submit, and auto-submit.
- Use transactions for submission and scoring.
- Prevent duplicate submissions from corrupting state.

## Anti-Cheating Rules

Log and evaluate:

- Candidate login and device/browser metadata.
- Tab blur, focus loss, and visibility changes.
- Fullscreen exit where fullscreen is required.
- Copy, paste, print, context menu, and suspicious keyboard actions.
- Network disconnect and reconnect.
- Multiple login attempts or session conflicts.
- Supervisor warnings, pauses, resumptions, and disqualifications.

Anti-cheating controls should be configurable per exam. Controls should produce audit events first; automatic disqualification should be a deliberate exam setting.

## Data Protection

- Store passwords using Laravel hashing.
- Keep candidate access credentials short-lived or exam-scoped.
- Avoid logging secrets, passwords, access codes in full, or answer keys.
- Encrypt sensitive offline packages.
- Use signed sync payloads for offline workflows.
- Use least privilege database and service credentials.

## Audit Logging

Log:

- Exam creation, updates, publication, and deletion.
- Question import, moderation, approval, and changes.
- Candidate login, answer save, submit, and auto-submit.
- Proctoring events and supervisor interventions.
- Result scoring, review, release, export, and correction.

Audit records should be append-only where practical and include actor, role, IP address, user agent, timestamp, target entity, and payload summary.

## Testing Requirements

Security-sensitive modules must test:

- Unauthorized access is denied.
- Invalid payloads are rejected.
- Candidate payloads hide correct answers.
- Submission is transactional and idempotent.
- Result release rules are enforced.
- Audit events are written.
