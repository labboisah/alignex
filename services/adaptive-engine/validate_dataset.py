"""Evaluate supplied reference cases. Passing criteria is NOT deployment approval."""
import argparse
import hashlib
import json
import math
import statistics
import time
from app import Evaluation
from engine import VERSION, evaluate


def validate(dataset):
    cases = dataset["cases"]
    if not cases:
        raise ValueError("At least one reference case is required")
    if not dataset.get("source_reference") or not dataset.get("criteria_reference"):
        raise ValueError("Record the dataset and specialist criteria references")
    results = []
    seen = set()
    for case in cases:
        if case["case_id"] in seen:
            raise ValueError("Duplicate case ID")
        seen.add(case["case_id"])
        reference = case["reference_theta"]
        if not isinstance(reference, (int, float)) or not math.isfinite(reference) or not -6 <= reference <= 6:
            raise ValueError("Reference theta must be finite and on the declared [-6, 6] scale")
        request = Evaluation.model_validate(case["request"]).model_dump()
        started = time.perf_counter()
        result = evaluate(request)
        elapsed = (time.perf_counter() - started) * 1000
        error = result["theta"] - reference
        classified = result["experimental_classification"] in ("above_cutpoint", "below_cutpoint")
        cutoff = request["policy"]["cutpoint"]
        correct = classified and ((result["experimental_classification"] == "above_cutpoint") == (reference > cutoff))
        results.append({"case_id": case["case_id"], "group": case["group"], "error": error,
                        "covered": result["interval_95"][0] <= reference <= result["interval_95"][1],
                        "classified": classified, "correct": correct, "latency_ms": elapsed})

    def metrics(rows):
        classified = sum(r["classified"] for r in rows)
        return {"n": len(rows), "bias": statistics.mean(r["error"] for r in rows),
                "rmse": math.sqrt(statistics.mean(r["error"] ** 2 for r in rows)),
                "interval_coverage": sum(r["covered"] for r in rows) / len(rows),
                "classification_rate": classified / len(rows),
                "classification_accuracy": sum(r["correct"] for r in rows) / classified if classified else None,
                "p95_compute_ms": sorted(r["latency_ms"] for r in rows)[math.ceil(0.95 * len(rows)) - 1]}

    groups = {group: metrics([r for r in results if r["group"] == group]) for group in sorted({r["group"] for r in results})}
    criteria = dataset["criteria"]
    required = {"max_rmse", "max_abs_bias", "min_interval_coverage", "min_classification_accuracy", "min_classification_rate", "max_p95_compute_ms", "min_group_size"}
    if set(criteria) != required or any(not isinstance(v, (int, float)) or not math.isfinite(v) or v < 0 for v in criteria.values()):
        raise ValueError("Provide all finite, non-negative acceptance criteria")
    if any(criteria[k] > 1 for k in ["min_interval_coverage", "min_classification_accuracy", "min_classification_rate"]) or criteria["min_group_size"] < 1:
        raise ValueError("Rates must be in [0,1]; minimum group size must be positive")

    def passed(m):
        accuracy = m["classification_accuracy"]
        return (m["n"] >= criteria["min_group_size"] and m["rmse"] <= criteria["max_rmse"]
                and abs(m["bias"]) <= criteria["max_abs_bias"] and m["interval_coverage"] >= criteria["min_interval_coverage"]
                and m["classification_rate"] >= criteria["min_classification_rate"]
                and (accuracy is not None and accuracy >= criteria["min_classification_accuracy"] or criteria["min_classification_accuracy"] == 0)
                and m["p95_compute_ms"] <= criteria["max_p95_compute_ms"])

    overall = metrics(results)
    return {"engine_version": VERSION, "dataset_hash": hashlib.sha256(json.dumps(dataset, sort_keys=True).encode()).hexdigest(),
            "source_reference": dataset["source_reference"], "criteria_reference": dataset["criteria_reference"],
            "overall": overall, "groups": groups, "criteria_passed": passed(overall) and all(passed(g) for g in groups.values()),
            "consequential_approved": False,
            "limitations": "Reference-case computation only. Requires independent scale, sampling, DIF/fairness, load, availability and owner review. Timings exclude HTTP and database overhead."}


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("dataset")
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    with open(args.dataset, encoding="utf-8") as source:
        report = validate(json.load(source))
    with open(args.output, "w", encoding="utf-8") as target:
        json.dump(report, target, indent=2)
    raise SystemExit(0 if report["criteria_passed"] else 1)
