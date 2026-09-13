# Offline result upload implementation ? 13 September 2026

Implemented for the Laravel portal and Center Server **0.1.4**. This follows the [original audit](offline-result-upload-audit-2026-09-13.md).

## Operator workflow

1. Complete the offline exam and close it on the center server.
2. Connect the server computer to the internet.
3. Open **Results Export ? Upload results to portal**, enter the local administrator email/password, and select **Upload / retry**.
4. Review each candidate's acknowledgement. Failed requests can be retried after restoring connectivity or resolving the displayed problem. Accepted attempts are retained locally and are not sent again.
5. On the portal, open the exam's results page. Review the **Offline upload reconciliation** table, including local and official scores, then use **Release results** when appropriate.
6. Candidates use the portal's existing **/candidate-result** page with their exam code and registration identifier. Held results and disqualified attempts are not returned.

Exams already marked **Exported** remain available for upload and backup export. No reimport is necessary. Uploading respects the exam's existing immediate/public release setting; an already released exam makes newly accepted results available immediately. Receipt visibility shown on the server is the state at acknowledgement; subsequent release changes are managed on the portal.

## Implemented contract and safeguards

- One complete candidate attempt per POST **/api/offline/results**, contract **alignex.offline-result.v1**.
- Active server license, matching device, portal administrator password, activation administrator identity, and the exam update policy are checked.
- Exact original portal exam/candidate/question/option IDs are recovered from the stored download. Local IDs are never used as cloud primary keys.
- New downloads contain the portal attempt ID and an authenticated encrypted paper proof. Export records prevent stripping a proof to claim legacy compatibility.
- The portal compares assigned questions, available marks, options and answer keys. Signed references also detect scoring-policy changes. It rejects changed papers and overlapping online activity.
- Legacy downloads without a proof require a uniquely identifiable candidate attempt and an exact match against the current assigned marks/options/answer keys. They cannot establish the original historical scoring policy cryptographically and are clearly marked as legacy in reconciliation.
- Official marks are calculated by the portal's existing scorer using saved candidate-paper marks. Local totals are retained only as reconciliation evidence; negative marking can cause an expected difference.
- Answers, terminal status, timestamps, proctoring messages, scoring, audit log and receipt are committed atomically. Disqualifications remain disqualified. Completed exam windows are accepted without reopening the exam; cancelled exams are rejected.
- SQLite stores a frozen payload and stable upload ID before sending. An identical retry returns the existing receipt; altered replays conflict. Lost acknowledgements survive app restarts.
- One malformed local attempt is marked blocked without preventing other candidates from uploading. Pending/error payloads retry unchanged; blocked attempts are rebuilt after local data is corrected.
- Accepted results are marked uploaded only after a matching portal acknowledgement. Network requests use HTTPS, except loopback development, with a timeout and no credential-forwarding redirects.
- Local upload and receipt APIs require an active local administrator; credentials entered in the renderer stay in page memory.
- Untrusted local evidence paths/URLs are not imported as clickable evidence. Only event type, severity, message and time are imported.
- Release/hold actions and accepted/conflicting uploads are audited. The result checker selects the latest submitted attempt by attempt number, submission time and ID.
- JSON/CSV export is still a local backup. There is no new portal JSON file-import screen or unattended background uploader.

## Local changes applied

Both additive portal migrations were applied:

- **2026_09_13_120000_create_offline_result_receipts.php**
- **2026_09_13_120100_create_offline_paper_exports.php**

The server initializes its upload queue table on startup without deleting existing exams, answers or activation data. The server package and lockfile are version **0.1.4**; the Windows executable file/product versions are also **0.1.4**. The candidate client version was not changed.

The Windows packager now builds under **alignex-server/releases/<version>**, checks its cleanup paths and stages runtime dependencies from the lockfile. It does not copy development dependencies into the application archive.

## Verified private build

- ZIP: **C:/laragon/www/alignex-server/releases/0.1.4/AlignEx-Center-Server-win-unpacked.zip**
- Size: **141,357,258 bytes**
- SHA-256: **6ef220e42220d05b5ab1f77cd41a978e14549934e5b257893376d504d63b6a64**

Extract the ZIP and run **AlignEx Center Server.exe**. Preserve the existing server data/activation folder when upgrading; do not reset or reimport completed exams.

## Verification

- Portal: **28 tests passed, 241 assertions** across OfflineResultUploadTest, ResultManagementTest and AdaptiveOfflineBoundaryTest.
- Server: **29 tests passed**, including durable retry after restart, partial batch recovery, local administrator authorization, existing package imports and diagnostic pilot boundaries.
- Server main TypeScript build, renderer type check and production renderer build passed.
- Portal Vite production build passed.
- Packaged executable smoke check passed using an isolated SQLite database: version 0.1.4, health HTTP 200, unauthenticated receipt request HTTP 401, upload table initialized, final uploader present in app.asar.
- No live center-to-hosted-portal transfer or remote deployment was performed.

The standalone portal TypeScript check reports 79 existing project-wide typing issues, including missing React declarations and older library targets. A compiler-host comparison against the original result pages from Git reported the same 79 diagnostics and zero introduced diagnostics. The production build passed.

## Publication status and deployment order

**The ZIP remains private in the server release folder.** Automatic approval review rejected copying it into the portal's public download directory and activating distribution, because publication was considered outside the rebuild/versioning authorization. No public 0.1.4 ZIP or release activation migration was created; the current portal download/update release remains unchanged. Explicit approval is required before publishing.

After publication is approved, deploy the portal changes and the two migrations before updating centers. Publish the verified ZIP through **App Releases**, with artifact **Server**, version **0.1.4** and the checksum above. If publishing on another environment, verify the uploaded file checksum and activate its release record there. The package's ASAR contains application code and is not a code-secrecy boundary.
