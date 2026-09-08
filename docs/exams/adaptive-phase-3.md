# Adaptive Phase 3: server lifecycle and recovery scoring

Implemented: 8 September 2026.

The adaptive server lifecycle is implemented behind the existing rollout containment. **This is not a live adaptive rollout.** Runtime readiness remains false, so ordinary publication/new starts remain blocked. The candidate/supervisor adaptive interface and browser acceptance work are Phase 4.

## What now runs on the server

A frozen adaptive attempt is dispatched to `AdaptiveLifecycleService`, not the traditional paper generator or scorer. Historical started attempts carrying only an adaptive label continue using their existing fixed papers.

After rollout authorization, an assigned candidate with no attempt history can be provisioned from the latest ready snapshot. This creates one empty initial attempt and opening ledger through the existing binding service. Repeated login reuses it; existing traditional attempt history is preserved. No ready snapshot means no initial attempt is created.

Adaptive login returns the latest bound level and an exam token. It does not start Level 1 or expose its first item. Explicit start checks assignment, owner, payment where required, bound device, opening time, rollout authorization and the snapshot. It freezes the level's quotas, budgets and deadline, then issues one item.

For an active level:

- A draft saves only the current selected options. It can be revised and resumed.
- A commitment validates the current question, options, state version and idempotency key. The server privately scores the frozen options, locks the response and selects the next item.
- Correct answers move the requested band upward; incorrect or empty commitments move it downward, bounded by easy/medium/hard.
- Required area/topic coverage takes precedence. If the target difficulty has no eligible item, the nearest available band is chosen. A deterministic tie-break and persisted decision preserve the path on retries/resume.
- Only the current issued item and its draft can be serialized. Answer keys, correctness, difficulty, item weights, future questions and unreleased budgets remain private.
- Completion, submit, auto-submit, timeout and disqualification finalize the level. An uncommitted draft earns nothing. A late answer cannot change a closed level.

The original `AdaptiveQuestionSelectorService` remains a legacy prototype; the lifecycle does not call its unscoped bank query.

## Supported scoring and content

The first engine supports text-based single-choice, true/false and multiple-choice questions. Multiple choice uses exact-set scoring: all correct options and no incorrect options are required. There is no partial credit or negative marking in this engine.

New readiness checks exclude questions with image paths because media bytes are not yet versioned. Existing snapshots are also filtered at runtime; insufficient supported content blocks the start before spending marks. Traditional image questions and negative marking are unchanged.

For a single level, the engine satisfies the paper-row quotas and configured minimum, without exceeding the maximum. If the minimum exceeds the sum of quotas, additional slots are assigned deterministically across rows. This is coverage-based stopping, not an ability/uncertainty estimate.

Progressive Level 1 uses the fixed configured per-area quotas. Later scored levels reuse quotas only for eligible weak areas. Each area's available marks are divided across its question slots using integer hundredths, distributing any remainder deterministically. Correct commitments earn that item's frozen weight.

## Weakness-focused recovery

After a level is finalized, each tested area is classified using its current level's correct answers, full planned quota and minimum committed evidence. Cumulative evidence count is retained separately. An area can remain weak or have insufficient evidence despite exhausted marks; score and mastery are distinct.

Mastered areas are excluded from later recovery. Any unused marks in a mastered area are closed rather than transferred to another area.

An explicit next-level request checks:

1. The latest predecessor is finalized, no later level already exists, and no attempt in the progression is disqualified.
2. Assignment, owner access, payment/device policy and rollout permission still hold.
3. The cooldown, maximum scored-level count and progression closing time permit another level.
4. Eligible weak areas have enough remaining marks after the configured percentage penalty.
5. The immutable pool contains enough fresh supported questions and topic coverage across those weak areas.

Only after those checks pass does one transaction create the next attempt/level, post the penalty and issue its first item. A preview, failed readiness check, reconnect or retry does not spend marks. A fresh question cannot be reused anywhere in the progression.

Level 1's deadline is bounded by the initial exam end. Recovery levels use their configured duration bounded by the separate progression closing time. Login selects the latest adaptive level, allowing recovery after the initial exam window has ended.

## Exact accounting

For remaining weak-area marks R and configured percentage p:

```text
penalty = half_up(R * p / 100, to 0.01 mark)
available = R - penalty
remaining_after_level = available - earned
original = earned + penalties + recoverable + closed
```

The penalty is rounded once, then distributed proportionally across participating areas using largest remainders with a stable tie-break. Posting cannot spend another area's marks or exceed remaining marks. Global and per-area conservation are checked after postings.

Examples:

- The agreed 100-mark arithmetic remains: 40 earned, 60 remaining; 10% penalty = 6, leaving 54; a further 20 earned leaves 34; next penalty = 3.40, leaving 30.60.
- The end-to-end three-level test uses six marks, three questions per level and one correct answer at each level. It finishes with 3.92 earned, 0.64 penalties and 1.44 closed unused marks: total six.
- If a 100% penalty or the minimum budget would leave no startable scored level, the remaining balance is closed. No penalty is posted for a level that never starts.
- A 0% penalty remains bounded by the scored-level cap and access deadline.

`AdaptiveLedgerService` performs exact integer arithmetic and reconciles balances. Existing ledger keys cannot be reused with a different posting. Responses become immutable on commitment.

## Practice and result release

