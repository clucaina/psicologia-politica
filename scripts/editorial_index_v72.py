#!/usr/bin/env python3
"""Extend V71 dedupe identities with T112; no CMS or production access."""
from pathlib import Path
import json,hashlib
from editorial_index_v71 import title_key
ROOT=Path(__file__).resolve().parents[1]
def build(root=ROOT):
    base=json.loads((root/'database/news_prepared_identity_index_v71.json').read_text(encoding='utf8'))
    batch=json.loads((root/'preintegration/T112_CANDIDATAS_EDITORIALES_20261010.json').read_text(encoding='utf8'))
    assert base['publication_authorized'] is False
    assert batch['publication_policy']=='review_only_no_auto_publication'
    keys=set(base['source_keys']);titles=set(base['title_hashes'])
    for item in batch['candidates']:
        assert item['publication_authorized'] is False and item['human_editor_approval'] is False
        doi='doi:'+item['doi'].lower();title=hashlib.sha256(title_key(item['title_es']).encode()).hexdigest()
        assert doi not in keys and title not in titles,'duplicate identity'
        keys.add(doi);titles.add(title)
    return {'version':'V72','generated_at':'2026-10-10','purpose':'dedupe_identities_only_not_publication',
        'source_keys':sorted(keys),'title_hashes':sorted(titles),
        'source_count':len(keys),'title_count':len(titles),'publication_authorized':False}
if __name__=='__main__':
    dest=ROOT/'database/news_prepared_identity_index_v72.json'
    dest.write_text(json.dumps(build(),ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf8')
    print('V72 EDITORIAL INDEX:',build()['source_count'],'sources,',build()['title_count'],'titles; no publishing')
