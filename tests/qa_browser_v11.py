"""V11 offline keyboard/reduced-motion/forced-colors QA. APIs mocked; no Hostinger integration claim."""
from pathlib import Path
import json,shutil
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parent.parent
html=(R/'docs/observatorio-espana-v8.html').read_text()
css=(R/'docs/observatorio-espana-v8.css').read_text()
js=(R/'docs/observatorio-espana-v8.js').read_text().replace("new URL(path,location.origin)","new URL(path,'https://mock.local')")
svg=(R/'docs/mapa-espana-v8.svg').read_text()
point={'value':0,'unit':'%','source_id':'S','source_url':'https://example.org','period':'2025','methodology_flag':'EPA','reviewed_at':'2026-10-08','geography':'Andalucía','geo_code':'ES61'}
responses={'coverage':{'groups':['total'],'years':['2025'],'coverage':[{'group':'total','year':'2025','territories':1,'possible':20,'ambiguous_rows':0}]},'compare':{'points':{'ES61':point},'comparability':{'homogeneous':True,'complete':False}},'catalog':{'items':[{'id':'EPA_ABANDONO_TEMPRANO_18_24_PCT','name':'Abandono educativo'}]}}
mock="""window.history.replaceState=()=>{};
const DB=__DATA__, SVG=__SVG__;
window.fetch=async url=>{const u=String(url);if(u.endsWith('.svg'))return {ok:true,text:async()=>SVG};const d=u.includes('/coverage')?DB.coverage:u.includes('/compare')?DB.compare:DB.catalog;return {ok:true,json:async()=>d};};
""".replace('__DATA__',json.dumps(responses,ensure_ascii=False)).replace('__SVG__',json.dumps(svg))
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=shutil.which('chromium'),args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':320,'height':700},reduced_motion='reduce')
 page.set_content(html);page.add_style_tag(content=css);page.add_script_tag(content=mock);page.add_script_tag(content=js)
 page.evaluate("window.dispatchEvent(new Event('DOMContentLoaded'))")
 page.wait_for_function("document.querySelectorAll('#map [data-geo]').length===19")
 assert page.locator('#map [data-geo]').count()==19
 # Direct keyboard activation must work for the 19 real territories.
 target=page.locator('#map [data-geo="ES30"]');target.focus();target.press('Enter')
 assert 'Comunidad de Madrid' in page.locator('#detail').inner_text()
 transition=page.locator('.esp-shape').first.evaluate('(el)=>getComputedStyle(el).transitionDuration')
 assert transition in ('0s','0s, 0s'),transition
 assert page.evaluate('document.documentElement.scrollWidth-window.innerWidth')<=3
 # No changes to production; test forced-colors fallback locally if supported.
 page.emulate_media(forced_colors='active')
 fill=page.locator('.esp-shape').first.evaluate('(el)=>getComputedStyle(el).fill')
 assert fill and 'url(' not in fill,fill
 print('BROWSER V11 PASS: keyboard map selection, reduced motion, forced-colors fallback, 320px no overflow.')
 browser.close()