When configured, one additional unscored weak-area practice level is available after scored progression closes, provided the access deadline and fresh pool permit it. Practice stores its own responses but adds no earned credit, penalty or changes to scored mastery. It cannot reopen the scored budget.

Per-level attempts retain their own earned marks. Candidate result lookup returns only a finalized aggregate, and only when the existing result-release policy allows it. An unreleased or still-open aggregate returns no score. Disqualified progressions cannot release a candidate result.

Traditional result recalculation cannot overwrite an adaptive attempt's ledger-derived score. Automatic per-level certificates and the traditional supervisor reset are blocked for bound adaptive attempts. Full adaptive admin reports, exports, aggregate certification policy and richer practice/mastery reporting remain later-phase work.

## API contract

Existing encrypted exam tokens authenticate the candidate attempt. An explicit candidate-attempt policy verifies participation; clients cannot choose another candidate/attempt by adding IDs to the body.

| Route | Adaptive behavior |
| --- | --- |
| POST /api/candidate/login | Resume latest bound level; issue token; no implicit initial start |
| POST /api/candidate/start | Explicit idempotent initial start |
| GET /api/candidate/exam | Current state/item only; enforce expiry |
| POST /api/candidate/answer | Save draft or commit current response |
| POST /api/candidate/submit | Finalize level without committing its draft |
| POST /api/candidate/auto-submit | Finalize with auto-submit status |
| POST /api/candidate/next-level | Explicit recovery/practice start, rate limited |
| POST /api/candidate/result | Released, closed aggregate only |

Adaptive answer body:

```json
{
  "question_id": "issued-question-id",
  "selected_option_ids": ["issued-option-id"],
  "state_version": 1,
  "commit": true,
  "idempotency_key": "unique-commit-key",
  "device_fingerprint": "bound-device-if-required"
}
```

For a draft use `commit: false`; a commit requires its idempotency key. After saving, use the returned state version. A stale version requires reloading the current state. Retrying the same commitment returns the current state without issuing or scoring twice.

Next-level body requires an idempotency key, with optional `practice: true` and device fingerprint. Successful next-level responses include the new attempt token. No GET, login, resume or ordinary submission automatically creates another level.

`AdaptiveAttemptResource` and `AdaptiveCandidateItemResource` define the candidate response shape. The Phase 4 UI must branch on `delivery_mode: adaptive`; it must not treat this payload as the traditional questions array.

## Persistence and concurrency

Migration `2026_09_08_170000_create_adaptive_runtime_tables.php` adds:

- `adaptive_level_runs`: immutable per-level area quotas/weights, practice flag and unique progression/start key.
- `adaptive_responses`: encrypted draft options, private scoring outcome, committed timestamp and unique decision/commit keys.

No traditional table is altered by this migration.

All adaptive mutations serialize on the progression lock, then the attempt and level. Immutable identity lookup occurs before the transaction. This is necessary under MySQL REPEATABLE READ: an ordinary SELECT before waiting for the lock can establish an outdated snapshot.

The first real MySQL contention run exposed that stale-read issue; the lock/read ordering was corrected. Separate PHP workers now verify duplicate commitment/start handling and late-write rejection.

`adaptive:expire` runs every minute through Laravel's scheduler to finalize overdue active levels and close expired progression balances. Deployment must run the usual Laravel scheduler. This command does not enable adaptive rollout.

## Traditional CBT protections included in Phase 3

The shared answer transaction now locks and rechecks the attempt before writing, including deadline revalidation. Submission and answer saving therefore serialize on the same authoritative row.

Pre-start payloads contain no question content. The existing candidate interface refreshes the paper through the start endpoint before writing. Submission/auto-submission responses include scores only under the existing result-release policy.

Regression fixtures explicitly verify withheld scores and pre-start papers, while retaining database assertions for traditional scoring, including negative marking and started legacy adaptive-labelled papers.

## Verification and Phase 4 handoff

- Combined adaptive/traditional/entity regression selection: **132 tests passed, 1,327 assertions** (including 23 Phase 3 lifecycle tests).
- Isolated MySQL concurrency suite: **3 tests passed, 42 assertions**, using separate PHP processes and actual row-lock contention.
- Frontend production build passed using `npm.cmd run build -- --outDir storage/framework/testing/phase3-build`; production build files were not replaced.
- PHP formatting checked with Pint.

The regression selection extends the Phase 2 command with `AdaptiveLifecycleTest`. The MySQL suite is `tests/Integration/AdaptiveMysqlConcurrencyTest.php`; it is intentionally outside the ordinary SQLite suite and requires `DB_CONNECTION=mysql`, `DB_DATABASE=alignex_adaptive_phase3_test` and an empty `DB_URL`. It resets only that isolated test database. The worker explicitly refuses any other database.

The additive runtime migration was applied to the local development database. MySQL race tests run only against the explicitly named `alignex_adaptive_phase3_test` database, never the development database.

Next, implement the candidate/supervisor adaptive experience: instructions and explicit start, confirm-and-continue, current-item-only navigation, version-aware retries, reconnect and next-level token persistence, release-aware recovery messages, proctor history and browser tests across enabled owners. Secondary-school policy remains unchanged. Runtime readiness stays false until the relevant UI, browser and rollout gates pass.

See the [adaptive knowledge base](adaptive.md) and [implementation plan](../adaptive-examination-audit-2026-09-08.md).
