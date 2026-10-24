#!/usr/bin/env python3
"""V75 local-only isolated staging assembler. Never contacts Hostinger or MariaDB."""
import argparse,hashlib,json,os,shutil,tempfile
from pathlib import Path,PurePosixPath
from qa_v75_reconciled_manifest import ROOT,MANIFEST,inspect
BLOCKED_NAMES={'public_html','htdocs','wwwroot'}
def safe_relative(name):
    if not isinstance(name,str) or not name or name.startswith(('/', '\\')) or '\\' in name:
        raise ValueError('Unsafe relative path')
    p=PurePosixPath(name)
    if any(x in ('','.','..') for x in p.parts):raise ValueError('Unsafe traversal')
    return Path(*p.parts)
def stage(destination,root=ROOT,manifest=MANIFEST):
    root=Path(root).resolve()
    destination=Path(destination).absolute()
    if inspect(root,manifest)['status']!='PASS_REVIEW_ONLY':
        raise ValueError('Manifest/content mismatch. No staging performed.')
    if destination==root or root in destination.parents:
        raise ValueError('Source must not be overwritten')
    if any(part.lower() in BLOCKED_NAMES for part in destination.parts):
        raise ValueError('Public website destination prohibited')
    if destination.exists() or not destination.parent.is_dir():
        raise ValueError('Destination must be new and parent must exist')
    for parent in (destination,*destination.parents):
        if parent.is_symlink():raise ValueError('Symlink destination')
    rows=json.loads(Path(manifest).read_text(encoding='utf-8'))['files']
    contents=[]
    for row in rows:
        relative=safe_relative(row['path'])
        source=root/relative
        if source.is_symlink() or not source.is_file():raise ValueError('Unsafe source file')
        raw=source.read_bytes()
        if len(raw)!=row['size'] or hashlib.sha256(raw).hexdigest()!=row['sha256']:
            raise ValueError('Content changed after preflight')
        contents.append((relative,raw))
    temp=Path(tempfile.mkdtemp(prefix='.v75-staging-',dir=destination.parent))
    try:
        for relative,raw in contents:
            target=temp/relative
            target.parent.mkdir(parents=True,exist_ok=True)
            target.write_bytes(raw)
            if hashlib.sha256(target.read_bytes()).digest()!=hashlib.sha256(raw).digest():
                raise ValueError('Copy failed')
        if destination.exists():raise ValueError('Destination appeared during stage')
        os.rename(temp,destination)
    finally:
        if temp.exists():shutil.rmtree(temp)
    return len(contents)
def main():
    p=argparse.ArgumentParser()
    p.add_argument('--destination',required=True)
    a=p.parse_args()
    print('V75 local staging files:',stage(Path(a.destination)),'PRODUCTION NO-GO')
if __name__=='__main__':main()
