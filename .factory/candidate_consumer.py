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
# A source blob SHA alone does not capture changes to provenance, digest or
# publication restrictions in the upstream ledger.
REVIEW_CONTRACT_FIELDS = (
    "schema", "id", "source_path", "source_repository", "source_ref",
    "source_blob_sha", "raw_sha256", "raw_values_copied", "release_gate",
    "candidate_kind", "independent_statistical_review_allowed",
)
def contract_fingerprint(candidate):
    """Recheck when any review-relevant source or safety assertion changes."""
    relevant = {key: candidate.get(key) for key in REVIEW_CONTRACT_FIELDS}
    encoded = json.dumps(relevant, sort_keys=True, ensure_ascii=False,
                         separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(encoded).hexdigest()

def get_json(url):
    if url!=UPSTREAM and not url.startswith(PREFIX+"database/T"):
        raise ValueError("Cross-repo origin is not allowlisted")
    req=Request(url,headers={"User-Agent":"Neurohuerto-PP-Reviewer/1.0","Accept":"application/json"})
    with urlopen(req,timeout=25) as r:
        contents=r.read(MAX_BYTES+1)
    if len(contents)>MAX_BYTES:raise ValueError("Source exceeded safe limit")
    return json.loads(contents.decode("utf-8")),hashlib.sha256(contents).hexdigest()
def verify_contract(contract,fetch=get_json):
    if contract.get("candidate_kind")=="DOCUMENT_ONLY" or contract.get("independent_statistical_review_allowed") is False:
        return {"status":"BLOCK_NON_STATISTICAL_DOCUMENT","release_gate":"NO_DEPLOY_NO_PUBLISH"}
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
        if not isinstance(batch,dict) or batch.get("batch_id")!=contract["id"]:
            return {"status":"BLOCK_FORMAT_OR_ID","digest_match":True}
        if not isinstance(batch.get("observations"),list):
            # Some NPL source references are authentic editorial/document batches,
            # not numeric series. Preserve traceability without statistical promotion.
            return {"status":"BLOCK_NON_STATISTICAL_DOCUMENT","digest_match":True,
                    "release_gate":"NO_DEPLOY_NO_PUBLISH"}
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
            try:
                valid_measurement = (not isinstance(val,bool) and isinstance(val,(int,float))
                                     and math.isfinite(val))
            except (OverflowError, ValueError, TypeError):
                valid_measurement = False
            if not valid_measurement:
                reasons.add("invalid_measurement")
            indicator = row.get("indicator_id")
            if not isinstance(indicator, str) or not indicator.strip():
                reasons.add("missing_indicator_id")
            else:
                indicators.add(indicator)
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
# Only a complete, fail-closed review can be reused. Prior JSON is not a
# trusted approval source, even when it repeats the upstream fingerprint.
REUSABLE_STATUSES = frozenset({
    "NEEDS_SOURCE_AND_LICENSE_REVIEW", "NEEDS_STRUCTURAL_REPAIR",
    "BLOCK_NON_STATISTICAL_DOCUMENT", "BLOCK_BAD_ID_OR_PATH",
    "BLOCK_UNTRUSTED_REPOSITORY", "BLOCK_UNTRUSTED_PUBLICATION_OR_PRIVACY",
    "BLOCK_SOURCE_CHANGED", "BLOCK_FORMAT_OR_ID", "BLOCK_ROW_COUNT",
})
def reusable_prior_review(previous, candidate):
    if not isinstance(previous, dict):
        return False
    check = previous.get("independent_check")
    return (previous.get("source_contract_sha256") == contract_fingerprint(candidate)
            and previous.get("source_path") == candidate.get("source_path")
            and previous.get("upstream_sha256") == candidate.get("raw_sha256")
            and previous.get("consumer") == "clucaina/psicologia-politica"
            and previous.get("source_code_release") == "integracion/v78-maestro-sin-despliegue"
            and previous.get("ready_to_import") is False
            and previous.get("no_publication_without_human_review") is True
            and isinstance(check, dict)
            and isinstance(check.get("status"), str)
            and check["status"] in REUSABLE_STATUSES)

def review(upstream,prior,fetch=get_json,limit=6):
    if type(limit)!=int or limit<1 or limit>12:raise ValueError("bounded max 12")
    source=upstream.get("candidates",[])
    if upstream.get("schema")!="neurohuerto.candidate_ledger.v1" or not isinstance(source,list):
        raise ValueError("Incompatible proposal schema")
    old={x["id"]:x for x in prior.get("reviews",[]) if isinstance(x,dict) and isinstance(x.get("id"),str)}
    # Validate upstream identifiers before dictionary lookup or queue selection.
    # A duplicate ID makes the source ambiguous and must not be silently overwritten.
    seen=set()
    eligible=[]
    for c in source:
        if not isinstance(c,dict):
            # A malformed ledger entry must never vanish from the review queue.
            # Otherwise a damaged upstream ledger could appear fully reviewed.
            raise ValueError("Malformed upstream candidate record")
        ident=c.get("id")
        if not isinstance(ident,str) or not ident.strip() or ident in seen:
            raise ValueError("Malformed or duplicated upstream candidate ID")
        seen.add(ident)
        if c.get("candidate_kind")=="DOCUMENT_ONLY" or c.get("independent_statistical_review_allowed") is False:
            # A formerly numeric candidate can become document-only without a
            # blob change. Never carry its old statistical clearance forward.
            old.pop(ident, None)
            continue
        eligible.append(c)
    pending=[c for c in eligible if not reusable_prior_review(old.get(c["id"]), c)]
    selected=pending[:limit]
    changes={}
    for c in selected:
        result=verify_contract(c,fetch)
        changes[c["id"]]={"id":c["id"],"source_path":str(c.get("source_path","")),
                         "source_contract_sha256":contract_fingerprint(c),
                         "source_blob_sha":str(c.get("source_blob_sha","")),
                         "upstream_sha256":str(c.get("raw_sha256","")),
                         "independent_check":result,
                         "ready_to_import":False,
                         "consumer":"clucaina/psicologia-politica",
                         "source_code_release":"integracion/v78-maestro-sin-despliegue",
                         "no_publication_without_human_review":True}
    # Retain historical records for traceability, but never propagate an old
    # import approval or publication permission into the new report. Sanitizing
    # happens AFTER queue selection: a forged approval must still force recheck.
    merged={}
    for ident,record in old.items():
        safe=dict(record)
        unsafe_inherited = (record.get("ready_to_import") is not False or
                            record.get("no_publication_without_human_review") is not True)
        safe["ready_to_import"]=False
        safe["no_publication_without_human_review"]=True
        check=safe.get("independent_check")
        if (unsafe_inherited or not isinstance(check,dict) or
                not isinstance(check.get("status"),str) or
                check["status"] not in REUSABLE_STATUSES):
            safe["independent_check"]={
                "status":"ARCHIVED_UNVERIFIED",
                "release_gate":"NO_DEPLOY_NO_PUBLISH"}
        merged[ident]=safe
    merged.update(changes)
    return {"schema":"neurohuerto.independent_reviewer.v1",
            "reviews":[merged[k] for k in sorted(merged)],
            "newly_reviewed":len(changes),
            "remaining_new":len(pending)-len(changes),
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
