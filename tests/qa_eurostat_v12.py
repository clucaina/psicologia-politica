"""Synthetic JSON-stat fixture; verifies geometry, zero, missing, provenance and filters."""
import importlib.util
from pathlib import Path
from types import SimpleNamespace
R=Path(__file__).resolve().parent.parent
spec=importlib.util.spec_from_file_location('eurostat',R/'scripts/eurostat_jsonstat2_to_npl.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
fixture={'version':'2.0','class':'dataset','id':['freq','unit','geo','time'],'size':[1,1,2,2],
 'dimension':{'freq':{'category':{'index':{'A':0}}},'unit':{'category':{'index':{'PC':0}}},
 'geo':{'category':{'index':{'ES61':0,'ES30':1}}},'time':{'category':{'index':{'2024':0,'2025':1}}}},
 'value':{'0':0,'1':25.7,'2':33.4},'status':{'0':'p'}}
values,missing=module.decode(fixture,{'freq':'A','unit':'PC'},['ES61','ES30'],['2024','2025'])
assert len(values)==3 and len(missing)==1
assert values[0]==('ES61','2024',0,'p')
assert missing==[{'geo_code':'ES30','period':'2025','reason':'null_in_source'}]
try:module.decode(fixture,{'freq':'A'},['ES61'],['2024'])
except ValueError as e:assert 'Unfiltered dimension unit' in str(e)
else:raise AssertionError('Unfiltered unit must be rejected')
try:module.decode({**fixture,'value':{'0':float('nan')}},{'freq':'A','unit':'PC'},['ES61'],['2024'])
except ValueError:pass
else:raise AssertionError('NaN must be rejected')
print('V12 EUROSTAT QA PASS: JSON-stat multidimensional index, 0, null, source status, explicit filters.')
