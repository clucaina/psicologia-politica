"""QA V71: inspect real cartography in Chromium and reject executable SVG mutations."""
from pathlib import Path
import json
from playwright.sync_api import sync_playwright

root=Path(__file__).resolve().parents[1]
try:
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
        page=browser.new_page(viewport={'width':390,'height':844})
        markup=(root/'_private/observatorio-espana.html').read_text()
        markup=markup.replace('<script src="/assets/observatorio/mapa-espana.js?v=c0ce65249e25" defer></script>','')
        page.set_content(markup,wait_until='domcontentloaded')
        page.add_script_tag(content=(root/'assets/observatorio/mapa-espana.js').read_text())
        page.wait_for_function("typeof verifyMapSVGDocument === 'function'",timeout=10000)
        svg=(root/'assets/observatorio/mapa-espana.svg').read_text()
        assert len(svg)<1_500_000
        checks=page.evaluate("""svg => {
          const parser=new DOMParser();
          const parse=s=>parser.parseFromString(s,'image/svg+xml');
          let pass=0; const expectReject=(s,label)=>{
            try{verifyMapSVGDocument(parse(s));throw new Error(label+': UNEXPECTED ACCEPT');}
            catch(e){if(e.message.includes('UNEXPECTED ACCEPT'))throw e;pass++;}
          };
          const doc=parse(svg);
          const verified=verifyMapSVGDocument(doc);
          const geos=[...verified.querySelectorAll('[data-geo]')].map(n=>n.getAttribute('data-geo'));
          if(geos.length!==19 || new Set(geos).size!==19)throw new Error('Missing geos');
          expectReject(svg.replace('<svg','<svg onload="alert(1)"'),'event handler');
          expectReject(svg.replace('<defs>','<script>alert(1)</script><defs>'),'script');
          expectReject(svg.replace('<defs>','<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">Hi</div></foreignObject><defs>'),'foreignObject');
          expectReject(svg.replace('<defs>','<image href="https://evil.invalid/image"/><defs>'),'foreign image');
          expectReject(svg.replace('data-geo="ES41"','data-geo="ES11"'),'duplicate territory');
          expectReject(svg.replace('data-geo="ES41"','data-geo="XX41"'),'unknown territory');
          expectReject(svg.replace('data-geo="ES41"','data-geo=""'),'empty code');
          expectReject(svg.replace('class="esp-shape"','class="no-shape"'),'shape missing');
          expectReject(svg.replace('<defs>','<defs><animate attributeName="x" from="0" to="100"/>'),'animate');
          expectReject(svg.replace('role="button"','role="link"'),'wrong accessibility role');
          return {geos:geos.length,malicious_rejected:pass};
        }""",svg)
        assert checks == {'geos':19,'malicious_rejected':10},checks
        print('V71 SVG TRUST BROWSER PASS:',checks)
        browser.close()
finally:
    pass
