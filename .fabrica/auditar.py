#!/usr/bin/env python3
"""Auditoria reproducible del repositorio. Solo lectura. Cero APIs externas."""
import fnmatch
import json
import os
from datetime import datetime, timezone
from pathlib import Path

BASE = Path(__file__).resolve().parents[1]
CFG = json.loads((Path(__file__).parent / "config.json").read_text("utf-8"))
IGNORE = {".git", ".github", ".fabrica", "node_modules", ".venv", "venv", ".next", "dist", "build", "__pycache__"}
SUFFIX = {".js", ".jsx", ".ts", ".tsx", ".html", ".css", ".php", ".py", ".vue", ".go"}
SECRET_PATTERNS = [".env", ".env.*", "*.pem", "*.p12", "*.key", "*.sqlite", "*.db", "*.sql"]

files, dirs, count, secrets_count = set(), set(), 0, 0
for parent, children, names in os.walk(BASE):
    children[:] = sorted(n for n in children if n not in IGNORE)
    rel = Path(parent).relative_to(BASE)
    for child in children:
        dirs.add((rel / child).as_posix())
    for n in names:
        files.add((rel / n).as_posix())
        count += Path(n).suffix.lower() in SUFFIX
        secrets_count += any(fnmatch.fnmatch(n.lower(), p) for p in SECRET_PATTERNS)
checks = []
for alternatives in CFG["source_evidence"]:
    checks.append({"alternatives": alternatives, "present": any(p in files or p in dirs for p in alternatives)})
blockers = ["Source marker absent: " + " or ".join(c["alternatives"]) for c in checks if not c["present"]]
if count == 0:
    blockers.append("No individual source code files visible")
if secrets_count:
    blockers.append("Potentially sensitive filenames detected. Review locally; names withheld.")
report = {
    "project": CFG["project"],
    "time_utc": datetime.now(timezone.utc).isoformat(),
    "revision": os.environ.get("GITHUB_SHA", "local"),
    "status": "BLOCKED" if blockers else "SOURCE_VISIBLE_NOT_FUNCTIONALLY_TESTED",
    "source_files": count,
    "potentially_sensitive_filenames": secrets_count,
    "checks": checks,
    "blockers": blockers,
    "tests_executed": 0,
    "deployments": 0,
    "priority_next_steps": CFG["work"]
}
print(json.dumps(report, ensure_ascii=False, indent=2))
summary_path = os.environ.get("GITHUB_STEP_SUMMARY")
if summary_path:
    with open(summary_path, "a", encoding="utf-8") as f:
        f.write(f'## {CFG["project"]} | auditoria independiente\n\n')
        f.write(f'**Estado:** {report["status"]}; **fuentes:** {count}; **tests ejecutados:** 0; **deploys:** 0\n\n')
        for problem in blockers:
            f.write(f'- Bloqueo: {problem}\n')
        f.write("\n### Proximos trabajos por prioridad\n")
        for job in CFG["work"]:
            f.write(f'- {job}\n')
