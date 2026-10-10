#!/usr/bin/env python3
"""Read-only NPL coverage/provenance analysis. Never import or approve candidate values.

No quantitative results are inferred from missing metadata. A row with measured
zero is a legitimate observation; missing value is NOT converted to zero.
"""
from __future__ import annotations
import argparse
from collections import Counter,defaultdict
from datetime import date
import hashlib
import json
from pathlib import Path
from urllib.parse import urlparse

CCAA={'ES11','ES12','ES13','ES21','ES22','ES23','ES24','ES30',
      'ES41','ES42','ES43','ES51','ES52','ES53','ES61','ES62','ES70','ES63','ES64'}
NATURAL=('indicator_id','period','geo_code','subgroup','source_id','dataset_code')
FIELDS=('indicator_id','period','geo_code','subgroup','source_id','dataset_code',
        'unit','measurement_type','population','source_url','source_locator',
        'methodology_flag','frequency')

def _read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def _https(value):
    if not isinstance(value,str) or value!=value.strip() or any(ord(c)<32 for c in value):return False
    try:
        u=urlparse(value)
        return u.scheme=='https' and bool(u.hostname) and not(u.username or u.password)
    except ValueError:return False

def inspect(root:Path,today:date|None=None):
    today=today or date.today()
    counts=Counter(); flags=Counter(); indicators={}; observations=[]
    natural_keys={}; case_refs=[]; sources=Counter(); domains=defaultdict(lambda:{'indicators':set(),'observations':0})
    for path in sorted((root/'database').rglob('*.json')):
        batch=_read(path)
        if not isinstance(batch,dict) or not isinstance(batch.get('observations'),list):continue
        n=int(len(batch['observations']))
        if not n:continue
        counts['batches']+=1
        for i in batch.get('new_indicators',[]):
            ident=i.get('indicator_id')
            if ident:
                if ident in indicators and (indicators[ident].get('dataset_code'),indicators[ident].get('unit_default')) != (i.get('dataset_code'),i.get('unit_default')):
                    case_refs.append({'issue':'indicator_identity_drift','indicator_id':ident,'file':path.name})
                indicators[ident]=i
        for idx,row in enumerate(batch['observations']):
            if not isinstance(row,dict) or not all(row.get(k) for k in ('indicator_id','period','geo_code')):
                continue  # audited snapshots are not actual candidate observation rows
            loc={'file':path.name,'index':idx,'indicator_id':str(row.get('indicator_id','')),'geo_code':row.get('geo_code')}
            counts['observations']+=1
            key=tuple(str(row.get(k) or '') for k in NATURAL)
            if key in natural_keys:
                flags['duplicate_natural_key']+=1
                case_refs.append({**loc,'issue':'duplicate_natural_key','other':natural_keys[key]})
            else:natural_keys[key]={'file':path.name,'index':idx}
            missing=[k for k in FIELDS if row.get(k) is None or row.get(k)=='']
            for k in missing:flags['missing_'+k]+=1
            if missing and len(case_refs)<200:
                case_refs.append({**loc,'issue':'missing_metadata','fields':missing})
            if not _https(row.get('source_url')):flags['non_https_source_url']+=1
            val=row.get('value',None)
            if val is None:
                flags['missing_measured_value']+=1
            elif isinstance(val,bool) or not isinstance(val,(int,float)):
                flags['invalid_measurement_type']+=1
            elif val==0:counts['measured_zeros']+=1
            geo=str(row.get('geo_code',''))
            if geo in CCAA:counts['territory_rows']+=1
            elif geo=='ES':counts['national_rows']+=1
            else:flags['unknown_geo_code']+=1
            frequency=row.get('frequency')
            if frequency not in {'annual','quarterly','monthly','yearly'}:flags['missing_or_invalid_frequency']+=1
            period=str(row.get('period',''))
            if period[:4].isdigit() and int(period[:4])>today.year:
                flags['future_period_year']+=1
            stamp=row.get('reference_date')
            if stamp:
                try:
                    when=date.fromisoformat(stamp)
                    if when>today:flags['future_reference_date']+=1
                except (TypeError,ValueError):flags['invalid_reference_date']+=1
            if row.get('human_source_verification') is True or row.get('publication_authorized') is True:
                flags['unexpected_approval_in_candidate']+=1
            if row.get('publication_policy') is None:
                flags['publication_policy_not_row_level']+=1
            sources[(str(row.get('source_id','')),str(row.get('dataset_code','')))]+=1
            dom=indicators.get(row.get('indicator_id'),{}).get('domain') or 'No declarado en su tanda'
            domains[dom]['indicators'].add(row.get('indicator_id'))
            domains[dom]['observations']+=1
            observations.append(row)
    # Group only annual CCAA observations with same indicator/subgroup/year,
    # explicitly separating sources and datasets. Count coverage, not validity.
    coverage=defaultdict(set)
    for row in observations:
        if row.get('geo_code') not in CCAA or row.get('frequency') not in ('annual','yearly'):continue
        key=tuple(str(row.get(k,'')) for k in ('indicator_id','subgroup','period','source_id','dataset_code'))
        coverage[key].add(row['geo_code'])
    full=sum(len(g)==len(CCAA) for g in coverage.values())
    groups=[{'indicator_id':k[0],'subgroup':k[1],'period':k[2],'source_id':k[3],
             'dataset_code':k[4],'territories':len(v),'missing_geos':sorted(CCAA-v)}
            for k,v in coverage.items()]
    groups.sort(key=lambda x:(-x['territories'],x['indicator_id'],x['period']))
    return {
       'status':'CANDIDATES_NOT_APPROVED','source_root':'database/**/*.json (documents containing observations)',
       'as_of':today.isoformat(),'counts':dict(counts),
       'unique_indicator_ids':len({r.get('indicator_id') for r in observations}),
       'new_indicator_definitions':len(indicators),
       'source_dataset_pairs':len(sources),'coverage_groups':len(coverage),
       'complete_19_territory_groups':full,
       'flags_not_automatic_rejections':dict(sorted(flags.items())),
       'domains':{k:{'indicator_ids':len(v['indicators']),'observations':v['observations']}
                  for k,v in sorted(domains.items())},
       'top_coverage':groups[:30],
       'triage_cases':case_refs[:200],
       'limitations':['La cobertura geográfica no valida la exactitud de cada cifra.',
         'Una URL oficial no significa que la transcripción se haya verificado.',
         'Ningún valor se publica ni se importa con este análisis.',
         'Los avisos de metadatos pueden coexistir y no implican valores falsos.']}

def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--root',type=Path,default=Path(__file__).resolve().parents[1]);ap.add_argument('--output',type=Path)
    args=ap.parse_args();result=inspect(args.root,date(2026,10,10))
    rendered=json.dumps(result,ensure_ascii=False,indent=2)+'\n'
    if args.output:args.output.write_text(rendered,encoding='utf8')
    print(f"NPL V71: {result['counts'].get('observations',0)} candidates, "
          f"{result['unique_indicator_ids']} indicators, "
          f"{result['complete_19_territory_groups']} complete 19-geo groups, "
          f"{sum(result['flags_not_automatic_rejections'].values())} QA flags; review only.")

if __name__=='__main__':main()
