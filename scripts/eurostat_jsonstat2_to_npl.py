#!/usr/bin/env python3
"""Offline, review-only JSON-stat 2.0 -> NPL candidate converter.

Never fetches or publishes data. Requires an independently archived official API
response and an actual human-supplied retrieval date. All numeric values are
copied from the input; zero is preserved; missing values are not imputed.
"""
import argparse
import datetime as dt
import hashlib
import itertools
import json
import math
import re
from pathlib import Path
from urllib.parse import urlsplit

NUTS2 = {
    'ES11':'Galicia','ES12':'Asturias','ES13':'Cantabria','ES21':'País Vasco',
    'ES22':'Navarra','ES23':'La Rioja','ES24':'Aragón','ES30':'Madrid',
    'ES41':'Castilla y León','ES42':'Castilla-La Mancha','ES43':'Extremadura',
    'ES51':'Cataluña','ES52':'Comunitat Valenciana','ES53':'Illes Balears',
    'ES61':'Andalucía','ES62':'Región de Murcia','ES70':'Canarias',
    'ES63':'Ceuta','ES64':'Melilla','ES':'España'
}

def categories(dataset, dimension):
    cat=dataset['dimension'][dimension]['category']
    index=cat['index']
    if isinstance(index,dict):
        positions={str(k):int(v) for k,v in index.items()}
    elif isinstance(index,list):
        positions={str(k):i for i,k in enumerate(index)}
    else:raise ValueError(f'Invalid category index for {dimension}')
    if sorted(positions.values())!=list(range(len(positions))):
        raise ValueError(f'Non-contiguous category positions for {dimension}')
    return positions

def decode(dataset, selected, geo_codes, years):
    if dataset.get('version')!='2.0' or dataset.get('class')!='dataset':
        raise ValueError('Expected a JSON-stat 2.0 dataset')
    dims=dataset.get('id',[]); sizes=dataset.get('size',[])
    if not isinstance(dims,list) or len(dims)!=len(sizes) or len(set(dims))!=len(dims):
        raise ValueError('Invalid dimension metadata')
    if 'geo' not in dims or 'time' not in dims:
        raise ValueError('Expected geo and time dimensions')
    indexes={dim:categories(dataset,dim) for dim in dims}
    if any(len(indexes[dim])!=int(sizes[i]) for i,dim in enumerate(dims)):
        raise ValueError('Dimension size/index mismatch')
    for dim in dims:
        if dim not in ('geo','time') and dim not in selected:
            raise ValueError(f'Unfiltered dimension {dim}; explicitly select one code')
    for dim,code in selected.items():
        if dim not in indexes or code not in indexes[dim] or dim in ('geo','time'):
            raise ValueError(f'Unknown/invalid filter {dim}={code}')
    values=dataset.get('value',{})
    statuses=dataset.get('status',{})
    if not isinstance(values,(dict,list)) or not isinstance(statuses,(dict,list)):
        raise ValueError('Invalid values or status')
    def cell(container,index):
        if isinstance(container,dict):return container.get(str(index))
        return container[index] if index<len(container) else None
    strides=[];stride=1
    for size in reversed(sizes):strides.append(stride);stride*=int(size)
    strides=list(reversed(strides))
    observations=[];missing=[]
    for geo,year in itertools.product(geo_codes,years):
        if geo not in indexes['geo'] or year not in indexes['time']:
            missing.append({'geo_code':geo,'period':year,'reason':'not_in_source_dimensions'});continue
        keys={**selected,'geo':geo,'time':year}
        flat=sum(indexes[dim][keys[dim]]*strides[i] for i,dim in enumerate(dims))
        value=cell(values,flat);status=cell(statuses,flat)
        if value is None:
            missing.append({'geo_code':geo,'period':year,'reason':'null_in_source'});continue
        if isinstance(value,bool) or not isinstance(value,(int,float)) or not math.isfinite(value):
            raise ValueError(f'Invalid numeric value at index {flat}')
        observations.append((geo,year,value,status))
    return observations,missing

