#!/usr/bin/env python3
"""Integra la medición GA4 existente en V16. No accede a Hostinger."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAGES = ROOT / "_private" / "pages.php"
SCRIPT = ROOT / "assets" / "analytics.js"
ID = "G-D7KPSFPMM5"
URL = f"https://www.googletagmanager.com/gtag/js?id={ID}"
TAG = f'<script async src="{URL}"></script><script defer src="/assets/analytics.js"></script>'
CSP_SCRIPT_OLD = "script-src 'self'; style-src 'self'"
CSP_SCRIPT_NEW = "script-src 'self' https://www.googletagmanager.com; style-src 'self'"
CSP_CONNECT_OLD = "connect-src 'self'; frame-src 'self'"
CSP_CONNECT_NEW = ("connect-src 'self' https://www.google-analytics.com "
                   "https://region1.google-analytics.com https://*.google-analytics.com "
                   "https://*.analytics.google.com; frame-src 'self'")

def main():
    original = PAGES.read_text(encoding="utf-8")
    if "GTM-" in original:
        raise SystemExit("STOP: se detectó un contenedor GTM; revisar posibles duplicados.")
    content = original

    if URL not in content:
        if content.count("</head><body") != 1:
            raise SystemExit("STOP: plantilla no reconocida; sin cambios.")
        if content.count(CSP_SCRIPT_OLD) != 1 or content.count(CSP_CONNECT_OLD) != 1:
            raise SystemExit("STOP: CSP no reconocida; sin cambios.")
        content = content.replace(CSP_SCRIPT_OLD, CSP_SCRIPT_NEW, 1)
        content = content.replace(CSP_CONNECT_OLD, CSP_CONNECT_NEW, 1)
        content = content.replace("</head><body", TAG + "</head><body", 1)

    if content.count(ID) != 1 or content.count("/assets/analytics.js") != 1:
        raise SystemExit("STOP: GA4 ausente o duplicada.")
    if CSP_SCRIPT_NEW not in content or CSP_CONNECT_NEW not in content:
        raise SystemExit("STOP: CSP insuficiente.")

    template = ("window.dataLayer = window.dataLayer || [];\n"
                "function gtag(){ window.dataLayer.push(arguments); }\n"
                "gtag('js', new Date());\n"
                f"gtag('config', '{ID}');\n")
    if SCRIPT.exists():
        current = SCRIPT.read_text(encoding="utf-8")
        if current.count(f"gtag('config', '{ID}')") != 1:
            raise SystemExit("STOP: analytics.js diferente; no sobrescribir.")
    if content != original:
        PAGES.write_text(content, encoding="utf-8")
    if not SCRIPT.exists():
        SCRIPT.parent.mkdir(parents=True, exist_ok=True)
        SCRIPT.write_text(template, encoding="utf-8")
    print("GA4 reconciliada en V16: una sola medición y CSP revisada. No se modificó Hostinger.")

if __name__ == "__main__":
    main()
