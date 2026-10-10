#!/usr/bin/env python3
import importlib.util
import json
import tempfile
from pathlib import Path
p=Path(__file__).resolve().parents[1]/"scripts/editorial_dedup_gate_v73.py"
spec=importlib.util.spec_from_file_location("editorial73",p)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.normalized_doi("https://doi.org/10.1234/ABC.1?utm_source=test")=="10.1234/abc.1"
assert m.normalized_doi("doi:10.1234/ABC.1")=="10.1234/abc.1"
assert m.source_id({"doi":"10.1234/ABC.1"})==m.source_id({"source_url":"https://doi.org/10.1234/abc.1"})
assert m.title_key({"title_es":"Política y educación"})==m.title_key({"title":"politica y educacion"})
base={"id":"t1","doi":"10.1234/ABC.1","title_es":"Polarización e IA",
      "what_happened":"Qué ocurrió","evidence":"Ensayo",
      "methodology":"Estudio","limitations":"Muestra pequeña",
      "why_it_matters":"Comparación","image_brief":"Ilustración original",
      "primary_source_url":"https://doi.org/10.1234/ABC.1"}
with tempfile.TemporaryDirectory() as d:
    folder=Path(d)/"database";folder.mkdir()
    batch={"candidates":[base,{**base,"id":"t2","doi":"doi:10.1234/abc.1",
                           "human_editor_approval":True}]}
    (folder/"lot.json").write_text(json.dumps(batch))
    report=m.inspect(Path(d))
    assert report["candidate_count"]==2
    assert len(report["repeated_source_groups"])==1
    assert report["unexpected_approvals"]==1
    assert not report["auto_publish"] and report["writes"]==0
    assert not report["missing_fields"]
print("PASS: V73 scientific editorial normalized DOI, accents, duplicates, human approval, no publication")
