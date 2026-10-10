import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../assets/observatorio/mapa-espana.js',import.meta.url),'utf8');
const ctx={Intl,Date,Number,String,URL,document:{getElementById(){return null}},window:{addEventListener(){}}};
const harness=`
 state.points={ES11:{value:null},ES12:{value:0,unit:'%',source_id:'INE'},
              ES13:{value:20,unit:'%'}};
 state.indicator='TEST';state.group='Total';state.year=2025;
 globalThis.__rows=csvRows();
 globalThis.__formula=csvCell('=HYPERLINK("https://evil.example","click")');
 globalThis.__negative=csvCell('-20');
 globalThis.__unicode=csvCell('\\uFEFF+2');
`;
vm.runInNewContext(source+'\n'+harness,ctx);
const rows=ctx.__rows,header=rows[0];
const valIndex=header.indexOf('valor'),statusIndex=header.indexOf('estado');
assert.ok(valIndex>=0&&statusIndex>=0);
const missing=rows.find(r=>r[1]==='ES11'),zero=rows.find(r=>r[1]==='ES12');
assert.equal(missing[valIndex],null,'unobserved value must not be zero');
assert.equal(missing[statusIndex],'sin_dato','missing value must be flagged');
assert.equal(zero[valIndex],0,'measured 0 remains 0');
assert.equal(zero[statusIndex],'revisado','measured 0 retains reviewed status');
assert.ok(ctx.__formula.startsWith('"\'='),'dangerous formula is escaped');
assert.ok(ctx.__unicode[0]==='"' && ctx.__unicode[1]==="'" && ctx.__unicode.endsWith('+2"'),
          'Unicode prefix formula is escaped before the hidden Unicode character');
assert.equal(ctx.__negative,'"-20"','valid negative measurement is not mangled');
assert.equal(rows.length,21,'19 NUTS2, Spain and CSV header');
console.log('PASS: V74 null != 0 in CSV, coverage, injection prevention and negative values');
