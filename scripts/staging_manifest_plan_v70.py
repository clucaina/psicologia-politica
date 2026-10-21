#!/usr/bin/env python3
"""V70 incremental staging plan: SHA256 verify, never delete or upload."""
import argparse
import hashlib
import json
from pathlib import Path

PROTECTED = ("_private/config.php", "_private/installed.lock", "uploads/",
             "database/", ".env", "analytics.js", "team-assets/", "group-assets/")

def safe_path(s):
    if not isinstance(s, str) or not s or s.startswith(("/", "\\")) or "\\" in s:
        return False
    return all(part not in ("", ".", "..") for part in s.split("/"))

def plan(root, manifest, previous=None):
    root = Path(root).resolve()
    issues = []
    changes = []
    unchanged = []
    files = manifest.get("files", [])
    if manifest.get("publication_gate") != "NO_GO":
        issues.append("EXPECTED_REVIEW_ONLY_MANIFEST")
    if manifest.get("count") != len(files):
        issues.append("MANIFEST_COUNT_MISMATCH")
    known = set()
    prior = {item["path"]: item.get("sha256") for item in (previous or {}).get("files", [])}
    for entry in files:
        name = entry.get("path")
        if not safe_path(name):
            issues.append("UNSAFE_PATH:" + str(name)); continue
        if name.casefold() in known:
            issues.append("CASE_DUPLICATE:" + name); continue
        known.add(name.casefold())
        if any(name == path or name.startswith(path) for path in PROTECTED):
            issues.append("PROTECTED_PATH:" + name); continue
        path = root / name
        if path.is_symlink() or any(x.is_symlink() for x in path.parents if x != root and root in x.parents):
            issues.append("SYMLINK:" + name); continue
        if not path.is_file():
            issues.append("MISSING:" + name); continue
        raw = path.read_bytes()
        if len(raw) != entry.get("size") or hashlib.sha256(raw).hexdigest() != entry.get("sha256"):
            issues.append("SHA_MISMATCH:" + name); continue
        (unchanged if prior.get(name) == entry["sha256"] else changes).append(name)
    return {"status": "BLOCK" if issues else "STAGING_PLAN_ONLY",
            "issues": issues, "upload_candidates": changes, "unchanged": unchanged,
            "delete_candidates": [], "touches_production": False, "remote_changes": 0}

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", default=".")
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--previous")
    parser.add_argument("--output")
    args = parser.parse_args()
    manifest = json.loads(Path(args.manifest).read_text(encoding="utf-8"))
    previous = json.loads(Path(args.previous).read_text(encoding="utf-8")) if args.previous else None
    report = plan(Path(args.root), manifest, previous)
    out = json.dumps(report, indent=2, ensure_ascii=False)
    if args.output: Path(args.output).write_text(out+"\n", encoding="utf-8")
    print(out)
    return 0 if report["status"] == "STAGING_PLAN_ONLY" else 2

if __name__ == "__main__":
    raise SystemExit(main())
