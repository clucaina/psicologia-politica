#!/usr/bin/env python3
"""Descriptive, read-only NPL regional comparison of an existing vetted-format batch.
A same-unit comparison is not a causal claim nor approval to publish candidates.
"""
from __future__ import annotations
import argparse
import html
import json
import math
from pathlib import Path

def compare(batch:dict, indicator:str, period:str, subgroup:str)->dict:
    metas={x.get("indicator_id"):x for x in batch.get("new_indicators",[]) if isinstance(x,dict)}
    chosen=[x for x in batch.get("observations",[]) if isinstance(x,dict)
            and x.get("indicator_id")==indicator and x.get("period")==period
            and x.get("subgroup")==subgroup]
    meta=metas.get(indicator,{})
    if not chosen: raise ValueError("No matching observations for this indicator, period and population subgroup")
    errors=[]
    entries=[]
    keys=set()
    units=set()
    origins=set()
    for row in chosen:
        geo=row.get("geo_code")
        value=row.get("value")
        if not isinstance(geo,str) or not (geo=="ES" or (len(geo)==4 and geo.startswith("ES"))):
            errors.append("Unsupported geography identifier")
            continue
        if geo in keys:
            errors.append("Duplicate observation per geography; comparison forbidden")
            continue
        keys.add(geo)
        if isinstance(value,bool) or not isinstance(value,(int,float)) or not math.isfinite(value):
            errors.append("Missing or non-finite numeric value for "+geo)
            continue
        units.add(row.get("unit"))
        origins.add((row.get("source_id"),row.get("dataset_code"),row.get("methodology_flag"),
                     row.get("measurement_type")))
        entries.append({"geo":geo,"name":row.get("geography") or geo,"value":float(value),
                        "source_url":row.get("source_url",""),"source_locator":row.get("source_locator","")})
    if len(units)!=1 or None in units:errors.append("Incompatible or missing measurement units")
    if len(origins)!=1:errors.append("Incompatible sources, dataset versions, methods or measurement types")
    reference=next((e for e in entries if e["geo"]=="ES"),None)
    if reference is None:errors.append("Missing national baseline (ES); no regional deltas are computed")
    if reference and reference["value"]==0:errors.append("Zero national baseline: percentage differences undefined")
    if len(entries)<2:errors.append("Insufficient comparable geographies")
    if errors:
        return {"status":"BLOCK_COMPARISON","errors":sorted(set(errors)),
                "indicator":indicator,"period":period,"subgroup":subgroup,
                "publication_authorized":False,"observations_checked":len(chosen),"regions":[]}
    unit=next(iter(units))
    regions=[]
    for e in entries:
        if e["geo"]=="ES":continue
        regions.append({"geo":e["geo"],"name":e["name"],
                        "value":round(e["value"],4),
                        "difference_absolute":round(e["value"]-reference["value"],4),
                        "difference_percent_of_national":round((e["value"]/reference["value"]-1)*100,2)})
    regions.sort(key=lambda e:(-e["value"],e["geo"]))
    return {"status":"CANDIDATE_RESEARCH_ONLY_NO_DEPLOY",
            "batch_id":batch.get("batch_id"),"indicator":indicator,"label":meta.get("label_es",indicator),
            "period":period,"subgroup":subgroup,"unit":unit,
            "national":{"value":reference["value"],"geo":"ES"},
            "regions":regions,"observations_checked":len(chosen),
            "regions_compared":len(regions),
            "source_id":next(iter(origins))[0],"dataset_code":next(iter(origins))[1],
            "publication_authorized":False,
            "editorial_status":meta.get("status","candidate_unverified"),
            "explanation":"Differentials are descriptive deviations from the national value, not causal or adjusted gaps.",
            "limits":meta.get("methodology_note","Review original source and denominator before publication."),
            "evidence_url":reference["source_url"] if str(reference["source_url"]).startswith("https://") else ""}
