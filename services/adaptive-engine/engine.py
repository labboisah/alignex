"""Deterministic experimental 2PL/EAP engine. Never issues grades or certificates."""
import math

VERSION = "shadow-2pl-eap-v1"
GRID = tuple(-6 + i * 0.05 for i in range(241))


def probability(theta, a, b):
    return 1 / (1 + math.exp(max(-60, min(60, -a * (theta - b)))))


def evaluate(data):
    items = {item["id"]: item for item in data["items"]}
    responses = data["responses"]
    logs = []
    for theta in GRID:
        value = -theta * theta / 2
        for response in responses:
            item = items[response["id"]]
            p = min(1 - 1e-12, max(1e-12, probability(theta, item["a"], item["b"])))
            value += math.log(p if response["correct"] else 1 - p)
        logs.append(value)
    peak = max(logs)
    weights = [math.exp(value - peak) for value in logs]
    total = sum(weights)
    weights = [value / total for value in weights]
    theta = sum(t * w for t, w in zip(GRID, weights))
    sd = math.sqrt(sum((t - theta) ** 2 * w for t, w in zip(GRID, weights)))
    cumulative = 0
    lower, upper = None, None
    for t, w in zip(GRID, weights):
        cumulative += w
        if lower is None and cumulative >= 0.025:
            lower = t
        if upper is None and cumulative >= 0.975:
            upper = t
    used = {r["id"] for r in responses}
    counts = {area: 0 for area in data["areas"]}
    topics = set()
    for r in responses:
        item = items[r["id"]]
        counts[item["area"]] += 1
        if item["topic"]:
            topics.add(item["topic"])
    coverage = all(counts[area] >= data["policy"]["min_per_area"] for area in counts)
    coverage = coverage and all(topic in topics for topic in data["required_topics"])
    eligible = [item for item in items.values() if item["id"] not in used and item["eligible"]]
    missing_areas = [area for area in data["areas"] if counts[area] < data["policy"]["min_per_area"]]
    missing_topics = [topic for topic in data["required_topics"] if topic not in topics]
    if missing_areas:
        eligible = [item for item in eligible if item["area"] == missing_areas[0]]
    if missing_topics:
        # Enforce topic coverage inside the required area when one is pending.
        relevant = [topic for topic in missing_topics if any(i["topic"] == topic for i in eligible)]
        if relevant:
            eligible = [item for item in eligible if item["topic"] == relevant[0]]
        elif not missing_areas:
            eligible = []
    n = len(responses)
    reason = None
    if n >= data["policy"]["max_questions"]:
        reason = "max_length"
    elif n >= data["policy"]["min_questions"] and coverage and sd <= data["policy"]["target_sd"]:
        reason = "precision"
    elif not eligible:
        reason = "pool_exhausted"
    selected = None
    if reason is None:
        def rank(item):
            p = probability(theta, item["a"], item["b"])
            return (-item["a"] ** 2 * p * (1 - p), item["id"])
        selected = sorted(eligible, key=rank)[0]["id"]
    classification = "undetermined"
    cutoff = data["policy"]["cutpoint"]
    if reason == "precision" and cutoff is not None:
        classification = "above_cutpoint" if lower > cutoff else "below_cutpoint" if upper < cutoff else "uncertain"
    return {
        "protocol": "alignex-shadow-v1", "engine_version": VERSION,
        "request_id": data["request_id"], "state_version": data["state_version"],
        "calibration_fingerprint": data["calibration_fingerprint"],
        "theta": round(theta, 6), "posterior_sd": round(sd, 6),
        "interval_95": [round(lower, 6), round(upper, 6)],
        "evidence_count": n, "coverage_satisfied": coverage,
        "action": "stop" if reason else "select", "stop_reason": reason,
        "selected_item_id": selected, "experimental_classification": classification,
        "interpretation": "experimental_shadow_only",
    }
