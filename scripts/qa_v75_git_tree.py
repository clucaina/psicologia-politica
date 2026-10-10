#!/usr/bin/env python3
"""V75 Git source provenance; read-only. NO deployment or database changes."""
import argparse, hashlib, json, subprocess
from pathlib import Path
REQUIRED={'index.php','.htaccess','_private/core.php','_private/pages.php',
          '_private/api.php','_private/assets.json','assets/site.css',
          'assets/observatorio/mapa-espana.js','geo/spain-communities.geojson'}
PROTECTED={'_private/config.php','.env','_private/installed.lock'}
PRESERVE_RUNTIME={'analytics.js'}
PROTECTED_PREFIX=('_private/sessions/','_private/backups/','uploads/')
def inspect(root):
    root=Path(root).resolve()
    raw=subprocess.run(['git','ls-files','-z'],cwd=root,check=True,capture_output=True).stdout
    tracked=[x.decode('utf-8') for x in raw.split(b'\x00') if x]
    errors=[];seen=set()
    for name in tracked:
        if name.casefold() in seen:errors.append('CASE_COLLISION:'+name)
        seen.add(name.casefold())
        if name.startswith('/') or '..' in Path(name).parts:errors.append('UNSAFE_PATH:'+name)
        if name in PROTECTED or (name.startswith(PROTECTED_PREFIX) and name!='uploads/.htaccess'):
            errors.append('PROTECTED_CONTENT:'+name)
        if (root/name).is_symlink():errors.append('SYMLINK:'+name)
    for name in sorted(REQUIRED):
        if name not in tracked or not (root/name).is_file():errors.append('MISSING:'+name)
    try:
        manifest=json.loads((root/'_private/assets.json').read_text(encoding='utf-8'))
        for key in ('js','css'):
            path=manifest[key]
            if not isinstance(path,str) or not path.startswith('/assets/') or '..' in path:
                errors.append('UNSAFE_ASSET:'+key)
            elif path.split('?',1)[0].removeprefix('/') not in tracked:
                errors.append('MISSING_ASSET:'+path)
    except (KeyError,TypeError,ValueError,OSError):errors.append('INVALID_ASSET_MANIFEST')
    files=[]
    for name in sorted(tracked):
        f=root/name
        if not f.is_file() or f.is_symlink() or name in PRESERVE_RUNTIME:continue
        b=f.read_bytes()
        files.append({'path':name,'bytes':len(b),'sha256':hashlib.sha256(b).hexdigest()})
    return {'status':'PASS_INVENTORY_ONLY' if not errors else 'BLOCK',
            'release_gate':'NO_GO','deployment_authorized':False,
            'file_count':len(files),'files':files,'issues':errors,
            'preserved_runtime_files':sorted(set(tracked)&PRESERVE_RUNTIME),
            'note':'Repository provenance only, not equality with Hostinger or scientific approval.'}
def main():
    p=argparse.ArgumentParser()
    p.add_argument('--root',default='.')
    p.add_argument('--output',default='build/qa_v75_git_inventory.json')
    args=p.parse_args()
    result=inspect(args.root)
    dest=Path(args.output);dest.parent.mkdir(parents=True,exist_ok=True)
    dest.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print('V75 tracked files:',result['file_count'],'status:',result['status'],'NO DEPLOY')
    for issue in result['issues'][:20]:print(issue)
    return 0 if result['status']=='PASS_INVENTORY_ONLY' else 2
if __name__=='__main__':raise SystemExit(main())
