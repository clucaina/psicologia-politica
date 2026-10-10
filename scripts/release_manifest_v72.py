#!/usr/bin/env python3
"""V72 isolated SHA allowlist; never authorizes remote deploy."""
from pathlib import Path
import hashlib,json
from release_manifest_v71 import ROOT,eligible as previous_eligible
OUT=ROOT/'docs/RELEASE_PAYLOAD_V72.json'
def eligible(path):
    rel=path.relative_to(ROOT).as_posix()
    if rel=='database/news_prepared_identity_index_v71.json':return False
    if rel=='database/news_prepared_identity_index_v72.json':
        if path.is_symlink():raise RuntimeError('Symlink forbidden')
        return True
    return previous_eligible(path)
def build():
    prior=json.loads((ROOT/'docs/RELEASE_PAYLOAD_V71.json').read_text(encoding='utf8'))
    entries=[];seen=set()
    for path in sorted(ROOT.rglob('*')):
        if not path.is_file() or not eligible(path):continue
        rel=path.relative_to(ROOT).as_posix()
        if rel.casefold() in seen:raise RuntimeError('Case collision '+rel)
        seen.add(rel.casefold())
        for parent in path.parents:
            if parent==ROOT:break
            if parent.is_symlink():raise RuntimeError('Symlink ancestor '+rel)
        content=path.read_bytes();entries.append({'path':rel,'size':len(content),'sha256':hashlib.sha256(content).hexdigest()})
    return {**prior,'version':'V72','created_at':'2026-10-10','count':len(entries),
        'files':entries,'publication_gate':'NO_GO',
        'requires_before_deploy':list(dict.fromkeys([*prior.get('requires_before_deploy',[]),
          'V72 verify correlation pair/coefficients parity against actual MariaDB staging API',
          'V72 T112 DOI and evidence are review-only; reconcile with CMS before publication',
          'V72 temporal candidate audit is not proof of primary-source accuracy',
          'V72 do not deploy without protected production files, uploads and database reconciliation']))}
if __name__=='__main__':
    result=build();OUT.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
    print('V72 NO-GO:',result['count'],'SHA allowlisted staging files')
