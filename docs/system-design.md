# System Design

## Overview

AlignEx is a hybrid Laravel and React CBT platform. Laravel owns the backend, authorization, database state, web routing, APIs, real-time events, and server-side exam authority. Inertia React provides public and admin pages. A dedicated candidate exam interface is served by Laravel but uses React Router internally for `/exam/*` screens.

The platform supports secondary school examinations, professional certification examinations, recruitment examinations, traditional CBT, online examination, supervisor monitoring, anti-cheating controls, result management, and reports. It is designed to later support offline center-based examination through Electron + SQLite and adaptive assessment through a Python FastAPI service.

## Active Context Model

The corrected platform structure uses four current contexts:

- Organization
- Secondary School
- Professional School
- CBT Center

Organization is separate from schools and CBT centers. Secondary school and professional school are separate entity types, not values of `school_type`. CBT center is also separate from the legacy `centers` module and owns center-based CBT candidates, question banks, and exams.

The selected current context drives dashboard data, sidebar modules, quick actions, and terminology. Context data is shared through Inertia props as `current_context` and `available_contexts`.

## Architecture

- Laravel web layer renders public/admin Inertia pages.
- Laravel API layer handles asynchronous operations and candidate exam actions.
- React + TypeScript implements UI components and page views.
- React Router is isolated to the candidate exam app under `/exam/*`.
- MySQL is the source of truth for organizations, exams, candidates, answers, results, and audit data.
- Laravel Reverb + Redis will support live monitoring, candidate presence, and proctor event streams.
- Electron + SQLite will be introduced later for offline centers with controlled sync.
- Python FastAPI will be introduced later for adaptive question selection and psychometric services.

## User Roles

- Super Admin: global platform administration.
- Organization Admin: organization-level configuration and operations.
- Exam Manager: exam setup, scheduling, candidate assignment, and result release.
- Question Author: question and question bank creation.
- Reviewer/Moderator: question approval and quality control.
- Supervisor/Proctor: live exam monitoring and incident response.
- Candidate: exam login, writing, submission, and result viewing where allowed.
- Support/Auditor: read-only operational review and audit inspection.

## Exam Workflow

1. Select the current context.
2. Prepare the context's learners, subjects or training structures, and question bank.
3. Create an exam allowed for that owner type.
4. Assign candidates or students.
5. Generate candidate papers with stored question and option order.
6. Candidate enters `/exam/login`, receives instructions, and starts the exam.
7. Answers are saved through Laravel candidate APIs.
8. Supervisor monitors live sessions and anti-cheating events.
9. Candidate submits or is auto-submitted by server-side rules.
10. Server scores objective items and marks certificate eligibility where applicable.
11. Results are reviewed, approved, released, and reported.

Allowed ownership rules:

- Organization: recruitment, assessment, certification, professional, practice, general; traditional or adaptive; requires candidates and question bank.
- Secondary school: terminal only; traditional only; requires session, term, class, and subject.
- Professional school: professional, certification, practice; traditional or adaptive; uses programme, course, and module.
- CBT center: recruitment, assessment, certification, professional, practice, general; traditional or adaptive; requires center candidates and question bank.

## Core Boundaries

- Admin/public pages: Laravel routes + Inertia only.
- Candidate pages: React Router only inside `/exam/*`.
- Candidate actions: Laravel API only.
- Correct answers: never sent to candidate frontend.
- Server authority: timing, eligibility, score, submission, disqualification, and result release.

## Future Offline Workflow

Offline centers will use Electron with local SQLite for controlled exam delivery. The offline app should receive encrypted exam packages, verify center/device authorization, collect answers and events locally, and sync signed payloads back to Laravel when connectivity returns. Conflict handling, replay protection, package expiry, and supervisor audit trails are mandatory.

## Future Adaptive Workflow

Adaptive exams will use a Python FastAPI engine for item selection and ability estimation. Laravel remains the authority for candidate identity, session state, question eligibility, audit logs, final scoring, and result release. The adaptive service should receive only the minimal data required for selection and must never be a direct candidate-facing service.
