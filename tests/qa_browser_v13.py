"""V13 browser regression with mocked APIs; standalone PHP route tested separately."""
from pathlib import Path
import json,shutil
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parent.parent
html=(R/'_private/observatorio-espana.html').read_text()
css=(R/'assets/observatorio/mapa-espana.css').read_text()
js=(R/'assets/observatorio/mapa-espana.js').read_text().replace('new URL(path,location.origin)',"new URL(path,'https://mock.local')")
svg=(R/'assets/observatorio/mapa-espana.svg').read_text()
mock='''window.history.replaceState=()=>{};
const SVG=__SVG__;
window.fetch=async (url)=>{
 const u=String(url); const q=new URL(u,'https://mock.local').searchParams;
 if(u.includes('mapa-espana.svg'))return {ok:true,text:async()=>SVG};
 if(u.includes('/coverage'))return {ok:true,json:async()=>({groups:['total'],coverage:[{group:'total',year:'2025',territories:2,possible:20,ambiguous_rows:0},{group:'total',year:'2024',territories:2,possible:20,ambiguous_rows:0}]})};
 const point=(geo,year)=>{let value=geo==='ES61'?(year==='2025'?0:1):geo==='ES30'?(year==='2025'?18.3:17):null;
  return value===null?null:{value,unit:'%',geo_code:geo,period:year,source_id:'INE',source_url:'https://www.ine.es/',dataset_code:'ECV',population:'Población residente',methodology_flag:'ECV',measurement_type:'survey',denominator:'personas',definition_version:'v1',reviewed_at:'2026-10-08',limits:'Encuesta, no causalidad'};};
 if(u.includes('/compare')){const geos=q.get('geos').split(',');const year=q.get('period');return {ok:true,json:async()=>({points:Object.fromEntries(geos.map(g=>[g,point(g,year)])),comparability:{complete:false,homogeneous:true,comparable:false},ambiguous:[]})};}
 if(u.includes('/series')){const geos=q.get('geos').split(','),from=Number(q.get('from')),to=Number(q.get('to'));
  return {ok:true,json:async()=>({from,to,series:Object.fromEntries(geos.map(g=>[g,{points:Object.fromEntries(Array.from({length:to-from+1},(_,i)=>[String(from+i),point(g,String(from+i))])),comparable:true}])),incompatible_series:[],cross_territory_comparable:true})};}
 if(u.includes('/ask'))return {ok:true,json:async()=>({status:'answered_from_reviewed_data',points:{ES61:point('ES61','2025')},interpretation_guard:'No causalidad'})};
 return {ok:true,json:async()=>({items:[{id:'ecv_arope_pct',name:'Tasa AROPE'}]})};
};'''.replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 for width in (320,375,768,1280):
  page=browser.new_page(viewport={'width':width,'height':850})
  errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.set_content(html)
  page.add_style_tag(content=css)
  page.add_script_tag(content=mock)
  page.add_script_tag(content=js)
  page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
  page.wait_for_function("document.querySelectorAll('#map [data-geo]').length===19")
  page.wait_for_function("document.querySelector('#loading').textContent.includes('territorios con datos')")
  page.locator('#zoom-in').click();assert '130 %' in page.locator('#zoom-level').inner_text();page.locator('#zoom-reset').click();assert '100 %' in page.locator('#zoom-level').inner_text()
  assert '0 %' in page.locator('#detail').inner_text(),'zero lost on Andalucía'
  assert 'Sin dato' in page.locator('#map [data-geo="ES51"]').get_attribute('aria-label'),'null mapped to zero'
  page.locator('#territory').select_option('ES30')
  assert 'Comunidad de Madrid' in page.locator('#detail').inner_text()
  page.locator('#search').fill('Ceuta');page.locator('#search-btn').click()
  assert 'Ceuta' in page.locator('#detail').inner_text()
  assert 'Sin dato' in page.locator('#detail').inner_text()
  page.locator('#territory').select_option('ES61')
  page.locator('#compare').select_option('ES30')
  assert 'Diferencia descriptiva' in page.locator('#comparison').inner_text()
  page.locator('#from').fill('2024');page.locator('#to').fill('2025');page.locator('#timeline-btn').click()
  page.wait_for_function("document.querySelector('#timeline table')!==null")
  page.locator('#question').fill('¿Cuál es la tasa AROPE de Andalucía en 2025?');page.locator('#ask-form button').click()
  page.wait_for_function("document.querySelector('#answer').textContent.includes('0 %')")
  assert page.locator('#answer a[href="https://www.ine.es/"]').count()==1
  assert page.evaluate('document.documentElement.scrollWidth-window.innerWidth')<=3,f'horizontal overflow at {width}'
  assert not errors,errors
  if width==375:page.screenshot(path='/tmp/pp_map_mobile_qa.png',full_page=True)
  print('V13 BROWSER PASS',width,'px, 19 CCAA, null/zero, search/compare/timeline/ask, no JS errors')
  page.close()
 browser.close()
