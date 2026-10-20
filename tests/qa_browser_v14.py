"""V14 browser regression: national-vs-regional data, reviewed-only CSV export and injection safety."""
from pathlib import Path
import csv, io, json, shutil
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parent.parent
html=(R/'_private/observatorio-espana.html').read_text()
css=(R/'assets/observatorio/mapa-espana.css').read_text()
js=(R/'assets/observatorio/mapa-espana.js').read_text().replace('new URL(path,location.origin)',"new URL(path,'https://mock.local')")
svg=(R/'assets/observatorio/mapa-espana.svg').read_text()
mock='''window.history.replaceState=()=>{};
const SVG=__SVG__;
window.fetch=async (url)=>{
 const u=String(url),q=new URL(u,'https://mock.local').searchParams;
 if(u.includes('mapa-espana.svg'))return {ok:true,text:async()=>SVG};
 if(u.includes('/coverage'))return {ok:true,json:async()=>({groups:['total'],coverage:[{group:'total',year:'2025',territories:3,possible:20}]})};
 const point=(geo)=>{const value=geo==='ES'?25.7:geo==='ES61'?0:geo==='ES30'?18.3:null;
  return value===null?null:{value,unit:'%',geo_code:geo,period:'2025',source_id:'INE',source_url:'https://www.ine.es/',dataset_code:'ECV',population:'Población residente',methodology_flag:'ECV',measurement_type:'survey',denominator:'personas',definition_version:'v1',reviewed_at:'2026-10-08',limits:geo==='ES30'?'=HYPERLINK("https://unsafe.invalid")':'Encuesta'};};
 if(u.includes('/compare')){const geos=q.get('geos').split(',');return {ok:true,json:async()=>({points:Object.fromEntries(geos.map(g=>[g,point(g)])),comparability:{complete:false,homogeneous:true},ambiguous:[]})};}
 return {ok:true,json:async()=>({items:[{id:'ecv_arope_pct',name:'Tasa AROPE'}]})};
};'''.replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 for width in (320,375,768,1280):
  page=browser.new_page(viewport={'width':width,'height':850},accept_downloads=True)
  errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.set_content(html);page.add_style_tag(content=css);page.add_script_tag(content=mock);page.add_script_tag(content=js)
  page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
  page.wait_for_function("document.querySelector('#loading').textContent.includes('2/19')")
  assert page.locator('#map [data-geo]').count()==19
  assert '18,3' in page.locator('#range').inner_text() and '25,7' not in page.locator('#range').inner_text(), 'National mean contaminated choropleth scale'
  assert page.locator('#ranking .rank-item').count()==2,'National observation incorrectly ranked among CCAA'
  page.locator('#territory').select_option('ES')
  assert '25,7 %' in page.locator('#detail').inner_text(),'National figure unavailable in territorial card'
  page.locator('#compare').select_option('ES61')
  assert 'Diferencia descriptiva' in page.locator('#comparison').inner_text(),'National vs CCAA comparison missing'
  assert 'no son muestras independientes' in page.locator('#comparison').inner_text()
  with page.expect_download() as dl:page.locator('#export-csv').click()
  download=dl.value
  assert download.suggested_filename=='npl-ecv_arope_pct-2025.csv'
  raw=Path(download.path()).read_text(encoding='utf-8-sig')
  rows=list(csv.DictReader(io.StringIO(raw),delimiter=';'))
  assert len(rows)==20 and rows[0]['codigo']=='ES'
  by={r['codigo']:r for r in rows}
  assert by['ES']['valor']=='25.7' and by['ES61']['valor']=='0'
  assert by['ES51']['valor']=='' and by['ES51']['estado']=='sin_dato'
  assert by['ES30']['limites'].startswith("'=HYPERLINK"),'Spreadsheet injection not neutralized'
  assert page.evaluate("csvCell(-12.5)")=='"-12.5"','Negative derived values should remain numeric'
  assert page.evaluate('document.documentElement.scrollWidth-window.innerWidth')<=3
  assert not errors,errors
  print('V14 BROWSER PASS',width,'px; national data, 19 CCAA, CSV, no formula injection')
  page.close()
 browser.close()