def build(args):
    data_bytes=Path(args.input).read_bytes()
    data=json.loads(data_bytes)
    parsed=urlsplit(args.source_url)
    if parsed.scheme!='https' or parsed.hostname!='ec.europa.eu' or not parsed.path.startswith('/eurostat/api/dissemination/statistics/1.0/data/'):
        raise ValueError('Official Eurostat Statistics API URL required')
    endpoint_code=parsed.path.rstrip('/').split('/')[-1]
    if endpoint_code.lower()!=args.dataset_code.lower():
        raise ValueError('Dataset code does not match the archived source URL')
    if not re.fullmatch(r'[A-Za-z0-9_.-]{2,100}',args.dataset_code):
        raise ValueError('Invalid dataset code')
    if not re.fullmatch(r'T\d{2}_[A-Z0-9_]{3,61}',args.batch_id):raise ValueError('Invalid batch ID')
    if not re.fullmatch(r'[A-Za-z0-9_-]{2,100}',args.indicator_id):raise ValueError('Invalid indicator ID')
    date=dt.date.fromisoformat(args.retrieved_at)
    if date>dt.date.today():raise ValueError('Future retrieval date is forbidden')
    if not args.source_locator.strip() or not args.limits.strip():raise ValueError('Source locator and methodological limits required')
    selected={}
    for item in args.filter:
        if '=' not in item:raise ValueError('Filter must be dimension=code')
        dim,code=item.split('=',1)
        if dim in selected:raise ValueError(f'Duplicate filter: {dim}')
        selected[dim]=code
    geos=args.geo or list(NUTS2)
    years=args.year
    if len(set(geos))!=len(geos) or any(g not in NUTS2 for g in geos):raise ValueError('Unknown or duplicate NUTS2 geography')
    if len(set(years))!=len(years) or any(not re.fullmatch(r'(19|20)\d{2}',y) for y in years):raise ValueError('Invalid years')
    if any(int(y)>dt.date.today().year for y in years):raise ValueError('Future observations cannot be treated as measured history')
    decoded,missing=decode(data,selected,geos,years)
    source_updated=data.get('updated')
    source_sha=hashlib.sha256(data_bytes).hexdigest()
    observations=[]
    for geo,year,value,status in decoded:
        if args.unit=='%' and not (0<=value<=100):
            raise ValueError(f'Out-of-range percentage {geo} {year}: {value}')
        observations.append({
            'indicator_id':args.indicator_id,'period':year,'geo_code':geo,'geography':NUTS2[geo],
            'subgroup':args.subgroup,'value':value,'unit':args.unit,'source_id':'ESTAT',
            'dataset_code':args.dataset_code,'source_url':args.source_url,
            'source_locator':args.source_locator,'source_updated':source_updated,
            'retrieved_at':args.retrieved_at,'batch_id':args.batch_id,
            'measurement_type':args.measurement_type,'methodology_flag':args.methodology_flag,
            'quality_flag':'PENDING_VISUAL_SOURCE_QA','import_review':'pending',
            'source_status_code':status,'source_snapshot_sha256':source_sha,
            'population':args.population,'limits':args.limits,
            'selected_dimensions':selected
        })
    return {
        'batch_id':args.batch_id,'depends_on_batch':None,
        'conversion_policy':'offline_source_snapshot; no estimates, no auto-publication',
        'source_snapshot_sha256':source_sha,'missing_cells':missing,
        'new_indicators':[{'indicator_id':args.indicator_id,'domain':args.domain,
            'label_es':args.label,'unit_default':args.unit,'source_id':'ESTAT',
            'dataset_code':args.dataset_code,'status':'candidate_review',
            'methodology_note':args.limits}],
        'source_endpoints':[{'source_id':'ESTAT','dataset_code':args.dataset_code,
            'url':args.source_url,'retrieved':args.retrieved_at,'snapshot_sha256':source_sha}],
        'observations':observations
    }

def main():
    ap=argparse.ArgumentParser(description=__doc__)
    for flag in ('input','output','source-url','retrieved-at','batch-id','indicator-id','dataset-code','domain','label','unit','subgroup','methodology-flag','measurement-type','population','source-locator','limits'):
        ap.add_argument('--'+flag,required=True)
    ap.add_argument('--filter',action='append',default=[],help='Fixed dimension code: freq=A, unit=PC, etc.')
    ap.add_argument('--geo',action='append',default=[],help='NUTS2 code, repeatable; default all 19 + ES')
    ap.add_argument('--year',action='append',required=True,help='Four-digit year, repeatable')
    args=ap.parse_args()
    output=build(args)
    Path(args.output).write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(f"Review-only batch: {len(output['observations'])} source values; {len(output['missing_cells'])} missing cells; 0 published")
if __name__=='__main__':main()
