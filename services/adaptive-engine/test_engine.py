import copy
import os
import pytest
from fastapi.testclient import TestClient
from app import app
from engine import evaluate

os.environ["ADAPTIVE_ENGINE_SECRET"] = "test-only-" + "x" * 40
client = TestClient(app)
HEADERS = {"X-AlignEx-Engine-Key": os.environ["ADAPTIVE_ENGINE_SECRET"]}


def payload():
    return {"protocol": "alignex-shadow-v1", "request_id": "replay-1", "state_version": 3,
            "calibration_fingerprint": "a" * 64,
            "items": [{"id": str(i), "a": 1.0, "b": 0.0, "area": "row:1", "topic": None, "eligible": True} for i in range(10)],
            "responses": [], "areas": ["row:1"], "required_topics": [],
            "policy": {"min_questions": 2, "max_questions": 10, "min_per_area": 1, "target_sd": 0.7, "cutpoint": 0.0}}


def test_authentication_and_secret_configuration():
    assert client.post("/v1/evaluate", json=payload()).status_code == 401
    old = os.environ.pop("ADAPTIVE_ENGINE_SECRET")
    try:
        assert client.post("/v1/evaluate", json=payload(), headers=HEADERS).status_code == 503
    finally:
        os.environ["ADAPTIVE_ENGINE_SECRET"] = old


def test_eap_symmetry_uncertainty_and_replay():
    data = payload()
    baseline = evaluate(data)
    assert abs(baseline["theta"]) < 1e-5
    data["responses"] = [{"id": "0", "correct": True}]
    positive = evaluate(data)
    data["responses"][0]["correct"] = False
    negative = evaluate(data)
    assert positive["theta"] > 0 and positive["theta"] == -negative["theta"]
    assert positive["posterior_sd"] < baseline["posterior_sd"]
    assert negative == evaluate(copy.deepcopy(data))


def test_selection_excludes_used_exposed_items_and_honors_coverage():
    data = payload()
    data["items"][0]["eligible"] = False
    data["responses"] = [{"id": "1", "correct": True}]
    data["areas"].append("row:2")
    data["items"][-1].update(area="row:2", topic="topic:2")
    data["required_topics"] = ["topic:2"]
    assert evaluate(data)["selected_item_id"] == "9"


def test_maximum_stop_cannot_claim_precision_or_classification():
    data = payload()
    data["policy"]["max_questions"] = 2
    data["responses"] = [{"id": str(i), "correct": True} for i in range(2)]
    result = evaluate(data)
    assert result["stop_reason"] == "max_length"
    assert result["experimental_classification"] == "undetermined"


def test_precision_and_pool_exhaustion():
    data = payload()
    data["policy"]["target_sd"] = 1.0
    data["responses"] = [{"id": str(i), "correct": True} for i in range(2)]
    assert evaluate(data)["stop_reason"] == "precision"
    data["responses"] = []
    for item in data["items"]:
        item["eligible"] = False
    assert evaluate(data)["stop_reason"] == "pool_exhausted"


@pytest.mark.parametrize("change", [
    lambda d: d["responses"].append({"id": "unknown", "correct": True}),
    lambda d: d["items"][0].update(a=-1.0),
    lambda d: d.update(candidate_name="Not permitted"),
    lambda d: d["policy"].update(max_questions=0),
    lambda d: d["responses"].extend([{"id": "0", "correct": True}] * 2),
])
def test_invalid_contracts(change):
    data = payload()
    change(data)
    assert client.post("/v1/evaluate", json=data, headers=HEADERS).status_code == 422


def test_http_contract():
    response = client.post("/v1/evaluate", json=payload(), headers=HEADERS)
    assert response.status_code == 200
    assert response.json() == evaluate(payload())
