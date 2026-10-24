from pathlib import Path
import sys,json
from tempfile import TemporaryDirectory
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
from editorial_dedup_gate_v72 import identity,normalize_doi,report
assert normalize_doi('https://doi.org/10.1234/ABC.2026')=='10.1234/abc.2026'
assert identity({'doi':'10.1234/ABC.2026'})==identity({'primary_source_url':'https://publisher.test/doi/10.1234/abc.2026'})
with TemporaryDirectory() as tmp:
 root=Path(tmp);(root/'database').mkdir();(root/'preintegration').mkdir()
 a={'what_happened':'x','evidence':'e','title_es':'Dos informes','doi':'10.1234/ABC.2026'}
 b={**a,'doi':'https://doi.org/10.1234/abc.2026','title_es':'Un segundo enfoque'}
 (root/'database'/'T1.json').write_text(json.dumps({'candidates':[a,b]}))
 out=report(root)
 assert out['candidate_count']==2 and out['repeated_source_groups']==1
 assert out['missing_evidence_fields']['methodology']==2 and out['missing_evidence_fields']['image_brief']==2
print('V72 EDITORIAL DEDUP PASS')
