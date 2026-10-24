"""V71 independent candidate provenance coverage test. No MariaDB access."""
from pathlib import Path
import sys,json,tempfile
from datetime import date
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from npl_readiness_v71 import inspect,CCAA

def check():
 r=inspect(ROOT,date(2026,10,10))
 assert r['counts']['observations']==1213,r['counts']
 assert r['unique_indicator_ids']==84
 assert r['counts']['national_rows']+r['counts']['territory_rows']==1213
 assert r['flags_not_automatic_rejections'].get('duplicate_natural_key',0)==0
 assert r['flags_not_automatic_rejections'].get('missing_measured_value',0)==0
 assert r['counts']['measured_zeros']>=1 # an actual measured zero must survive
 assert r['status']=='CANDIDATES_NOT_APPROVED'
 assert 0<r['complete_19_territory_groups']<=r['coverage_groups']
 assert all(set(x['missing_geos'])<=CCAA for x in r['top_coverage'])
 assert r['source_dataset_pairs']>10
 with tempfile.TemporaryDirectory() as tmp:
  p=Path(tmp)/'database';p.mkdir()
  row={'indicator_id':'TEST','period':'2025','geo_code':'ES11','subgroup':'Total',
       'source_id':'INE','dataset_code':'SERIE','value':0,'unit':'%',
       'source_url':'https://ine.es/serie','source_locator':'Tabla 1',
       'population':'Personas','measurement_type':'survey',
       'methodology_flag':'TEST','frequency':'annual','publication_policy':'review_only'}
  (p/'T19_TEST.json').write_text(json.dumps({'observations':[row,{**row,'geo_code':'ES12','value':None}]}))
  (p/'PUBLIC_AUDIT_V1.json').write_text(json.dumps({'observations':[{'total':1}]}))
  sample=inspect(Path(tmp),date(2026,10,10))
  assert sample['counts']['observations']==2,sample
  assert sample['counts']['measured_zeros']==1 and sample['flags_not_automatic_rejections']['missing_measured_value']==1
  assert sample['complete_19_territory_groups']==0
  assert sample['status']=='CANDIDATES_NOT_APPROVED'
 print('V71 NPL READINESS PASS: 1,213 rows; 84 indicator IDs; 19-territory groups; zero/null and audited snapshots.')
if __name__=='__main__':check()
