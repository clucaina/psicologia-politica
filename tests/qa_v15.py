"""V15 editorial cross-batch, 0/null, correlation release gate, no production writes."""
from pathlib import Path
import json,re
R=Path(__file__).resolve().parent.parent
batches=sorted(p for p in (R/'database').glob('T*_CANDIDATAS*.json') if int(p.name[1:3])<=38)
all_dois=set();all_urls=set();count=0
for p in batches:
 d=json.loads(p.read_text())
 assert d['publication_policy'] in ('review_only','human_approval_required'),p
 for item in d.get('candidates',d.get('items',[])):
  doi=(item.get('doi') or '').lower().strip()
  if doi:
   assert doi not in all_dois,(doi,p)
   all_dois.add(doi)
  url=item.get('primary_source_url') or item.get('source_url')
  assert url and url.startswith('https://'),p
  assert url not in all_urls,(url,p)
  all_urls.add(url)
  assert all(item.get(k) for k in ('what_happened','evidence','methodology')),p
  assert item.get('limitations') or item.get('limits'),p
  assert item.get('image_brief') or item.get('original_image_brief'),p
  count+=1
assert count==18,(count,batches)
q=(R/'_private/npl_analytics.php').read_text()
assert 'pp_analytics_correlation_quality' in q and 'pp_analytics_point($left[$geo]??null)' in q
assert "'status'=>'insufficient_metadata'" in q
assert 'pp_analytics_correlation_quality($left' in q
js=(R/'assets/observatorio/mapa-espana.js').read_text()
assert 'lastRank=i+1' in js
assert 'String(lastRank)' in js
for wf in ['qa.yml','staging-hostinger.yml']:
 s=(R/'.github/workflows'/wf).read_text()
 assert 'qa_v15.py' in s and 'qa_semantic_v15.php' in s and 'qa_browser_v15.py' in s
backlog=json.loads((R/'database/NPL_REVIEW_BACKLOG_V15.json').read_text())
assert backlog['missing_field_totals']=={'retrieved_at':237,'population':327,'measurement_type':237}
assert backlog['review_tasks']<=25 and backlog['review_tasks']>0
public=json.loads((R/'database/PUBLIC_AUDIT_V15.json').read_text())
assert public['release_gate']=='NO-GO' and len(public['observations'])>=9
print(f'V15 STATIC PASS: {count} review-only editorials, unique DOI/URL, correlation and tied-ranking gates')
