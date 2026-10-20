#!/usr/bin/env python3
"""Psicología Política specialist: independently recheck Neurohuerto proposals.
Never imports candidate rows into the production DB or Hostinger.
Cross-repository reading uses only PUBLIC metadata and its own public records.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import math
import re
from pathlib import Path
from urllib.request import Request,urlopen
from urllib.error import HTTPError,URLError
UPSTREAM="https://raw.githubusercontent.com/clucaina/NPL-CANONICAL/factory/data-candidates/review/index.json"
PREFIX="https://raw.githubusercontent.com/clucaina/psicologia-politica/integracion/v78-maestro-sin-despliegue/"
PATH=re.compile(r"^database/(T[0-9]{1,4}_[A-Za-z0-9_-]+)\.json$")
MAX_BYTES=1_900_000
def get_json(url):
    if url!=UPSTREAM and not url.startswith(PREFIX+"database/T"):
        raise ValueError("Cross-repo origin is not allowlisted")
    req=Request(url,headers={"User-Agent":"Neurohuerto-PP-Reviewer/1.0","Accept":"application/json"})
    with urlopen(req,timeout=25) as r:
        contents=r.read(MAX_BYTES+1)
    if len(contents)>MAX_BYTES:raise ValueError("Source exceeded safe limit")
    return json.loads(contents.decode("utf-8")),hashlib.sha256(contents).hexdigest()
def verify_contract(contract,fetch=get_json):
    path=contract.get("source_path","")
    match=PATH.fullmatch(str(path))
    if not match or match.group(1)!=contract.get("id"):
        return {"status":"BLOCK_BAD_ID_OR_PATH"}
    if contract.get("source_repository")!="clucaina/psicologia-politica" or contract.get("source_ref")!="integracion/v78-maestro-sin-despliegue":
        return {"status":"BLOCK_UNTRUSTED_REPOSITORY"}
    if contract.get("release_gate")!="NO_AUTO_IMPORT_NO_AUTO_DEPLOY" or contract.get("raw_values_copied") is not False:
        return {"status":"BLOCK_UNTRUSTED_PUBLICATION_OR_PRIVACY"}
    url=PREFIX+path
    try:
        batch,digest=fetch(url)
        if contract.get("raw_sha256")!=digest:
            return {"status":"BLOCK_SOURCE_CHANGED","digest_match":False}
        if not isinstance(batch,dict) or batch.get("batch_id")!=contract["id"] or not isinstance(batch.get("observations"),list):
            return {"status":"BLOCK_FORMAT_OR_ID","digest_match":True}
        observations=batch["observations"]
        if not observations or len(observations)>20000:
            return {"status":"BLOCK_ROW_COUNT","digest_match":True}
        reasons=set()
        geos=set()
        indicators=set()
        for row in observations:
            if not isinstance(row,dict):
                reasons.add("invalid_row");continue
            geo=row.get("geo_code")
            val=row.get("value")
            if not isinstance(geo,str) or not geo.startswith("ES"):
                reasons.add("non_standard_geography")
            else:geos.add(geo)
            if not isinstance(row.get("unit"),str) or not row["unit"]:
                reasons.add("missing_unit")
            if not row.get("period") or not row.get("source_id"):
                reasons.add("missing_period_or_source")
            if isinstance(val,bool) or not isinstance(val,(int,float)) or not math.isfinite(val):
                reasons.add("invalid_measurement")
            if isinstance(row.get("indicator_id"),str):indicators.add(row["indicator_id"])
        return {
            "status":"NEEDS_SOURCE_AND_LICENSE_REVIEW" if not reasons else "NEEDS_STRUCTURAL_REPAIR",
            "digest_match":True,
            "rows_independently_checked":len(observations),
            "geographies":len(geos),
            "indicators":len(indicators),
            "structural_flags":sorted(reasons),
            "release_gate":"NO_DEPLOY_NO_PUBLISH",
            "human_factual_review":False,
            "methodology_review":False}
    except (OSError,UnicodeError,ValueError,TypeError,HTTPError,URLError,KeyError):
        return {"status":"SOURCE_UNAVAILABLE","release_gate":"NO_DEPLOY_NO_PUBLISH"}
def review(upstream,prior,fetch=get_json,limit=6):
    if type(limit)!=int or limit<1 or limit>12:raise ValueError("bounded max 12")
    source=upstream.get("candidates",[])
    if upstream.get("schema")!="neurohuerto.candidate_ledger.v1" or not isinstance(source,list):
        raise ValueError("Incompatible proposal schema")
    old={x["id"]:x for x in prior.get("reviews",[]) if isinstance(x,dict) and isinstance(x.get("id"),str)}
    selected=[c for c in source if isinstance(c,dict) and c.get("id") and
              (c.get("id") not in old or old[c["id"]].get("source_blob_sha")!=c.get("source_blob_sha"))][:limit]
    changes={}
    for c in selected:
        result=verify_contract(c,fetch)
        changes[c["id"]]={"id":c["id"],"source_path":str(c.get("source_path","")),
                         "source_blob_sha":str(c.get("source_blob_sha","")),
                         "upstream_sha256":str(c.get("raw_sha256","")),
                         "independent_check":result,
                         "ready_to_import":False,
                         "consumer":"clucaina/psicologia-politica",
                         "source_code_release":"integracion/v78-maestro-sin-despliegue",
                         "no_publication_without_human_review":True}
    merged={**old,**changes}
    return {"schema":"neurohuerto.independent_reviewer.v1",
            "reviews":[merged[k] for k in sorted(merged)],
            "newly_reviewed":len(changes),
            "remaining_new":len(source)-len(old)-len(changes),
            "ready_for_production":False,
            "principle":"Reading another worker's report does not constitute source validation."}
def main():
    p=argparse.ArgumentParser()
    p.add_argument("--out",type=Path,required=True)
    p.add_argument("--prior",type=Path,required=True)
    args=p.parse_args()
    if args.prior.is_file():
        previous=json.loads(args.prior.read_text(encoding="utf-8"))
    else:previous={}
    try:
        upstream,_=get_json(UPSTREAM)
    except (OSError,UnicodeError,ValueError,HTTPError,URLError):
        # No upstream yet: keep previously verified reviews, no empty overwrite.
        print(json.dumps({"source":"unavailable","existing_reviews":len(previous.get("reviews",[]))}))
        return
    result=review(upstream,previous)
    args.out.parent.mkdir(parents=True,exist_ok=True)
    args.out.write_text(json.dumps(result,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print(json.dumps({"reviewed":result["newly_reviewed"],"total":len(result["reviews"]),
                      "ready_for_production":False}))
if __name__=="__main__":main()
