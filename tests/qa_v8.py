"""V8 no-network QA for data lineage, geography and accessible 2D prototype."""
import json,re,xml.etree.ElementTree as ET
from pathlib import Path
from collections import Counter
R=Path(__file__).resolve().parent.parent
load=lambda path:json.loads((R/path).read_text(encoding='utf-8'))
batches=[load('database/seeds/T19_CCAA_BASELINE.json'),load('database/T26_VULNERABILIDAD_SEGURIDAD_2025.json'),load('database/T27_SALARIOS_CCAA_2024.json'),load('database/T28_DERIVADOS_SALARIOS_2024.json')]
assert [len(x['observations']) for x in batches]==[152,85,54,36]
keys=[]
for batch in batches:
 for o in batch['observations']:
  assert o['batch_id']==batch['batch_id']
  assert o['source_url'].startswith('https://')
  assert o['value'] is not None and isinstance(o['value'],(int,float))
  keys.append(tuple(o[k] for k in ('indicator_id','period','geo_code','subgroup','source_id','dataset_code')))
assert len(keys)==len(set(keys)), 'cross-batch natural-key collision'
source=batches[2]['observations'];by={}
for o in source:by.setdefault(o['geo_code'],{})[o['subgroup']]=o['value']
assert len(by)==18 and all(len(x)==3 for x in by.values())
base=by['ES']['Ambos sexos']
for o in batches[3]['observations']:
 assert o['measurement_type']=='derived' and o['signed_percent'] is True
 geo=o['geo_code'];values=by[geo]
 if o['indicator_id']=='EAES2024_GAP_MEDIA_ANUAL_PCT':
  expected=round((values['Hombres']-values['Mujeres'])/values['Hombres']*100,4)
 else:
  assert o['indicator_id']=='EAES2024_DIFF_MEDIA_NACIONAL_PCT'
  expected=round((values['Ambos sexos']/base-1)*100,4)
 assert abs(o['value']-expected)<1e-8,(geo,o['indicator_id'])
 assert o['geo_code'] not in {'ES63','ES64'}
assert batches[3]['depends_on_batch']==batches[2]['batch_id']
geo=load('geo/spain-communities.geojson')
mapcodes={x['properties']['nuts2'] for x in geo['features']}
assert len(mapcodes)==19
svg=ET.parse(R/'docs/mapa-espana-v8.svg')
svgcodes={e.attrib['data-geo'] for e in svg.iter() if 'data-geo' in e.attrib}
assert svgcodes==mapcodes
assert all(e.attrib.get('role')=='button' and e.attrib.get('tabindex')=='0' for e in svg.iter() if 'data-geo' in e.attrib)
html=(R/'docs/observatorio-espana-v8.html').read_text()
js=(R/'docs/observatorio-espana-v8.js').read_text()
ids=set(re.findall(r'\bid="([a-z-]+)"',html))
requested=set(re.findall(r"\$\('([a-z-]+)'\)",js))
assert requested<=ids,requested-ids
assert "'/api/v1/npl/compare'" in js and "'/api/v1/npl/series'" in js and "'/api/v1/npl/ask'" in js
assert (R/'docs/observatorio-espana-v8.css').exists()
assert all(f in (R/'.htaccess').read_text() for f in ('database','docs','experiments'))
assert all((R/d/'.htaccess').exists() and 'Require all denied' in (R/d/'.htaccess').read_text() for d in ('database','docs','experiments','scripts'))
missing_dates=sum(not bool(o.get('retrieved_at')) for b in batches for o in b['observations'])
assert missing_dates==237
print('V8 QA PASS:',len(keys),'prepared observations (36 derived), 19 accessible map territories, 0 cross-batch duplicates; 237 retrieval dates pending verification.')
