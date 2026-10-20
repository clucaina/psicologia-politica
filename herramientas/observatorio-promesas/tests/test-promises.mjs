import {readFileSync} from "node:fs";
import {test} from "node:test";
import assert from "node:assert/strict";
import vm from "node:vm";
const html=readFileSync(new URL("../index.html",import.meta.url),"utf8");
const script=html.match(/<script>([\s\S]*?)<\/script>/)?.[1]??"";
test("JS parses and provenance controls are visible",()=>{
 assert.doesNotThrow(()=>new vm.Script(script));
 for(const s of ["Competencia y contexto","Plazo o momento","Contraste externo","Exportar proyecto JSON","Importar proyecto","Mapa de evidencias"])
  assert.ok(html.includes(s),s);
});
const a=script.indexOf("function safeURL("),b=script.indexOf("\nfunction save()",a);
assert.ok(a>0&&b>a);
const model=vm.runInNewContext(script.slice(a,b)+"; ({safeURL,hostname,criteria})",{URL});
test("Only public http(s) schemes are accepted",()=>{
 assert.equal(model.safeURL("javascript:alert(1)"),"");
 assert.equal(model.safeURL("file:///etc/passwd"),"");
 assert.equal(model.safeURL("https://www.congreso.es/es/datos-abiertos"),"https://www.congreso.es/es/datos-abiertos");
 assert.equal(model.hostname("https://www.congreso.es/test"),"congreso.es");
});
test("Completeness does not rate truth",()=>{
 const r={promise:"Medida futura",actor:"Grupo ficticio",deadline:"2027-01-01",published:"",level:"Municipal",context:"Competencia municipal",originalURL:"https://example.org/programa",sources:[]};
 const before=model.criteria(r);
 assert.equal(before.length,6);
 assert.equal(before.filter(([t,v])=>v).length,5);
 r.sources=[{url:"https://example.org/documento",note:"Una fuente con una explicación detallada de lo observado."}];
 assert.equal(model.criteria(r).filter(([t,v])=>v).length,6);
});
test("No network transmission of evidence or party preferences",()=>{
 assert.doesNotMatch(script,/fetch\(|navigator\.sendBeacon|XMLHttpRequest|new WebSocket/);
});
test("Security constraints and explicit demo labelling",()=>{
 assert.ok(script.includes("2,5 MB"));
 assert.ok(script.includes("Agrupación Ciudadana Ejemplo (ficticia)"));
 assert.ok(script.includes("Sin investigar"));
 assert.ok(script.includes('confirm("¿Borrar TODAS las fichas'));
 assert.ok(script.includes('replace(/^[=+\\-@\\t\\r]/'));
});
