#!/usr/bin/env python3
"""Solo lectura. Valida canarios de producción sin utilizar claves."""
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
import sys

ROOT = "https://psicologiapolitica.es"
CHECKS = (
    ("/blog/decreto-mari-carmen-0071e38b", "Decreto Mari Carmen"),
    ("/blog", "Decreto Mari Carmen"),
    ("/analytics.js", "G-D7KPSFPMM5"),
)

def main():
    failed = 0
    for path, sentinel in CHECKS:
        try:
            request = Request(ROOT + path, headers={"User-Agent": "PsicologiaPolitica-Release-Smoke/1.0"})
            with urlopen(request, timeout=18) as response:
                body = response.read(1_500_000).decode("utf-8", errors="replace")
                status = response.status
            if status != 200 or sentinel not in body:
                failed += 1
                print(f"FAIL {path}: status={status} contenido_de_control={sentinel in body}")
            else:
                print(f"PASS {path}: status=200 contenido_de_control=presente")
        except (HTTPError, URLError, TimeoutError) as exc:
            failed += 1
            print(f"FAIL {path}: {type(exc).__name__} (sin modificar el servidor)")
    if failed:
        print(f"{failed} canarios no superados. No desplegar ni sincronizar public_html.")
        sys.exit(1)
    print("Canarios públicos OK; sigue faltando validar MySQL y restauración.")

if __name__ == "__main__":
    main()
