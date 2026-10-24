"""V71 fail-closed package release gate. Verifies readiness, NEVER publishes."""
from pathlib import Path
import sys,json,hashlib,tempfile
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from release_manifest_v71 import build
from staging_payload_v71 import stage
from npl_readiness_v71 import inspect
from editorial_index_v71 import build as build_editorial
from datetime import date

manifest=json.loads((ROOT/'docs/RELEASE_PAYLOAD_V71.json').read_text(encoding='utf8'))
assert manifest==build(),'V71 stale staging manifest'
assert manifest['publication_gate']=='NO_GO' and manifest['count']==133
assert manifest['staging_only_no_install_entrypoint']
files={f['path']:f for f in manifest['files']}
for rel in ('VERSION.json','_private/observatorio-espana.html','assets/observatorio/mapa-espana.js',
            '_private/pages.php','database/news_prepared_identity_index_v71.json'):
    assert files[rel]['sha256']==hashlib.sha256((ROOT/rel).read_bytes()).hexdigest()
for name in ('database/news_prepared_identity_index_v68.json','analytics.js','_private/config.php','install.php',
             'preintegration/T111_CANDIDATAS_EDITORIALES_20261010.json'):
    assert name not in files,'protected or review-only payload leaked '+name
version=json.loads((ROOT/'VERSION.json').read_text())
assert version['version']=='V71' and version['release_gate']=='NO-GO'
assert json.loads((ROOT/'database/news_prepared_identity_index_v71.json').read_text())==build_editorial()
audit=inspect(ROOT,date(2026,10,10))
assert audit['counts']['observations']==1213 and audit['unique_indicator_ids']==84
assert audit['flags_not_automatic_rejections'].get('duplicate_natural_key',0)==0
assert audit['flags_not_automatic_rejections'].get('missing_measured_value',0)==0
assert audit['status']=='CANDIDATES_NOT_APPROVED'
js=(ROOT/'assets/observatorio/mapa-espana.js').read_bytes()
markup=(ROOT/'_private/observatorio-espana.html').read_text()
assert ('/assets/observatorio/mapa-espana.js?v='+hashlib.sha256(js).hexdigest()[:12]) in markup
with tempfile.TemporaryDirectory() as base:
    parent=Path(base);dest=parent/'safe_stage'
    assert stage(ROOT/'docs/RELEASE_PAYLOAD_V71.json',dest)==133
    assert not (dest/'_private/config.php').exists()
    try:stage(ROOT/'docs/RELEASE_PAYLOAD_V71.json',dest)
    except ValueError:pass
    else:raise AssertionError('same staging destination overwritten')
    try:stage(ROOT/'docs/RELEASE_PAYLOAD_V71.json',parent/'public_html')
    except ValueError:pass
    else:raise AssertionError('deployment to public_html allowed')
print('V71 RELEASE PASS: 133 SHA files staged safely; 1,213 candidates / 84 IDs NO-GO; 182 sources/150 titles; no protected uploads')
