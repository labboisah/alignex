# Adaptive Phase 6: engine research and validation foundation

Implemented: 8 September 2026. **Phase 6 is not fully accepted.** Its engineering foundation runs in shadow mode; representative calibration data, specialist criteria and consequential-use approval are still required.

No real-response calibration dataset or specialist-approved criteria have been supplied. At the owner's request, the synthetic demonstration below was generated and evaluated for engineering checks only.

## Synthetic demonstration supplied and evaluated

At the owner's request, a reproducible [synthetic validation pack](../../services/adaptive-engine/examples/synthetic-validation/README.md) now provides 60 artificial item parameters, 150 simulated response histories and explicitly illustrative criteria. The existing Python engine/validator evaluated 6,000 simulated responses: all demo thresholds passed, while an inverted-reference negative control failed as expected.

This pack uses assigned parameters, not parameters estimated from actual candidate responses. It does not map to or modify a Laravel question snapshot. Consequential approval remains false, real calibration sample size is zero, and no specialist approval is asserted. It proves neither empirical calibration nor the Phase 6 acceptance gate. See the pack for results, limitations, reproducible commands and the real-data requirements.

## What runs now

Laravel remains authoritative for candidate delivery, current questions, confirmations, deadlines, exact recovery marks, percentage penalties, disqualification and result release. The existing simple diagnostic engine continues unchanged.

An authorized administrator can now import calibration versions for an immutable snapshot, obtain independent review, run an experimental FastAPI evaluation against a recorded level, and replay that evaluation. A proposal never changes the candidate's issued question, marks, mastery or result.

The experimental engine computes a unidimensional dichotomous 2PL model:

- Probability: logistic(a * (theta - b)).
- EAP estimate using a standard normal prior on a fixed 241-point grid from -6 to 6.
- Posterior standard deviation and a central 95% posterior interval.
- Maximum Fisher-information selection among eligible items, with stable item-ID tie breaking.
- Content minimums and required-topic coverage before precision stopping.
- Maximum-length, precision and pool-exhaustion stop reasons.
- Experimental above/below-cutpoint classification only at a precision stop with coverage; otherwise uncertain/undetermined.

These are modeling choices for research, not a claim that the items fit 2PL or that the scale, prior, cutpoint or interval has been validated. Posterior SD is not automatically an empirically validated standard error. Multiple domains may require a different model. Guessing/3PL, multidimensional estimation and media items are not implemented.

The engine is deliberately separate from recovered marks. For example, a recovery penalty can reduce available marks without changing the meaning of the experimental latent scale. These quantities must never be added together.

## Admin workflow and data format

Open **Exams > Adaptive preparation > Calibration and shadow engine research**.

1. Download a JSON template for the exact frozen snapshot.
2. Fill in real calibration parameters, provenance and the specialist's criteria references.
3. Import the document as a draft.
4. A different authorized administrator reviews it for shadow use.
5. Select a reviewed version and a started level from the same snapshot, then evaluate.
6. Review estimates, evidence, coverage, proposal, engine version and error history.
7. Replay a successful run to compare the same captured request with the same engine version.
8. Revoke a version to prevent future evaluations/replays. Historical runs remain.

The interface shows the latest 50 calibration versions and runs and the latest 100 level records. It provides import errors, loading states, flash notifications, empty states, explicit disabled-engine state and revocation confirmation.

Template fields:

| Field | Meaning |
| --- | --- |
| schema_version | Must be calibration-2pl-v1 |
| snapshot_id | Existing ready frozen snapshot owned by this exam |
| source_reference | Dataset/calibration study identifier or controlled document reference |
| specialist | Responsible specialist's name/reference; recording a name does not verify qualifications |
| sample_size | Declared calibration sample size; minimum 1 is input validation, not a validity threshold |
| criteria_reference | Versioned specialist acceptance-criteria reference |
| validation_notes | Fit, scale, sampling, limitations and review notes |
| policy.min_questions / max_questions | Experimental stopping limits, 1-1000 |
| policy.min_per_area | Committed evidence minimum per included paper-row area |
| policy.target_sd | Experimental posterior-SD precision target, greater than 0 and at most 2 |
| policy.cutpoint | Optional experimental cutpoint on the declared latent scale |
| items[].id | Frozen adaptive pool-item ID, not the source question ID |
| items[].content_hash | Exact hash from the downloaded snapshot template |
| items[].a | Positive 2PL discrimination, at most 4 |
| items[].b | 2PL location within -6 to 6 |
| items[].exposure_limit | Positive snapshot-local issued-item cap for shadow eligibility |

