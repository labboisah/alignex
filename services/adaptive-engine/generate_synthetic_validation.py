"""Generate and evaluate a reproducible, SYNTHETIC-ONLY research fixture.

The item parameters are assigned, not estimated from real candidates.
Nothing in this script reads or writes Laravel data or enables an engine flag.
"""
import hashlib
import json
import math
import random
from pathlib import Path

from validate_dataset import validate

SEED = 20260908
OUTPUT = Path(__file__).parent / "examples" / "synthetic-validation"
CRITERIA = {
    "max_rmse": 0.65,
    "max_abs_bias": 0.35,
    "min_interval_coverage": 0.85,
    "min_classification_accuracy": 0.90,
    "min_classification_rate": 0.25,
    "max_p95_compute_ms": 250,
    "min_group_size": 40,
}


def make_dataset():
    rng = random.Random(SEED)
    areas = ["synthetic-area-" + str(i) for i in range(1, 5)]
    items = [{
        "id": "synthetic-item-" + str(i + 1),
        "a": round(1.2 + (i % 7) * 0.1, 2),
        "b": round(-2.5 + 5 * (i // 4) / 14, 6),
        "area": areas[i % 4], "topic": None, "eligible": True,
    } for i in range(60)]
    bank = {
        "synthetic": True, "consequential_approved": False,
        "provenance": "Assigned 2PL parameters; no real response calibration or specialist approval.",
        "seed": SEED, "items": items,
    }
    fingerprint = hashlib.sha256(json.dumps(bank, sort_keys=True).encode()).hexdigest()
    cases = []
    # These are simulated ability strata, NOT demographic or tenant fairness groups.
    for group, low, high in [("lower_theta", -2.0, -0.75),
                             ("central_theta", -0.75, 0.75),
                             ("higher_theta", 0.75, 2.0)]:
        for number in range(50):
            theta = rng.uniform(low, high)
            # Fixed balanced administration, intentionally independent of engine selection.
            administered = []
            for area in areas:
                administered.extend(rng.sample([i for i in items if i["area"] == area], 10))
            responses = []
            for item in administered:
                probability = 1 / (1 + math.exp(-item["a"] * (theta - item["b"])))
                responses.append({"id": item["id"], "correct": rng.random() < probability})
            case_id = "synthetic-" + group + "-" + str(number + 1)
            cases.append({
                "case_id": case_id, "group": group, "reference_theta": theta,
                "request": {
                    "protocol": "alignex-shadow-v1", "request_id": case_id,
                    "state_version": 40, "calibration_fingerprint": fingerprint,
                    "items": items, "responses": responses, "areas": areas,
                    "required_topics": [],
                    "policy": {"min_questions": 10, "max_questions": 60,
                               "min_per_area": 2, "target_sd": 0.6, "cutpoint": 0.0},
                },
            })
    return bank, {
        "synthetic": True, "consequential_approved": False, "seed": SEED,
        "source_reference": "SYNTHETIC ONLY: generate_synthetic_validation.py seed 20260908; no observed candidates",
        "criteria_reference": "SYNTHETIC-DEMO-v1: illustrative engineering thresholds; no specialist approval",
        "criteria": CRITERIA, "cases": cases,
    }


def write_json(name, value):
    (OUTPUT / name).write_text(json.dumps(value, indent=2) + "\n", encoding="utf-8")


def main():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    bank, dataset = make_dataset()
    # Criteria are fixed above before evaluating; failures are retained, never tuned away.
    write_json("item-bank.json", bank)
    write_json("reference-cases.json", dataset)
    report = validate(dataset)
    report.update({"synthetic": True, "real_calibration_sample_size": 0,
                   "specialist_approved": False})
    write_json("validation-report.json", report)

    # A deliberately wrong reference scale must fail the same criteria.
    negative = json.loads(json.dumps(dataset))
    negative["source_reference"] += "; NEGATIVE CONTROL: inverted reference theta"
    for case in negative["cases"]:
        case["reference_theta"] = -case["reference_theta"]
    control_report = validate(negative)
    control_report.update({"synthetic": True, "negative_control": "inverted_reference_theta",
                           "specialist_approved": False})
    write_json("negative-control-report.json", control_report)
    print(json.dumps({"synthetic": True, "cases": len(dataset["cases"]), "items": len(bank["items"]),
                      "criteria_passed": report["criteria_passed"], "overall": report["overall"],
                      "groups": report["groups"],
                      "negative_control_rejected": not control_report["criteria_passed"],
                      "consequential_approved": False}, indent=2))
    if control_report["criteria_passed"]:
        raise SystemExit("Negative control unexpectedly passed; investigate the validation workflow.")


if __name__ == "__main__":
    main()
