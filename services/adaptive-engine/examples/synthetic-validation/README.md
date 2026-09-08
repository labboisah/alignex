# Synthetic calibration and validation demonstration

**Synthetic only. Not calibrated question-bank data or specialist-approved criteria.**

This pack was generated and evaluated on 8 September 2026 at the owner's request. It exercises the existing Python research engine without importing data into Laravel or changing any exam, rollout flag or scoring policy.

## Contents and reproduction

- item-bank.json: 60 artificial items with assigned discrimination (a), difficulty (b), four artificial content areas and an explicit synthetic provenance flag.
- reference-cases.json: 150 simulated candidates, 40 responses each (6,000 binary responses), known generating theta and the complete existing engine request schema.
- validation-report.json: overall and ability-stratum metrics from the engine/validator.
- negative-control-report.json: results after reversing reference theta while retaining responses. The same criteria reject this intentionally incorrect scale.
- ../../generate_synthetic_validation.py: deterministic generator, seed 20260908. Timings vary between runs.

From the repository root:

```powershell
& services/adaptive-engine/.venv/Scripts/python.exe services/adaptive-engine/generate_synthetic_validation.py
```

To evaluate the saved reference cases directly:

```powershell
& services/adaptive-engine/.venv/Scripts/python.exe services/adaptive-engine/validate_dataset.py services/adaptive-engine/examples/synthetic-validation/reference-cases.json --output services/adaptive-engine/examples/synthetic-validation/rechecked-report.json
```

These item IDs and the bank fingerprint are synthetic. They do not correspond to a Laravel frozen snapshot. This is a standalone engine validation fixture, not a calibration-import document. Do not copy these a/b values onto real question IDs or fabricate content hashes to import them into an actual exam.

## Illustrative criteria, version SYNTHETIC-DEMO-v1

The following engineering thresholds were chosen before the recorded evaluation and were not changed to obtain a passing result. They are examples of the validation configuration, not psychometric standards or recommended acceptance limits for real assessments.

| Metric | Demo threshold |
| --- | --- |
| Root mean square theta error (RMSE) | At most 0.65 |
| Absolute average theta error (bias) | At most 0.35 |
| Observed coverage of the engine's nominal 95% interval | At least 85% |
| Classification accuracy among classified cases | At least 90% |
| Fraction classified above/below the artificial cutpoint | At least 25% |
| Local p95 computation time | At most 250 ms |
| Cases per simulated ability stratum | At least 40 |

The validator applies these criteria both overall and to each stratum. Lower, central and higher theta strata contain 50 cases each. They are not demographic groups, fairness evidence or samples of the five entity contexts.

The example stopping policy uses minimum 10, maximum 60 questions, at least two responses per area, posterior SD target 0.6 and an artificial cutpoint of zero. Each reference case supplies 40 balanced, preselected responses. This tests estimation and stopping/classification on fixed histories, not a complete adaptive selection trajectory or operational exposure policy.

## Recorded result

All demo criteria passed. Overall RMSE was approximately 0.316, average bias 0.0058 and interval coverage 95.3%. The engine classified 74% of cases; all classified cases matched the synthetic reference side of the cutpoint. The remaining 26% were not classified. Central-stratum classification was only 26%; overall accuracy must not be read as accuracy on every candidate.

The existing Python regression suite also passed all 14 tests (with the two previously recorded upstream deprecation warnings). The inverted-reference negative control failed, as intended. Both reports retain consequential_approved=false. The primary report also records zero real calibration candidates and specialist_approved=false.

The data generator and estimator assume the same 2PL model, and estimation receives the exact assigned generating parameters. There is no fitted item calibration, parameter-estimation error, guessing, local dependence, real item-fit analysis, differential item functioning, external standard setting or empirical validity assessment. This intentionally favorable simulation is a software check. Local computation timing excludes transport, database and concurrent-user overhead.

## What real calibration still requires

For an actual exam, collect securely handled pilot responses tied to exact immutable item IDs/content hashes and administration records. Document omissions/not-reached responses, the scoring rule, item exposure and the target candidate population; do not automatically code every missing response as incorrect. Use pseudonymous candidate references and access controls for response data.

An assessment specialist must define the intended score interpretation, suitable model and scale, calibration design/sample requirements, linking/reference design, item-fit and parameter-precision checks, coverage and classification requirements, relevant subgroup analyses, and acceptance criteria for the intended stakes. Estimate item parameters from observed data and assess them with independent validation evidence. The current demo does not implement parameter estimation.

The five use cases require separate intended-use decisions: school learning feedback, institutional assessment, professional certification, organizational assessment and CBT-center delivery cannot inherit validity merely by sharing software. Hosting at a CBT center does not establish validity for the assessment it delivers. Existing secondary-school adaptive restrictions remain in place.

After real parameters are mapped to an owned frozen snapshot, use the existing draft import and independent shadow-review process. Phase 6 consequential acceptance and Phase 7 offline adaptive delivery remain open.