Templates contain blank a/b/precision/exposure values deliberately. Do not replace these with plausible-looking numbers for real assessment. Imports require exactly one item entry for every frozen pool item, with no duplicates or stale hashes. Question stems/options/keys are not in the template.

Draft/reviewed/revoked is a research lifecycle. **Reviewed does not mean validated or approved for consequential scoring.** An importer cannot review their own version. Revoked versions cannot be reactivated; corrections require another immutable version and independent review.

## Internal contract and failure isolation

FastAPI exposes only authenticated POST /v1/evaluate. Interactive API documentation is disabled. Both services require the same server-only secret of at least 32 characters. The secret must never be placed in VITE_* settings or candidate code.

The request contains:

- Protocol/version, opaque request ID, frozen calibration fingerprint and attempt state version.
- Pool-item IDs, a/b parameters, area/topic identifiers and eligibility flags.
- Committed binary responses from that level.
- Required content coverage and experimental stopping policy.

No candidate name, email, registration number, selected options, question stem or answer key is transmitted. Binary response outcomes remain confidential internal assessment data.

Laravel captures requests under the progression lock, releases the lock before network I/O, and rechecks the state/version and revocation status before retaining a usable result. The HTTP client uses a 1-second connect timeout, 5-second total timeout, no redirects and no automatic retries. HTTP is allowed only on loopback; use HTTPS for a remote internal service.

Laravel validates response version, request identity, calibration fingerprint, state version, numeric ranges, item eligibility, content coverage, stopping conditions and experimental classification. Invalid, unavailable, revoked or stale responses are recorded as failed shadow runs. Errors use fixed codes rather than raw HTTP bodies or credentials.

A replay retains the original request, eligible pool and exposure counts. It never recomputes eligibility from today's database. It requires a still-reviewed calibration and compares the returned result against the original. A different engine version or output produces a failure rather than rewriting history.

The Python endpoint rejects extra fields, invalid/duplicate item references, impossible limits and bodies larger than 2 MB. The Laravel evaluation/replay routes are limited to 10 requests per minute. No production capacity claim is made from these controls.

Current shadow exposure is an absolute count of issued items within the frozen snapshot. Already-issued progression items are ineligible for another proposed selection. These are captured research constraints, not global exposure reservations. A live external engine will need atomic reservation and calibrated exposure policies before it can issue real questions.

## Persistence and authorization

The additive migration creates:

- adaptive_calibrations: frozen snapshot/version, owner, encrypted payload, fingerprint, import/review identity and research lifecycle timestamps.
- adaptive_engine_runs: immutable encrypted request/result, captured request hash, calibration/progression/level links, actor, replay parent, duration and failure code.

Existing examination, answer, scoring and ledger tables are not altered. The migration was applied to the local application database.

Research reads use the scoped adaptive-report policy. Imports, reviews, revocations, evaluations and replays additionally require exam-update permission. Exact owner and frozen snapshot/progression ownership are enforced. Template downloads also require management access. Candidate/student accounts cannot access research routes. Import/review/revoke/evaluation events are audited.

Organization, institution, professional-school and CBT-center imports/reviews are covered. Secondary-school adaptive remains blocked. Traditional CBT, historical fixed papers, diagnostic release controls and certificate/recruitment safeguards remain intact.

## Running the engine locally

From services/adaptive-engine:

```text
python -m venv .venv
.venv/Scripts/python.exe -m pip install -r requirements.lock
.venv/Scripts/python.exe -m pytest -q
```

Set ADAPTIVE_ENGINE_SECRET to the same securely generated value in the Python process environment and Laravel's server environment. Start the local service with:

