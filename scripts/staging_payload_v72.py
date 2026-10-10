#!/usr/bin/env python3
"""V72 isolated staging assembler with atomic fresh destination and NO overwrites."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import shutil
import tempfile
from release_manifest_v72 import ROOT,build
from staging_payload_v52 import safe_relative

def stage(manifest:Path,destination:Path):
    expected=build();actual=json.loads(manifest.read_text(encoding='utf8'))
    if actual!=expected or actual['publication_gate']!='NO_GO' or not actual['staging_only_no_install_entrypoint']:
        raise ValueError('Manifest stale/unsafe, staging aborted')
    destination=destination.absolute()
    if ROOT==destination or ROOT in destination.parents:raise ValueError('Destination inside repo')
    if any(x.lower() in ('public_html','htdocs','wwwroot') for x in destination.parts):
        raise ValueError('Never write a public website directory')
    for parent in (destination,*destination.parents):
        if parent.is_symlink():raise ValueError('Symlink destination forbidden')
    if destination.exists() or not destination.parent.is_dir():raise ValueError('Requires new destination, existing parent')
    files=[]
    for row in actual['files']:
        rel=safe_relative(row['path']);source=ROOT/rel
        if not source.is_file() or source.is_symlink():raise ValueError('Unsafe source')
        for parent in source.parents:
            if parent==ROOT:break
            if parent.is_symlink():raise ValueError('Unsafe parent')
        content=source.read_bytes()
        if len(content)!=row['size'] or hashlib.sha256(content).hexdigest()!=row['sha256']:
            raise ValueError('Source tampered '+str(rel))
        files.append((rel,content))
    tmp=Path(tempfile.mkdtemp(prefix='.pp-v72-',dir=destination.parent))
    try:
        for rel,content in files:
            path=tmp/rel;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(content)
            if hashlib.sha256(path.read_bytes()).hexdigest()!=hashlib.sha256(content).hexdigest():
                raise ValueError('Staging copy mismatch')
        if destination.exists():raise ValueError('Destination created unexpectedly')
        os.rename(tmp,destination)
    finally:
        if tmp.exists():shutil.rmtree(tmp)
    return len(files)

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--manifest',type=Path,default=ROOT/'docs/RELEASE_PAYLOAD_V72.json')
    parser.add_argument('--destination',type=Path,required=True)
    args=parser.parse_args();print('V72 LOCAL STAGING:',stage(args.manifest,args.destination),'files, NO host changes')
