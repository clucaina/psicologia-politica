"""V10 cross-batch, primary-source, editorial and release-gate checks (offline)."""
from pathlib import Path
import json,re
R=Path(__file__).resolve().parent.parent
load=lambda p:json.loads((R/p).read_text(encoding='utf-8'))
files=['database/seeds/T19_CCAA_BASELINE.json','database/T26_VULNERABILIDAD_SEGURIDAD_2025.json','database/T27_SALARIOS_CCAA_2024.json','database/T28_DERIVADOS_SALARIOS_2024.json','database/T30_ABANDONO_EDUCATIVO_2022_2025.json','database/T31_EMPLEO_PARO_25_64_2015_2025.json','database/T32_PEW_PERCEPCION_DEMOCRACIA_2026.json']
batches=[load(p) for p in files]
assert [len(b['observations']) for b in batches]==[152,85,54,36,80,108,6], [len(b['observations']) for b in batches]
assert sum(len(b['observations']) for b in batches)==521
keys=[]
for b in batches:
 assert re.fullmatch(r'T\d{2}(?:_[A-Z0-9_]{3,61})?',b['batch_id']) and len(b['batch_id'])<=64
 for o in b['observations']:
  assert o['batch_id']==b['batch_id']
  assert o['source_url'].startswith('https://')
  assert isinstance(o['value'],(int,float)) and not isinstance(o['value'],bool)
  keys.append(tuple(o[k] for k in ('indicator_id','period','geo_code','subgroup','source_id','dataset_code')))
assert len(keys)==len(set(keys))
pew=batches[-1];rows={(o['indicator_id'],o['period']):o for o in pew['observations']}
expected={'PEW_SOCIAL_DEMOCRACY_BAD_PCT':{'2022':35,'2026':47},'PEW_SOCIAL_DEMOCRACY_GOOD_PCT':{'2022':61,'2026':49},'PEW_SOCIAL_DIVISION_MORE_PCT':{'2022':66,'2026':80}}
for indicator,years in expected.items():
 for year,value in years.items():
  o=rows[(indicator,year)];assert o['value']==value and o['geo_code']=='ES' and o['unit']=='%'
  assert o['quality_flag']=='PENDING_HUMAN_SOURCE_QA' and o['human_source_verification'] is False
  assert o['measurement_type']=='survey' and 'causal' in o['limits'].lower()
assert len(pew['new_indicators'])==3 and len(pew['source_endpoints'])==1
editorial=load('database/T33_CANDIDATAS_EDITORIALES_20261008.json')
assert len(editorial['items'])==3 and editorial['publication_policy']=='human_approval_required'
prior29=load('database/T29_CANDIDATAS_CIENCIA_PSICOPOLITICA_2026.json')['items']
prior25=load('database/T25_SCIENCE_EVIDENCE_GRAPH_2026.json')['records']
urls=[x['source_url'].rstrip('/') for x in prior29+prior25+editorial['items']]
assert len(urls)==len(set(urls)), 'Duplicate primary source in science candidates'
for x in editorial['items']:
 for field in ['what_happened','evidence','methodology','limits','importance','original_image_brief','image_alt','source_url']:
  assert isinstance(x[field],str) and len(x[field])>30,(x['id'],field)
 assert x['status']=='review_only' and x['source_url'].startswith('https://')
js=(R/'docs/observatorio-espana-v8.js').read_text()
assert 'groupRequestId' in js and 'timelineRequestId' in js and 'measureSignature' in js
assert 'available-years' in (R/'docs/observatorio-espana-v8.html').read_text()
assert 'pp_analytics_coverage' in (R/'_private/npl_analytics.php').read_text()
assert 'pp_batch_existing_conflicts' in (R/'_private/npl_batch.php').read_text()
print('V10 DATA/EDITORIAL QA PASS: 521 unique observations (6 new Pew perception measures), 3 deduplicated review candidates, coverage/async/provenance guards present.')
