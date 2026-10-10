#!/usr/bin/env python3
"""V70 regression tests, standard-library only. No Hostinger or database access."""
import importlib.util
import json
import tempfile
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "scripts/predeploy_guard_v70.py"
spec = importlib.util.spec_from_file_location("predeploy_guard_v70", SCRIPT)
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)

with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp)
    assert guard.inspect(root)["status"] == "BLOCK"
    for rel in guard.REQUIRED:
        (root / rel).parent.mkdir(parents=True, exist_ok=True)
        (root / rel).write_text("")
    (root / "index.php").write_text("<?php require __DIR__.'/_private/core.php';")
    (root / ".htaccess").write_text("DirectoryIndex index.php\nRewriteRule ^ index.php [QSA,L]\n")
    (root / "_private/assets.json").write_text(json.dumps({"js": "/assets/app-test.js", "css": "/assets/site.css"}))
    (root / "assets/app-test.js").write_text("export const healthy = true;")
    assert guard.inspect(root)["status"] == "PASS_STAGING_ONLY"
    assert guard.inspect(root, target="public_html")["status"] == "BLOCK"
    (root / "_private/config.php").write_text("secret")
    assert guard.inspect(root)["status"] == "BLOCK"
    (root / "_private/config.php").unlink()
    (root / "_private/assets.json").write_text(json.dumps({"js": "/assets/missing.js", "css": "/assets/site.css"}))
    assert "MISSING_ASSET" in str(guard.inspect(root)["issues"])
    (root / "_private/assets.json").write_text('INVALID')
    assert "INVALID_ASSET_MANIFEST" in str(guard.inspect(root)["issues"])

print("PASS: V70 release guard: missing app, valid staging, production block, secrets, assets and malformed JSON")
