#!/usr/bin/env python3
"""Deterministic QA inventory of prepared (not published) NPL batches. No network or DB writes."""
from pathlib import Path
from collections import defaultdict,Counter
import json,hashlib
R=Path(__file__).resolve().parent.parent
paths=['database/seeds/T19_CCAA_BASELINE.json','database/T26_VULNERABILIDAD_SEGURIDAD_2025.json','database/T27_SALARIOS_CCAA_2024.json','database/T28_DERIVADOS_SALARIOS_2024.json','database/T30_ABANDONO_EDUCATIVO_2022_2025.json','database/T31_EMPLEO_PARO_25_64_2015_2025.json','database/T32_PEW_PERCEPCION_DEMOCRACIA_2026.json']
rows=[];batches=[]
for f in paths:
 raw=(R/f).read_bytes();d=json.loads(raw);batches.append({'batch_id':d['batch_id'],'sha256':hashlib.sha256(raw).hexdigest(),'observations':len(d['observations'])})
 rows.extend(d['observations'])
summary=defaultdict(list)
for o in rows:summary[o['indicator_id']].append(o)
records=[]
for indicator,items in sorted(summary.items()):
 groups=defaultdict(list)
 for x in items:groups[x['subgroup']].append(x)
 for group,points in sorted(groups.items()):
  years=sorted({x['period'] for x in points});geos=sorted({x['geo_code'] for x in points})
  combos=Counter((x['period'],x['geo_code']) for x in points)
  sources=sorted({x['source_id'] for x in points});units=sorted({x['unit'] for x in points});methods=sorted({x.get('methodology_flag') for x in points if x.get('methodology_flag')})
  flags=[]
  if any(c>1 for c in combos.values()):flags.append('multiple_sources_or_rows_per_year_geo_check_comparability')
  if len(units)>1:flags.append('mixed_units')
  if len(sources)>1:flags.append('mixed_sources')
  if len(methods)>1:flags.append('mixed_methodologies')
  if geos==['ES']:flags.append('national_only_do_not_copy_to_ccaa')
  if any('25_64' in indicator for _ in [0]):flags.append('age_25_64_not_general_population')
  if any('PEW_' in indicator for _ in [0]):flags.append('survey_perception_not_observed_impact')
  if any('interior_' in indicator for _ in [0]):flags.append('administrative_counts_not_population_prevalence')
  if any(x.get('measurement_type')=='derived' for x in points):flags.append('derived_not_official')
  if any(not x.get('retrieved_at') for x in points):flags.append('retrieval_date_requires_human_verification')
  records.append({'indicator_id':indicator,'subgroup':group,'prepared_rows':len(points),'years':years,'territories':geos,'sources':sources,'units':units,'methodologies':methods,'flags':flags})
report={'version':'V11','date':'2026-10-08','status':'prepared_only_not_published','total_rows':len(rows),'unique_indicators':len(summary),'indicator_subgroups':len(records),'batches':batches,'inventory':records,'methodology':'This report describes prepared JSON only, not the published MySQL database. No missing territorial observation is treated as zero.'}
out=R/'database/NPL_PREPARED_COVERAGE_V11.json';out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('NPL coverage audit:',len(rows),'rows,',len(summary),'indicators,',len(records),'indicator-subgroups')
for x in records:
 print(x['indicator_id'],x['subgroup'],len(x['years']),len(x['territories']),','.join(x['flags']))
