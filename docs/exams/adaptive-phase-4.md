# Adaptive Phase 4: candidate and supervisor experience

Implemented: 8 September 2026.

The adaptive candidate interface now runs inside the existing `/exam/*` React Router island. Laravel still serves the island through Inertia, and candidate actions still use Laravel APIs. Live adaptive publication and new starts remain disabled by the rollout service. Phase 5 reporting and controlled pilot acceptance come next.

## Candidate workflow

1. Log in using the assigned exam and candidate identifier. The server returns the latest bound adaptive level, safe instructions and frozen proctoring requirements. Login does not begin Level 1 or reveal its first question.
2. Enable any required camera/fullscreen controls, then select **Start adaptive exam**.
3. Answer the current question. **Save draft** preserves selections without committing. **Confirm and continue** makes the response final and asks the server for the next eligible question. There is no previous-question button or editable list of committed items.
4. Continue until the server completes the required coverage, time expires, or the candidate selects **Finish level**. Unconfirmed drafts earn no marks.
5. Read the server's recovery/cooldown/closure message. If eligible, explicitly request another scored recovery level or unscored practice. The confirmation explains the configured percentage penalty or the practice restriction.
6. View an aggregate score only when scored progression is closed and the existing result-release policy permits disclosure.

Progress displays the number of confirmed responses rather than a guessed final question count. The interface explains that coverage and configured limits determine completion. Option controls support keyboard interaction; a newly issued question receives heading focus. Loading, empty, save-success, error, disqualification and restoration states are explicit.

The administrative settings editor also shows an illustrative penalty calculation. For example, 60 remaining marks at 10% leaves 54. The preview is explanatory and cannot post a penalty.

## Interrupted requests, refresh and multiple tabs

The browser persists one session envelope under `alignex_adaptive_session`, containing the token, current payload and any pending mutation. Existing token/payload keys remain compatibility mirrors. Pending requests are saved before transmission; commitments and recovery starts retain their generated idempotency key.

- A failed connection or ambiguous server response leaves **Retry pending request** available. The retry sends the same operation, token and key.
- A successful recovery response installs the new level/token together. If the response is lost after the server commits, retrying the predecessor operation retrieves that level without another penalty.
- Reload first verifies server state before showing a cached question. A read cannot create a level, issue an additional question or spend marks.
- Definite validation failures clear the failed operation and reload authoritative state.
- Mutations disable duplicate clicks. Changes from another tab block writing until the user refreshes. Older reads/responses cannot overwrite a newer local attempt/version.
- Offline/reconnect messages explain recovery. The server timer continues when the browser is closed or disconnected.
- At a displayed deadline, the client requests server state; it does not calculate a score or override a deadline.

The envelope is a recovery aid, not an authority or an offline exam database. Users still need a working connection to receive questions and finalize writes. Clearing browser storage removes the local pending request; logging in again retrieves the server's latest level.

## Proctoring and supervisor behavior

Adaptive controls use the snapshot's fullscreen, webcam and monitoring settings. Camera denial and fullscreen loss block answer controls until restored. Camera streams stop when the session ends. Focus loss, clipboard attempts, fullscreen exit, reconnects and heartbeat events use the existing candidate event endpoint.

The server evaluates adaptive tab-switch limits from frozen settings. Disqualification finalizes the adaptive lifecycle and removes question/recovery actions. Ordinary traditional proctoring settings remain unchanged.

Authorized supervisors see one current row per progression with expandable level history: level number, practice flag, status, issued/confirmed counts and stop reason. The existing incident feed receives adaptive selection, commitment, finalization and supervisor-end audit events. Reverb broadcasts run after commit, with polling as the existing fallback. Candidate identities and owner authorization remain in the existing monitor boundary.

Traditional reset is disabled for adaptive rows and rejected with a JSON validation error on the server. **End Exam** finalizes adaptive levels through their locked ledger lifecycle, retains earned marks, closes remaining balances and prevents further scored recovery or practice. Historical levels are retained. Traditional attempts keep the existing end/reset path.

No option keys, correctness flags, item weights or unreleased candidate score budgets are added to the candidate payload. Supervisor history is operational history; richer item-path and mastery reports belong to Phase 5.

## Owner compatibility

| Context | Phase 4 behavior |
| --- | --- |
| Organizations | Adaptive assessment experience supported behind rollout containment |
| Institutions | Same adaptive experience with existing institution/course ownership rules |
| Professional schools | Same adaptive experience with existing owner and eligibility boundaries |
| CBT centers | Same online adaptive experience; adaptive offline packages remain blocked |
| Secondary schools | Adaptive remains disallowed; traditional school CBT is retained |

The browser branch depends on the server response's adaptive delivery mode. A historical adaptive-labelled exam that still has a traditional paper therefore continues in the traditional interface.

## Verification

The combined adaptive/traditional/entity regression selection passes **140 tests / 1,392 assertions**. The isolated MySQL contention suite also passes **3 tests / 42 assertions**. Browser coverage verifies **15 distinct scenarios**: the full run passed 12; after correcting test timing and traditional fixture setup, a focused rerun passed all four selected cases (including one repeated camera case), with no failures or skipped tests. The application build and PHP formatting checks pass.

Coverage includes all four supported adaptive owners, explicit start/current-only delivery, draft/keyboard restoration, duplicate clicks, lost commitment/recovery responses, exactly-once penalties, offline reconnect, multiple tabs, server expiry, disqualification, camera/fullscreen recovery, released aggregates, supervisor history/termination, and organization/secondary-school traditional navigation and flags.

The combined backend selection is the Phase 3 selection plus `AdaptiveExperienceTest`. The commands below provide the complete browser run and a smaller targeted backend rerun.

Browser tests use the real Laravel API, actual candidate screens and an isolated SQLite database. Test-only fixture/control routes and the rollout override exist only in `tests/Browser/router.php`; they are never registered by production application routes. The harness refuses cached configuration and any database other than `storage/framework/testing/adaptive-browser.sqlite`. It resets only that test database.

Run:

```text
npm run build:browser
npm run test:browser
php artisan test --compact --filter="AdaptiveExperienceTest|AdaptiveLifecycleTest|AdaptiveContractsTest|AdaptiveRolloutTest|CandidateExamApiTest|ExamMonitorTest"
```

On Windows use `npm.cmd` and `npx.cmd` where PowerShell script execution is restricted. Playwright defaults to installed Microsoft Edge on Windows, and Playwright Chromium on other platforms (install with `npx playwright install chromium` when needed). Set `PLAYWRIGHT_CHANNEL=chrome` to use installed Chrome. Build output, traces, screenshots and test reports are ignored. Production build assets and the development database are not replaced.

The existing isolated MySQL contention suite remains separate; see the database guard and command in [Phase 3](adaptive-phase-3.md).

## Phase 5 handoff

Implement authorized item-path, coverage, evidence, raw-practice and configuration-version reports and exports. Distinguish recovered score from mastery and from validated ability estimates. Exercise release/certification rules and establish creation-to-result tests per enabled owner before controlled pilots. Secondary-school adaptive policy remains a separate decision.

This phase does not enable production pilots, implement FastAPI, calibrate items, validate consequential scoring, or add offline adaptive delivery.

See the [knowledge base](adaptive.md) and [implementation plan](../adaptive-examination-audit-2026-09-08.md).
