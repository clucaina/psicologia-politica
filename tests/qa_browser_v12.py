"""V12 offline regression: latest question wins, source details visible, zero != missing."""
from pathlib import Path
import json, shutil
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parent.parent
html=(R/'docs/observatorio-espana-v8.html').read_text()
css=(R/'docs/observatorio-espana-v8.css').read_text()
js=(R/'docs/observatorio-espana-v8.js').read_text().replace('new URL(path,location.origin)',"new URL(path,'https://mock.local')")
svg=(R/'docs/mapa-espana-v8.svg').read_text()
mock='''window.history.replaceState=()=>{};
const SVG=__SVG__;
window.fetch=async (url)=>{
 const u=String(url);
 if(u.endsWith('.svg'))return {ok:true,text:async()=>SVG};
 if(u.includes('/ask?')){
   const old=u.includes('Andaluc');
   await new Promise(r=>setTimeout(r,old?230:15));
   const code=old?'ES61':'ES30';
   const p={value:old?99:0,unit:'%',source_id:'INE',source_url:'https://www.ine.es/',period:'2025',population:'Hogares',methodology_flag:'ECV',reviewed_at:'2026-10-08',limits:'Encuesta; no causalidad'};
   return {ok:true,json:async()=>({status:'answered_from_reviewed_data',points:{[code]:p},interpretation_guard:'No es causal'})};
 }
 if(u.includes('/coverage'))return {ok:true,json:async()=>({groups:['total'],coverage:[{group:'total',year:'2025',territories:1,possible:20,ambiguous_rows:0}]})};
 if(u.includes('/compare'))return {ok:true,json:async()=>({points:{},comparability:{homogeneous:true,complete:false}})};
 return {ok:true,json:async()=>({items:[{id:'ecv_arope_pct',name:'Tasa AROPE'}]})};
};'''.replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':320,'height':780})
 page.set_content(html);page.add_style_tag(content=css);page.add_script_tag(content=mock);page.add_script_tag(content=js)
 page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
 page.wait_for_function("document.querySelectorAll('#map [data-geo]').length===19")
 page.locator('#question').fill('¿Cuál es la tasa AROPE de Andalucía en 2025?')
 page.locator('#ask-form button').click()
 page.locator('#question').fill('¿Cuál es la tasa AROPE de Madrid en 2025?')
 page.locator('#ask-form button').click()
 page.wait_for_timeout(450)
 text=page.locator('#answer').inner_text()
 assert 'Comunidad de Madrid' in text and '0 %' in text,text
 assert 'Andalucía' not in text and '99 %' not in text,text
 assert 'Hogares' in text and 'ECV' in text and 'Encuesta; no causalidad' in text,text
 assert page.locator('#answer a[href="https://www.ine.es/"]').count()==1
 assert page.evaluate('document.documentElement.scrollWidth-window.innerWidth')<=3
 print('V12 BROWSER PASS: latest question wins, zero preserved, provenance and limits, 320px responsive.')
 browser.close()
