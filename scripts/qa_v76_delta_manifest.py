#!/usr/bin/env python3
"""V76 isolated staging delta audit against the verified V75 GitHub baseline.
Historical V75 SHA hashes MUST NOT be silently rewritten. NO deployment.
"""
import hashlib
import json
from pathlib import Path
from qa_v75_reconciled_manifest import inspect, ROOT, MANIFEST

APPROVED_CHANGED = frozenset({
    "_private/core.php",
    "_private/editorial.php",
    "_private/text_analysis_v2.php",
})

def inspect_delta(root=ROOT, baseline=MANIFEST):
    root=Path(root).resolve()
    historical=inspect(root,baseline)
    changes=set()
    issues=[]
    for problem in historical["issues"]:
        if problem.startswith("SHA_MISMATCH:"):
            changes.add(problem.partition(":")[2])
        else:
            issues.append(problem)
    if changes!=APPROVED_CHANGED:
        issues.append("V76_RUNTIME_CHANGES_NOT_RECONCILED:"+",".join(sorted(changes^APPROVED_CHANGED)))
    for name in APPROVED_CHANGED:
        f=root/name
        if not f.is_file() or f.is_symlink() or f.stat().st_size<100:
            issues.append("MISSING_V76_RUNTIME:"+name)
    batch=root/"database/T111_INE_IPI_TV_202608.json"
    index=root/"database/news_prepared_identity_index_v76.json"
    for name in (batch,index):
        if not name.is_file() or name.is_symlink():
            issues.append("MISSING_CANDIDATE_"+name.name)
    if not issues:
        b=json.loads(batch.read_text(encoding="utf-8"))
        n=json.loads(index.read_text(encoding="utf-8"))
        if (b.get("publication_policy")!="review_only_no_auto_publish" or
           b.get("automatic_publication") is not False or
           b.get("human_source_verification") is not False or
           len(b.get("observations",[]))!=25 or len(b.get("new_indicators",[]))!=12):
            issues.append("INVALID_CANDIDATE_PUBLICATION_STATE")
        if (n.get("version")!="V76" or n.get("publication_authorized") is not False or
           n.get("source_count")!=185 or n.get("title_count")!=153):
            issues.append("INVALID_EDITORIAL_DEDUP")
    return {
        "status":"PASS_DELTA_AUDIT_NO_GO" if not issues else "BLOCK",
        "baseline_unchanged_entries":historical["checked"]-len(changes),
        "changed_v76_entries":sorted(changes),
        "issues":issues,
        "derived_release_verified":False,
        "hostinger_reconciled":False,
        "database_validated":False,
        "deployment_authorized":False,
    }

if __name__=="__main__":
    report=inspect_delta()
    print(json.dumps(report,ensure_ascii=False,indent=2))
    raise SystemExit(0 if report["status"]=="PASS_DELTA_AUDIT_NO_GO" else 2)
