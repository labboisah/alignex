# Adaptive Phase 5: reporting and controlled diagnostic pilots

Implemented: 8 September 2026. Software support is available; no live cohort was enabled in this work.

## Report workflow

Open **Results > Adaptive diagnostics**, select a candidate progression, and review its retained levels. The list is paginated at 25 candidates. Existing adaptive attempt-result links open the same progression report. A candidate with three recovery levels appears once in the diagnostic list.

The report provides:

- Original, earned, penalty, recoverable and closed marks, globally and per area.
- Each level's immutable attempt identity, incoming budget, percentage penalty, available budget and earned marks.
- Issued and committed counts, correct committed responses, planned coverage and raw accuracy.
- Topic coverage, including selected topics with no evidence and an explicit count of untagged responses.
- An expandable item path with issued question IDs, difficulty, paper-row area and topic. CSV also includes issue/commit timestamps.
- Scored area evidence, rule-based mastery, configured mastery threshold and minimum evidence.
- Level/progression stop reasons, candidate aggregate release state, snapshot version/fingerprint, engine version and scoring version.

The path comes from persisted decisions and responses, not current question-bank content. Editing a source question does not rewrite the recorded path. Reports do not return question stems, options, keys or selected answers. Authorized staff can see correctness for issued responses; candidates cannot access these reports.

Reads lock the progression row within a transaction, sharing the lifecycle's first lock. This keeps the ledger and level history coherent without recalculating results or posting marks. Related levels, attempts, decisions and responses are fetched in batches. A prepared level has no runtime plan or item path until explicitly started.

## Meaning of scores and evidence

Recovered marks and mastery answer different questions. The report preserves:

```text
original = earned + penalty + recoverable + closed
```

Earned marks include all scored recovery levels. Penalties reduce the remaining budget. Closed marks cannot be recovered. While a progression remains open, its aggregate is provisional.

Raw accuracy is correct committed responses divided by all committed responses in that level. It excludes unconfirmed questions, so the report also shows planned, issued and committed counts. A 100% raw accuracy on one response does not imply completed coverage or full marks.

Rule-based mastery uses the latest scored level's planned area quota and configured minimum evidence. Scored evidence counts accumulate separately. Optional unscored practice shows its own raw accuracy and coverage, earns zero marks, and does not change scored mastery or the aggregate.

These are descriptive practice/diagnostic results. They are not validated ability estimates, certification outcomes, recruitment rankings or automatic academic grades. Every diagnostic screen and CSV states this limitation.

## Authorization, release and traditional compatibility

The new report routes require authentication, portal access and the existing `viewReports` permission. Policies also require a super administrator or the exact owning organization/institution/professional-school/CBT-center identity. Parent-organization membership alone does not grant access to another entity's complete diagnostic history. Frozen progression ownership is checked as well: reassignment cannot transfer historical diagnostic reports to the new owner.

Reviewer/auditor work can use an existing portal role configured with `viewReports` and no exam-management permission. This phase does not introduce new account roles. Partial teacher/facilitator assignments do not grant whole-progression reports. Candidate/student accounts are rejected. Exports repeat the policy check and require the existing `csv_export` plan feature.

Detailed report views and exports generate exam audit events. Export filenames use numeric progression IDs, responses are private/no-store, and spreadsheet formula prefixes are escaped. The UI handles download progress, failure and retry.

Candidate APIs continue to return only a closed, released aggregate, and never the staff report. Disqualification withholds the aggregate. Legacy adaptive verification hashes are excluded from the public verification endpoint.

Bound adaptive attempts are excluded from traditional result queries, dashboard charts, submitted-result counts and CSV/PDF exports. They cannot receive a traditional grade or verification hash through the result service. The traditional marked-paper endpoint rejects bound adaptive attempts. Existing traditional papers, scoring, result formats, grades and exports retain their original behavior, including historical adaptive-labelled fixed papers that were never bound to the adaptive lifecycle.

Recruitment ranking/shortlisting rejects adaptive pilot exams. Certificate generation already rejects bound adaptive attempts; professional pass classification now also excludes them.

## Controlled pilot configuration

