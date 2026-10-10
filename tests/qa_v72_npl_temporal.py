import sys
from pathlib import Path
from datetime import date
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
from npl_temporal_semantic_v72 import inspect
from tempfile import TemporaryDirectory
import json
with TemporaryDirectory() as d:
    root=Path(d);(root/'database').mkdir()
    rows=[
        {'indicator_id':'X','period':'2027-Q1','geo_code':'ES11','frequency':'quarterly','value':0},
        {'indicator_id':'X','period':'2026-11','geo_code':'ES12','frequency':'monthly','value':2},
        {'indicator_id':'X','period':'2026-Q2','geo_code':'ES13','frequency':'annual','value':3},
        {'indicator_id':'X','period':'2025','geo_code':'ES21','frequency':'annual','value':-1,'percentage_semantics':'bounded_share'},
        {'indicator_id':'X','period':'2025','geo_code':'ES22','frequency':'annual','value':-120,'percentage_semantics':'relative_change'},
        {'indicator_id':'X','period':'2025','geo_code':'ES23','frequency':'annual','value':None},
        {'indicator_id':'X','period':'2025','geo_code':'ES24','frequency':'annual','value':5,
         'measurement_period_start':'2025-11-30','measurement_period_end':'2025-01-01'},
        {'indicator_id':'X','period':'2025','geo_code':'ES30','frequency':'annual','value':0},
    ]
    (root/'database'/'fixture.json').write_text(json.dumps({'observations':rows}))
    result=inspect(root,date(2026,10,10));f=result['flags']
    assert result['rows_inspected']==8 and result['measured_zeros']==2
    assert f['future_period']==2 and f['frequency_period_mismatch']==1
    assert f['bounded_share_outside_0_100']==1 and f['null_measurement_requires_explicit_missingness']==1
    assert f['reversed_measurement_window']==1
    assert 'invalid_numeric_measurement' not in f
print('V72 NPL temporal contract PASS')
