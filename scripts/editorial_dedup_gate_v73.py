#!/usr/bin/env python3
"""Editorial pre-publication audit. Duplicate identities are review flags, not removals."""
import argparse
import json
import re
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path
from urllib.parse import urlparse, unquote

DOI = re.compile(r"10\.\d{4,9}/[-._;()/:a-z0-9]+", re.I)
NEEDED = {
    "title": ("title_es", "title"),
    "primary_source": ("primary_source_url", "source_url"),
    "what_happened": ("what_happened",),
    "evidence": ("evidence",),
    "methodology": ("methodology",),
    "limitations": ("limitations", "limits"),
    "importance": ("why_it_matters", "importance"),
    "original_image_brief": ("image_brief", "original_image_brief"),
}

def normalized_doi(value):
    if not isinstance(value, str):
        return None
    unescaped = unquote(value.strip()).lower()
    m = DOI.search(unescaped)
    return m.group().rstrip(".,;:)") if m else None

def source_id(item):
    for key in ("doi", "primary_source_url", "source_url"):
        value = item.get(key)
        doi = normalized_doi(value)
        if doi:
            return "doi:" + doi
        if key != "doi" and isinstance(value, str):
            p = urlparse(value)
            if p.scheme == "https" and p.hostname and not p.username and not p.password:
                return "url:" + p.hostname.lower() + p.path.rstrip("/").lower()
    return None

def title_key(item):
    raw = item.get("title_es") or item.get("title") or ""
    norm = unicodedata.normalize("NFKD", raw.lower())
    return re.sub(r"[^a-z0-9]+", " ", "".join(c for c in norm if not unicodedata.combining(c))).strip()

def inspect(root):
    rows = []
    for f in sorted((Path(root)/"database").rglob("*.json")):
        try:
            obj = json.loads(f.read_text(encoding="utf-8"))
        except (ValueError, OSError):
            continue
        if not isinstance(obj, dict):
            continue
        for prop in ("candidates", "items"):
            items = obj.get(prop)
            if not isinstance(items, list):
                continue
            for index, item in enumerate(items):
                if not isinstance(item, dict) or not ("what_happened" in item and "evidence" in item):
                    continue
                missing = [name for name, keys in NEEDED.items()
                           if not any(isinstance(item.get(k), str) and item[k].strip() for k in keys)]
                rows.append({"file":str(f.relative_to(root)),"index":index,"id":item.get("id"),
                             "source":source_id(item),"title":title_key(item),"missing":missing,
                             "misapproved":any(item.get(k) is True for k in
                                               ("human_editor_approval","publication_approved","publication_authorized"))})
    identities=defaultdict(list)
    titles=defaultdict(list)
    for row in rows:
        if row["source"]:
            identities[row["source"]].append(row)
        if row["title"]:
            titles[row["title"]].append(row)
    return {"status":"HUMAN_REVIEW_REQUIRED","candidate_count":len(rows),
            "unidentified_sources":sum(r["source"] is None for r in rows),
            "repeated_source_groups":{k:[{"file":x["file"],"id":x["id"]} for x in v]
                                      for k,v in identities.items() if len(v)>1},
            "same_title_different_source_groups":
                {k:len(v) for k,v in titles.items() if len({x["source"] for x in v})>1},
            "missing_fields":dict(Counter(m for r in rows for m in r["missing"])),
            "unexpected_approvals":sum(r["misapproved"] for r in rows),
            "auto_publish":False,"writes":0,
            "note":"Se detectan posibles duplicados; jamás se publican o borran automáticamente."}

def main():
    p=argparse.ArgumentParser()
    p.add_argument("--root",default=".")
    p.add_argument("--output")
    args=p.parse_args()
    result=inspect(Path(args.root))
    if args.output:
        Path(args.output).write_text(json.dumps(result,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print(json.dumps({"candidate_count":result["candidate_count"],"duplicate_groups":
                      len(result["repeated_source_groups"]),"status":result["status"]},
                     ensure_ascii=False))
if __name__=="__main__":
    main()
