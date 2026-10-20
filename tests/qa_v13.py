"""V13 static contracts: standalone Spain route, strict 0/null, and no unreviewed publication."""
from pathlib import Path
import json,re,xml.etree.ElementTree as ET
R=Path(__file__).resolve().parent.parent
index=(R/'index.php').read_text()
analytics=(R/'_private/npl_analytics.php').read_text()
html=(R/'_private/observatorio-espana.html').read_text()
js=(R/'assets/observatorio/mapa-espana.js').read_text()
assert "if($path==='/mapa-espana')" in index
assert "Disallow: /mapa-espana" in index
assert '<meta name="robots" content="noindex,nofollow">' in html
assert 'PROTOTIPO PREPRODUCCIÓN V9' not in html
assert any('href="/assets/observatorio/mapa-espana.css?v='+v+'"' in html for v in ('13','14','16'))
assert any('src="/assets/observatorio/mapa-espana.js?v='+v+'"' in html for v in ('13','14','16'))
assert "fetch('/assets/observatorio/mapa-espana.svg')" in js
assert "p.value!==null" in js
assert 'findTerritory()' in js and 'search-btn' in html
assert 'SUM(CASE WHEN geo_rows=1 THEN 1 ELSE 0 END) AS territories' in analytics
assert "['rows'=>[],'ambiguous'=>[]]" in analytics
assert "!array_key_exists('value',$r)||$r['value']===null" in analytics
assert "PP_PUBLISHED" in analytics and "r.status='published'" in (R/'_private/npl.php').read_text()
assert len(set(re.findall(r'data-geo="(ES\d\d)"',(R/'assets/observatorio/mapa-espana.svg').read_text())))==19
ET.parse(R/'assets/observatorio/mapa-espana.svg')
for p in [R/'database/T35_CANDIDATAS_EDITORIALES_20261008.json']:
 d=json.loads(p.read_text()); assert d['publication_policy']=='review_only' and len(d['candidates'])==3
 assert all(x['status']=='review' and x['doi'] and x['primary_source_url'].startswith('https://') for x in d['candidates'])
print('V13 STATIC PASS: first-class noindex map, 19 geometries, null != zero, ambiguous coverage, editorial review gate')
