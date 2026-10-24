#!/usr/bin/env python3
"""Read-only temporal and semantic QA of candidate NPL rows; never approves data."""
from __future__ import annotations
from collections import Counter
from datetime import date
from pathlib import Path
import json, math, re

PERIOD=re.compile(r'^((?:19|20)\d{2})(?:-(Q[1-4]|0[1-9]|1[0-2]))?$')
ANNUAL={'annual','yearly'}

def inspect(root:Path,today:date=date(2026,10,10)):
    flags=Counter();cases=[];rows=0;zeros=0;nonzero=0
    for file in sorted((root/'database').rglob('*.json')):
        try:batch=json.loads(file.read_text(encoding='utf8'))
        except (OSError,ValueError):continue
        if not isinstance(batch,dict) or not isinstance(batch.get('observations'),list):continue
        for i,row in enumerate(batch['observations']):
            if not isinstance(row,dict) or not all(row.get(k) for k in ('indicator_id','period','geo_code')):continue
            rows+=1;issues=[];period=str(row['period']);match=PERIOD.fullmatch(period)
            frequency=row.get('frequency')
            if not match:issues.append('invalid_period_syntax')
            else:
                year=int(match[1]);part=match[2]
                if year>today.year:issues.append('future_period')
                elif year==today.year and part:
                    if part.startswith('Q') and int(part[1])>(today.month-1)//3+1:issues.append('future_period')
                    if not part.startswith('Q') and int(part)>today.month:issues.append('future_period')
                if frequency in ANNUAL and part is not None:issues.append('frequency_period_mismatch')
                elif frequency=='quarterly' and (part is None or not part.startswith('Q')):issues.append('frequency_period_mismatch')
                elif frequency=='monthly' and (part is None or part.startswith('Q')):issues.append('frequency_period_mismatch')
            value=row.get('value')
            if value is None:issues.append('null_measurement_requires_explicit_missingness')
            elif isinstance(value,bool) or not isinstance(value,(int,float)) or not math.isfinite(value):issues.append('invalid_numeric_measurement')
            else:
                if value==0:zeros+=1
                else:nonzero+=1
                semantics=row.get('percentage_semantics')
                # Changes, index growth and percent-point differences may exceed 100
                # or be negative. Only a clearly declared bounded share is bounded.
                if semantics in {'bounded_share','share_of_all_residents_16_74','share_of_denominator_population'} and not 0<=value<=100:
                    issues.append('bounded_share_outside_0_100')
            if row.get('reference_date'):
                try:
                    ref=date.fromisoformat(row['reference_date'])
                    if ref>today:issues.append('future_reference_date')
                except (ValueError,TypeError):issues.append('invalid_reference_date')
            if row.get('measurement_period_start') and row.get('measurement_period_end'):
                try:
                    start=date.fromisoformat(row['measurement_period_start']);end=date.fromisoformat(row['measurement_period_end'])
                    if start>end:issues.append('reversed_measurement_window')
                    if start>today or end>today:issues.append('future_measurement_window')
                except (ValueError,TypeError):issues.append('invalid_measurement_window')
            elif bool(row.get('measurement_period_start'))!=bool(row.get('measurement_period_end')):
                issues.append('incomplete_measurement_window')
            for issue in issues:
                flags[issue]+=1
                if len(cases)<150:cases.append({'issue':issue,'file':str(file.relative_to(root)),'row':i,
                    'indicator_id':row['indicator_id'],'period':period,'geo_code':row['geo_code']})
    return {'as_of':today.isoformat(),'scope':'CANDIDATES_ONLY_NO_IMPORT','rows_inspected':rows,
            'measured_zeros':zeros,'other_numeric_measurements':nonzero,
            'flags':dict(sorted(flags.items())),'cases':cases,
            'note':'Las alertas son solicitudes de revisión, no una afirmación de que la fuente primaria sea incorrecta.'}

def main():
    import argparse
    parser=argparse.ArgumentParser();parser.add_argument('--root',type=Path,default=Path(__file__).resolve().parents[1]);parser.add_argument('--output',type=Path)
    args=parser.parse_args();result=inspect(args.root)
    if args.output:args.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
    print('V72 NPL temporal:',result['rows_inspected'],'rows,',sum(result['flags'].values()),'alerts:',result['flags'])
if __name__=='__main__':main()
