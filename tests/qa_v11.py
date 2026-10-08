"""V11 non-destructive release-gate: audit source metadata and editorial review candidates."""
from pathlib import Path
import json, re
R=Path(__file__).resolve().parent.parent
read=lambda f:json.loads((R/f).read_text(encoding='utf-8'))
files=['database/seeds/T19_CCAA_BASELINE.json','database/T26_VULNERABILIDAD_SEGURIDAD_2025.json','database/T27_SALARIOS_CCAA_2024.json','database/T28_DERIVADOS_SALARIOS_2024.json','database/T30_ABANDONO_EDUCATIVO_2022_2025.json','database/T31_EMPLEO_PARO_25_64_2015_2025.json','database/T32_PEW_PERCEPCION_DEMOCRACIA_2026.json']
batches=[read(f) for f in files]
observations=[o for b in batches for o in b['observations']]
assert len(observations)==521
assert len({tuple(o.get(k) for k in ('indicator_id','period','geo_code','subgroup','source_id','dataset_code')) for o in observations})==521
assert sum(o.get('measurement_type')=='derived' for o in observations)==36
assert all(o.get('source_url','').startswith('https://') for o in observations)
assert all(isinstance(o['value'],(int,float)) and not isinstance(o['value'],bool) for o in observations)
# No newly invented numerical observations in this release.
news=read('database/T34_CANDIDATAS_EDITORIALES_20261008.json')
assert len(news['items'])==3 and news['publication_policy']=='human_approval_required'
prior=[]
for path in ['database/T25_SCIENCE_EVIDENCE_GRAPH_2026.json','database/T29_CANDIDATAS_CIENCIA_PSICOPOLITICA_2026.json','database/T33_CANDIDATAS_EDITORIALES_20261008.json']:
 d=read(path);prior+=d.get('records',d.get('items',[]))
seen={x.get('source_url','').rstrip('/') for x in prior}
seen_dois={x.get('doi','').lower() for x in prior if x.get('doi')}
for n in news['items']:
 assert n['status']=='review_only'
 assert n['source_url'].startswith('https://')
 assert n['source_url'].rstrip('/') not in seen
 assert n['doi'].lower() not in seen_dois
 for key in ['what_happened','evidence','methodology','limits','importance','original_image_brief','image_alt']:
  assert isinstance(n.get(key),str) and len(n[key])>=45,(n['id'],key)
 seen.add(n['source_url'].rstrip('/'));seen_dois.add(n['doi'].lower())
pages=(R/'_private/pages.php').read_text();core=(R/'_private/core.php').read_text()
assert "'/blog/escribir'&&!$editor" in pages
assert "'/blog/proponer','/blog/escribir'" in pages
assert "header('Retry-After: '" in core
css=(R/'docs/observatorio-espana-v8.css').read_text()
assert 'prefers-reduced-motion' in css and 'forced-colors' in css
print('V11 QA PASS: 521 preserved unique observations, 3 original-source review candidates, editor auth, 429 diagnostics and accessibility fallbacks.')
