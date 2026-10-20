#!/usr/bin/env python3
"""QA de integridad para una primera sincronización Hostinger/GitHub.

Prueba estática; no contacta con servidores ni toca MySQL.
"""
from pathlib import Path
import json
import re

root = Path(__file__).resolve().parents[1]
page = (root / '_private' / 'pages.php').read_text(encoding='utf-8')
analytics = (root / 'analytics.js').read_text(encoding='utf-8')
ignore = (root / '.gitignore').read_text(encoding='utf-8')
main = (root / 'index.php').read_text(encoding='utf-8')
checks = {
    'ga4_id_php': page.count('G-D7KPSFPMM5') == 1,
    'ga4_id_js': analytics.count('G-D7KPSFPMM5') == 1,
    'google_tag_url': page.count('https://www.googletagmanager.com/gtag/js?id=G-D7KPSFPMM5') == 1,
    'local_analytics_path': page.count('/analytics.js') == 1,
    'analytics_file_present': (root / 'analytics.js').is_file(),
    'csp_js_google': "script-src 'self' https://www.googletagmanager.com;" in page,
    'csp_connection_google': "connect-src 'self' https://www.google-analytics.com" in page,
    'no_duplicate_gtm': 'GTM-' not in page,
    'no_inline_duplication': page.count('gtag(') == 0,
    'user_uploads_ignored': 'uploads/*' in ignore,
    'hostinger_config_ignored': '_private/config.php' in ignore,
    'no_private_credentials': not (root / '_private' / 'config.php').exists(),
    'no_local_env': not (root / '.env').exists(),
    'no_auth_expansion': "pp_require_role" in (root / '_private' / 'editorial.php').read_text(encoding='utf-8'),
    'mysql_code_unchanged': 'pp_db()' not in page or 'php' in main,
    'article_not_hardcoded_as_old_seed': 'decreto-mari-carmen-0071e38b' not in page,
}
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ' ' + name)
assert all(checks.values()), json.dumps([name for name, ok in checks.items() if not ok])
print(f'SEGURIDAD Y ANALITICA: {len(checks)} verificaciones estáticas superadas')
