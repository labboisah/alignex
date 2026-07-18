# Candidate Client App MVP Test Checklist

Use this checklist to manually verify the AlignEx Candidate Client Windows MVP against a running AlignEx Center Server.

## Prerequisites

- AlignEx Center Server is running and reachable on the local network.
- At least one active exam exists with a valid exam code.
- At least one assigned candidate exists with a valid registration number.
- Candidate Client App is installed or running in development mode.
- For socket event tests, supervisor/server tooling can emit exam control events.

## Manual Checklist

| # | Test | Expected Result |
|---|------|-----------------|
| 1 | Start Candidate Client App. | App opens to welcome/setup flow or candidate login if a server URL is already configured. No admin UI is visible. |
| 2 | Configure Center Server URL. | Setup page accepts a valid `http://` or `https://` Center Server URL. |
| 3 | Test connection with correct server IP. | Connection test succeeds, connected message appears, and Continue button is enabled. |
| 4 | Test connection with wrong server IP. | Connection test fails with a simple error explaining that the Center Server cannot be reached. |
| 5 | Login with a registration number assigned to the active exam. | Candidate is authenticated against the currently active exam and routed to the instructions page. Candidate name, exam, and session details are loaded. |
| 6 | Login when no exam is active for the registration number. | Login is rejected with a clear message that no active exam was found. Candidate remains on login/recovery flow. |
| 7 | Login with invalid registration number. | Login is rejected with a clear candidate not found or invalid registration message. Candidate remains on login/recovery flow. |
| 8 | Load instructions page. | Instructions page displays candidate details, exam details, duration/remaining time, rules, server status, and device status. |
| 9 | Start exam. | Exam questions load and the candidate is routed to the exam screen. |
| 10 | Confirm exam mode activates if enabled. | When `VITE_ENABLE_EXAM_LOCKDOWN=true`, starting the exam enters fullscreen/kiosk-style exam mode and shows lockdown active status. |
| 11 | Navigate questions. | Previous, Next, Review, and question palette navigation move between questions without layout issues. |
| 12 | Select answer. | Selected option is visibly highlighted and remains selected when navigating away and back. |
| 13 | Confirm answer save status shows Saving then Saved. | Save status changes to Saving shortly after selection, then All answers synced/Saved after server acknowledgement. |
| 14 | Disconnect network temporarily. | Connection status changes to Disconnected or Reconnecting and a clear warning is shown. |
| 15 | Select another answer while disconnected. | Answer can be selected locally and remains visible in the UI. |
| 16 | Confirm answer enters pending queue. | Save status shows failed/pending state and warning explains the answer is saved locally until sync returns. |
| 17 | Reconnect network. | Connection status recovers to Syncing/Connected. |
| 18 | Confirm pending answer syncs. | Pending answer is sent to the server, pending warning disappears, and save status returns to Saved/All answers synced. |
| 19 | Confirm question palette updates. | Palette marks answered questions, current question, pending save, and failed save states correctly. |
| 20 | Confirm timer warning below 5 minutes. | Warning banner appears when remaining time is below 5 minutes and timer remains visible. |
| 21 | Confirm timer warning below 1 minute. | Danger warning appears below 1 minute and timer remains visible. |
| 22 | Submit manually. | Submit button opens confirmation dialog when exam is not locked and no answers are pending save. |
| 23 | Confirm submit dialog shows answered/unanswered count. | Dialog clearly displays candidate, exam, answered count, unanswered count, and pending sync count. |
| 24 | Confirm submitted success page. | After confirming submit, success page appears with candidate/exam submission summary. |
| 25 | Test auto-submit when time expires. | At time expiry, manual input is locked, auto-submit overlay appears, pending answers sync if possible, and success page appears after submit. |
| 26 | Test right-click/copy/paste warning during exam. | Restricted actions are blocked or logged, and candidate receives an understandable warning where supported by the client. |
| 27 | Test `exam_closed` socket event. | Client shows the supervisor/closed message and either submits or routes to exam closed recovery according to event action. |
| 28 | Test `candidate_disqualified` socket event. | Client locks/removes exam access and shows a simple disqualified page with the provided reason when available. |
| 29 | Confirm session clears after Done button. | Done on submitted/disqualified flow clears exam session and returns to candidate login without exposing previous exam data. |
| 30 | Confirm app builds successfully for Windows. | `npm run build` completes successfully and `npm run dist` creates the Windows NSIS installer in `offline-candidate-browser/dist-release/`. |

## Acceptance Summary

The MVP passes when a candidate can configure the server, log in to the active exam with registration number only, read instructions, write the exam, auto-save answers, recover from a brief disconnection, submit manually, auto-submit at time expiry, and see the successful submission page without any admin UI appearing in the client app.