```text
.venv/Scripts/python.exe -m uvicorn app:app --host 127.0.0.1 --port 8096 --no-access-log
```

On Linux/macOS use .venv/bin/python. Laravel defaults:

```dotenv
ADAPTIVE_ENGINE_SHADOW_ENABLED=false
ADAPTIVE_ENGINE_URL=http://127.0.0.1:8096
ADAPTIVE_ENGINE_SECRET=
```

Enabling shadow requests does not enable candidate pilots or consequential scoring. Existing owner/exam pilot allowlists remain independent and default off. This work did not change local .env flags or install a persistent background service.

The complete tested dependency versions are in requirements.lock. Dependencies were installed only in the ignored project-local virtual environment.

## Validation runner for the forthcoming data

validate_dataset.py evaluates supplied reference cases and emits aggregate/group bias, RMSE, posterior interval coverage, classification rate/accuracy and p95 compute time. It fails requested criteria when groups are undersized or classification is required but the engine abstains.

Input is a JSON object with:

- source_reference and criteria_reference.
- criteria: max_rmse, max_abs_bias, min_interval_coverage, min_classification_accuracy, min_classification_rate, max_p95_compute_ms, min_group_size.
- cases: each has a unique case_id, an approved evaluation group identifier, reference_theta on the same scale, and a complete request matching the internal Evaluation schema in app.py.

```text
.venv/Scripts/python.exe validate_dataset.py reference-cases.json --output validation-report.json
```

The report records the dataset hash, engine version, references and per-group metrics. It always returns consequential_approved=false. Passing configured numeric checks does not approve deployment, establish fairness or verify that reference labels are valid. Compute time excludes HTTP/database overhead; real throughput and availability need separate testing.

## Verification

The final combined backend regression selection passes **163 tests / 1,793 assertions**. Python passes **14 tests**. A separate real Laravel-to-FastAPI integration test passes **1 test / 6 assertions**, including authentication and deterministic replay. The browser calibration template/import, independent review and revocation scenario passes after removing a duplicate dialog handler from the test. The Vite build, additive local migration, PHP formatting and whitespace checks pass.

The Python test client reports two upstream deprecation warnings (httpx/Starlette and the AnyIO portal alias); they do not fail the tests. No calibrated production data, psychometric acceptance, realistic HTTP throughput or live external-engine candidate dispatch was tested. Existing MySQL contention evidence from Phase 4 is historical and was not rerun because candidate lifecycle/ledger algorithms were unchanged.

Tests cover malformed/stale engine responses, actual localhost Laravel-to-FastAPI transport and authentication, deterministic replay/mismatch, immutable records, content-hash imports, independent review, revocation, concurrent state changes, private payloads, owner/candidate authorization and continued diagnostic delivery during engine failure.

Python numerical tests use synthetic fixtures, including prior symmetry, positive/negative response symmetry, reduced uncertainty, eligible selection, coverage and stopping. No real item calibration, fairness or assessment-validity result is claimed.

## Remaining Phase 6 acceptance work

1. Receive the promised calibrated item data and specialist criteria; confirm model family, dimensionality, scale, provenance and immutable content mapping.
2. Import/review versions and evaluate representative held-out data. Review fit, bias, classification, uncertainty coverage, subgroup performance and exposure/content behavior.
3. Establish representative HTTP/database throughput, failure recovery and operational availability evidence.
4. Obtain explicit assessment-owner approval for any consequential classification rules.
5. Only then implement and validate a frozen live external-engine dispatch/reservation path, with retry/recovery semantics and approved result interpretation. There is currently no switch that promotes a shadow result to a candidate score.

Phase 7 offline adaptive delivery remains separate. Do not treat the new FastAPI service as evidence of offline or validated adaptive capability.

Technical references: [FastAPI security tools](https://fastapi.tiangolo.com/reference/security/) describe header-based dependency extraction; the service additionally validates the secret itself. [Research on EAP estimation in adaptive testing](https://pubmed.ncbi.nlm.nih.gov/19564695/) discusses estimation bias and precision considerations that require specialist review.

See the [adaptive knowledge base](adaptive.md) and [implementation plan](../adaptive-examination-audit-2026-09-08.md).
