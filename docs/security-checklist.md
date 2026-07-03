# Security Checklist

## Access Control

- Super admin can access all platform areas.
- Organization admin can access only their organization and child contexts.
- Secondary school admin can access only their secondary school.
- Professional school admin can access only their professional school.
- CBT center admin can access only their CBT center.
- Examiner access is limited by permissions and assigned scope.
- Supervisor access is limited to monitoring and report workflows.
- Candidate/student users cannot access the admin dashboard.
- Candidate/student users use `/exam/*` only.
- Users must not access another entity's data.

## Candidate Exam Security

- Candidate APIs use Laravel API routes.
- Candidate screens are under `/exam/*`.
- Candidate frontend receives no answer keys, `is_correct`, scoring rubrics, or internal moderation fields.
- Server state controls timing, status, submission, disqualification, score, and result release.
- Answer saving, auto-submit, submit, and event logging are server-authoritative.

## Validation Rules

Organization exams:

- Require question bank and candidates.
- Reject academic session, term, class, arm, and professional structure fields.
- Allow traditional and adaptive modes.

Secondary school exams:

- Require academic session, term, class, and subject.
- Allow terminal category only.
- Allow traditional mode only.
- Reject adaptive, recruitment, and professional structure fields.

Professional school exams:

- Allow professional, certification, and practice categories.
- Allow traditional and adaptive modes.
- Use programme, course, and module where applicable.
- Reject academic session, term, and class fields.

CBT center exams:

- Require question bank and candidates.
- Allow traditional and adaptive modes.
- Reject school and professional structure fields.

## Audit Events

Log:

- Candidate login success/failure.
- Answer save.
- Submit and auto-submit.
- Tab switch, focus loss, fullscreen exit, copy/paste attempts, and proctor events.
- Disqualification.
- Result calculation and certificate generation.

## QA Commands

```bash
php artisan test tests/Feature/RbacTest.php
php artisan test tests/Feature/CandidateExamApiTest.php
php artisan test tests/Feature/SharedExamWorkflowTest.php
```
