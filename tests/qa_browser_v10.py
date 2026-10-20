"""V10 browser race and availability regression, with mocked API (not staging integration)."""
from pathlib import Path
import shutil
from playwright.sync_api import sync_playwright
import json
R=Path(__file__).resolve().parent.parent
html=(R/'docs/observatorio-espana-v8.html').read_text()
js=(R/'docs/observatorio-espana-v8.js').read_text().replace("new URL(path,location.origin)","new URL(path,'https://mock.local')")
svg=(R/'docs/mapa-espana-v8.svg').read_text()
mock=r'''
const SVG=__SVG__;
window.history.replaceState=()=>{};
window.fetch=async url=>{
 const u=String(url),p=new URL(u,'https://mock.local'),indicator=p.searchParams.get('indicator');
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 if(u.endsWith('.svg'))return {ok:true,text:async()=>SVG};
 let data={};
 if(p.pathname.endsWith('/coverage')){
  await wait(indicator==='I_A'?130:5);
  data=indicator==='I_A'?{groups:['adultos'],years:['2022'],coverage:[{group:'adultos',year:'2022',territories:1,ambiguous_rows:0}]}:
    {groups:['total'],years:['2025','2024'],coverage:[{group:'total',year:'2025',territories:1,ambiguous_rows:0},{group:'total',year:'2024',territories:1,ambiguous_rows:0}]};
 }else if(p.pathname.endsWith('/compare')){
  const period=p.searchParams.get('period');await wait(period==='2022'?90:5);
  const value=period==='2023'?null:{value:period==='2024'?0:25,unit:'%',source_id:'OFFICIAL',period,methodology_flag:'v1',source_url:'https://example.org/'};
  data={points:{ES61:value},comparability:{homogeneous:true},ambiguous:[]};
 }else if(p.pathname.endsWith('/series')){
  await wait(130);
  data={from:2024,to:2025,series:{ES61:{points:{2024:null,2025:{value:0,unit:'%'}},comparable:true}},cross_territory_comparable:true,incompatible_series:[]};
 }else if(p.pathname==='/api/v1/npl'){
  data={items:[{id:'I_A',name:'A'},{id:'I_B',name:'B'}]};
 }else if(p.pathname.endsWith('/ask'))data={status:'unsupported'};
 return {ok:true,json:async()=>data};
};
'''.replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 b=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 page=b.new_page(viewport={'width':375,'height':860});errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.set_content(html);page.add_style_tag(content=(R/'docs/observatorio-espana-v8.css').read_text());page.add_script_tag(content=mock);page.add_script_tag(content=js)
 page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
 page.wait_for_function("document.querySelectorAll('#indicator option').length===2")
 page.select_option('#indicator','I_B')
 page.wait_for_function("document.querySelector('#group').value==='total' && document.querySelector('#year').value==='2025'")
 page.wait_for_timeout(250) # old A coverage must not replace B after slow response
 assert page.locator('#group').input_value()=='total'
 assert page.locator('#year').input_value()=='2025'
 assert page.locator('#coverage').inner_text().startswith('1/20')
 page.fill('#year','2023');page.locator('#year').dispatch_event('change')
 page.wait_for_function("document.querySelector('#year').value==='2023' && document.querySelector('#loading').textContent.includes('territorios')")
 assert 'Sin dato' in page.locator('#detail').inner_text()
 page.fill('#year','2024');page.locator('#year').dispatch_event('change')
 page.wait_for_function("document.querySelector('#detail').textContent.includes('0 %')")
 page.locator('#timeline-btn').click();page.select_option('#territory','ES30')
 page.wait_for_timeout(200)
 assert page.locator('#timeline table').count()==0, 'Stale timeline rendered after territory change'
 page.select_option('#compare','ES61');page.select_option('#territory','ES61')
 assert page.locator('#compare').input_value()=='' and page.locator('#comparison').inner_text()=='', 'Selecting compared territory must clear duplicate comparison'
 page.select_option('#indicator','I_A')
 page.wait_for_function("document.querySelector('#group').value==='adultos' && document.querySelector('#year').value==='2022'")
 assert not errors,errors
 print('BROWSER V10 PASS: stale async responses ignored, published-year discovery, explicit empty year, 0 retained, timeline invalidated')
 b.close()
