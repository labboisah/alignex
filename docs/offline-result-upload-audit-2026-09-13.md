# Offline result upload and online candidate access audit

Reviewed: 13 September 2026.

Implementation follow-up: the portal receiver and Center Server 0.1.4 have now been built and tested. See [implementation and release notes](offline-result-upload-implementation-2026-09-13.md). The findings below describe the pre-implementation baseline. Publication of the new ZIP is pending approval.

## Scope and conclusion

Reviewed the cloud repository at `C:\laragon\www\alignex` and the offline-server source at `C:\laragon\www\alignex-server`. This is a source audit with targeted cloud result tests, not a certification of deployed Electron installers or a live center-to-cloud transfer. No exam, result, or offline-server application code was changed.

**Traditional offline results cannot currently complete an upload-to-online-result workflow.** The server can export local results, and the portal can display released cloud results, but the traditional result ingestion connection between them is missing.

The neighbouring offline-server source is available and was inspected for this audit, superseding the earlier conversation's uncertainty about its availability.

| Stage | Current implementation |
| --- | --- |
| Download traditional exam package | Implemented: portal export and offline import |
| Write, submit and score locally | Implemented in offline-server source |
| Export completed exam results | Implemented: local JSON and CSV files |
| Upload traditional results to portal | Missing |
| Translate exported answers into cloud attempts | Missing |
| Receive durable upload acknowledgement and retry | Missing |
| Candidate checks released cloud result | Implemented, but requires a cloud submitted/auto-submitted attempt |

## Findings

### 1. High ? Traditional result upload is not implemented

[routes/api.php](../routes/api.php) registers offline activation, exam-package download and updates. Its only offline sync endpoint is `POST /api/offline/adaptive/leases/{lease}/sync`, handled by the separate adaptive pilot controller/service; it is not a traditional result importer.

The offline [ResultsExportPage.tsx](../../alignex-server/src/renderer/pages/ResultsExportPage.tsx) calls its local `POST /api/results/export`. [app.ts](../../alignex-server/src/server/app.ts) passes that request to [result-export-service.ts](../../alignex-server/src/server/result-export-service.ts), which writes files and updates local database records. This operation does not upload them to the portal.

**Impact:** ?Export completed? does not mean candidates can view results online.

### 2. High ? Cloud identity and package-version mapping need an explicit contract

[exam-import-service.ts](../../alignex-server/src/server/exam-import-service.ts) rewrites candidate, question, option and attempt identifiers using `localImportId()`: `localExamId:entityType:sourceId`. Local attempts are derived from the candidate ID, not the portal attempt ID. The export contains those local identifiers.

[OfflineExamPackageController.php](../app/Http/Controllers/Api/OfflineExamPackageController.php) exports cloud candidate/question/option IDs, but its paper entries do not carry a cloud attempt ID or attempt number.

The prefixes are structured and can support a carefully validated legacy adapter; they are not inherently unrecoverable. However, directly inserting exported IDs into cloud answer/attempt tables will not work.

**Required:** persist explicit source IDs, portal attempt ID/number, package version and a stable paper fingerprint. Validate every candidate, question and option against the assigned portal paper. Quarantine unknown/local-only exams instead of inventing cloud records from names or exam codes. Distinguish multiple imports, retakes and multiple centers.

### 3. High ? Final scores must be recalculated and checked, not copied

The exported JSON contains local scores, percentages, correctness and awarded marks. These are evidence for reconciliation, not authoritative cloud scores.

The current mark change is compatible in source: cloud export uses saved candidate-paper marks; offline import stores package `questions[].marks`; [candidate-submit-service.ts](../../alignex-server/src/server/candidate-submit-service.ts) awards those marks for correct answers.

There is still a scoring difference: the inspected offline scoring function awards zero for incorrect answers, whereas [ExamResultService.php](../app/Services/ExamResultService.php) can apply configured negative marking. Blindly accepting the offline total could produce a different official result.

**Required:** ingest submitted answers, preserve local totals separately, recalculate with the authorized cloud paper marks and scoring rules, and flag discrepancies. Do not automatically overwrite a scored online attempt or recalculate already released results on retries. Question options/correctness are read from current bank records by traditional cloud scoring; a frozen answer-key/scoring-policy reference is also needed to avoid later bank edits changing an offline result.

### 4. High ? A checksum is not upload authentication

[result-export-service.ts](../../alignex-server/src/server/result-export-service.ts) computes a SHA-256 digest of the JSON. It logs/returns that digest; the exported payload does not implement the separate signed sync contract.

[contracts/sync-bundle.ts](../../alignex-server/src/contracts/sync-bundle.ts) defines a versioned structure with a signature, but the inspected source has no other usage of `OfflineSyncBundle`. Its camelCase contract also differs from the actual snake_case export.