The runtime readiness gate now permits the implemented online diagnostic engine. New publication/starts still require all of:

1. `ADAPTIVE_PILOT_ENABLED=true`.
2. The exact owner key in `ADAPTIVE_PILOT_OWNERS`.
3. The exact existing exam ULID in `ADAPTIVE_PILOT_EXAMS`.
4. Online delivery, assessment/practice category, and a supported owner.

All defaults remain disabled/empty. No local `.env` was changed. An owner allowlist alone cannot enable every exam in that tenant. Create a draft first so the exam has an ID; active creation without an approved existing ID remains blocked.

| Context | Pilot scope |
| --- | --- |
| Organization | Online assessment/practice diagnostics only |
| Institution | Online assessment diagnostics under existing course ownership |
| Professional school | Online assessment/practice diagnostics only; no certificates |
| CBT center | Online assessment/practice diagnostics only; no adaptive offline packages |
| Secondary school | Adaptive remains blocked; traditional school CBT continues |

Before enabling a live cohort, the operator must review the intended diagnostic use, approve the owner/exam IDs, configure the exam's recovery percentage, limits, evidence and release policy, and prepare a ready immutable snapshot from approved pools. Candidate assignments and existing eligibility/payment/device checks still apply. Changes after preparation can require a fresh snapshot before initial binding.

Use the preparation page and diagnostic list to see whether new starts are enabled. Pilot operators should inspect item coverage, pool-exhaustion/stop reasons, progression balances, audit incidents and withheld/released results during a small cohort before expansion.

Rollback means disabling the flag or removing approved IDs. Already-started levels can resume/read/finalize; new initial and recovery/practice starts are blocked. Existing state and historical papers are retained. Rollback does not rewrite scoring or close active sessions automatically.

Live cohort selection, operational observation and assessment-owner acceptance are deployment work still to perform. Engineering tests are not evidence of psychometric validity.

## Verification

The combined adaptive, traditional CBT and owner-context regression selection passes **152 tests / 1,632 assertions**. This includes real pilot-gate preparation, candidate login/start/commit, report authorization and released aggregate retrieval for all four supported owners, plus existing secondary-school traditional coverage.

The final targeted reporting/recruitment/traditional-results rerun passes **20 tests / 377 assertions**, including the additional historical fixed-paper regression and frozen-owner reassignment checks. Focused reporting tests cover recovery budgets/history, frozen paths, unscored practice, release/disqualification, legacy-hash secrecy, cross-owner and candidate rejection, report-only reviewers, export auditing/formula escaping, and recruitment/certificate safeguards.

Four distinct browser scenarios are verified: organization and secondary-school traditional navigation, the empty diagnostic list, and report history with failed-download/retry/success. The four-case run passed three; after correcting the report disclosure locator, the remaining export scenario passed in isolation with no failures or skips. The Vite build passes. Tests use isolated SQLite databases; no development data was reset. No schema migration was needed.

The prior Phase 4 MySQL contention evidence remains historical: 3 tests / 42 assertions. It was not rerun for this reporting phase; no lifecycle scoring or ledger-posting algorithm changed. Production load, assessment validity and live cohort acceptance remain unverified.

```text
php artisan test --compact --filter="AdaptiveReportingTest|AdaptiveRolloutTest|ResultManagementTest"
npm run build:browser
npx playwright test --grep "adaptive diagnostic report|adaptive report list|traditional paper navigation"
php vendor/bin/pint --dirty --test
```

Use `npm.cmd` and `npx.cmd` on Windows where PowerShell script execution is restricted.

## Phase 6 handoff

The next engineering phase is the internal FastAPI engine contract, calibrated item lifecycle, ability/uncertainty estimates, validated stopping/classification rules, replayability and engine-failure behavior. Retain the current diagnostic interpretation and default-off rollout until the appropriate gates are met.

Consequential scoring requires an assessment specialist and representative calibration/validation data, followed by owner approval of the decision rules. Secondary-school formative policy remains a separate decision. Adaptive offline delivery remains Phase 7.

See the [knowledge base](adaptive.md) and [implementation plan](../adaptive-examination-audit-2026-09-08.md).
