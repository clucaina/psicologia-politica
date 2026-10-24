#!/usr/bin/env python3
"""Mutations that must fail the scientific SVG/NUTS2 release gate."""
from pathlib import Path
import importlib.util
import json
import shutil
import tempfile

ROOT=Path(__file__).resolve().parents[1]
modfile=ROOT/"scripts/qa_v74_cartography.py"
spec=importlib.util.spec_from_file_location("qageo",modfile)
mod=importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
assert mod.check(ROOT)["status"]=="PASS_RESEARCH_ONLY"

with tempfile.TemporaryDirectory() as d:
    root=Path(d)
    for src in ["geo/spain-communities.geojson","geo/spain-communities-display.geojson",
                "geo/ccaa-code-map.json","assets/observatorio/mapa-espana.svg",
                "_private/observatorio-espana.html"]:
        path=root/src;path.parent.mkdir(parents=True,exist_ok=True)
        shutil.copyfile(ROOT/src,path)
    svg=root/"assets/observatorio/mapa-espana.svg"
    original=svg.read_text(encoding="utf-8")
    assert '<defs>' in original and 'data-geo="ES41"' in original
    svg.write_text(original.replace("<defs>","<script>alert(1)</script><defs>",1),encoding="utf-8")
    assert mod.check(root)["status"]=="BLOCK","SVG script must fail"
    svg.write_text(original.replace('data-geo="ES41"','data-geo="ES11"',1),encoding="utf-8")
    assert mod.check(root)["status"]=="BLOCK","duplicate code must fail"
    svg.write_text(original,encoding="utf-8")
    p=root/"geo/ccaa-code-map.json";data=json.loads(p.read_text())
    data["by_cod_ccaa"]["01"]="XX00"
    p.write_text(json.dumps(data),encoding="utf-8")
    assert mod.check(root)["status"]=="BLOCK","wrong map code must fail"
print("PASS: V74 SVG injection, duplicated geography and mismatched NUTS2 blocked")
