"""Offline Chromium smoke test with API mocks; no network or MySQL required.
Run locally: python tests/qa_browser_v9.py. Requires playwright + system chromium.
"""
import json
from pathlib import Path
import shutil
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parent.parent
html=(R/'docs/observatorio-espana-v8.html').read_text()
js=(R/'docs/observatorio-espana-v8.js').read_text().replace("new URL(path,location.origin)","new URL(path,'https://mock.local')")
svg=(R/'docs/mapa-espana-v8.svg').read_text()

def point(geo,value):
 return {'value':value,'unit':'%','source_id':'MEFPD_EPA_EDU','source_url':'https://www.educacionfpydeportes.gob.es/','period':'2025','methodology_flag':'EPA','reviewed_at':'2026-10-07','geography':geo,'geo_code':geo}
points={'ES61':point('ES61',0),'ES30':point('ES30',9.6),'ES21':point('ES21',3.6)}
responses={'npl_catalog':{'items':[{'id':'EPA_ABANDONO_TEMPRANO_18_24_PCT','name':'Abandono educativo temprano'}]},
 'npl_groups':{'groups':['total']},
 'coverage':{'groups':['total'],'years':['2025'],'coverage':[{'group':'total','year':'2025','territories':3,'possible':20,'ambiguous_rows':0}]},
 'compare':{'points':points,'comparability':{'homogeneous':True,'complete':False,'comparable':False},'ambiguous':[]},
 'series':{'from':2024,'to':2025,'series':{'ES61':{'points':{'2024':None,'2025':points['ES61']},'comparable':True}},'cross_territory_comparable':True,'incompatible_series':[]},
 'ask':{'status':'answered_from_reviewed_data','points':{'ES61':points['ES61']}}}
mock='''
window.history.replaceState=()=>{};
const DATA=__DATA__;const SVG=__SVG__;
window.fetch=async (url)=>{
 const u=String(url);let d;
 if(u.endsWith('.svg'))return {ok:true,text:async()=>SVG};
 if(u.includes('/npl/coverage'))d=DATA.coverage;
 else if(u.includes('/npl/compare'))d=DATA.compare;
 else if(u.includes('/npl/series'))d=DATA.series;
 else if(u.includes('/npl/ask'))d=DATA.ask;
 else if(u.includes('/npl?'))d=DATA.npl_groups;
 else if(u.includes('/npl'))d=DATA.npl_catalog;
 else throw Error('Unexpected '+u);
 return {ok:true,json:async()=>d};
};
'''.replace('__DATA__',json.dumps(responses,ensure_ascii=False)).replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 for width in (320,375,768,1280):
  page=browser.new_page(viewport={'width':width,'height':900});errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.set_content(html)
  page.add_style_tag(content=(R/'docs/observatorio-espana-v8.css').read_text())
  page.add_script_tag(content=mock)
  page.add_script_tag(content=js)
  page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
  page.wait_for_function("document.querySelectorAll('#map [data-geo]').length===19 && document.querySelectorAll('.rank-item').length===3")
  assert '3 territorios' in page.locator('#loading').inner_text()
  assert '3/20 ámbitos' in page.locator('#coverage').inner_text()
  assert page.locator('#detail').inner_text().find('0 %')>=0,'0 is real data'
  assert page.locator('#map [data-geo]').count()==19
  assert page.locator('#territory option').count()==19
  page.select_option('#territory','ES30')
  assert 'Comunidad de Madrid' in page.locator('#detail').inner_text()
  page.select_option('#compare','ES21')
  assert 'Diferencia descriptiva' in page.locator('#comparison').inner_text()
  page.locator('#timeline-btn').click()
  page.wait_for_function("document.querySelector('#timeline table')!==null")
  assert page.locator('#timeline tbody tr').count()==2
  page.fill('#question','¿Cuál es el abandono educativo temprano de Andalucía en 2025?')
  page.locator('#ask-form button').click()
  page.wait_for_function("document.querySelector('#answer').innerText.includes('Andalucía')")
  page.evaluate("state.points.ES30.source_id='INCOMPATIBLE';state.compat={homogeneous:false};renderMap();renderRanking();")
  assert 'Escala cromática desactivada' in page.locator('#range').inner_text()
  assert 'Ranking bloqueado' in page.locator('#ranking').inner_text()
  # No body horizontal overflow; map remains selectable via native select on mobile.
  overflow=page.evaluate('document.documentElement.scrollWidth - window.innerWidth')
  assert overflow<=3,(width,overflow)
  assert not errors,(width,errors)
  print(f'BROWSER V9 PASS {width}px: 19 map regions, 0/null, selection, comparison, timeline, ask, incompatibility, no overflow')
  page.close()
 browser.close()
