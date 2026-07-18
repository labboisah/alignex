# Center Server MVP Test Checklist

Use this checklist to manually test the Import Exam Package MVP.

## Start App

1. Open a terminal in `offline-server`.
2. Run `npm run dev`.
3. Confirm the Electron app opens.
4. Confirm the top status bar shows Server Status as Online.
5. Confirm Local IP Address and Candidate URL are visible.

## Import Valid Package

1. Open Import Exam from the sidebar.
2. Click Download Sample Package, or use `docs/samples/valid-exam-package.json`.
3. Drag the JSON file into the upload area or use the file picker.
4. Confirm the selected filename appears.
5. Click Import Package.
6. Confirm the progress steps move through Read file, Validate JSON, and Save to SQLite.
7. Confirm a success toast appears.
8. Confirm the success summary shows:
   - Exam Title
   - Exam Code
   - Organization
   - Candidates
   - Questions
   - Duration
   - Status
9. Click View Imported Exams.
10. Confirm the imported exam appears with status `ready`.
11. Return to Dashboard and confirm Imported Exams count increased.
12. Confirm the Exams sidebar badge count increased.

## Duplicate Package

1. Import the same valid package again.
2. Confirm the import fails.
3. Confirm the error says the package has already been imported.

## Invalid Package

1. Open Import Exam.
2. Select `docs/samples/invalid-exam-package.json`.
3. Click Import Package.
4. Confirm a failure toast appears.
5. Confirm detailed validation errors appear on the page.
6. Confirm Imported Exams count does not increase.

## API Checks

1. Open `http://127.0.0.1:4080/api/server-info`.
2. Confirm imported exam and candidate counts match the UI.
3. Open `http://127.0.0.1:4080/api/exams`.
4. Confirm the imported exam list is returned.

## Start Exam

1. Open Imported Exams.
2. Click View Details for a ready exam.
3. Confirm Start Exam is visible.
4. Click Start Exam.
5. Confirm the dialog message says: "Starting this exam will allow candidates to login from the candidate computers."
6. Confirm the dialog.
7. Confirm a success toast appears.
8. Confirm the exam status changes to Active.
9. Confirm Candidate Access URL is shown as `http://LOCAL_IP`.
10. Click Copy Candidate URL and confirm the success toast.
11. Click Go to Active Monitor.
12. Confirm the Active Monitor page opens.
13. Return to Dashboard and confirm Active Exams count is updated.
14. Try to start another ready exam while one is active and confirm the API blocks it.

## Candidate Login

1. Start a ready exam so its status becomes Active.
2. Open the Candidate URL shown by the admin app. It should open the candidate login page at the root LAN URL.
3. Confirm the page shows Center Server name and connection status.
4. Enter a valid Exam Code and Registration Number from the imported package.
5. Click Login and confirm the button shows a loading state.
6. Confirm successful login redirects to the Exam Instructions page.
7. Confirm candidate details, exam details, and duration are shown.
8. Try an invalid exam code and confirm a clear invalid exam code message.
9. Try an unknown registration number and confirm candidate not found.
10. Try login before starting an exam and confirm exam not active.
11. Try a submitted or disqualified candidate and confirm login is blocked.
12. Try the same candidate from another browser/device fingerprint and confirm already logged in is shown.

## Candidate Paper And Instructions

1. Login as a valid candidate for an active exam.
2. Confirm the Exam Instructions page shows candidate name, registration number, exam title, duration, total questions, and rules.
3. Click Start Exam.
4. Confirm the button shows a loading state.
5. Confirm the exam screen opens with questions and options.
6. Refresh or call `/api/candidate/exam` again with the same attempt token.
7. Confirm the question order and option order remain the same.
8. Confirm `/api/candidate/exam` returns saved answers and remaining time.
9. Confirm response data does not include `is_correct`, answer keys, correct answers, or scoring rubrics.

## Answer Auto-Save

1. Open the candidate exam screen.
2. Select an option and confirm the option is highlighted immediately.
3. Confirm Save Status changes to Saving, then Saved.
4. Confirm the question palette marks the question as answered immediately.
5. Temporarily stop the server or network and select another answer.
6. Confirm the selected answer remains highlighted locally.
7. Confirm a warning banner shows pending answers.
8. Confirm Submit is disabled while pending answers exist.
9. Restore the server/network and click Retry Save.
10. Confirm the warning clears and Save Status returns to Saved.

## Notes

Candidate exam writing, monitoring, scoring, and export sync are not part of this MVP test.
