#!/usr/bin/env python3
"""Editorial queue read-only identity and evidence gate; no automatic publication."""
from __future__ import annotations
from collections import defaultdict,Counter
from pathlib import Path
from urllib.parse import urlparse,unquote
import json,re,unicodedata

DOI=re.compile(r'10\.\d{4,9}/[-._;()/:a-z0-9]+',re.I)

def normalize_doi(raw):
    if not isinstance(raw,str):return None
    value=unquote(raw.strip()).lower().replace('https://doi.org/','').replace('http://dx.doi.org/','').removeprefix('doi:')
    match=DOI.search(value)
    if not match:return None
    return match.group().rstrip('.,;:)')

def identity(item):
    doi=normalize_doi(item.get('doi'))
    if doi:return 'doi:'+doi
    for field in ('primary_source_url','source_url'):
        url=item.get(field)
        if not isinstance(url,str):continue
        doi=normalize_doi(url)
        if doi:return 'doi:'+doi
        try:
            u=urlparse(url)
            if u.scheme=='https' and u.hostname and not(u.username or u.password):
                return 'url:'+u.hostname.lower()+u.path.rstrip('/').lower()
        except ValueError:pass
    return None

def normalize_title(title):
    if not isinstance(title,str):return ''
    value=unicodedata.normalize('NFKD',title).lower()
    return re.sub(r'[^a-z0-9]+',' ',''.join(c for c in value if not unicodedata.combining(c))).strip()

def scan(root):
    candidates=[]
    files=sorted((root/'database').rglob('*.json'))+sorted((root/'preintegration').glob('T*_CANDIDATAS*.json'))
    for file in files:
        try:batch=json.loads(file.read_text(encoding='utf8'))
        except (OSError,ValueError):continue
        if not isinstance(batch,dict):continue
        for field in ('candidates','items'):
            for index,item in enumerate(batch.get(field,[])):
                if not isinstance(item,dict) or not('what_happened' in item and 'evidence' in item):continue
                candidates.append({'file':str(file.relative_to(root)),'index':index,'id':item.get('id'),
                    'key':identity(item),'title':item.get('title_es') or item.get('title'),
                    'title_normalized':normalize_title(item.get('title_es') or item.get('title')),
                    'source_url':item.get('primary_source_url') or item.get('source_url'),
                    'missing':[name for name,alternates in {
                        'title':['title_es','title'],'source':['primary_source_url','source_url'],
                        'what_happened':['what_happened'],'evidence':['evidence'],
                        'methodology':['methodology'],'limits':['limitations','limits'],
                        'importance':['why_it_matters','importance'],
                        'image_brief':['image_brief','original_image_brief']}.items()
                        if not any(isinstance(item.get(key),str) and item[key].strip() for key in alternates)],
                    'approval_conflict':any(item.get(key) is True for key in ('human_editor_approval','publication_approved','publication_authorized'))})
    return candidates

def report(root):
    rows=scan(root);keys=defaultdict(list);titles=defaultdict(list);missing=Counter()
    for row in rows:
        if row['key']:keys[row['key']].append(row)
        if row['title_normalized']:titles[row['title_normalized']].append(row)
        missing.update(row['missing'])
    duplicates=[{'key':key,'count':len(group),'items':[{'file':r['file'],'id':r['id']} for r in group]}
        for key,group in sorted(keys.items()) if len(group)>1]
    duplicate_titles=[{'title':key,'count':len(group),'items':[{'file':r['file'],'id':r['id']} for r in group]}
        for key,group in sorted(titles.items()) if len(group)>1 and len({r['key'] for r in group})>1]
    return {'status':'REVIEW_ONLY_NOT_PUBLISHABLE','candidate_count':len(rows),'identified_source_count':len(keys),
        'repeated_source_groups':len(duplicates),'repeated_source_items':sum(d['count'] for d in duplicates),
        'same_title_different_source_groups':len(duplicate_titles),
        'missing_evidence_fields':dict(missing),'unidentified_sources':sum(r['key'] is None for r in rows),
        'unexpected_approved_items':sum(r['approval_conflict'] for r in rows),
        'duplicates':duplicates,'title_collisions':duplicate_titles,
        'note':'Un mismo DOI puede dar lugar a diferentes enfoques periodísticos: se señala revisión, no se borra ni publica.'}

def main():
    import argparse
    ap=argparse.ArgumentParser();ap.add_argument('--root',type=Path,default=Path(__file__).resolve().parents[1]);ap.add_argument('--output',type=Path)
    a=ap.parse_args();r=report(a.root)
    if a.output:a.output.write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
    print('V72 EDITORIAL:',r['candidate_count'],'candidates,',r['repeated_source_groups'],'repeated-source groups,',r['unidentified_sources'],'without source identity')
if __name__=='__main__':main()
