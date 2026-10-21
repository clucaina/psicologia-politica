#!/usr/bin/env python3
"""V70: read-only structural release guard. It NEVER deploys to Hostinger."""
import argparse
import json
from pathlib import Path

REQUIRED = ("index.php", ".htaccess", "_private/core.php", "_private/pages.php",
            "_private/api.php", "_private/assets.json", "assets/site.css")
PROTECTED = ("_private/config.php", "_private/installed.lock", ".env",
             "uploads/private", "database/production.sql")

def inspect(root: Path, *, target: str = "isolated-staging"):
    root = Path(root).resolve()
    problems = []
    if target.strip("/") == "public_html" or target.replace("\\", "/").rstrip("/").endswith("/public_html"):
        problems.append("PRODUCTION_TARGET_FORBIDDEN: approval and Hostinger reconciliation are required")
    for rel in REQUIRED:
        p = root / rel
        if not p.is_file() or p.is_symlink():
            problems.append("MISSING_OR_UNSAFE: " + rel)
    for rel in PROTECTED:
        if (root / rel).exists():
            problems.append("PRIVATE_ARTIFACT_FOUND: " + rel)
    if (root / "index.php").is_file():
        content = (root / "index.php").read_text(encoding="utf-8", errors="replace")
        if "_private/core.php" not in content:
            problems.append("ENTRYPOINT_MISSING_BOOTSTRAP")
    if (root / ".htaccess").is_file():
        rules = (root / ".htaccess").read_text(encoding="utf-8", errors="replace")
        if "DirectoryIndex index.php" not in rules or "RewriteRule" not in rules:
            problems.append("UNSAFE_OR_INCOMPLETE_HTACCESS")
    assets = root / "_private/assets.json"
    if assets.is_file():
        try:
            metadata = json.loads(assets.read_text(encoding="utf-8"))
            for key in ("js", "css"):
                resource = metadata[key].split("?", 1)[0]
                if not resource.startswith("/assets/") or ".." in resource:
                    problems.append("INVALID_ASSET_ROUTE: " + key)
                elif not (root / resource.lstrip("/")).is_file():
                    problems.append("MISSING_ASSET: " + resource)
        except (ValueError, KeyError, TypeError):
            problems.append("INVALID_ASSET_MANIFEST")
    return {"status": "PASS_STAGING_ONLY" if not problems else "BLOCK",
            "issues": problems, "publication_authorized": False,
            "remote_changes": 0, "target": target}

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", default=".")
    parser.add_argument("--target", default="isolated-staging")
    args = parser.parse_args()
    result = inspect(Path(args.root), target=args.target)
    print(json.dumps(result, indent=2))
    return 0 if result["status"] == "PASS_STAGING_ONLY" else 2

if __name__ == "__main__":
    raise SystemExit(main())
