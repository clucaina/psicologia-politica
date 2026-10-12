(() => {
"use strict";
const el=id=>document.getElementById(id);
const escape=x=>String(x??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const normalize=x=>String(x||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
const state={records:[],filtered:[],page:0,size:16,groups:[]};
function matches(v){
const query=normalize(el("q").value),theme=el("tema").value,group=el("grupo").value,vote=el("voto").value;
if(query&&!normalize([v.titulo,v.id,v.tipo,v.temas,v.url_expediente].join(" ")).includes(query))return false;
if(theme&&!String(v.temas).split(/[,;|]/).map(t=>t.trim()).includes(theme))return false;
if(group){const info=v.grupos[group];if(!info)return false;if(vote&&info.mayoria!==vote)return false;}
else if(vote&&!Object.values(v.grupos).some(x=>x.mayoria===vote))return false;
return true;
}
function fit(){
state.filtered=state.records.filter(matches);state.page=0;render();
}
function exportCSV(rows){
const quoted=s=>'"'+String(s??"").replace(/"/g,'""')+'"';
const lines=[["id","fecha","tipo","titulo","temas","resultado","grupo","si","no","abstencion","no_vota","voto_mayoritario","url_votacion","url_expediente"].map(quoted).join(";")];
rows.forEach(v=>Object.keys(v.grupos).forEach(group=>{
const g=v.grupos[group];lines.push([v.id,v.fecha,v.tipo,v.titulo,v.temas,v.resultado,group,g.si,g.no,g.abstencion,g.no_vota,g.mayoria,v.url_votacion,v.url_expediente].map(quoted).join(";"));
}));
const url=URL.createObjectURL(new Blob(["\ufeff"+lines.join("\r\n")],{type:"text/csv;charset=utf-8"})),a=document.createElement("a");a.href=url;a.download="votaciones-filtradas-xv.csv";document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
}
function render(){
const total=state.filtered.length,start=state.page*state.size;
el("sources").textContent=total.toLocaleString("es-ES");
el("status").textContent=total.toLocaleString("es-ES")+" votaciones corresponden a los filtros. No se infiere intención de voto.";
const page=state.filtered.slice(start,start+state.size);let html="";
for(const v of page){
html+='<article class="result-vote"><div class="meta"><span class="pill">'+escape(v.fecha)+'</span><span class="pill">'+escape(v.id)+'</span><span class="pill">'+escape(v.resultado)+'</span></div>';
html+='<h3>'+escape(v.titulo)+'</h3><p class="dim">'+escape(v.tipo)+' · '+escape(v.temas||"Sin tema inferible")+'</p>';
html+='<p class="dim">Total oficial: '+v.si+' sí · '+v.no+' no · '+v.abstencion+' abstenciones · '+v.no_vota+' sin voto</p>';
html+='<details><summary>Ver el sentido mayoritario por grupo y los recuentos</summary><div class="smallgrid">';
state.groups.forEach(g=>{
if(!v.grupos[g])return;const x=v.grupos[g],cls=x.mayoria==="Sí"?"sí":x.mayoria==="No"?"no":x.mayoria==="Abstención"?"abstención":x.mayoria==="Empate"?"empate":"none";
html+='<div class="cell '+cls+'"><b>'+escape(g)+'</b>'+escape(x.mayoria||'No consta')+'<div class="dim" style="font-size:10px">'+x.si+' / '+x.no+' / '+x.abstencion+'</div></div>';
});
html+='</div><p class="dim">Recuento de sí, no y abstención; el Grupo Mixto puede incluir partidos diferentes.</p></details>';
html+='<p class="sourcebox">'+(v.url_votacion?'<a href="'+escape(v.url_votacion)+'" rel="noopener noreferrer" target="_blank">Votación oficial (JSON) ↗</a>':'Voto nominal no disponible')+(v.url_expediente?' · <a href="'+escape(v.url_expediente)+'" rel="noopener noreferrer" target="_blank">Expediente ↗</a>':'')+'</p></article>';
}
el("rows").innerHTML=html||'<div class="empty">No hay votaciones que respondan a esta consulta.</div>';
const pages=Math.max(1,Math.ceil(total/state.size));el("pageinfo").textContent="Página "+(state.page+1)+" de "+pages;el("prev").disabled=state.page===0;el("next").disabled=state.page>=pages-1;
}
function timeline(){
const years={};for(const v of state.records){const year=v.fecha.slice(0,4);years[year]=(years[year]||0)+1;}
const max=Math.max(1,...Object.values(years)),keys=Object.keys(years).sort();
el("timeline").innerHTML=keys.map(y=>'<div class="graph-col"><div class="graph-bg"><span style="height:'+(years[y]/max*100)+'%"></span></div><div><b>'+y+'</b><div>'+years[y].toLocaleString("es-ES")+'</div></div></div>').join("");
}
el("q").addEventListener("input",fit);
["tema","grupo","voto"].forEach(id=>el(id).addEventListener("change",fit));
el("prev").addEventListener("click",()=>{state.page=Math.max(0,state.page-1);render();});
el("next").addEventListener("click",()=>{state.page++;render();});
el("csv").addEventListener("click",()=>exportCSV(state.filtered));
fetch("./archivo-xv-votos.json",{cache:"no-store"}).then(r=>{if(!r.ok)throw Error("HTTP "+r.status);return r.json()}).then(data=>{
if(data.schema!=="pp-congreso-xv-grupos/1"||!Array.isArray(data.records))throw Error("El archivo no cumple el contrato.");
state.records=data.records;state.groups=[...new Set(data.records.flatMap(v=>Object.keys(v.grupos)))].sort();
el("total").textContent=state.records.length.toLocaleString("es-ES");
el("group-total").textContent=state.records.reduce((s,v)=>s+Object.keys(v.grupos).length,0).toLocaleString("es-ES");
const categories=[...new Set(data.records.flatMap(v=>String(v.temas).split(/[,;|]/).map(t=>t.trim()).filter(Boolean)))].sort();
el("tema").innerHTML='<option value="">Todas</option>'+categories.map(t=>'<option value="'+escape(t)+'">'+escape(t)+'</option>').join("");
el("grupo").innerHTML='<option value="">Todos</option>'+state.groups.map(t=>'<option value="'+escape(t)+'">'+escape(t)+'</option>').join("");
timeline();fit();
}).catch(e=>{el("status").textContent="El archivo completo no se ha podido cargar: "+e.message+". Para utilizarlo abre esta carpeta desde un servidor web HTTPS o local.";el("rows").innerHTML='<p class="empty">Conservamos la muestra documentada de ocho votaciones en el comparador anterior.</p>';el("prev").disabled=true;el("next").disabled=true;el("csv").disabled=true;});
})();