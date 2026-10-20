"""Group missing NPL provenance into source-level review tasks, without filling any field."""
from pathlib import Path
from collections import defaultdict,Counter
import json
R=Path(__file__).resolve().parent.parent
files=[R/'database/seeds/T19_CCAA_BASELINE.json']+sorted((R/'database').glob('T*.json'))
items=[]
for p in files:
 data=json.loads(p.read_text())
 for row in data.get('observations',[]):
  if row.get('value') is None:continue
  missing=[k for k in ('retrieved_at','population','measurement_type') if not row.get(k)]
  if not missing:continue
  items.append({'batch':p.name,'indicator_id':row.get('indicator_id'),
      'source_id':row.get('source_id'),'dataset_code':row.get('dataset_code'),
      'source_url':row.get('source_url'),'missing':missing,'natural_key':'|'.join(str(row.get(k,'')) for k in ('indicator_id','period','geo_code','subgroup'))})
by=defaultdict(list)
for item in items:
 key=(item['batch'],item['source_id'],item['dataset_code'],item['indicator_id'],item['source_url'])
 by[key].append(item)
tasks=[]
for (batch,source,dataset,indicator,url),group in by.items():
 counts=Counter(x for row in group for x in row['missing'])
 task={'batch':batch,'indicator_id':indicator,'source_id':source,'dataset_code':dataset,
       'source_url':url,'affected_rows':len(group),'missing_counts':dict(counts),
       'example_natural_keys':[x['natural_key'] for x in group[:3]],
       'review_instructions':'Consultar fuente primaria, documentar evidencia exacta y fecha REAL de consulta; no rellenar por heurística ni inferir población desde el título.',
       'publish_gate':'PENDING_HUMAN_SOURCE_QA'}
 task['priority_score']=counts['retrieved_at']*3+counts['measurement_type']*2+counts['population']
 tasks.append(task)
tasks.sort(key=lambda x:(-x['priority_score'],-x['affected_rows'],str(x['indicator_id'])))
report={'version':'V15','generated_on':'2026-10-08','policy':'review_only_no_auto_fill',
 'observations_with_any_missing':len(items),'missing_field_totals':dict(Counter(x for row in items for x in row['missing'])),
 'review_tasks':len(tasks),'tasks':tasks}
(R/'database/NPL_REVIEW_BACKLOG_V15.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('V15 review backlog',report['observations_with_any_missing'],report['missing_field_totals'],report['review_tasks'])
