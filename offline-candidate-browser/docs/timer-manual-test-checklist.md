# Exam Timer Manual Test Checklist

1. Login with an exam attempt that returns `remaining_time_seconds`.
2. Start the exam and confirm the timer appears in the fixed top bar.
3. Navigate between questions and confirm the timer does not reset.
4. Disconnect the network briefly and confirm the timer continues counting down.
5. Set or mock remaining time below 5 minutes and confirm the orange warning banner appears.
6. Set or mock remaining time below 1 minute and confirm the red critical banner appears.
7. Let the timer reach zero and confirm options are disabled.
8. Confirm previous, next, palette navigation, and manual submit are blocked after expiry.
9. Confirm the message `Time is up. Submitting your exam...` appears.
10. Confirm the app calls `POST /api/candidate/auto-submit`.
11. Confirm backend expiry is still enforced by trying to save an answer after time expiry.
