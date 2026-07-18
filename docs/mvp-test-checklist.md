# AlignEx Center Server MVP Test Checklist

Use this checklist for the final offline center server MVP smoke test.

## Test Steps

1. Start Center Server App.
   - Expected: The Electron app opens to the Center Server dashboard.

2. Confirm server status.
   - Expected: Server Status shows Online, database status is connected, and Candidate URL is visible.

3. Import sample exam package.
   - Use `samples/sample-exam-package.json` or the in-app sample package button.
   - Expected: Import completes with a success toast and the exam appears in Imported Exams.

4. View imported exam.
   - Expected: Exam details show title, exam code, candidates, questions, duration, and Ready status.

5. Start exam.
   - Expected: Start Exam confirmation appears, exam status changes to Active, and candidate access URL is available.

6. Open candidate URL from another computer.
   - Expected: Candidate Login page loads from the center server URL.

7. Candidate login.
   - Use the sample candidate registration number from the package.
   - Expected: Candidate is authenticated and taken to the instructions page.

8. Candidate loads instructions.
   - Expected: Instructions, candidate details, exam title, duration, and Start Exam button are visible.

9. Candidate starts exam.
   - Expected: Exam screen opens with timer, question palette, options, and submit controls.

10. Candidate selects answer.
    - Expected: Selected option updates immediately and question palette marks the question as answered.

11. Confirm answer auto-save.
    - Expected: Save Status changes through Saving to Saved. If save fails, warning banner and retry are shown.

12. Supervisor sees progress.
    - Expected: Active Monitor shows candidate progress, status, IP address, and live event updates.

13. Candidate submits exam.
    - Expected: Confirmation dialog opens, pending answers block submission, and successful submit shows Submitted page.

14. Supervisor sees submitted status.
    - Expected: Active Monitor updates candidate status to Submitted and summary cards update.

15. Close exam.
    - Expected: Close Exam confirmation warns that active candidates will be auto-submitted. Exam status changes to Closed.

16. Export result.
    - Expected: Results Export page lists the closed exam and Export Result completes successfully.

17. Confirm JSON and CSV files exist.
    - Expected: Files exist inside `exports/{exam_code}/` beside the offline SQLite database.

18. Confirm result hash generated.
    - Expected: Export summary shows a SHA-256 hash, and Copy Hash copies it to clipboard.

## Pass Criteria

- No blank pages.
- No unhandled API errors.
- Candidate answers save before submission.
- Submitted and auto-submitted attempts cannot be edited.
- Closed exams cannot accept new candidate logins.
- Exported JSON and CSV files are created and the hash is displayed.