**Required:** choose one versioned wire contract. Authenticate the activated server and its authorized exam/owner/center, validate package provenance, and enforce request size/rate limits. Verify any signature using a real enrolled key or credential; an unkeyed checksum alone cannot prove who produced a result. Reject cross-owner, wrong-package and unassigned-answer uploads.

### 5. High ? Export state does not establish safe delivery

The local export service accepts only `closed` exams, then changes them to `exported`. The UI offers files/folder/hash actions; no cloud-accepted state or receipt was found. A later upload should not depend on running this closed-only export operation again.

**Required:** a persistent upload queue with pending/uploading/accepted/failed/conflict states, stable per-attempt idempotency keys, content digests and portal receipts. A repeated identical upload returns the prior acknowledgement; changed content for the same finalized attempt becomes a conflict. Mark uploaded only after cloud acknowledgement. Retain local records and support retry after a lost connection or app restart. Display accepted/rejected counts and actionable errors.

### 6. Medium ? The result checker exists, but upload and release must remain separate

[routes/web.php](../routes/web.php) exposes `/candidate-result`. [Results/Self.tsx](../resources/js/Pages/Results/Self.tsx) posts the exam code and registration number to `/api/candidate/result`.

[ResultController.php](../app/Http/Controllers/ResultController.php), `candidateResult()`, requires:
- The candidate to be linked to the exam.
- A cloud attempt in submitted or auto-submitted status.
- Immediate-result display enabled, or release mode public/automatic/released.

The checker does not exclude offline exams. Correctly imported and scored attempts can reuse it. Its wording currently says ?Online Exam Result?; change that to ?Exam Result? when offline uploads are supported.

The inspected routes/UI do not expose a dedicated general review/release action for `result_release_settings`; the wizard's immediate-result option is available. Implement an explicit review/release workflow or document and test the immediate-release policy. Successful upload alone must not publish held results. Candidate lookup currently uses identifiers without a separate proof-of-possession step; evaluate candidate authentication/access-code verification and explicit lookup throttling before expanding access.

The lookup uses `first()` without a defined retake ordering, so attempt selection must also be explicit when multiple attempts exist.

### 7. Medium ? Late upload and conflicts need dedicated handling

Completed offline exams may synchronize after the portal exam window has ended. Reusing the online answer/submit endpoints would apply online token/timer/state assumptions to historical offline activity.

**Required:** a dedicated ingestion service that accepts authenticated late submissions against the original assignment/package, preserves offline timestamps and records cloud receipt time separately. Do not reopen the exam. Preserve disqualification, auto-submission, unanswered questions and audit events. Reject or quarantine overlapping online/offline submissions instead of applying last-write-wins.

## Recommended implementation sequence

1. Define and test the traditional result contract and source-ID mapping. Include contract version, upload ID, activation/server identity, original package ID/fingerprint, portal exam/attempt/candidate IDs, terminal status, timestamps, answers, event IDs and local score evidence.
2. Add a Laravel form request, authorization policy, import service, API resource and transactional attempt ingestion. Store upload receipts and provenance; validate original assigned questions/options; recalculate official scores; audit acceptance, conflicts and rejected attempts.
3. Add ?Upload results to portal? to the offline server with durable retries and per-attempt acknowledgements. Keep JSON export as a transfer fallback; any later portal file-upload screen should use the same ingestion service.
4. Add a portal offline-upload review screen with accepted/pending/conflicted counts and release controls. Show clear distinctions between uploaded, scored, held and released.
5. Reuse the candidate result checker after release and update its wording and attempt-selection rules.

Suggested MVP unit of acceptance: one complete candidate attempt per transaction, with explicit acknowledgements for each attempt in a batch. Never acknowledge half an attempt. This is a proposed design, not existing behavior.

## Acceptance tests required before rollout

- Download a cloud paper with bank marks different from configured exam marks; complete it offline; upload; verify online marks, total and released result.
- Test negative marking, unanswered questions, auto-submit, disqualification, decimal marks and changed-bank-content conflicts.
- Verify all supported owner contexts and candidate/student linkage, not only organization candidates.
- Duplicate upload, altered replay, lost acknowledgement, interrupted batch, app restart and retry must not duplicate answers, attempts, scores or events.
- Reject wrong activation/owner/exam/package, unassigned candidate, unknown question/option and oversized/malformed bundles.
- Upload after the scheduled end without reopening the exam; detect an existing online submission.
- Keep results hidden until the selected release rule is satisfied; test the correct retake selection.
- Confirm source files remain available until acknowledgement and that ?exported? is never presented as ?uploaded.?

## Validation performed

Inspected the source paths linked above. Ran `php artisan test --compact --filter=ResultManagementTest`: 5 tests passed, with 117 assertions. These cover the existing result-management and candidate lookup/release behavior; they do not prove offline upload, which is absent. No traditional upload end-to-end test is possible until its receiver and uploader exist. Offline-server packaged binaries were not launched or rebuilt.
