#!/usr/bin/env python3
"""Read-only public smoke audit. Outputs HTTP status and known post-recovery warnings."""
import json
import ssl
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

BASE = "https://psicologiapolitica.es"
PATHS = ("/", "/blog", "/noticias", "/actividades", "/quienes-somos",
         "/conflictometro", "/contacto", "/blog/decreto-mari-carmen-0071e38b",
         "/assets/site.css", "/analytics.js", "/mapa-espana", "/VERSION.json")
CORE = ("/", "/blog", "/noticias", "/conflictometro", "/contacto")

def probe(fetch=None):
    def remote(path):
        req = Request(BASE + path, headers={"User-Agent": "PP-readonly-health-V70"})
        try:
            with urlopen(req, timeout=10) as response:
                return response.status, response.read(120000).decode("utf-8", errors="replace")
        except HTTPError as err:
            return err.code, ""
        except (URLError, TimeoutError, OSError):
            return None, ""
    fetch = fetch or remote
    pages = {p: fetch(p) for p in PATHS}
    status = {p: pair[0] for p,pair in pages.items()}
    critical = [p for p in CORE if status[p] != 200]
    warnings = []
    if status["/mapa-espana"] != 200:
        warnings.append("MAPA_2D_NOT_PUBLIC")
    if status["/VERSION.json"] != 200:
        warnings.append("VERSION_METADATA_MISSING")
    if "debug_mode: true" in pages["/analytics.js"][1] or "debug_mode:true" in pages["/analytics.js"][1]:
        warnings.append("GA4_DEBUG_MODE_ENABLED")
    if "Decreto Mari Carmen" not in pages["/blog/decreto-mari-carmen-0071e38b"][1]:
        warnings.append("PUBLISHED_ARTICLE_NOT_CONFIRMED_IN_BODY")
    return {"status": "HTTP_OK_PENDING_INTEGRATION" if not critical else "INCIDENT",
            "http": status, "critical": critical, "warnings": warnings,
            "database_authenticated": False, "remote_changes": 0,
            "deployment_approved": False}

def main():
    report = probe()
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0 if report["status"] == "HTTP_OK_PENDING_INTEGRATION" else 2

if __name__ == "__main__":
    raise SystemExit(main())