def render(report:dict)->str:
    esc=lambda x:html.escape(str(x),quote=True)
    title=esc(report.get("label",report.get("indicator","NPL")))
    if report["status"]=="BLOCK_COMPARISON":
        body="<h2>Comparación bloqueada</h2>"+"".join("<p>"+esc(e)+"</p>" for e in report["errors"])
    else:
        maxv=max(0.00001,max(r["value"] for r in report["regions"]))
        bars=[]
        for r in report["regions"]:
            width=100*r["value"]/maxv if maxv>0 else 0
            bars.append(f'<div class="bar" role="group" aria-label="{esc(r["name"])}">'
                f'<span>{esc(r["name"])}</span><div class="track"><div class="fill" style="width:{width:.1f}%"></div></div>'
                f'<strong>{esc(r["value"])} {esc(report["unit"])}</strong>'
                f'<small>{r["difference_percent_of_national"]:+.2f}% respecto al valor nacional</small></div>')
        body=(f'<h2>{title} · {esc(report["period"])}</h2><p>Población: <b>{esc(report["subgroup"])}</b> · Referencia España: '
              f'<b>{esc(report["national"]["value"])} {esc(report["unit"])}</b></p>'+
              "".join(bars)+f'<p class="limits">{esc(report["limits"])}</p>')
    return """<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comparador NPL · Candidatos</title><style>
:root{font:15px system-ui,sans-serif;color:#fbf1ea;background:#20121e}*{box-sizing:border-box}
body{margin:0;background:linear-gradient(155deg,#4c2c40,#20121e 65%);min-height:100vh}
main{max-width:920px;margin:auto;padding:28px 18px}h1{font-weight:550;font-size:clamp(32px,5vw,60px);margin:10px 0}
h2{font-size:25px}p{line-height:1.6}.eyebrow{font-size:11px;letter-spacing:.2em;color:#e5c5cd}
.caution{padding:14px;border:1px solid #c8969c;border-radius:12px;background:#452938}
.bar{display:grid;grid-template-columns:minmax(125px,1fr) minmax(120px,2fr) auto;gap:8px;align-items:center;
margin:12px 0;border-bottom:1px solid #65445c;padding-bottom:9px}
.track{height:19px;border-radius:8px;background:#513247;overflow:hidden}
.fill{background:linear-gradient(90deg,#ad6178,#e4a4a5);height:100%}
small{grid-column:2/-1;color:#d5bbc4;text-align:right}strong{white-space:nowrap}
.limits{color:#dbc2ce;border-top:1px solid #785169;padding-top:20px}
@media(max-width:580px){.bar{grid-template-columns:1fr 1fr}.track{grid-column:1/3;grid-row:2}.bar strong{justify-self:end}
.bar small{grid-column:1/3;grid-row:3}}
</style></head><body><main><div class="eyebrow">NPL · CONFLICTÓMETRO · INVESTIGACIÓN</div><h1>Comparación regional</h1>
<p class="caution">CANDIDATO · NO PUBLICADO · NO CERTIFICA EXACTITUD DE LA FUENTE NI CAUSALIDAD. Datos de un archivo local de investigación; no es el estado de Hostinger.</p>"""+body+"""<footer><p>Sin despliegue. Las comparaciones solo se calculan para mismo indicador, periodo, subgrupo, unidad y origen.</p></footer></main></body></html>"""
def main():
    p=argparse.ArgumentParser()
    p.add_argument("--file",required=True,type=Path)
    p.add_argument("--root",default=".",type=Path)
    p.add_argument("--indicator",required=True)
    p.add_argument("--period",required=True)
    p.add_argument("--subgroup",required=True)
    p.add_argument("--out",required=True,type=Path)
    a=p.parse_args()
    root=a.root.resolve()
    target=(root/a.file).resolve()
    base=(root/"database").resolve()
    if not target.is_relative_to(base):
        raise SystemExit("Only project-local research data within database/ are supported")
    batch=json.loads(target.read_text(encoding="utf-8"))
    report=compare(batch,a.indicator,a.period,a.subgroup)
    a.out.mkdir(parents=True,exist_ok=True)
    (a.out/"NPL_comparacion.json").write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding="utf-8")
    (a.out/"NPL_comparacion.html").write_text(render(report),encoding="utf-8")
    print(json.dumps({"status":report["status"],"regions":report.get("regions_compared",0),
                      "publication_authorized":False},ensure_ascii=False))
    if report["status"]=="BLOCK_COMPARISON":raise SystemExit(2)
if __name__=="__main__":main()
