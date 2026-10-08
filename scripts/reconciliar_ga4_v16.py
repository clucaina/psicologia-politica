#!/usr/bin/env python3
"""Reconciliar GA4 en el árbol V16 del repositorio. NUNCA tocar el servidor Hostinger."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAGES = ROOT / "_private/pages.php"
ASSET = ROOT / "assets/analytics.js"
GA4 = "G-D7KPSFPMM5"
EXTERNAL = f"https://www.googletagmanager.com/gtag/js?id={GA4}"
OLD_CSP = "script-src 'self'; style-src 'self'"
NEW_CSP = "script-src 'self' https://www.googletagmanager.com; style-src 'self'"
OLD_CONNECT = "connect-src 'self'; frame-src 'self'"
NEW_CONNECT = ("connect-src 'self' https://www.google-analytics.com "
               "https://region1.google-analytics.com https://*.google-analytics.com "
               "https://*.analytics.google.com; frame-src 'self'")

def main():
    src = PAGES.read_text(encoding="utf-8")
    if "GTM-" in src:
        raise SystemExit("REVISAR: contenedor GTM ya presente. No duplicar medición.")
    if EXTERNAL not in src:
        if src.count(OLD_CSP) != 1 or src.count(OLD_CONNECT) != 1:
            raise SystemExit("CSP distinta: comparar manualmente, sin sobrescribir.")
        src = src.replace(OLD_CSP, NEW_CSP).replace(OLD_CONNECT, NEW_CONNECT)
        needle = '.pp_escape($assets[\'css\']).\'"></head><body\'.('
        if src.count(needle) != 1:
            raise SystemExit("Cabecera no reconocida: revisar V16.")
        replacement = ('.pp_escape($assets[\'css\']).\'">'
                       f'<script async src="{EXTERNAL}"></script>'
                       '<script defer src="/assets/analytics.js"></script></head><body\'.(')
        src = src.replace(needle, replacement)
    if src.count(EXTERNAL) != 1 or src.count("/assets/analytics.js") != 1:
        raise SystemExit("Posible etiqueta duplicada; no escribir.")
    js = ("/* GA4 existente: conservar identificador y evitar una segunda instalación. */\n"
          "window.dataLayer = window.dataLayer || [];\n"
          "function gtag(){ dataLayer.push(arguments); }\n"
          "gtag('js', new Date());\n"
          f"gtag('config', '{GA4}');\n")
    if NEW_CSP not in src or NEW_CONNECT not in src:
        raise SystemExit("CSP incompleta para GA4; no escribir.")
    if ASSET.exists():
        current = ASSET.read_text(encoding="utf-8")
        if current.count(f"gtag('config', '{GA4}')") != 1:
            raise SystemExit("Revisar analytics.js: código distinto o duplicado; no sobreescribir.")
    PAGES.write_text(src, encoding="utf-8")
    if not ASSET.exists():
        ASSET.write_text(js, encoding="utf-8")
    print("GA4 V16 reconciliada, sin modificar analytics.js preexistente. No se tocó Hostinger.")

if __name__ == "__main__":
    main()
