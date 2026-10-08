#!/usr/bin/env python3
"""Cross-batch source/coverage integrity audit, no network and no publication."""
from collections import Counter, defaultdict
from pathlib import Path
import json,math,datetime
R=Path(__file__).resolve().parent.parent
batches=['T19_CCAA_BASELINE','T26_VULNERABILIDAD_SEGURIDAD_2025','T27_SALARIOS_CCAA_2024','T28_DERIVADOS_SALARIOS_2024','T30_ABANDONO_EDUCATIVO_2022_2025','T31_EMPLEO_PARO_25_64_2015_2025','T32_PEW_PERCEPCION_DEMOCRACIA_2026']
geo=json.loads((R/'geo/ccaa-code-map.json').read_text())
valid_geos=set(geo['by_cod_ccaa'].values())|{'ES'}
rows=[];errors=[];keys={};per_batch={};retrieval_missing=0;derived=0;zero=0
for name in batches:
 path=R/'database'/f'{name}.json'
 if not path.exists():path=R/'database'/'seeds'/f'{name}.json'
 data=json.loads(path.read_text())
 obs=data['observations'];per_batch[data['batch_id']]=len(obs)
 for i,o in enumerate(obs):
  label=f'{name}:{i}'
  key=tuple(o.get(k) for k in ('indicator_id','period','geo_code','subgroup','source_id','dataset_code'))
  if key in keys:errors.append(f'Duplicate natural key {label} vs {keys[key]}')
  keys[key]=label
  if o.get('geo_code') not in valid_geos:errors.append(f'Unknown geography {label}')
  if not str(o.get('period','')).startswith(('19','20')) or not str(o['period'])[:4].isdigit():errors.append(f'Invalid period {label}')
  v=o.get('value')
  if isinstance(v,bool) or not isinstance(v,(int,float)) or not math.isfinite(v):errors.append(f'Invalid numeric value {label}')
  if v==0:zero+=1
  if o.get('unit')=='%' and not(o.get('measurement_type')=='derived' and o.get('signed_percent') is True) and not 0<=v<=100:errors.append(f'Invalid rate {label}')
  if not str(o.get('source_url','')).startswith('https://'):errors.append(f'Invalid source URL {label}')
  if not o.get('retrieved_at'):retrieval_missing+=1
  if o.get('measurement_type')=='derived':derived+=1
  rows.append(o)
if errors:raise AssertionError('\n'.join(errors[:20]))
# AROPE cross-source numerical equality is a duplicate measurement candidate, not independent replication.
arope=defaultdict(list)
for o in rows:
 if o['indicator_id'] in ('POV_AROPE','ecv_arope_pct') and o['period']=='2025':
  arope[o['geo_code']].append((o['indicator_id'],o['value'],o['source_id']))
exact=sum(1 for points in arope.values() if len(points)==2 and points[0][1]==points[1][1])
report={'release':'V12','prepared_only':True,'source_rows':len(rows),'batch_counts':per_batch,
        'unique_natural_keys':len(keys),'derived_observations':derived,'numeric_zeros':zero,
        'missing_retrieval_dates':retrieval_missing,'arope_cross_source_equal_values':exact,
        'quality_notes':['No values generated in V12','Cross-source equal values are not independent confirmation',
                         'All staged observations require human verification and MySQL staging QA',
                         'A missing source retrieval date must not be fabricated']}
(R/'database'/'NPL_CROSSBATCH_QA_V12.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('V12 NPL CROSSBATCH PASS:',json.dumps({k:report[k] for k in ['source_rows','unique_natural_keys','derived_observations','missing_retrieval_dates','arope_cross_source_equal_values']}))
