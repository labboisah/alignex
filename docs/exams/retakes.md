# Candidate retakes

Authorized exam managers can schedule a retake from **Results ? Exam ? Candidate retakes**. The candidate keeps the same exam code and registration number.

## Behaviour

- A retake creates a numbered attempt in the existing exam. It copies the previous paper's questions, option order and marks, with empty answers and a fresh timer/device binding.
- The admin sets a future start, closing time, duration and reason. The window must allow the full duration. A late start receives only the time remaining before closing.
- Browser-local date/time inputs are transmitted with a timezone and normalized to the application timezone before storage.
- The original exam may be active or completed. Its schedule, other candidates and assignments stay unchanged.
- An admin's manual approval is an audited exception to the general allow-retake setting and professional attempt limit. Existing payment clearance is carried forward; a pending/failed payment still blocks professional eligibility.
- Adaptive exams and exams with adaptive attempt history cannot use this operation.
- Only one pending/running attempt is allowed per candidate. Concurrent schedule requests are serialized with database locks.
- An unstarted retake can be cancelled. Previously issued tokens then stop working. A missed, cancelled or disqualified retake leaves the previous completed result current.
- A submitted or auto-submitted and scored retake replaces the current result even when its score is lower.
- Current exam results, aggregate dashboards, CSV/PDF summaries, candidate result lookup and recruitment ranking select the highest completed traditional attempt number. Pending attempts do not count as results.
- Prior attempt details and marked papers remain accessible to authorized staff through attempt history. An older verification hash no longer validates as the current result.
- Existing result-release rules still determine candidate visibility.
- Supervisor reset cannot erase attempts involved in retake history. Ending the original exam does not close separately scheduled retakes.

## Online delivery and offline history

This release delivers scheduled retakes in the online candidate app. The existing offline package has one exam-wide schedule and cannot enforce candidate-specific windows. Exporting a package whose selected attempt is a retake, or uploading a result against a scheduled retake, is rejected explicitly.

Candidates whose original result was uploaded from an offline center can retake online. A replay of their original accepted upload remains attached to its original attempt and cannot replace the newer result. Legacy uploads without an attempt reference are rejected when multiple attempts make the identity ambiguous.

Certificates remain individual attempt records; this feature does not revoke or replace previously issued certificates.

## Implementation and deployment

The migration `2026_09_14_120000_add_candidate_retake_scheduling.php` adds the prior-attempt reference, window, duration, reason, scheduling actor and cancellation timestamp.

Web routes:

- `POST /exams/attempts/{attempt}/retake`
- `POST /exams/attempts/{attempt}/retake/cancel`

Both use the candidate-attempt policy, which requires authorization to update the owning exam. Scheduling uses a form request and the transactional `CandidateRetakeService`. Candidate operations continue through the existing candidate API and React Router island.

Apply the migration and rebuild frontend assets when deploying:

```sh
php artisan migrate
npm run build
```

Verification completed: 104 backend tests passed across the affected workflows and adaptive/offline boundaries; the Playwright retake browser test passed; production/browser builds and PHP formatting checks passed. Applying the migration to the development MySQL database was not possible because the local server was unreachable. Start MySQL before running `php artisan migrate`.

Feature tests in `tests/Feature/CandidateRetakeTest.php` cover permissions, validation, scheduling, timezone conversion, candidate access, timer recovery, cancellation, replacement exports, history and reset protection. `OfflineResultUploadTest` covers replaying an original offline receipt after a completed online retake.
