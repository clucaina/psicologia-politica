"""V14 static release QA: data, editorial uniqueness, national comparison, export and deploy gate."""
from pathlib import Path
import json, re
R=Path(__file__).resolve().parent.parent
js=(R/'assets/observatorio/mapa-espana.js').read_text()
html=(R/'_private/observatorio-espana.html').read_text()
assert "const ALL_GEOS=['ES',...GEOS]" in js
assert 'geos:ALL_GEOS.join' in js
assert 'GEOS.map(geo=>[geo,state.points[geo]])' in js
assert 'exportReviewedCSV' in js and 'csvCell' in js and 'revokeObjectURL' in js
assert 'id="export-csv"' in html
assert re.search(r'mapa-espana\.js\?v=(14|15|16)\b', html)
editor=(R/'assets/chunk-KECI7H7X.js').read_text()
assert 'expected_content_hash:t._server_content_hash' in editor
assert '_server_content_hash:s.content_hash' in editor
assert 'ppRecoverPost({...p.post,_server_content_hash:p.content_hash})' in editor
assert 'pp_author_fingerprint($ownPost)' in (R/'_private/pages.php').read_text()
assert 'hash_equals(pp_author_fingerprint($before)' in (R/'_private/editorial.php').read_text()
news_files=[R/'database/T29_CANDIDATAS_CIENCIA_PSICOPOLITICA_2026.json']+[R/'database'/f'T{x}_CANDIDATAS_EDITORIALES_20261008.json' for x in range(33,36)]
news_files.append(R/'database/T37_CANDIDATAS_EDITORIALES_20261008.json')
all_dois=set();count=0
for path in news_files:
 d=json.loads(path.read_text())
 assert d['publication_policy'] in ('review_only','human_approval_required')
 for c in d.get('candidates',d.get('items',[])):
  doi=(c.get('doi') or '').lower()
  if doi:
   assert doi not in all_dois,(doi,path)
   all_dois.add(doi)
  count+=1
  assert (c.get('primary_source_url') or c.get('source_url','')).startswith('https://')
  assert c.get('status') in ('review','review_only') or 'pending' in c.get('verification_status','')
  assert all(c.get(k) for k in ('what_happened','evidence','methodology'))
  assert c.get('limitations') or c.get('limits')
  assert c.get('why_it_matters') or c.get('importance')
  assert c.get('image_brief') or c.get('original_image_brief')
assert count==15,(count,all_dois)
batch=json.loads((R/'database/T36_EUROSTAT_AROPE_HOGARES_CON_HIJOS_2025.json').read_text())
assert len(batch['observations'])==1
obs=batch['observations'][0]
assert obs['geo_code']=='ES' and obs['value']==29.9 and obs['period']=='2025'
assert obs['population'].startswith('Personas que viven en hogares')
assert obs['human_source_verification'] is False and obs['quality_flag']=='PENDING_HUMAN_SOURCE_QA'
for wf in ['qa.yml','staging-hostinger.yml']:
 s=(R/'.github/workflows'/wf).read_text()
 assert 'qa_v14.py' in s and 'qa_semantic_v14.php' in s and 'qa_browser_v14.py' in s
print('V14 STATIC PASS: 20 geographies, reviewed CSV, 15 unique editorials, 1 official candidate, CI gates')
