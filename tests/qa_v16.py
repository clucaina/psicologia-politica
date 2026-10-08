"""V16 static integration: account-scoped drafts, mandatory hashes, source and editorial quality."""
import json,re
from pathlib import Path
R=Path(__file__).resolve().parent.parent
ed=(R/'_private/editorial.php').read_text()
assert "!is_string($p['expected_content_hash']??null)" in ed
assert "hash_equals(pp_author_fingerprint($before),$p['expected_content_hash'])" in ed
page=(R/'_private/pages.php').read_text()
assert 'data-pp-editor-scope' in page and "hash('sha256',mb_strtolower" in page
chunk=(R/'assets/chunk-KECI7H7X.js').read_text()
assert 'pp-author-desk-autosave-v3:' in chunk
assert 'document.body?.dataset.ppEditorScope' in chunk
assert 'expected_content_hash:t._server_content_hash' in chunk
js=(R/'assets/observatorio/mapa-espana.js').read_text()
assert 'puntos porcentuales' in js and 'requestedIndicator!==state.indicator' in js
assert 'Selecciona un año válido, no futuro.' in js
news=(R/'_private/news_ingest.php').read_text()
assert 'LIMIT 10001' in news and 'Índice editorial incompleto' in news
source=json.loads((R/'database/T40_FUENTES_CIS_REVISION_20261008.json').read_text())
assert source['numeric_npl_observations']==0 and len(source['source_endpoints'])==2
assert all(x['geo_scope']=='ES' for x in source['source_endpoints'])
existing=set();urls=set();total=0
for p in sorted((R/'database').glob('T*_CANDIDATAS*.json')):
 d=json.loads(p.read_text());assert d['publication_policy'] in ('review_only','human_approval_required')
 for item in d.get('items',d.get('candidates',[])):
  doi=str(item.get('doi') or '').lower();url=item.get('primary_source_url') or item.get('source_url')
  assert url and url.startswith('https://')
  assert url not in urls,('duplicate URL',p,url)
  urls.add(url)
  if doi:assert doi not in existing,('duplicate DOI',p,doi);existing.add(doi)
  for k in ('what_happened','evidence','methodology'):
   assert item.get(k),(p,k)
  assert item.get('limits') or item.get('limitations')
  assert item.get('image_brief') or item.get('original_image_brief')
  total+=1
assert total==21,total
assert len(json.loads((R/'database/T39_CANDIDATAS_EDITORIALES_20261008.json').read_text())['candidates'])==3
print(f'V16 STATIC PASS: account-scoped autosave, mandatory lock, strict years, {total} DOI/URL-deduplicated editorial candidates, 2 CIS catalogs.')
