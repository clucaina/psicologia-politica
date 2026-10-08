#!/usr/bin/env python3
"""Offline NPL audit; does not fetch, change, infer or publish observations."""
from pathlib import Path
from collections import Counter,defaultdict
from urllib.parse import urlsplit
import json,math,hashlib
R=Path(__file__).resolve().parent.parent
files=[R/'database/seeds/T19_CCAA_BASELINE.json']+[R/'database'/f'{x}.json' for x in (
 'T26_VULNERABILIDAD_SEGURIDAD_2025','T27_SALARIOS_CCAA_2024','T28_DERIVADOS_SALARIOS_2024',
 'T30_ABANDONO_EDUCATIVO_2022_2025','T31_EMPLEO_PARO_25_64_2015_2025','T32_PEW_PERCEPCION_DEMOCRACIA_2026')]
valid_geos=set(json.loads((R/'geo/ccaa-code-map.json').read_text())['by_cod_ccaa'].values())|{'ES'}
issues=[];allrows=[];summary={};keys={}
for f in files:
 d=json.loads(f.read_text());rows=d['observations'];bid=d['batch_id'];seen=set();bissues=[]
 for i,o in enumerate(rows):
  key=tuple(o.get(x) for x in ('indicator_id','period','geo_code','subgroup','source_id','dataset_code'))
  if key in seen:bissues.append(f'duplicate_within_batch:{i}')
  seen.add(key)
  if key in keys:issues.append(f'crossbatch_collision:{bid}:{i}:{keys[key]}')
  keys[key]=bid
  if o.get('geo_code') not in valid_geos:bissues.append(f'unknown_geocode:{i}:{o.get("geo_code")}')
  if not isinstance(o.get('value'),(int,float)) or not math.isfinite(o['value']):bissues.append(f'bad_value:{i}')
  if o.get('unit')=='%' and not (0<=o['value']<=100):
   if not (o.get('measurement_type')=='derived' and o.get('signed_percent') is True and str(o.get('methodology_flag','')).startswith('NPL_DERIVED_')):bissues.append(f'percentage_out_of_range:{i}')
  url=o.get('source_url','')
  if urlsplit(url).scheme!='https' or not urlsplit(url).hostname:bissues.append(f'bad_source_url:{i}')
  if o.get('batch_id')!=bid:bissues.append(f'wrong_batch_id:{i}')
  if o.get('measurement_type')=='derived' and 'derivation' not in o:bissues.append(f'no_derivation:{i}')
  if o.get('indicator_id')=='EAES2024_GAP_MEDIA_ANUAL_PCT':
   inputs=o.get('derivation',{}).get('inputs',{});m=inputs.get('male_annual_mean_eur');w=inputs.get('female_annual_mean_eur')
   if not isinstance(m,(int,float)) or not isinstance(w,(int,float)) or m==0 or abs(round((m-w)/m*100,4)-o['value'])>0.00011:bissues.append(f'formula_mismatch:{i}')
  allrows.append(o)
 summary[bid]={'observations':len(rows),'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),
  'geographies':len({x.get('geo_code') for x in rows}),
  'missing_retrieved_at':sum(not x.get('retrieved_at') for x in rows),
  'missing_population':sum(not x.get('population') for x in rows),
  'missing_measurement_type':sum(not x.get('measurement_type') for x in rows),
  'derived':sum(x.get('measurement_type')=='derived' for x in rows),
  'status':'structurally_valid' if not bissues else 'requires_correction','issues':bissues}
 issues.extend(f'{bid}:{x}' for x in bissues)
report={'release':'V13','audit_date':'2026-10-08','scope':'offline_prepared_batches_only',
 'unique_natural_keys':len(keys),'observations':len(allrows),
 'missing_retrieval_dates':sum(not x.get('retrieved_at') for x in allrows),
 'missing_explicit_population':sum(not x.get('population') for x in allrows),
 'missing_explicit_measurement_type':sum(not x.get('measurement_type') for x in allrows),
 'derived_observations':sum(x.get('measurement_type')=='derived' for x in allrows),
 'numeric_zeros':sum(x.get('value')==0 for x in allrows),
 'batches':summary,'issues':issues,'status':'structural_pass' if not issues else 'structural_fail',
 'publication_gate':'NO-GO until real source dates, reviewed population/methodology, independent MySQL staging tests, and editor approval.',
 'notes':['Population may be added in npl_reviews.evidence during human review; never infer from subgroup automatically.',
          'Missing retrieved_at must not be backfilled with invented dates.',
          'A structurally valid batch is not independently verified scientific evidence.',
          'No production data was accessed or modified.']}
output=R/'database/NPL_QUALITY_REPORT_V13.json';output.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
assert not issues,issues[:10]
assert report['observations']==521 and report['unique_natural_keys']==521
assert report['derived_observations']==36
print('V13 NPL AUDIT PASS',json.dumps({k:report[k] for k in ('observations','missing_retrieval_dates','missing_explicit_population','missing_explicit_measurement_type','derived_observations','numeric_zeros')},ensure_ascii=False))
