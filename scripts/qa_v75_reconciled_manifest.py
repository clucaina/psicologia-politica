#!/usr/bin/env python3
"""V75 read-only GitHub SHA256 release gate. NEVER publishes or migrates."""
import argparse,hashlib,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
MANIFEST=ROOT/'docs/RELEASE_PAYLOAD_V75_GITHUB.json'
PROTECTED={'analytics.js','_private/config.php','install.php','.env'}
PROTECTED_PREFIX=('uploads/','_private/backups/','_private/sessions/')
def inspect(root=ROOT,manifest=MANIFEST):
    root=Path(root).resolve();doc=json.loads(Path(manifest).read_text(encoding='utf-8'))
    failures=[]
    rows=doc.get('files')
    if doc.get('version')!='V75' or doc.get('publication_gate')!='NO_GO':
        failures.append('INVALID_RELEASE_GATE')
    if not isinstance(rows,list) or len(rows)!=133 or doc.get('count')!=133:
        failures.append('INCOMPLETE_RELEASE_MANIFEST');rows=rows if isinstance(rows,list) else []
    if doc.get('reconciliation_state')!='GITHUB_SHA_VERIFIED_NOT_HOSTINGER':
        failures.append('MISLEADING_VERIFICATION_SCOPE')
    seen=set()
    for row in rows:
        name=row.get('path','')
        if not isinstance(name,str) or not name or name.startswith('/') or '..' in Path(name).parts or '\\' in name:
            failures.append('INVALID_PATH:'+str(name));continue
        if name.casefold() in seen:failures.append('DUPLICATE:'+name)
        seen.add(name.casefold())
        if name in PROTECTED or name.startswith(PROTECTED_PREFIX):
            failures.append('PROTECTED_IN_STAGE:'+name);continue
        path=root/name
        if not path.is_file() or path.is_symlink():
            failures.append('MISSING_OR_SYMLINK:'+name);continue
        if any(a.is_symlink() for a in path.parents if root in a.parents):
            failures.append('SYMLINK_PARENT:'+name);continue
        b=path.read_bytes()
        if len(b)!=row.get('size') or hashlib.sha256(b).hexdigest()!=row.get('sha256'):
            failures.append('SHA_MISMATCH:'+name)
    return {'status':'PASS_REVIEW_ONLY' if not failures else 'BLOCK',
            'checked':len(rows),'issues':failures,'deployment_authorized':False,
            'hostinger_reconciled':False,'database_validated':False}
def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--root',default=str(ROOT))
    parser.add_argument('--manifest',default=str(MANIFEST))
    args=parser.parse_args()
    report=inspect(args.root,args.manifest)
    print(json.dumps(report,ensure_ascii=False,indent=2))
    return 0 if report['status']=='PASS_REVIEW_ONLY' else 2
if __name__=='__main__':raise SystemExit(main())
