#!/usr/bin/env python3
"""Fail-closed integrity QA for the nonauthoritative Spain choropleth cartography.
Read-only. This does NOT verify cadastral, administrative or legal boundaries.
"""
from pathlib import Path
from xml.etree import ElementTree as ET
import json
import math

ALLOWED_TAGS={"svg","g","path","title","text","rect","circle","defs","pattern"}
PREFIX="http://www.w3.org/2000/svg"

def check(root: Path):
    root=Path(root)
    paths=[root/"geo/spain-communities.geojson",root/"geo/spain-communities-display.geojson"]
    shapes=[]
    errors=[]
    for p in paths:
        try:
            obj=json.loads(p.read_text(encoding="utf-8"))
            if obj.get("type")!="FeatureCollection" or len(obj.get("features",[]))!=19:
                errors.append(f"invalid collection: {p.name}");continue
            codes=[]
            for f in obj["features"]:
                prop=f.get("properties",{});geo=prop.get("nuts2")
                geom=f.get("geometry",{})
                if geom.get("type") not in ("Polygon","MultiPolygon") or not geom.get("coordinates"):
                    errors.append(f"invalid geometry: {geo}")
                if not isinstance(geo,str) or not geo.startswith("ES") or len(geo)!=4:
                    errors.append(f"invalid NUTS2: {geo}")
                codes.append(geo)
            if len(set(codes))!=19:errors.append(f"duplicate geo in {p.name}")
            shapes.append(set(codes))
        except (OSError,ValueError,TypeError,KeyError) as exc:
            errors.append(f"unreadable {p.name}: {exc}")
    if len(shapes)==2 and shapes[0]!=shapes[1]:
        errors.append("source and display maps disagree on NUTS2 codes")
    try:
        mapping=json.loads((root/"geo/ccaa-code-map.json").read_text(encoding="utf-8"))
        mapped=set(mapping["by_cod_ccaa"].values())
        if len(mapping["by_cod_ccaa"])!=19 or not shapes or mapped!=shapes[0]:
            errors.append("NUTS2 mapping differs from source")
    except (OSError,ValueError,KeyError,TypeError) as exc:
        errors.append(f"mapping unreadable: {exc}")
    try:
        svg=ET.parse(root/"assets/observatorio/mapa-espana.svg").getroot()
        territories=[]
        for el in svg.iter():
            tag=el.tag
            if tag.startswith("{"):
                namespace,tag=tag[1:].split("}",1)
                if namespace!=PREFIX:errors.append("foreign SVG namespace")
            if tag not in ALLOWED_TAGS:errors.append("executable or unexpected SVG tag "+tag)
            for key,value in el.attrib.items():
                k=key.split("}")[-1].lower()
                if k.startswith("on") or k in ("href","src","srcset","formaction"):
                    errors.append("potentially executable SVG attribute "+k)
                if "javascript:" in value.lower() or "data:text/html" in value.lower():
                    errors.append("script-capable SVG URI")
            if "data-geo" in el.attrib:
                territories.append(el.attrib["data-geo"])
        if len(territories)!=19 or len(set(territories))!=19:
            errors.append("SVG needs exactly 19 unique territories")
        if shapes and set(territories)!=shapes[0]:
            errors.append("SVG codes and GeoJSON codes disagree")
    except (OSError,ET.ParseError) as exc:
        errors.append("SVG unreadable: "+str(exc))
    html=(root/"_private/observatorio-espana.html")
    try:
        body=html.read_text(encoding="utf-8")
        if "mapa-espana.js" not in body or "mapa-espana.css" not in body:
            errors.append("map routes absent from HTML")
    except OSError:
        errors.append("map HTML missing")
    return {"status":"PASS_RESEARCH_ONLY" if not errors else "BLOCK",
            "territories":19 if shapes and len(shapes[0])==19 else 0,
            "errors":sorted(set(errors)), "legal_boundary_claim":False,
            "production_deploy_authorized":False}

if __name__=="__main__":
    import sys
    out=check(Path(__file__).resolve().parents[1])
    print(json.dumps(out,ensure_ascii=False,indent=2))
    sys.exit(0 if out["status"]=="PASS_RESEARCH_ONLY" else 2)
