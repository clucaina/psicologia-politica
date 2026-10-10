#!/usr/bin/env python3
from datetime import date
from pathlib import Path
import importlib.util
import json
import tempfile

path=Path(__file__).resolve().parents[1]/"scripts/npl_semantic_gate_v73.py"
spec=importlib.util.spec_from_file_location("npl73",path)
m=importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
now=date(2026,10,10)
base={"indicator_id":"X","geo_code":"ES","period":"2025","value":0,
      "frequency":"annual","percentage_semantics":"bounded_share"}
assert m.inspect_observation(base,now)==[]
assert "missing_not_zero" in m.inspect_observation({**base,"value":None},now)
assert "future_period" in m.inspect_observation({**base,"period":"2027"},now)
assert "frequency_period_mismatch" in m.inspect_observation({**base,"period":"2025-Q2"},now)
assert "bounded_share_outside_range" in m.inspect_observation({**base,"value":120},now)
assert "bounded_share_outside_range" not in m.inspect_observation({**base,"value":-5,"percentage_semantics":"relative_change"},now)
assert "reversed_measurement_window" in m.inspect_observation({**base,"measurement_period_start":"2025-10-01","measurement_period_end":"2025-09-01"},now)
assert "future_reference_date" in m.inspect_observation({**base,"reference_date":"2030-01-01"},now)
with tempfile.TemporaryDirectory() as d:
    folder=Path(d)/"database"; folder.mkdir()
    (folder/"lot.json").write_text(json.dumps({"observations":[base,{**base,"period":"2027"}]}))
    result=m.inspect_directory(Path(d),now)
    assert result["observations_checked"]==2
    assert result["measured_zero_rows"]==2
    assert result["flags"]["future_period"]==1
    assert not result["approved_for_publication"]
print("PASS: V73 temporal semantics, zero/NULL, period/frequency, bounded shares, candidates no-publish")
