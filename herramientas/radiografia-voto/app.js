(() => {
"use strict";
const D=window.VOTO_DATA;
if(!D||!Array.isArray(D.records)){document.querySelector("main").textContent="No se han cargado los datos.";return;}
const votes=D.records, groups=D.groups, answer=Object.create(null), weight=Object.create(null), topics=[...new Set(votes.map(v=>v.theme))];
let cursor=0;
const el=id=>document.getElementById(id);
const esc=x=>String(x==null?"":x).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const label={si:"Sí",no:"No",abst:"Abstención"};
const nAnswered=()=>Object.values(answer).filter(x=>x==="si"||x==="no").length;
function setPage(page){
document.querySelectorAll(".section").forEach(x=>x.classList.toggle("active",x.id==="tab-"+page));
document.querySelectorAll("[data-nav]").forEach(x=>x.setAttribute("aria-current",String(x.dataset.nav===page)));
if(page==="archivo") renderArchive();
window.scrollTo(0,0);
}
document.querySelectorAll("[data-nav]").forEach(x=>x.addEventListener("click",()=>setPage(x.dataset.nav)));
el("go-test").addEventListener("click",()=>setPage("test"));
el("metric-votes").textContent=votes.length;el("metric-groups").textContent=groups.length;
function renderQuestion(){
const v=votes[cursor],chosen=answer[v.id]||"",w=weight[v.id]||2;
el("step-count").textContent=(cursor+1)+" / "+votes.length;
el("progress-bar").style.width=(Object.keys(answer).length/votes.length*100)+"%";
el("answered-count").textContent=nAnswered()+" decisiones respondidas. "+Object.keys(answer).length+" revisadas.";
let h='<div class="meta"><span class="pill">'+esc(v.theme)+'</span><span class="pill">'+esc(v.date)+'</span><span class="pill">'+esc(v.kind)+'</span></div>';
h+='<h3>'+esc(v.q)+'</h3><p class="muted">'+esc(v.title)+'</p>';
h+='<div class="actionRow" role="group" aria-label="Tu posición en esta votación">';
[["si","Votaría sí"],["no","Votaría no"],["skip","No tengo criterio"]].forEach(pair=>{
const k=pair[0];h+='<button type="button" data-answer="'+k+'" aria-pressed="'+(chosen===k)+'" class="answer '+(chosen===k?"selected ":"")+(k==="skip"?"skip":"")+'">'+pair[1]+'</button>';
});h+='</div><div class="importance"><label for="weight">Importancia para mí</label><select id="weight">';
[[1,"Menor"],[2,"Media"],[3,"Mayor"]].forEach(pair=>h+='<option value="'+pair[0]+'" '+(w===pair[0]?"selected":"")+'>'+pair[1]+'</option>');
h+='</select></div><p class="notice" style="font-size:13px">'+esc(v.clarity)+'</p>';
h+='<div class="sourcebox"><strong>Documento verificable</strong><p>'+esc(v.doc)+' · '+esc(v.result)+'</p><a href="'+esc(v.url)+'" target="_blank" rel="noopener noreferrer">Resultado y documentos oficiales ↗</a></div>';
el("question-root").innerHTML=h;
el("question-root").querySelectorAll("[data-answer]").forEach(b=>b.addEventListener("click",()=>{answer[v.id]=b.dataset.answer;if(!weight[v.id])weight[v.id]=2;renderQuestion();renderResult();}));
el("weight").addEventListener("change",e=>{weight[v.id]=Number(e.target.value);renderResult();});
el("previous").disabled=cursor===0;
el("next").textContent=cursor===votes.length-1?"Ver las coincidencias ↓":"Siguiente →";
}
function summarize(group){
const sub=[];
topics.forEach(topic=>{
let possible=0,matched=0,count=0,abst=0;
votes.filter(v=>v.theme===topic).forEach(v=>{
if(answer[v.id]!=="si"&&answer[v.id]!=="no")return;
const position=v.groups[group];if(position==="abst"){abst++;return;}
const w=weight[v.id]||2;possible+=w;count++;
if(position===answer[v.id])matched+=w;
});
if(possible>0)sub.push({topic,score:100*matched/possible,count,abst});
});
return {group,topics:sub.length,count:sub.reduce((s,x)=>s+x.count,0),score:sub.length?sub.reduce((s,x)=>s+x.score,0)/sub.length:null,detail:sub};
}
function renderResult(){
const out=el("result-root");
if(!nAnswered()){out.innerHTML='<div class="empty">Contesta algunas decisiones para comparar sus votos con los de los grupos. La herramienta no te recomendará partidos.</div>';return;}
const res=groups.map(summarize).sort((a,b)=>{let x=a.topics>=2?a.score:-1,y=b.topics>=2?b.score:-1;return y-x||a.group.localeCompare(b.group,"es");});
const usable=res.some(x=>x.topics>=2);
let html='<div class="notice summary"><strong>'+(usable?"Comparación exploratoria, no recomendación electoral":"Cobertura insuficiente para comparar materias diferentes")+'</strong>';
html+=(usable?"Los porcentajes solo reflejan esta selección documental.":"Responde a votaciones de al menos dos temas. Nunca mostramos como conclusión general un solo asunto.")+'</div><div class="results">';
res.forEach(r=>{
let enough=r.topics>=2,pct=enough?Math.round(r.score):0;
html+='<article class="result"><div class="resulthead"><div><h3>'+esc(r.group)+'</h3><p class="muted" style="font-size:12px">Grupo parlamentario</p></div><div class="score">'+(enough?pct+" %":"—")+'<small>'+(enough?"muestra":"insuficiente")+'</small></div></div>';
html+='<div class="track" aria-hidden="true"><span style="width:'+pct+'%"></span></div>';
html+='<p class="muted" style="font-size:12px">'+r.count+' votos comparables · '+r.topics+' de '+topics.length+' temas</p><div class="breakdown">';
r.detail.forEach(t=>html+='<div>'+esc(t.topic)+' <b>'+Math.round(t.score)+' %</b> · '+t.count+' caso(s)'+(t.abst?" · "+t.abst+" abstención(es)":"")+'</div>');
html+='</div></article>';
});
out.innerHTML=html+'</div>';
}
function renderArchive(){
const ftheme=el("tema").value, fgroup=el("grupo").value, fvote=el("sentido").value;
const rows=votes.filter(v=>(!ftheme||v.theme===ftheme)&&(!fgroup||!fvote||v.groups[fgroup]===fvote));
let html="";
rows.forEach(v=>{
html+='<article class="vote"><div class="meta"><span class="pill">'+esc(v.theme)+'</span><span class="pill">'+esc(v.date)+'</span></div><h3>'+esc(v.title)+'</h3><p>'+esc(v.q)+'</p><p class="muted">'+esc(v.clarity)+'</p><div class="partygrid">';
groups.filter(g=>!fgroup||g===fgroup).forEach(g=>html+='<div class="gvote '+v.groups[g]+'"><b>'+esc(g)+'</b>'+label[v.groups[g]]+'</div>');
html+='</div><details><summary>Resultado oficial y fuente</summary><p>'+esc(v.result)+' · Sí '+v.totals.si+', no '+v.totals.no+', abstención '+v.totals.abst+'.</p><p>'+esc(v.doc)+' · <a class="source" href="'+esc(v.url)+'" rel="noopener noreferrer" target="_blank">Documentos y detalle ↗</a></p></details></article>';
});
el("archive-root").innerHTML=html||'<div class="empty">Ninguna votación coincide con estos filtros.</div>';
}
el("tema").innerHTML='<option value="">Todos</option>'+topics.map(t=>'<option value="'+esc(t)+'">'+esc(t)+'</option>').join("");
el("grupo").innerHTML='<option value="">Todos</option>'+groups.map(g=>'<option value="'+esc(g)+'">'+esc(g)+'</option>').join("");
["tema","grupo","sentido"].forEach(id=>el(id).addEventListener("change",renderArchive));
el("previous").addEventListener("click",()=>{cursor=Math.max(0,cursor-1);renderQuestion();});
el("next").addEventListener("click",()=>{if(cursor<votes.length-1){cursor++;renderQuestion();}else{renderResult();el("resultado").scrollIntoView({behavior:"smooth"});}});
el("clear").addEventListener("click",()=>{if(Object.keys(answer).length&& !confirm("¿Borrar las respuestas de esta pestaña?"))return;Object.keys(answer).forEach(k=>delete answer[k]);Object.keys(weight).forEach(k=>delete weight[k]);cursor=0;renderQuestion();renderResult();});
function save(name,content,type){
const url=URL.createObjectURL(new Blob([content],{type})),a=document.createElement("a");
a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
}
el("export").addEventListener("click",()=>{
const result={name:"Radiografía del voto",snapshot:D.snapshot,note:D.scope,answers:votes.filter(v=>answer[v.id]).map(v=>({votacion:v.id,tema:v.theme,respuesta:answer[v.id],importancia:weight[v.id]||2,fuente:v.url})),comparacion:groups.map(summarize)};
save("mis-decisiones-parlamentarias.json",JSON.stringify(result,null,2),"application/json");
});
el("exportcsv").addEventListener("click",()=>{
const q=v=>'"'+String(v).replace(/"/g,'""')+'"';
const lines=[["fecha","expediente","tema","asunto","grupo","voto_mayoritario","fuente"].map(q).join(";")];
votes.forEach(v=>groups.forEach(g=>lines.push([v.date,v.id,v.theme,v.title,g,v.groups[g],v.url].map(q).join(";"))));
save("votaciones-grupos-fuentes.csv","\ufeff"+lines.join("\r\n"),"text/csv;charset=utf-8");
});
renderQuestion();renderArchive();renderResult();
})();