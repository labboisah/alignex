from test_engine import payload
from validate_dataset import validate
import pytest


def dataset():
    return {"source_reference": "Synthetic unit test only", "criteria_reference": "Synthetic thresholds",
            "criteria": {"max_rmse": 1, "max_abs_bias": 1, "min_interval_coverage": 1,
                         "min_classification_accuracy": 0, "min_classification_rate": 0,
                         "max_p95_compute_ms": 10000, "min_group_size": 1},
            "cases": [{"case_id": "a", "group": "synthetic", "reference_theta": 0, "request": payload()}]}


def test_reference_metrics_and_no_automatic_approval():
    result = validate(dataset())
    assert result["criteria_passed"]
    assert not result["consequential_approved"]
    assert result["overall"]["rmse"] < 1e-6
    assert result["overall"]["classification_accuracy"] is None


def test_empty_reference_dataset_and_missing_groups_fail():
    data = dataset()
    data["cases"] = []
    with pytest.raises(ValueError):
        validate(data)
    data = dataset()
    data["criteria"]["min_group_size"] = 2
    assert not validate(data)["criteria_passed"]


def test_requiring_classification_cannot_pass_abstentions():
    data = dataset()
    data["criteria"]["min_classification_accuracy"] = 0.9
    assert not validate(data)["criteria_passed"]
