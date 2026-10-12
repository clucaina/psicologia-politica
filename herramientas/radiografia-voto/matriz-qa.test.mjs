import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import {fileURLToPath} from 'node:url';
import {dirname,join} from 'node:path';
const base=dirname(fileURLToPath(import.meta.url));
const archive=JSON.parse(readFileSync(join(base,'archivo-xv-votos.json'),'utf8'));
const votes=archive.records;
const groups=[...new Set(votes.flatMap(v=>Object.keys(v.grupos||{})))].filter(g=>g!=='Sin grupo');
const allowed=new Set(['Sí','No','Abstención','Empate','No consta','']);
const pair=(a,b,withAbst=false,rows=votes)=>{
 let n=0,match=0;
 for(const v of rows){const x=v.grupos?.[a]?.mayoria,y=v.grupos?.[b]?.mayoria;
  if(!['Sí','No',...(withAbst?['Abstención']:[])].includes(x)||!['Sí','No',...(withAbst?['Abstención']:[])].includes(y))continue;
  n++;if(x===y)match++;
 }
 return {n,match,score:n?100*match/n:null};
};
test('fuente y esquema documentados',()=>{
 assert.equal(archive.schema,'pp-congreso-xv-grupos/1');
 assert.match(archive.attribution,/Congreso Abierto/);
 assert.ok(votes.length>=2000);
});
test('sin ids duplicados ni fechas no estructuradas',()=>{
 assert.equal(new Set(votes.map(v=>v.id)).size,votes.length);
 for(const v of votes){assert.match(v.fecha,/^20\d{2}-\d{2}-\d{2}$/);assert.ok(v.grupos);}
});
test('los ausentes y empates no se convierten en abstención',()=>{
 for(const v of votes)for(const g of Object.values(v.grupos))assert.ok(allowed.has(g.mayoria),String(g.mayoria));
});
test('matriz simétrica, acotada y abstenciones explícitas',()=>{
 for(const a of groups)for(const b of groups){
  const r=pair(a,b),reverse=pair(b,a);
  assert.deepEqual(r,reverse);
  assert.ok(r.n<=votes.length);
  if(r.n)assert.ok(r.score>=0&&r.score<=100);
  assert.ok(pair(a,b,true).n>=r.n);
 }
});
test('filtros sin votos ajenos',()=>{
 const housing=votes.filter(v=>String(v.temas).split(/[,;|]/).map(x=>x.trim()).includes('vivienda'));
 assert.ok(housing.length>0);
 for(const a of groups)for(const b of groups)assert.ok(pair(a,b,false,housing).n<=housing.length);
});
test('interfaz sin perfilado político local ni transmisión',()=>{
 const html=readFileSync(join(base,'matriz.html'),'utf8');
 for(const term of ['localStorage.setItem','sessionStorage.setItem','navigator.sendBeacon','gtag(','dataLayer.push'])assert.ok(!html.includes(term),term);
 assert.match(html,/No se recopilan respuestas políticas/);
});
test('resultados de votaciones no se presentan como iniciativas únicas',()=>{
 const counts=new Map();
 for(const v of votes)counts.set(v.resultado,(counts.get(v.resultado)||0)+1);
 assert.equal([...counts.values()].reduce((a,b)=>a+b,0),votes.length);
 for(const label of ['Aprobada','Rechazada','Convalidado','Derogado'])assert.ok(counts.has(label));
 const html=readFileSync(join(base,'archivo.html'),'utf8');
 const js=readFileSync(join(base,'archivo.js'),'utf8');
 assert.match(html,/Los resultados cuentan votaciones, no leyes o propuestas únicas/);
 assert.match(js,/outcome-summary/);
 assert.match(js,/el\("resultado"\)/);
});
