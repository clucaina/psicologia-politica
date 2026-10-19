#!/usr/bin/env python3
"""Branch source inventory with zero app execution and no private file names in output."""
import json
import os
import subprocess
from pathlib import Path
from datetime import datetime, timezone

SOURCE_EXT={".py",".php",".js",".jsx",".ts",".tsx",".html",".css",".vue",".go"}
IGNORE_PREFIX=("node_modules/","vendor/",".github/",".factory/","dist/","build/")
def git(*args):
    return subprocess.check_output(["git",*args],text=True,stderr=subprocess.DEVNULL).strip()
def main():
    refs=git("for-each-ref","--format=%(refname:short)","refs/remotes/origin").splitlines()
    rows=[]
    for ref in refs:
        if ref.endswith("/HEAD"): continue
        names=git("ls-tree","-r","--name-only",ref).splitlines()
        count=sum(Path(n).suffix.lower() in SOURCE_EXT and not n.startswith(IGNORE_PREFIX) for n in names)
        assets=sum(Path(n).suffix.lower() in {".glb",".gltf",".blend",".png",".jpg",".webp"} for n in names)
        package=sum(n in {"package.json","composer.json","pyproject.toml","requirements.txt"} for n in names)
        has_workflows=any(n.startswith(".github/workflows/") for n in names)
        rows.append({"branch":ref.replace("origin/","",1),"source_code_files":count,"asset_files":assets,
            "dependency_manifests":package,"workflow_present":has_workflows,
            "sha":git("rev-parse",ref)[:12],"commit_date":git("show","-s","--format=%cI",ref)})
    rows.sort(key=lambda r:(r["source_code_files"],r["commit_date"]),reverse=True)
    mainrow=next((r for r in rows if r["branch"]=="main"),None)
    report={
      "observed_utc":datetime.now(timezone.utc).isoformat(),
      "repository":os.getenv("GITHUB_REPOSITORY","local"),
      "branch_count":len(rows),"main_source_files":mainrow["source_code_files"] if mainrow else None,
      "candidates_by_code_volume_not_canonical":rows,
      "warnings":["Code volume alone does NOT rank quality, freshness or production canonical status",
       "No user/patient data inspected; only repository tree paths counted in memory",
       "No branch merged, no deployment, no secret values read, and no application tests executed",
       "Manually compare current deployment and Drive master before selecting a source candidate"]
    }
    Path("source_inventory.json").write_text(json.dumps(report,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print(json.dumps({"branch_count":len(rows),"main_source_files":report["main_source_files"],
       "top_branches":rows[:5],"warnings":report["warnings"]},ensure_ascii=False,indent=2))
if __name__=="__main__":main()
