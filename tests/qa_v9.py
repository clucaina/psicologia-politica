"""V9 data-lineage and prototype regression checks; no network, DB or publication."""
import json, xml.etree.ElementTree as ET
from pathlib import Path
from collections import Counter
R=Path(__file__).resolve().parent.parent
load=lambda x:json.loads((R/x).read_text(encoding='utf-8'))
old=[load('database/seeds/T19_CCAA_BASELINE.json'),load('database/T26_VULNERABILIDAD_SEGURIDAD_2025.json'),load('database/T27_SALARIOS_CCAA_2024.json'),load('database/T28_DERIVADOS_SALARIOS_2024.json')]
new=[load('database/T30_ABANDONO_EDUCATIVO_2022_2025.json'),load('database/T31_EMPLEO_PARO_25_64_2015_2025.json')]
assert [len(x['observations']) for x in new]==[80,108]
assert sum(len(x['observations']) for x in old+new)==515
keys=[]
for b in old+new:
 assert len(b['batch_id'])<=64
 assert b['source_endpoints'] and b['new_indicators']
 for o in b['observations']:
  assert o['batch_id']==b['batch_id']
  assert o['source_url'].startswith('https://')
  assert isinstance(o['value'],(int,float)) and o['value'] is not None
  keys.append(tuple(o[k] for k in ('indicator_id','period','geo_code','subgroup','source_id','dataset_code')))
assert len(keys)==len(set(keys)), 'cross-batch collision'
geo=load('geo/ccaa-code-map.json')
territories=set(geo['by_cod_ccaa'].values())
assert len(territories)==19
edu=new[0]['observations']; labor=new[1]['observations']
assert {o['geo_code'] for o in edu}==territories|{'ES'}
assert Counter(o['geo_code'] for o in edu)=={code:4 for code in territories|{'ES'}}
assert {o['period'] for o in edu}=={'2022','2023','2024','2025'}
assert all(o['unit']=='%' and 0<=o['value']<=100 for o in edu+labor)
assert all(o['human_source_verification'] is False and o['quality_flag'].startswith('PENDING_VISUAL_SOURCE_QA') for o in edu+labor)
assert all(o['retrieved_at']=='2026-10-07' for o in edu+labor)
assert all(o['source_extraction']=='indexed_official_pdf_table' for o in edu+labor)
by_edu={(o['geo_code'],o['period']):o['value'] for o in edu}
assert by_edu[('ES','2025')]==12.8
assert by_edu[('ES21','2025')]==3.6
assert by_edu[('ES62','2025')]==20.6
assert by_edu[('ES63','2025')]==11.65  # two decimals as printed in official source table
assert by_edu[('ES64','2024')]==26.0
assert by_edu[('ES61','2022')]==15.5
assert by_edu[('ES51','2025')]==13.5
assert sum(o['quality_flag'].endswith('_SMALL_SAMPLE') for o in edu)==5*4
assert all(o['methodology_flag']=='MEFPD_EPA_EARLY_LEAVING_18_24_ANNUAL' for o in edu)
assert len({o['geo_code'] for o in labor})==18
assert {o['geo_code'] for o in labor}==territories-{'ES63','ES64'}|{'ES'}
assert set(o['period'] for o in labor)=={'2015','2020','2025'}
assert Counter(o['indicator_id'] for o in labor)=={'EPA_TASA_EMPLEO_25_64_PCT':54,'EPA_TASA_PARO_25_64_PCT':54}
assert all('25' in o['population'] and '64' in o['population'] for o in labor)
by_labor={(o['indicator_id'],o['geo_code'],o['period']):o['value'] for o in labor}
assert by_labor[('EPA_TASA_EMPLEO_25_64_PCT','ES','2025')]==75.3
assert by_labor[('EPA_TASA_PARO_25_64_PCT','ES','2025')]==9.4
assert by_labor[('EPA_TASA_PARO_25_64_PCT','ES61','2015')]==29.6
assert by_labor[('EPA_TASA_EMPLEO_25_64_PCT','ES30','2025')]==81.0
assert by_labor[('EPA_TASA_EMPLEO_25_64_PCT','ES53','2020')]==69.0
assert by_labor[('EPA_TASA_PARO_25_64_PCT','ES21','2025')]==6.6
svg=ET.parse(R/'docs/mapa-espana-v8.svg').getroot()
assert svg.tag=='{http://www.w3.org/2000/svg}svg', 'SVG root must declare namespace: browser DOMParser requires SVGElement.style'
assert {e.attrib['data-geo'] for e in svg.iter() if 'data-geo' in e.attrib}==territories
js=(R/'docs/observatorio-espana-v8.js').read_text()
html=(R/'docs/observatorio-espana-v8.html').read_text()
assert 'id="territory"' in html and "$('territory').addEventListener('change'" in js
assert 'sourceLine.append(a);d.append(sourceLine)' in js, 'DOM append returns undefined; never chain it'
assert 'Escala cromática desactivada' in js and 'state.compat?.homogeneous!==false' in js
assert "'EPA_ABANDONO_TEMPRANO_18_24_PCT'" in (R/'_private/npl_analytics.php').read_text()
assert 'source_verification_confirmed' in (R/'_private/npl.php').read_text()
assert "source_quality_flag" in (R/'_private/npl_batch.php').read_text()
print('V9 DATA/STATIC QA PASS: 515 prepared rows total, 188 new (80 education + 108 employment), no key collisions, 19 map regions, SVG namespace, review gate and map fixes.')
