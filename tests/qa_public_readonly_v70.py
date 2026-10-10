#!/usr/bin/env python3
"""V70 read-only HTTP checks with fake transports; no network required."""
import importlib.util
from pathlib import Path
spec = importlib.util.spec_from_file_location("public_audit",
    Path(__file__).resolve().parents[1] / "scripts/public_readonly_audit_v70.py")
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
def good(path):
    body = "Decreto Mari Carmen" if path.endswith("decreto-mari-carmen-0071e38b") else "ok"
    if path == "/analytics.js": body = "debug_mode: true"
    if path in ("/mapa-espana", "/VERSION.json"): return 404, ""
    return 200, body
r = mod.probe(good)
assert r["status"] == "HTTP_OK_PENDING_INTEGRATION", r
assert "GA4_DEBUG_MODE_ENABLED" in r["warnings"]
assert "MAPA_2D_NOT_PUBLIC" in r["warnings"]
assert r["remote_changes"] == 0 and not r["deployment_approved"]
def broken(path): return (403, "") if path == "/" else good(path)
assert mod.probe(broken)["status"] == "INCIDENT"
print("PASS: V70 HTTP recovery smoke: healthy warning flags and blocked homepage")
