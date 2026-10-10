"""Current V72 fail-closed staging gate. Never modifies production."""
from pathlib import Path
import hashlib,json,sys,tempfile
from datetime import date
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from release_manifest_v72 import build
from staging_payload_v72 import stage
from editorial_index_v72 import build as build_editorial
from npl_readiness_v71 import inspect
from npl_temporal_semantic_v72 import inspect as inspect_temporal
from editorial_dedup_gate_v72 import report as report_editorial
manifest=json.loads((ROOT/'docs/RELEASE_PAYLOAD_V72.json').read_text(encoding='utf8'))
assert manifest==build(),'stale V72 staging manifest'
assert manifest['publication_gate']=='NO_GO' and manifest['staging_only_no_install_entrypoint']
assert manifest['count']==133
files={item['path']:item for item in manifest['files']}
for rel in ('VERSION.json','_private/observatorio-espana.html','assets/observatorio/mapa-espana.js',
            '_private/pages.php','database/news_prepared_identity_index_v72.json'):
    assert files[rel]['sha256']==hashlib.sha256((ROOT/rel).read_bytes()).hexdigest()
for rel in ('_private/config.php','install.php','analytics.js','database/news_prepared_identity_index_v71.json',
            'preintegration/T112_CANDIDATAS_EDITORIALES_20261010.json'):
    assert rel not in files,'protected/review-only payload leaked '+rel
version=json.loads((ROOT/'VERSION.json').read_text(encoding='utf8'))
assert version['version']=='V72' and version['release_gate']=='NO-GO'
assert json.loads((ROOT/'database/news_prepared_identity_index_v72.json').read_text())==build_editorial()
assert build_editorial()['source_count']==184 and build_editorial()['title_count']==152
npl=inspect(ROOT,date(2026,10,10));temporal=inspect_temporal(ROOT,date(2026,10,10))
assert npl['counts']['observations']==1213 and npl['unique_indicator_ids']==84
assert temporal['rows_inspected']==1213 and not temporal['flags']
assert report_editorial(ROOT)['repeated_source_groups']==0
js=(ROOT/'assets/observatorio/mapa-espana.js').read_bytes()
markup=(ROOT/'_private/observatorio-espana.html').read_text()
assert '/assets/observatorio/mapa-espana.js?v='+hashlib.sha256(js).hexdigest()[:12] in markup
with tempfile.TemporaryDirectory() as folder:
    parent=Path(folder);dest=parent/'isolated_stage'
    assert stage(ROOT/'docs/RELEASE_PAYLOAD_V72.json',dest)==133
    assert not (dest/'_private/config.php').exists()
    try:stage(ROOT/'docs/RELEASE_PAYLOAD_V72.json',dest)
    except ValueError:pass
    else:raise AssertionError('overwrote staging')
    try:stage(ROOT/'docs/RELEASE_PAYLOAD_V72.json',parent/'public_html')
    except ValueError:pass
    else:raise AssertionError('public_html deployment permitted')
print('V72 RELEASE PASS: 133 staging files, 1,213 candidates, 184 sources, zero production writes')
