#!/usr/bin/env python3
"""Read-only candidate NPL temporal semantics; NO imports, no values invented."""
import argparse
import json
import math
import re
from collections import Counter
from datetime import date
from pathlib import Path

PERIOD = re.compile(r"^((?:19|20)\d{2})(?:-(Q[1-4]|0[1-9]|1[0-2]))?$")
PERCENT_SHARES = {"bounded_share", "share_of_all_residents_16_74", "share_of_denominator_population"}
FREQUENCIES = {"annual": "year", "yearly": "year", "quarterly": "quarter", "monthly": "month"}

def inspect_observation(row, today):
    problems = []
    if not isinstance(row, dict):
        return ["not_an_object"]
    value = row.get("value")
    if value is None:
        problems.append("missing_not_zero")
    elif isinstance(value, bool) or not isinstance(value, (int, float)) or not math.isfinite(value):
        problems.append("not_a_finite_number")
    elif row.get("percentage_semantics") in PERCENT_SHARES and not 0 <= value <= 100:
        problems.append("bounded_share_outside_range")
    p = PERIOD.fullmatch(str(row.get("period", "")))
    freq = row.get("frequency")
    if not p:
        problems.append("invalid_period")
    else:
        year = int(p[1])
        part = p[2]
        if year > today.year:
            problems.append("future_period")
        if year == today.year and part:
            if part.startswith("Q") and int(part[1]) > (today.month - 1) // 3 + 1:
                problems.append("future_period")
            elif not part.startswith("Q") and int(part) > today.month:
                problems.append("future_period")
        kind = "quarter" if part and part.startswith("Q") else "month" if part else "year"
        if freq in FREQUENCIES and kind != FREQUENCIES[freq]:
            problems.append("frequency_period_mismatch")
    for key in ("reference_date", "measurement_period_start", "measurement_period_end"):
        if row.get(key):
            try:
                t = date.fromisoformat(row[key])
                if t > today:
                    problems.append("future_" + key)
            except (TypeError, ValueError):
                problems.append("invalid_" + key)
    start, end = row.get("measurement_period_start"), row.get("measurement_period_end")
    if bool(start) != bool(end):
        problems.append("incomplete_measurement_window")
    if start and end:
        try:
            if date.fromisoformat(start) > date.fromisoformat(end):
                problems.append("reversed_measurement_window")
        except (TypeError, ValueError):
            pass
    return sorted(set(problems))

def inspect_directory(root, today):
    counts = Counter()
    examples = []
    rows = 0
    observed_zeros = 0
    for file in sorted((Path(root)/"database").rglob("*.json")):
        try:
            data = json.loads(file.read_text(encoding="utf-8"))
        except (ValueError, OSError):
            continue
        if not isinstance(data, dict) or not isinstance(data.get("observations"), list):
            continue
        for i, obs in enumerate(data["observations"]):
            if not isinstance(obs, dict) or not all(obs.get(k) for k in ("indicator_id", "period", "geo_code")):
                continue
            rows += 1
            observed_zeros += (obs.get("value") == 0 and obs.get("value") is not False)
            for flag in inspect_observation(obs, today):
                counts[flag] += 1
                if len(examples) < 100:
                    examples.append({"file": str(file.relative_to(root)), "row": i, "flag": flag})
    return {"mode": "CANDIDATES_ONLY_NO_IMPORT", "as_of": today.isoformat(),
            "observations_checked": rows, "measured_zero_rows": observed_zeros,
            "flags": dict(sorted(counts.items())), "examples": examples,
            "approved_for_publication": False}

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--root", default=".")
    p.add_argument("--as-of", default=date.today().isoformat())
    p.add_argument("--output")
    a = p.parse_args()
    result = inspect_directory(Path(a.root), date.fromisoformat(a.as_of))
    if a.output:
        Path(a.output).write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"checked":result["observations_checked"],"flags":result["flags"],
                      "approved_for_publication":False},ensure_ascii=False))
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
