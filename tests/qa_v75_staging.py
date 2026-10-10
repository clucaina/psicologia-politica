#!/usr/bin/env python3
"""V75 staging negative cases; no network, database or production writes."""
import sys,tempfile,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from qa_v75_reconciled_manifest import inspect
from staging_payload_v75 import stage
assert inspect()['status']=='PASS_REVIEW_ONLY'
with tempfile.TemporaryDirectory() as tmp:
    base=Path(tmp)
    dest=base/'safe_v75_staging'
    assert stage(dest)==133
    assert (dest/'index.php').is_file()
    assert not (dest/'analytics.js').exists()
    assert not (dest/'_private/config.php').exists()
    for forbidden in (dest,base/'public_html',base/'htdocs',base/'wwwroot'):
        try:stage(forbidden)
        except ValueError:pass
        else:raise AssertionError('Unsafe destination accepted: '+str(forbidden))
    symlink=base/'linked'
    symlink.symlink_to(dest,target_is_directory=True)
    try:stage(symlink)
    except ValueError:pass
    else:raise AssertionError('Symlink target accepted')
print('PASS V75 staging: 133 SHA256 files, no secrets/Analytics, no overwrite, public routes blocked')
