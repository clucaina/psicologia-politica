#!/usr/bin/env python3
"""Comprobaciones de integración GA4 y protección; nunca accede a producción."""
from pathlib import Path

root = Path(__file__).resolve().parents[1]
page = (root / '_private' / 'pages.php').read_text(encoding='utf-8')
script = (root / 'analytics.js').read_text(encoding='utf-8')
ignore = (root / '.gitignore').read_text(encoding='utf-8')
checks = {
    'GA4 una vez en PHP': page.count('G-D7KPSFPMM5') == 1,
    'GA4 una vez en JS': script.count('G-D7KPSFPMM5') == 1,
    'Librería gtag en head': page.count('https://www.googletagmanager.com/gtag/js?id=G-D7KPSFPMM5') == 1,
    'Ruta local del script': page.count('/analytics.js') == 1,
    'CSP gtag': "script-src 'self' https://www.googletagmanager.com;" in page,
    'CSP analytics': "connect-src 'self' https://www.google-analytics.com" in page,
    'Sin duplicado GTM': 'GTM-' not in page,
    'Ficheros subidos excluidos': 'uploads/*' in ignore,
    'Configuración secreta excluida': '_private/config.php' in ignore,
    'No secretos importados': not (root / '_private/config.php').exists(),
    'Sin .env importado': not (root / '.env').exists(),
    'No insertar artículo antiguo como seed': 'decreto-mari-carmen-0071e38b' not in page,
}
for name, good in checks.items():
    print(('PASS' if good else 'FAIL') + ' ' + name)
if not all(checks.values()):
    raise SystemExit('BLOQUEADO: código no completo o inconsistencia GA4. No desplegar.')
print(f'PASS: {len(checks)} controles de compatibilidad GA4/GitHub')
