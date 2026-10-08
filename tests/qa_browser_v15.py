"""V15 Chromium: identical values must share a competition rank; no decorative ranking."""
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
 const u=String(url);
 if(u.includes('mapa-espana.svg'))return {ok:true,text:async()=>SVG};
 if(u.includes('/coverage'))return {ok:true,json:async()=>({groups:['total'],coverage:[{group:'total',year:'2025',territories:3,possible:20}]})};
 if(u.includes('/compare')){
  const q=new URL(u,'https://mock.local').searchParams;
  const values={ES61:10,ES30:10,ES51:20};
  const points=Object.fromEntries(q.get('geos').split(',').map(g=>[g,values[g]===undefined?null:{value:values[g],unit:'%',source_id:'INE',dataset_code:'ECV',population:'Personas',measurement_type:'survey',definition_version:'1',methodology_flag:'official',source_url:'https://www.ine.es/',period:'2025',reviewed_at:'2026-10-08'}]));
  return {ok:true,json:async()=>({points,comparability:{homogeneous:true},ambiguous:[]})};
 }
 return {ok:true,json:async()=>({items:[{id:'ecv_arope_pct',name:'AROPE'}]})};
};'''.replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 for width in (320,375,768,1280):
  page=browser.new_page(viewport={'width':width,'height':900});errors=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.set_content(html);page.add_style_tag(content=css);page.add_script_tag(content=mock);page.add_script_tag(content=js)
  page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
  page.wait_for_function("document.querySelectorAll('#ranking .rank-item').length===3")
  assert [x.inner_text() for x in page.locator('#ranking .rank-item > span').all()]==['1','2','2']
  page.locator('#order').select_option('asc')
  assert [x.inner_text() for x in page.locator('#ranking .rank-item > span').all()]==['1','1','3']
  page.evaluate("state.points.ES61.population=null;renderMap();renderRanking();")
  assert 'Escala cromática desactivada' in page.locator('#range').inner_text()
  assert 'Ranking bloqueado' in page.locator('#ranking').inner_text()
  assert page.evaluate('document.documentElement.scrollWidth-window.innerWidth')<=3
  assert not errors,errors
  print('V15 BROWSER PASS',width,'px; tied ranks 1,2,2 and 1,1,3')
  page.close()
 browser.close()
