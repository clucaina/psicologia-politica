/* Spain Observatory V68 preproduction: published-only API; no local fallback values or fabricated estimates. */
'use strict';
const GEOS=['ES11','ES12','ES13','ES21','ES22','ES23','ES24','ES30','ES41','ES42','ES43','ES51','ES52','ES53','ES61','ES62','ES70','ES63','ES64'];
const ALL_GEOS=['ES',...GEOS];
const SIGNATURE_FIELDS=Object.freeze(['unit','source_id','source_url','dataset_code','methodology_flag','measurement_type','percentage_semantics','population','denominator','definition_version','data_kind','frequency','measurement_window_type','measurement_period_start','measurement_period_end','reference_date']);
const signatureIndex=key=>SIGNATURE_FIELDS.indexOf(key);
const todayMadrid=()=>new Date().toLocaleDateString('sv-SE',{timeZone:'Europe/Madrid'});
const NAMES={ES:'España (dato nacional)',ES11:'Galicia',ES12:'Principado de Asturias',ES13:'Cantabria',ES21:'País Vasco',ES22:'Comunidad Foral de Navarra',ES23:'La Rioja',ES24:'Aragón',ES30:'Comunidad de Madrid',ES41:'Castilla y León',ES42:'Castilla-La Mancha',ES43:'Extremadura',ES51:'Cataluña',ES52:'Comunitat Valenciana',ES53:'Illes Balears',ES61:'Andalucía',ES62:'Región de Murcia',ES70:'Canarias',ES63:'Ceuta',ES64:'Melilla'};
const $=id=>document.getElementById(id);
const state={indicator:'',group:'',year:2025,selected:'ES61',compare:'',points:{},compat:null,requestId:0,groupRequestId:0,timelineRequestId:0,askRequestId:0,coverage:[],zoom:1,loadedKey:"",quarterSelection:null};
function el(tag,attrs={},text=''){const node=document.createElement(tag);for(const [k,v] of Object.entries(attrs)){if(k==='class')node.className=v;else if(k==='href')node.setAttribute('href',v);else node.setAttribute(k,v);}if(text!==undefined&&text!==null)node.textContent=String(text);return node;}
function clear(id){$(id).replaceChildren();return $(id);}
function note(id,message,kind='muted'){clear(id).append(el('p',{class:kind},message));}
function fmt(n){return new Intl.NumberFormat('es-ES',{maximumFractionDigits:2}).format(n);}
/** Null, empty strings, booleans and non-finite values are NEVER zero.
 * API values should be JSON numbers; a strict numeric string is tolerated for
 * older PHP/PDO projections, without accepting JS Number('') == 0. */
function measuredValue(p){
 if(!p||p.value===null||p.value===undefined||typeof p.value==='boolean')return null;
 const raw=p.value;
 if(typeof raw!=='number'&&!(typeof raw==='string'&&/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:e[+-]?\d+)?$/i.test(raw.trim())))return null;
 const n=Number(raw);return Number.isFinite(n)?n:null;
}
function valueLabel(p){const v=measuredValue(p);return v!==null?`${fmt(v)} ${p.unit??''}`:'Sin dato publicado y revisado';}
/** Catalog totals are not measurements. Missing metadata must never display as zero. */
/** Rank ties are exact measured equalities. A fixed 1e-12 tolerance can
 * merge distinct low-magnitude rates into the same rank and contradict the
 * server's Spearman ranking. Display rounding is not measurement equality. */
function rankPosition(previous,current,index,previousRank){
 return previous===null||current!==previous?index+1:previousRank;
}
function reviewedCount(value){
 if(typeof value==='number')return Number.isSafeInteger(value)&&value>=0?value:null;
 if(typeof value==='string'&&/^(0|[1-9]\d{0,14})$/.test(value)){
  const n=Number(value);return Number.isSafeInteger(n)?n:null;
 }
 return null;
}
function catalogSummary(data){
 if(!Array.isArray(data?.items))throw new Error('Catálogo público inválido: falta la lista de indicadores.');
 const count=reviewedCount(data.stats?.observations),archived=reviewedCount(data.archive?.observations);
 const publicLabel=count===null?'recuento de observaciones no comunicado por la API':
  `${count.toLocaleString('es-ES')} observaciones revisadas y publicadas`;
 const archiveLabel=count!==null&&archived!==null&&archived>count?
  ` El archivo histórico contiene ${archived.toLocaleString('es-ES')} registros adicionales o en distinto estado editorial; no son automáticamente datos publicables.`:'';
 return `Cobertura pública comprobable: ${data.items.length} indicadores y ${publicLabel}.`+archiveLabel+
  ' Un indicador sin observaciones revisadas no aparece en este selector.';
}

function safeSourceURL(value){
 if(typeof value!=='string')return null;
 // URL() normalizes embedded tabs/newlines and whitespace. Refuse those inputs
 // rather than turning untrusted metadata into a clickable, altered address.
 if(value!==value.trim()||/[\u0000-\u001f\u007f]/.test(value))return null;
 try{const u=new URL(value);return u.protocol==='https:'&&u.hostname&&!u.username&&!u.password?u.href:null;}catch{return null;}
}
function denominatorRequired(p){
 const unit=String(p?.unit??'').trim().toLowerCase(),type=String(p?.measurement_type??'').trim().toLowerCase();
 return /[%‰]|\b(?:por|per)\s+(?:cada\s+)?(?:[0-9][0-9.,]*|mil|mill[oó]n(?:es)?)\b|\/\s*[0-9][0-9.,]*/i.test(unit)||
  ['rate','ratio','prevalence','percentage'].some(word=>type.includes(word));
}
function sourceFamily(url,period){
 try{
  const u=new URL(url);if(u.protocol!=='https:'||u.username||u.password)return url;
  const year=String(period??'').slice(0,4);
  const normal=s=>/^(19|20)\d{2}$/.test(year)?s.replace(new RegExp(`(?<![0-9])${year}(?![0-9])`,'g'),'[year]'):s;
  return u.host.toLowerCase()+normal(u.pathname)+normal(u.search);
 }catch{return url;}
}
function measurementReady(p){
 if(!['unit','source_id','source_url','population','measurement_type','period','reviewed_at'].every(k=>typeof p?.[k]==='string'&&p[k].trim()!==''))return false;
 if(denominatorRequired(p)&&!(typeof p?.denominator==='string'&&p.denominator.trim()))return false;
 const frequency=String(p?.frequency??'').trim().toLowerCase(),period=String(p?.period??'');
 if(!['annual','yearly','quarterly','monthly'].includes(frequency))return false;
 if(['annual','yearly'].includes(frequency)&&! /^(19|20)\d{2}$/.test(period))return false;
 if(frequency==='quarterly'&&! /^(19|20)\d{2}-Q[1-4]$/.test(period))return false;
 if(frequency==='monthly'&&! /^(19|20)\d{2}-(0[1-9]|1[0-2])$/.test(period))return false;
 const reference=p.reference_date??null;
 if(reference!==null){
  if(typeof reference!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(reference))return false;
  const date=new Date(reference+'T00:00:00Z');
  if(Number.isNaN(date.getTime())||date.toISOString().slice(0,10)!==reference||reference>todayMadrid())return false;
 }
 // ECP population is a boundary stock, never an undocumented quarterly flow.
 if(/ECP/i.test(String(p.dataset_code??'')) &&
    (frequency!=='quarterly'||!quarterStockReference(p,period)))return false;

 // Match the server's provenance gate: an HTTPS URL containing credentials
 // is not a verifiable public source even if the host itself is legitimate.
 try{const source=new URL(p.source_url);if(source.protocol!=='https:'||!source.hostname||source.username||source.password)return false;}
 catch{return false;}
 const start=p.measurement_period_start??null,end=p.measurement_period_end??null;
 if(Boolean(start)!==Boolean(end))return false;
 const cumulative=String(p.methodology_flag??'').toUpperCase().includes('CUMULATIVE_JAN_JUN');
 if(cumulative&&!start)return false;
 if(start){
  if(!/^\d{4}-\d{2}-\d{2}$/.test(start)||!/^\d{4}-\d{2}-\d{2}$/.test(end))return false;
  const validDate=d=>{const date=new Date(d+'T00:00:00Z');return !Number.isNaN(date.getTime())&&date.toISOString().slice(0,10)===d;};
  if(!validDate(start)||!validDate(end)||start>end||start.slice(0,4)!==String(p.period).slice(0,4)||end.slice(0,4)!==String(p.period).slice(0,4))return false;
  if(cumulative&&(!start.endsWith('-01-01')||!end.endsWith('-06-30')))return false;
 }
 return true;
}
function cumulativeWarning(p){
 if(!p)return '';
 const flag=String(p.methodology_flag??'');const group=String(p.subgroup??'');
 if(!/CUMULATIVE_JAN_JUN/i.test(flag)&&!/acumulado\s+enero[-\s]+junio/i.test(group))return '';
 const dates=p.measurement_period_start&&p.measurement_period_end?` (${p.measurement_period_start} a ${p.measurement_period_end})`:'';
 const police=/POLICE_RECORDED/i.test(flag)||/police_record/i.test(String(p.measurement_type??''));
 return `ATENCIÓN: acumulado enero-junio${dates}. NO representa el año completo ni únicamente abril-junio.${police?' Se refiere a hechos registrados por la policía, no a condenas, personas únicas ni delitos no denunciados.':''}`;
}
function measureSignature(p,yearNeutral=false){return JSON.stringify(SIGNATURE_FIELDS.map(k=>{
 const v=p?.[k]??null;
 if(!yearNeutral)return v;
 if((k.startsWith('measurement_period_')||k==='reference_date')&&typeof v==='string'&&/^\d{4}-\d{2}-\d{2}$/.test(v))return v.slice(5);
 if(k==='source_url'&&typeof v==='string')return sourceFamily(v,p?.period);
 if(k==='dataset_code'&&typeof v==='string'){const year=String(p?.period??'').slice(0,4);if(/^(19|20)\d{2}$/.test(year)&&v.endsWith(year))return v.slice(0,-4)+'[year]';}
 return v;
}));}
async function api(path,params={}){const u=new URL(path,location.origin);for(const [k,v] of Object.entries(params))if(v!==null&&v!==undefined&&v!=='')u.searchParams.set(k,String(v));const response=await fetch(u,{credentials:'same-origin',headers:{Accept:'application/json'}});let data;try{data=await response.json()}catch{throw new Error(`Respuesta no JSON (${response.status}).`)}if(!response.ok)throw new Error(data.error||`Error HTTP ${response.status}`);return data;}
function updateURL(){
 const u=new URL(location.href),quarter=state.quarterSelection;
 const filters={indicator:state.indicator,group:state.group,year:state.year,geo:state.selected,compare:state.compare,
  from:$('from').value,to:$('to').value,qindicator:quarter?.indicator_id??'',qgroup:quarter?.subgroup??'',qgeo:quarter?.geo_code??'',corr_indicator:corrQuery?.year===state.year?corrQuery.indicator:'',
  corr_group:corrQuery?.year===state.year?corrQuery.group:''};
 for(const [k,v] of Object.entries(filters)){if(v!==''&&v!==null&&v!==undefined)u.searchParams.set(k,String(v));else u.searchParams.delete(k);}
 history.replaceState(null,'',u.pathname+u.search+u.hash);
}
function setStatus(s){$('loading').textContent=s;}
// A map is only exportable after the API has returned data for the CURRENT filter snapshot.
// This prevents stale observations being labelled with a newly selected indicator/year in CSV.
function observationKey(){return JSON.stringify([state.indicator,state.group,String(state.year)]);}
/** Never export or share an old result while the form displays new filters.
 * A keyboard-edited year may not have fired `change` yet. */
function displayedFiltersMatchLoaded(){
 return state.loadedKey!==''&&state.loadedKey===observationKey()&&
  $('indicator').value===state.indicator&&$('group').value===state.group&&
  String($('year').value)===String(state.year);
}
function invalidateObservations(){
 resetCorrelationForMapChange();
 state.requestId++;state.timelineRequestId++;state.loadedKey='';state.points={};state.compat=null;
 $('export-csv').disabled=true;
 renderMap();renderDetail();renderComparison();renderRanking();
 note('timeline','Consulta la evolución para los filtros actuales.');
}
function color(value,min,max){if(!Number.isFinite(value))return 'url(#esp-no-data)';const t=max===min?.55:Math.max(0,Math.min(1,(value-min)/(max-min)));const a=[197,232,229],b=[10,63,90];return `rgb(${a.map((v,i)=>Math.round(v+(b[i]-v)*t)).join(',')})`;}
function setZoom(next){state.zoom=Math.max(1,Math.min(2.5,Math.round(next*10)/10));const svg=$('map').querySelector('svg');if(svg){svg.style.width=`${Math.round(state.zoom*100)}%`;svg.style.maxHeight='none';} $('zoom-level').textContent=`${Math.round(state.zoom*100)} %`; }
/** Territory search is an exact/unique match, never the first substring found.
 * A short query like "la" previously selected Galicia by accident. City/province
 * queries must not silently become a regional statistic. */
function normalizeTerritoryQuery(value){return String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim().replace(/\s+/g,' ');}
function resolveTerritorySearch(raw){
 const query=normalizeTerritoryQuery(raw);
 if(!query||query.length<3)return {status:'too_short',candidates:[]};
 const codes=Object.keys(NAMES);
 const aliases={ES:['espana','nacional','total espana'],ES11:['galicia'],ES12:['asturias'],ES13:['cantabria'],
  ES21:['pais vasco','euskadi'],ES22:['navarra'],ES23:['la rioja'],ES24:['aragon'],
  ES30:['comunidad de madrid'],ES41:['castilla y leon'],ES42:['castilla la mancha'],
  ES43:['extremadura'],ES51:['cataluna','catalunya'],ES52:['comunitat valenciana','comunidad valenciana'],
  ES53:['illes balears','islas baleares','baleares'],ES61:['andalucia'],ES62:['region de murcia'],
  ES70:['canarias'],ES63:['ceuta'],ES64:['melilla']};
 // A city or province has a different statistical unit from its autonomous community.
 if(/\b(?:ciudad|capital|municipio|provincia|ayuntamiento|area metropolitana)\b/.test(query))
  return {status:'unsupported_geography',candidates:[]};
 // Capital, province and region may share a name. Require an explicit CCAA.
 if(['madrid','valencia','murcia'].includes(query))return {status:'unsupported_geography',candidates:[]};
 const exact=codes.filter(code=>normalizeTerritoryQuery(code)===query||
  [NAMES[code],...(aliases[code]||[])].some(name=>normalizeTerritoryQuery(name)===query));
 if(exact.length===1)return {status:'match',code:exact[0],candidates:exact};
 if(exact.length>1)return {status:'ambiguous',candidates:exact};
 const prefix=codes.filter(code=>[NAMES[code],...(aliases[code]||[])].some(name=>
  normalizeTerritoryQuery(name).startsWith(query)));
 if(prefix.length===1)return {status:'match',code:prefix[0],candidates:prefix};
 if(prefix.length>1)return {status:'ambiguous',candidates:prefix};
 return {status:'not_found',candidates:[]};
}
function selectTerritory(code){if(!NAMES[code])return;state.timelineRequestId++;note('timeline','Consulta la evolución para el nuevo territorio.');state.selected=code;if(state.compare===code){state.compare='';if($('compare'))$('compare').value='';}if($('territory'))$('territory').value=code;updateURL();renderMap();renderDetail();renderComparison();}
/** The fetched SVG is markup, not data. A same-origin asset could be replaced
 * accidentally or by a compromised build. Verify the 19 identities and allow
 * only inert geometry before importing it into the live DOM. No remote href,
 * foreignObject, script, inline event handlers or CSS execution is allowed. */
function verifyMapSVGDocument(parsed){
 const root=parsed?.documentElement,ns='http://www.w3.org/2000/svg';
 if(!root||root.namespaceURI!==ns||root.localName!=='svg'||parsed.querySelector('parsererror'))
  throw new Error('El recurso recibido no es un mapa SVG válido.');
 const permitted=new Set(['svg','g','defs','pattern','path','rect','circle','text','title']);
 const attributes=new Set(['id','class','viewBox','role','aria-label','tabindex','data-geo',
  'd','fill-rule','x','y','width','height','fill','stroke','cx','cy','r',
  'text-anchor','patternUnits','stroke-width','rx','stroke-dasharray']);
 const seen=new Set();
 for(const node of [root,...root.querySelectorAll('*')]){
  if(node.namespaceURI!==ns||!permitted.has(node.localName))
   throw new Error('El mapa contiene elementos SVG no autorizados.');
  for(const attr of Array.from(node.attributes)){
   if(node===root&&attr.name==='xmlns'&&attr.value===ns)continue;
   if(attr.namespaceURI||!attributes.has(attr.name)||/url\s*\(|[<>]/i.test(attr.value))
    throw new Error('El mapa contiene atributos SVG no autorizados.');
  }
  if(node.hasAttribute('data-geo')){
   const geo=node.getAttribute('data-geo');
   if(node.localName!=='g'||!GEOS.includes(geo)||seen.has(geo)||
      node.getAttribute('role')!=='button'||node.getAttribute('tabindex')!=='0'||
      !node.querySelector('.esp-shape'))
    throw new Error('Geometría territorial duplicada, incompleta o inválida.');
   seen.add(geo);
  }
 }
 if(seen.size!==GEOS.length)throw new Error('El mapa no representa los 19 territorios.');
 return root;
}
async function loadMapSVG(){
 const r=await fetch('/assets/observatorio/mapa-espana.svg');
 if(!r.ok)throw new Error('No se pudo cargar el mapa SVG de España.');
 const source=await r.text();
 if(source.length>1500000)throw new Error('El mapa recibido supera el tamaño permitido.');
 const parsed=new DOMParser().parseFromString(source,'image/svg+xml');
 const verified=verifyMapSVGDocument(parsed);
 const svg=document.importNode(verified,true);
 clear('map').append(svg);$('map').setAttribute('aria-busy','false');
 for(const node of svg.querySelectorAll('[data-geo]')){
  const code=node.getAttribute('data-geo');
  node.addEventListener('click',()=>selectTerritory(code));
  node.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();selectTerritory(code);}});
 }
 renderMap();setZoom(state.zoom);
}
function renderMap(){const nodes=$('map').querySelectorAll('[data-geo]');const available=GEOS.map(geo=>state.points[geo]).filter(p=>measuredValue(p)!==null);const values=available.map(measuredValue);const min=values.length?Math.min(...values):0,max=values.length?Math.max(...values):0;const signatures=new Set(available.map(p=>measureSignature(p)));const metadataComplete=available.length>0&&available.every(measurementReady)&&state.compat?.metadata_complete!==false;const homogeneous=signatures.size<=1&&state.compat?.homogeneous!==false&&metadataComplete;for(const node of nodes){const geo=node.getAttribute('data-geo'),p=state.points[geo]??null,shape=node.querySelector('.esp-shape');if(shape)shape.style.fill=measuredValue(p)===null?'url(#esp-no-data)':homogeneous?color(measuredValue(p),min,max):'#d2dce4';node.setAttribute('aria-pressed',String(geo===state.selected));node.setAttribute('aria-label',`${NAMES[geo]}: ${valueLabel(p)}. Pulsar para ver ficha.`);}const range=$('range');if(!values.length){range.textContent='Ningún territorio tiene un dato publicado y revisado para estos filtros. Rayado = sin dato; no significa cero.';return;}if(!homogeneous){range.textContent=`${values.length}/19 territorios con datos, pero falta documentar población, denominador o tipo de medida, o existen fuentes, unidades o metodologías incompatibles. Escala cromática desactivada: se muestran fichas individuales, no un ranking ni un gradiente engañoso.`;return;}range.textContent=`Rango observado: ${fmt(min)}–${fmt(max)} ${available[0]?.unit||''}. ${values.length}/19 territorios con dato. Color = magnitud; rayado = sin dato. No equivale a riesgo ni juicio de valor.`;}
function addFact(parent,label,value){const p=el('p',{class:'source'});p.append(el('strong',{},label+': '),el('span',{},value??'No documentado'));parent.append(p);}
function renderDetail(){const d=clear('detail'),p=state.points[state.selected]??null;d.append(el('h3',{},NAMES[state.selected]));if(measuredValue(p)===null){d.append(el('p',{class:'muted'},'Sin dato publicado y revisado para este indicador, subgrupo y año. No significa valor cero.'));return;}d.append(el('p',{class:'metric'},valueLabel(p)));if(cumulativeWarning(p))d.append(el('p',{class:'warning'},cumulativeWarning(p)));addFact(d,'Periodo',p.period);addFact(d,'Fuente',p.source_id);addFact(d,'Conjunto',p.dataset_code);addFact(d,'Población',p.population);addFact(d,'Tipo de medida',p.measurement_type);if(p.measurement_window_type)addFact(d,'Ventana temporal',p.measurement_window_type);if(p.reference_date)addFact(d,'Fecha de referencia del stock',p.reference_date);addFact(d,'Metodología',p.methodology_flag);if(p.limits)addFact(d,'Límites',p.limits);addFact(d,'Revisión',p.reviewed_at);if(p.source_locator)addFact(d,'Localizador de tabla',p.source_locator);if(p.citation)addFact(d,'Cita documental',p.citation);if(safeSourceURL(p.source_url)){const a=el('a',{href:safeSourceURL(p.source_url),target:'_blank',rel:'noopener noreferrer'},'Abrir fuente original ↗');const sourceLine=el('p',{class:'source'});sourceLine.append(a);d.append(sourceLine);}d.append(el('p',{class:'hint'},'El dato corresponde únicamente al territorio y periodo indicados. No demuestra una causa ni predice su evolución.'));}
function renderComparison(){const d=clear('comparison');if(!state.compare)return;const a=state.points[state.selected]??null,b=state.points[state.compare]??null;d.append(el('h3',{},`${NAMES[state.selected]} frente a ${NAMES[state.compare]}`));d.append(el('p',{},`${NAMES[state.selected]}: ${valueLabel(a)} · ${NAMES[state.compare]}: ${valueLabel(b)}`));if(cumulativeWarning(a)||cumulativeWarning(b))d.append(el('p',{class:'warning'},cumulativeWarning(a)||cumulativeWarning(b)));if(measuredValue(a)===null||measuredValue(b)===null){d.append(el('p',{class:'warning'},'Comparación incompleta: no se infiere un valor ausente.'));return;}const compatible=measurementReady(a)&&measurementReady(b)&&measureSignature(a)===measureSignature(b);if(!compatible){d.append(el('p',{class:'warning'},'Los valores no son directamente comparables: faltan metadatos de población, denominador o tipo de medida, o difieren fuente, unidad o metodología.'));return;}const delta=measuredValue(a)-measuredValue(b);const differenceUnit=a.unit==='%'?'puntos porcentuales':a.unit;d.append(el('p',{},`Diferencia descriptiva: ${fmt(delta)} ${differenceUnit}. No implica causalidad.`));if(state.selected==='ES'||state.compare==='ES')d.append(el('p',{class:'hint'},'El dato de España incluye el territorio comparado: ambas observaciones no son muestras independientes.'));}
function renderRanking(){const root=clear('ranking');const available=GEOS.map(geo=>[geo,state.points[geo]]).filter(([_,p])=>measuredValue(p)!==null);if(available.length<2){root.append(el('p',{class:'muted'},'Se necesitan al menos dos territorios con datos revisados para establecer un ranking. La ausencia de datos no es un cero.'));return;}const signatures=new Set(available.map(([_,p])=>measureSignature(p)));if(signatures.size>1||available.some(([_,p])=>!measurementReady(p))){root.append(el('p',{class:'warning'},'Ranking bloqueado: falta documentar población o tipo de medida, o existen fuentes, unidades o metodologías distintas.'));return;}const dir=$('order').value==='asc'?1:-1;available.sort((a,b)=>dir*(measuredValue(a[1])-measuredValue(b[1]))||NAMES[a[0]].localeCompare(NAMES[b[0]],'es'));const hasNegative=available.some(x=>measuredValue(x[1])<0);if(hasNegative)root.append(el('p',{class:'hint'},'Hay valores negativos: la barra representa magnitud absoluta; el signo y el orden se leen en la cifra.'));const max=Math.max(...available.map(x=>Math.abs(measuredValue(x[1]))),0.001);let lastValue=null,lastRank=0;available.forEach(([code,p],i)=>{lastRank=rankPosition(lastValue,measuredValue(p),i,lastRank);lastValue=measuredValue(p);const row=el('div',{class:'rank-item'});row.append(el('span',{},String(lastRank)));const btn=el('button',{type:'button'},NAMES[code]);btn.addEventListener('click',()=>selectTerritory(code));row.append(btn);const bar=el('div',{class:'bar','aria-hidden':'true'}),fill=el('span');fill.style.width=`${Math.min(100,Math.abs(measuredValue(p))/max*100)}%`;bar.append(fill);row.append(bar,el('strong',{},valueLabel(p)));root.append(row);});if(available.length<19)root.append(el('p',{class:'hint'},`${19-available.length} territorios sin datos. No se ordenan como si tuvieran valor cero.`));}
function chooseYearForGroup(keepYear=false){
 const available=state.coverage.filter(x=>x.group===state.group).map(x=>Number(x.year)).filter(Number.isInteger).sort((a,b)=>b-a);
 const list=$('available-years');list.replaceChildren();for(const y of available)list.append(el('option',{value:String(y)}));
 if(!keepYear||!Number.isInteger(state.year))state.year=available[0]??new Date().getFullYear();
 $('year').value=state.year;
 const selected=state.coverage.find(x=>x.group===state.group&&Number(x.year)===state.year);
 $('coverage').textContent=selected?`${selected.territories}/20 ámbitos (España y 19 territorios) con observaciones revisadas en ${state.year}.${selected.ambiguous_rows?' Hay registros duplicados que se omitirán en las comparaciones.':''}`:'No hay datos revisados para el año y subgrupo elegidos.';
}
async function loadIndicatorGroups(keepYear=false){
 const rid=++state.groupRequestId,indicator=state.indicator;
 state.coverage=[];
 const select=$('group');select.replaceChildren(el('option',{value:''},'Cargando cobertura revisada…'));
 if(!indicator){select.replaceChildren();return;}
 // Bounded, verified pages: never show a partial catalogue as complete.
 const all=[],seen=new Set();let offset=0,complete=false,snapshot=null;
 try{
  for(let page=0;page<101;page++){
   const data=await api('/api/v1/npl/coverage',{indicator,offset,page_size:500,snapshot});
   if(rid!==state.groupRequestId||indicator!==state.indicator)return;
   const pageSize=data.page_size??200; // Compatibilidad con mocks y API V25.
   if((data.offset??0)!==offset||![200,500].includes(pageSize)||!Array.isArray(data.coverage)||data.coverage.length>pageSize)
    throw new Error('Paginación de cobertura anual inconsistente.');
   if(data.snapshot!==undefined){
    if(!/^[a-f0-9]{64}$/.test(data.snapshot)||(snapshot!==null&&snapshot!==data.snapshot))
     throw new Error('El catálogo cambió durante la paginación anual; vuelve a cargar.');
    snapshot=data.snapshot;
   }else if(snapshot!==null)throw new Error('Falta la huella de catálogo anual.');
   for(const cell of data.coverage){
    if(typeof cell.group!=='string'||!/^(19|20)\d{2}$/.test(String(cell.year)))
     throw new Error('Metadatos de cobertura anual inválidos.');
    const key=JSON.stringify([cell.year,cell.group]);
    if(seen.has(key))throw new Error('La cobertura cambió durante la consulta; vuelve a cargar.');
    seen.add(key);all.push(cell);
   }
   if(!data.has_more){complete=true;break;}
   if(!Number.isInteger(data.next_offset)||data.next_offset!==offset+pageSize||data.coverage.length!==pageSize)
    throw new Error('Cursor anual inválido o página incompleta.');
   offset=data.next_offset;
   setStatus(`Cargando cobertura anual revisada: ${all.length} combinaciones…`);
  }
  if(!complete)throw new Error('Cobertura anual demasiado extensa para una consulta segura.');
  if(rid!==state.groupRequestId||indicator!==state.indicator)return;
  state.coverage=all;
  const groups=[...new Set(all.map(c=>c.group))];
  select.replaceChildren();for(const group of groups)select.append(el('option',{value:group},group));
  if(groups.includes(state.group))select.value=state.group;else state.group=select.value||'';
  chooseYearForGroup(keepYear);
 }catch(e){
  if(rid!==state.groupRequestId||indicator!==state.indicator)return;
  state.coverage=[];state.group='';select.replaceChildren(el('option',{value:''},'Cobertura no disponible'));
  setStatus('No se pudo verificar toda la cobertura anual: '+e.message);
  throw e;
 }
}
/** Reject inconsistent API projections before marking a filter snapshot as
 * loaded/exportable. Missing territories remain missing; an API response for
 * another period or with a swapped geographic key is not silently relabelled. */
function validateComparisonPayload(data,indicator,group,year){
 if(!data||typeof data!=='object'||Array.isArray(data)||
    !data.points||typeof data.points!=='object'||Array.isArray(data.points))
  throw new Error('La API no devolvió un conjunto territorial válido.');
 if(data.indicator!==undefined&&data.indicator!==indicator ||
    data.period!==undefined&&String(data.period)!==String(year) ||
    data.subgroup!==undefined&&data.subgroup!==group)
  throw new Error('La respuesta no corresponde al indicador, subgrupo y periodo solicitados.');
 for(const [geo,p] of Object.entries(data.points)){
  if(!ALL_GEOS.includes(geo))throw new Error('La API devolvió un territorio desconocido.');
  if(p===null)continue;
  if(typeof p!=='object'||Array.isArray(p)||
     p.geo_code!==undefined&&p.geo_code!==geo ||
     p.period!==undefined&&String(p.period)!==String(year) ||
     p.subgroup!==undefined&&p.subgroup!==group)
   throw new Error('Metadatos territoriales incompatibles con la consulta.');
  if(p.value!==null&&p.value!==undefined&&measuredValue(p)===null)
   throw new Error('Valor territorial inválido; no se convierte en cero.');
 }
 return data;
}
async function loadObservations(){
 state.group=$('group').value;state.year=Number($('year').value);
 invalidateObservations();
 if(!state.indicator||!state.group){setStatus('Selecciona un indicador y subgrupo con datos publicados.');return;}
 if(!Number.isInteger(state.year)||state.year<1900||state.year>new Date().getFullYear()){
  setStatus('Selecciona un año válido, no futuro.');return;
 }
 chooseYearForGroup(true);
 const rid=state.requestId,queryKey=observationKey();
 setStatus('Consultando registros revisados…');
 try{
  const data=await api('/api/v1/npl/compare',{indicator:state.indicator,period:state.year,group:state.group,geos:ALL_GEOS.join(',')});
  if(rid!==state.requestId||queryKey!==observationKey())return;
  validateComparisonPayload(data,state.indicator,state.group,state.year);
  state.points=data.points;state.compat=data.comparability??null;state.loadedKey=queryKey;
  $('export-csv').disabled=false;
  setStatus(`${GEOS.filter(g=>measuredValue(state.points[g])!==null).length}/19 territorios con datos revisados. ${measuredValue(state.points.ES)!==null?'Dato nacional disponible.':''} ${data.ambiguous?.length?`${data.ambiguous.length} registros ambiguos omitidos.`:''}`);
  renderMap();renderDetail();renderComparison();renderRanking();updateURL();
 }catch(e){
  if(rid!==state.requestId||queryKey!==observationKey())return;
  state.loadedKey='';$('export-csv').disabled=true;
  setStatus('No se pudo consultar la API.');renderMap();renderComparison();
  note('detail',e.message,'error');note('ranking',e.message,'error');
 }
}
/** Use the server's reviewed segmentation plan as an upper bound, not a hint.
 * A browser must NEVER reconnect two observations separated by an absent year,
 * missing denominator, or incompatible methodology. Older APIs without an
 * explicit drawable_segments contract are rendered as unconnected points.
 */
function annualSeriesRenderPlan(series,years){
 const points=series?.points??{},valid=new Map(),segments=[];
 for(const year of years){
  const p=points[year];
  if(measuredValue(p)!==null&&measurementReady(p)&&String(p.period)===year)
   valid.set(year,p);
 }
 const used=new Set();
 for(const candidate of Array.isArray(series?.drawable_segments)?series.drawable_segments:[]){
  if(!Array.isArray(candidate)||candidate.length<2||candidate.length>years.length)continue;
  const unique=new Set(candidate);
  if(unique.size!==candidate.length||candidate.some((y,i)=>
   typeof y!=='string'||!valid.has(y)||!years.includes(y)||
   (i>0&&Number(y)!==Number(candidate[i-1])+1)))continue;
  const signatures=new Set(candidate.map(y=>measureSignature(valid.get(y),true)));
  if(signatures.size!==1)continue;
  // A malformed overlapping segment must not create duplicate or ambiguous lines.
  if(candidate.some(y=>used.has(y)))continue;
  candidate.forEach(y=>used.add(y));
  segments.push(candidate);
 }
 return {segments,dots:[...valid.keys()],isolated:[...valid.keys()].filter(y=>!used.has(y)),
  withheld:years.filter(y=>points[y]&&!valid.has(y))};
}
function annualChart(root,geo,series,plan,years,domain,colorIndex,sharedScale){
 if(!plan.dots.length)return;
 const fig=el('figure',{class:'annual-figure'});
 const unit=series.points[plan.dots[0]].unit??'';
 const caption=el('figcaption',{},`${NAMES[geo]||geo} · ${unit} · ${sharedScale?'escala común':'escala independiente'}`);
 const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
 svg.setAttribute('viewBox','0 0 460 185');svg.setAttribute('role','img');
 svg.setAttribute('aria-label',`${NAMES[geo]||geo}: ${plan.dots.length} mediciones documentadas; ${plan.segments.length} tramos continuos. Años sin dato y cambios de metodología no se unen.`);
 const title=document.createElementNS('http://www.w3.org/2000/svg','title');
 title.textContent=`Serie anual de ${NAMES[geo]||geo}, ${years[0]}–${years.at(-1)}. No se interpolan huecos.`;svg.append(title);
 const stroke=['#176b7b','#8a4b12','#5e448f','#21684a','#a42f5c'][colorIndex%5];
 const [lo,hi]=domain,range=hi-lo||1;
 const x=y=>38+(Number(y)-Number(years[0]))/Math.max(1,years.length-1)*383;
 const y=v=>145-(v-lo)/range*111;
 for(const segment of plan.segments){
  const poly=document.createElementNS('http://www.w3.org/2000/svg','polyline');
  poly.setAttribute('points',segment.map(year=>`${x(year).toFixed(2)},${y(series.points[year].value).toFixed(2)}`).join(' '));
  poly.setAttribute('class','plot');poly.setAttribute('fill','none');poly.setAttribute('stroke',stroke);
  poly.setAttribute('stroke-width','3');svg.append(poly);
 }
 for(const year of plan.dots){
  const point=series.points[year],dot=document.createElementNS('http://www.w3.org/2000/svg','circle');
  dot.setAttribute('cx',x(year).toFixed(2));dot.setAttribute('cy',y(point.value).toFixed(2));
  dot.setAttribute('r',plan.isolated.includes(year)?'4.5':'3.5');dot.setAttribute('fill',stroke);
  const dotTitle=document.createElementNS('http://www.w3.org/2000/svg','title');
  dotTitle.textContent=`${year}: ${valueLabel(point)}. ${point.source_id||'Fuente no indicada'}.`;
  dot.append(dotTitle);svg.append(dot);
 }
 const labels=[[38,years[0],'start'],[421,years.at(-1),'end']];
 for(const [pos,year,anchor] of labels){
  const t=document.createElementNS('http://www.w3.org/2000/svg','text');
  t.setAttribute('x',String(pos));t.setAttribute('y','172');t.setAttribute('text-anchor',anchor);
  t.setAttribute('font-size','12');t.setAttribute('fill','#425b6c');t.textContent=year;svg.append(t);
 }
 for(const [v,vertical] of [[hi,35],[lo,145]]){
  const t=document.createElementNS('http://www.w3.org/2000/svg','text');
  t.setAttribute('x','35');t.setAttribute('y',String(vertical));t.setAttribute('text-anchor','end');
  t.setAttribute('font-size','11');t.setAttribute('fill','#425b6c');t.textContent=fmt(v);svg.append(t);
 }
 fig.append(caption,svg);
 if(plan.withheld.length)fig.append(el('p',{class:'hint'},`Sin representación gráfica por metadatos incompletos: ${plan.withheld.join(', ')}.`));
 if(plan.isolated.length)fig.append(el('p',{class:'hint'},`Puntos aislados, sin línea inferida: ${plan.isolated.join(', ')}.`));
 root.append(fig);
}
function renderTimeline(data){
 const root=clear('timeline'),years=Array.from({length:data.to-data.from+1},(_,i)=>String(data.from+i));
 const entries=Object.entries(data.series??{});
 const comparable=data.cross_territory_comparable===true&&!(data.incompatible_series?.length);
 if(!comparable)root.append(el('p',{class:'warning'},'No se superponen territorios ni se calcula una tendencia conjunta: faltan metadatos o difieren fuentes, unidades, poblaciones o metodologías. Los gráficos individuales, cuando son posibles, utilizan escalas independientes.'));
 const isCumulative=entries.some(([,s])=>Object.values(s.points??{}).some(p=>cumulativeWarning(p)));
 if(isCumulative)root.append(el('p',{class:'warning'},'Recuentos acumulados enero-junio: no totales anuales ni datos exclusivos del segundo trimestre.'));
 const table=el('table'),caption=el('caption',{},'Valores anuales publicados y revisados; ausencia no equivale a cero');
 const thead=el('thead'),head=el('tr');
 head.append(el('th',{scope:'col'},isCumulative?'Año (enero-junio acumulado)':'Año'));
 for(const [geo] of entries)head.append(el('th',{scope:'col'},NAMES[geo]||geo));
 thead.append(head);table.append(caption,thead);
 const body=el('tbody');
 for(const year of years){
  const tr=el('tr');tr.append(el('th',{scope:'row'},year));
  for(const [,s] of entries){
   const p=s.points?.[year]??null,cell=el('td');cell.append(el('span',{},valueLabel(p)));
   if(p?.source_url){
    try{const safe=safeSourceURL(p.source_url);if(safe){
     cell.append(el('span',{},' · '),el('a',{href:safe,target:'_blank',rel:'noopener noreferrer','aria-label':`Fuente del año ${year}`},'fuente ↗'));
    }}catch{/* An invalid provenance URL is never converted to a link. */}
   }
   tr.append(cell);
  }
  body.append(tr);
 }
 table.append(body);
 const plans=entries.map(([geo,s])=>[geo,s,annualSeriesRenderPlan(s,years)]);
 const drawable=plans.flatMap(([,s,plan])=>plan.dots.map(year=>s.points[year].value));
 if(drawable.length){
  const shared=[Math.min(...drawable),Math.max(...drawable)];
  const charts=el('div',{class:'annual-charts'});
  plans.forEach(([geo,s,plan],i)=>{
   const values=plan.dots.map(year=>s.points[year].value);
   if(values.length)annualChart(charts,geo,s,plan,years,comparable?shared:[Math.min(...values),Math.max(...values)],i,comparable);
  });
  root.append(charts);
 }else root.append(el('p',{class:'muted'},'No hay observaciones con metadatos suficientes para representar una serie. Las cifras individuales permanecen en la tabla.'));
 root.append(table);
 if(data.ambiguous?.length)root.append(el('p',{class:'warning'},`Observaciones excluidas por duplicidad: ${data.ambiguous.length}. Consulta la fuente y el periodo antes de interpretar la serie.`));
}

/** Never label a series with the user's selected region/year if the API returned
 * another request's result. The same guard applies before charts and tables,
 * not only before CSV export. Reject unknown periods, unexpected geographies,
 * swapped subgroups and invalid numeric values; null remains absence, not zero. */
function validateAnnualSeriesPayload(data,query){
 const {indicator,group,from,to,geos}=query;
 if(!data||typeof data!=='object'||Array.isArray(data)||
    data.indicator!==indicator||data.subgroup!==group||
    data.from!==from||data.to!==to||!data.series||
    typeof data.series!=='object'||Array.isArray(data.series))
  throw new Error('La serie anual no corresponde a los filtros solicitados.');
 const actual=Object.keys(data.series);
 if(actual.length!==geos.length||actual.some(geo=>!geos.includes(geo)))
  throw new Error('La API devolvió territorios distintos de los solicitados.');
 const expectedYears=Array.from({length:to-from+1},(_,i)=>String(from+i));
 for(const geo of geos){
  const series=data.series[geo];
  if(!series||typeof series!=='object'||Array.isArray(series)||
     !series.points||typeof series.points!=='object'||Array.isArray(series.points))
   throw new Error('Serie anual incompleta o mal formada.');
  const years=Object.keys(series.points);
  if(years.length!==expectedYears.length||years.some(y=>!expectedYears.includes(y)))
   throw new Error('La API devolvió años ajenos o ausentes en la serie.');
  for(const year of expectedYears){
   const point=series.points[year];if(point===null)continue;
   if(!point||typeof point!=='object'||Array.isArray(point)||
      point.geo_code!==geo||String(point.period)!==year||point.subgroup!==group||
      measuredValue(point)===null)
    throw new Error('Observación anual incompatible con el territorio, año o subgrupo.');
  }
 }
 return data;
}
async function loadTimeline(){
 if(!state.indicator||!state.group)return;
 const rid=++state.timelineRequestId,from=Number($('from').value),to=Number($('to').value);
 if(!Number.isInteger(from)||!Number.isInteger(to)||from<1900||to>new Date().getFullYear()||from>to||to-from>29){
  note('timeline','Selecciona años válidos y no futuros, con intervalo máximo de 30 años.','error');return;
 }
 note('timeline','Consultando serie…');$('timeline').setAttribute('aria-busy','true');updateURL();
 const query={indicator:state.indicator,group:state.group,from,to,geos:[state.selected,...(state.compare?[state.compare]:[])]};
 try{
  const data=await api('/api/v1/npl/series',{indicator:query.indicator,group:query.group,from,to,geos:query.geos.join(',')});
  if(rid===state.timelineRequestId)renderTimeline(validateAnnualSeriesPayload(data,query));
 }catch(e){if(rid===state.timelineRequestId)note('timeline',e.message,'error');}
 finally{if(rid===state.timelineRequestId)$('timeline').setAttribute('aria-busy','false');}
}
async function askQuestion(e){
 e.preventDefault();const rid=++state.askRequestId;
 const question=$('question').value.trim();note('answer','Buscando evidencia revisada…');
 try{
  const data=await api('/api/v1/npl/ask',{q:question});
  if(rid!==state.askRequestId)return; // A slow previous answer must not overwrite the latest query.
  const root=clear('answer');
  if(['answered_from_reviewed_data','incomplete_coverage','incompatible_measurements','insufficient_metadata'].includes(data.status)){
   for(const [geo,p] of Object.entries(data.points??{})){
    const article=el('section',{class:'answer-evidence'});
    article.append(el('h3',{},NAMES[geo]||geo),el('p',{},valueLabel(p)));
    if(p){
     const detail=[['Periodo',p.period],['Fuente',p.source_id],['Unidad',p.unit],['Población',p.population],['Método',p.methodology_flag],['Verificado',p.reviewed_at],['Localizador',p.source_locator],['Cita',p.citation]];
     const dl=el('dl',{class:'answer-metadata'});
     for(const [label,value] of detail)if(value!==null&&value!==undefined&&String(value).trim()){
      dl.append(el('dt',{},label),el('dd',{},String(value)));
     }
     article.append(dl);
     if(p.limits)article.append(el('p',{class:'hint'},`Límites: ${p.limits}`));
     if(safeSourceURL(p.source_url))article.append(el('a',{href:safeSourceURL(p.source_url),target:'_blank',rel:'noopener noreferrer'},'Consultar fuente primaria ↗'));
    }else article.append(el('p',{class:'warning'},'Sin observación publicada y revisada; no equivale a cero.'));
    root.append(article);
   }
   if(data.status!=='answered_from_reviewed_data')root.append(el('p',{class:'warning'},data.status==='incomplete_coverage'?'Cobertura incompleta; no se inventan cifras.':(data.status==='insufficient_metadata'?'Metadatos de fuente o población incompletos: las cifras no están listas para compararse.':'Las mediciones no son directamente comparables.')));
   if(data.interpretation_guard)root.append(el('p',{class:'hint'},data.interpretation_guard));
  }else{
   // API status codes are stable for machines; explain them in human Spanish.
   const guidance={
    ambiguous_geography_level:'Territorio ambiguo',
    unsupported_geography_level:'Nivel territorial no disponible',
    unsupported_or_ambiguous_indicator:'Indicador no reconocido o ambiguo',
    causal_question_not_supported:'No se puede inferir una causa de estos datos',
    future_period_not_available:'El periodo solicitado todavía no tiene observaciones',
    year_required:'Falta concretar el año',
    territory_required:'Falta concretar el territorio',
    unsupported_population:'Desglose poblacional no disponible'
   };
   root.append(el('p',{class:'warning'},`${guidance[data.status]||'Consulta no resuelta'}. ${data.hint||'Precisa indicador, periodo y territorio.'}`));
  }
  root.append(el('p',{class:'hint'},'Consulta determinista de datos revisados, no verificador de veracidad ni sistema de inferencia causal.'));
 }catch(err){if(rid===state.askRequestId)note('answer',err.message,'error');}
}
function csvCell(value){
 let text=value===null||value===undefined?'':String(value);
 // Spreadsheet programs may interpret even quoted cells beginning with these characters as formulas.
 if(/^[\p{Z}\p{Cc}\p{Cf}\uFEFF]*[=+@-]/u.test(text)&&! /^-?(?:\d+)(?:\.\d+)?(?:[eE][+-]?\d+)?$/.test(text))text="'"+text;
 return '"'+text.replace(/"/g,'""')+'"';
}
function csvRows(){
 const header=['territorio','codigo','indicador','subgrupo','anio','periodo_inicio','periodo_fin','fecha_referencia','ventana_temporal','valor','unidad','fuente','url_fuente','poblacion','tipo_medicion','metodologia','revision','limites','estado'];
 const rows=[header];
 for(const geo of ALL_GEOS){const p=state.points[geo]??null;
  rows.push([NAMES[geo],geo,state.indicator,state.group,state.year,p?.measurement_period_start??null,p?.measurement_period_end??null,p?.reference_date??null,p?.measurement_window_type??null,measuredValue(p),p?.unit??null,p?.source_id??null,p?.source_url??null,p?.population??null,p?.measurement_type??null,p?.methodology_flag??null,p?.reviewed_at??null,p?.limits??null,measuredValue(p)!==null?'revisado':'sin_dato']);
 }
 return rows;
}
function exportReviewedCSV(){
 if(!state.indicator||!state.group||!displayedFiltersMatchLoaded()){
  setStatus('No se exporta: actualiza primero el mapa y espera los datos del indicador, subgrupo y año seleccionados.');return;
 }
 const rows=csvRows();const text='\uFEFF'+rows.map(row=>row.map(csvCell).join(';')).join('\r\n')+'\r\n';
 const url=URL.createObjectURL(new Blob([text],{type:'text/csv;charset=utf-8'}));
 const link=document.createElement('a');link.href=url;link.download=`npl-${state.indicator}-${state.year}.csv`;
 document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
 setStatus('CSV de observaciones revisadas preparado: 19 territorios y España. Las ausencias permanecen vacías.');
}
/* V21: quarterly series are isolated from annual maps and remain reviewed-only. */
let quarterCatalog=[],quarterRequestId=0,quarterCatalogRequestId=0;
function quarterIndex(period){const m=/^(19|20)\d{2}-Q[1-4]$/.exec(period);return m?Number(period.slice(0,4))*4+Number(period.slice(-1))-1:null;}
function quarterName(index){return `${Math.floor(index/4)}-Q${index%4+1}`;}
function quarterPeriods(points){const indices=Object.keys(points).map(quarterIndex).filter(Number.isInteger).sort((a,b)=>a-b);if(!indices.length)return [];const start=indices[0],end=indices.at(-1);if(end-start>79)return [];return Array.from({length:end-start+1},(_,i)=>quarterName(start+i));}
/** Client-side verification of the server's quarterly drawing contract.
 * Never reconnect a missing/invalid quarter, even if an old API says
 * comparable_trend=true or sends a malformed drawable_segments array.
 */
/** INE ECP stock observations use the boundary AFTER the labelled quarter.
 * Do not present them as a quarterly average or as a three-month flow. */
function quarterStockReference(p,period){
 if(!p||!/ECP/i.test(String(p.dataset_code??''))||!/^(19|20)\d{2}-Q[1-4]$/.test(period))return false;
 const year=Number(period.slice(0,4)),q=Number(period.slice(-1));
 const expected=q===4?`${year+1}-01-01`:`${year}-${String(q*3+1).padStart(2,'0')}-01`;
 return p.reference_date===expected&&expected<=todayMadrid()&&
  !p.measurement_period_start&&!p.measurement_period_end;
}
function quarterRenderPlan(data,periods){
 const points=data?.points??{},valid=new Map(),segments=[],used=new Set();
 for(const period of periods){
  const p=points[period];
  if(measuredValue(p)===null||String(p.period)!==period||
     String(p.frequency??'').toLowerCase()!=='quarterly'||!measurementReady(p))continue;
  // Explicit windows must fit the selected quarter. A partial quarter can
  // be inspected, but never presented as a full-quarter connected trend.
  const start=p.measurement_period_start,end=p.measurement_period_end;
  if(p.reference_date && /ECP/i.test(String(p.dataset_code??'')) && !quarterStockReference(p,period))continue;
  if(start&&end){
   const q=Number(period.slice(-1)),year=Number(period.slice(0,4));
   const first=`${year}-${String((q-1)*3+1).padStart(2,'0')}-01`;
   const last=new Date(Date.UTC(year,q*3,0)).toISOString().slice(0,10);
   if(start<first||end>last)continue;
  }
  valid.set(period,p);
 }
 const signature=(period)=>{
  const p=valid.get(period),values=JSON.parse(measureSignature(p,true));
  if(quarterStockReference(p,period)){
   values[signatureIndex('reference_date')]='ecp_point_in_time';
  }else if(!p.measurement_period_start||!p.measurement_period_end){
   // Missing documentary windows never authorize connecting two quarters,
   // even if an old API mistakenly supplies drawable_segments.
   values[signatureIndex('measurement_period_start')]='undocumented:'+period;
   values[signatureIndex('measurement_period_end')]='undocumented:'+period;
  }else if(p.measurement_period_start&&p.measurement_period_end){
   const year=Number(period.slice(0,4)),q=Number(period.slice(-1));
   const first=`${year}-${String((q-1)*3+1).padStart(2,'0')}-01`;
   const last=new Date(Date.UTC(year,q*3,0)).toISOString().slice(0,10);
   if(p.measurement_period_start===first&&p.measurement_period_end===last){
    values[signatureIndex('measurement_period_start')]='full_quarter';
    values[signatureIndex('measurement_period_end')]='full_quarter';
   }else{
    // A partial quarter is not a full quarter; it must never be connected
    // to another quarter without a separate scientific comparability model.
    values[signatureIndex('measurement_period_start')]='partial:'+period+':'+p.measurement_period_start;
    values[signatureIndex('measurement_period_end')]='partial:'+period+':'+p.measurement_period_end;
   }
  }
  return JSON.stringify(values);
 };
 if(data?.reason!=='incompatible_measurements'&&Array.isArray(data?.drawable_segments)){
  for(const run of data.drawable_segments){
   if(!Array.isArray(run)||run.length<2||run.length>periods.length||
      new Set(run).size!==run.length||run.some((period,i)=>
       typeof period!=='string'||!valid.has(period)||used.has(period)||
       (i>0&&quarterIndex(period)!==quarterIndex(run[i-1])+1)))continue;
   if(new Set(run.map(signature)).size!==1)continue;
   run.forEach(period=>used.add(period));segments.push(run);
  }
 }
 return {segments,dots:[...valid.keys()],isolated:[...valid.keys()].filter(p=>!used.has(p)),
  withheld:periods.filter(p=>points[p]&&!valid.has(p))};
}
function quarterChart(root,data,periods,plan){
 if(!plan.segments.length)return;
 const measured=plan.segments.flat().map(period=>data.points[period]);
 const vals=measured.map(measuredValue),lo=Math.min(...vals),hi=Math.max(...vals),range=hi-lo||1;
 const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 680 185');svg.setAttribute('class','quarter-chart');svg.setAttribute('role','img');svg.setAttribute('aria-label',`Tramos trimestrales revisados desde ${periods[0]} hasta ${periods.at(-1)}. Los huecos y cambios metodológicos no se conectan.`);
 const title=document.createElementNS('http://www.w3.org/2000/svg','title');title.textContent='Evolución trimestral: segmentos compatibles, sin interpolación';svg.append(title);
 for(const run of plan.segments){
  const coords=[];
  for(const period of run){
   const i=periods.indexOf(period),point=data.points[period];
   const x=36+i/Math.max(1,periods.length-1)*608,y=150-(point.value-lo)/range*115;
   coords.push(`${x.toFixed(2)},${y.toFixed(2)}`);
   const dot=document.createElementNS('http://www.w3.org/2000/svg','circle');dot.setAttribute('cx',String(x));dot.setAttribute('cy',String(y));dot.setAttribute('r','3.5');dot.setAttribute('fill','#13677b');
   const dotTitle=document.createElementNS('http://www.w3.org/2000/svg','title');dotTitle.textContent=`${period}: ${valueLabel(point)}`;dot.append(dotTitle);svg.append(dot);
  }
  const line=document.createElementNS('http://www.w3.org/2000/svg','polyline');line.setAttribute('points',coords.join(' '));line.setAttribute('fill','none');line.setAttribute('stroke','#13677b');line.setAttribute('stroke-width','3');svg.append(line);
 }
 for(const [x,label] of [[36,periods[0]],[644,periods.at(-1)]]){const t=document.createElementNS('http://www.w3.org/2000/svg','text');t.setAttribute('x',String(x));t.setAttribute('y','174');t.setAttribute('text-anchor',x===36?'start':'end');t.setAttribute('fill','#455c6d');t.setAttribute('font-size','13');t.textContent=label;svg.append(t);}
 root.append(svg);
}
function renderQuarterly(data){
 const root=clear('quarter-results'),periods=quarterPeriods(data.points||{}),selected=selectedQuarter();
 if(selected)root.append(el('h3',{},`${selected.name||selected.indicator_id} · ${selected.subgroup} · ${NAMES[selected.geo_code]||selected.geo_code}`));
 if(!periods.length){root.append(el('p',{class:'muted'},'No hay trimestres revisados y publicados para esta serie.'));return;}
 const plan=quarterRenderPlan(data,periods);
 const hasRuns=plan.segments.length>0;
 const stockDates=periods.filter(period=>quarterStockReference(data.points?.[period],period));
 const comparable=data.comparable_trend===true&&hasRuns&&plan.segments.length===1&&plan.segments[0].length===periods.length&&plan.withheld.length===0;
 root.append(el('p',{class:comparable?'hint':'warning'},comparable?'Serie descriptiva comparable según sus metadatos; las variaciones no demuestran causalidad.':
   hasRuns?'Se dibujan únicamente los tramos consecutivos y metodológicamente compatibles; los huecos y cambios de método quedan desconectados. No es una tendencia causal.':
   `No se dibuja una línea de tendencia: ${data.reason==='missing_metadata'?'faltan metadatos de medición':data.reason==='incompatible_measurements'?'cambian definiciones, fuentes o poblaciones':data.reason==='nonconsecutive_periods'?'faltan trimestres consecutivos':'se necesitan al menos dos trimestres válidos'}. Las cifras individuales siguen consultables.`));
 if(stockDates.length)root.append(el('p',{class:'hint'},`Los ${stockDates.length} valores ECP son stocks a fechas de referencia, NO medias trimestrales ni flujos migratorios. Cada fecha se indica en la tabla.`));
 quarterChart(root,data,periods,plan);
 const wrap=el('div',{class:'quarter-table-wrap'}),table=el('table',{class:'quarter-table'}),caption=el('caption',{},'Valores trimestrales revisados y sus fuentes');
 const head=el('thead'),hr=el('tr');for(const label of ['Trimestre','Valor','Población','Fuente y límites'])hr.append(el('th',{scope:'col'},label));head.append(hr);table.append(caption,head);
 const body=el('tbody');for(const period of periods){const p=data.points[period]??null,row=el('tr');row.append(el('th',{scope:'row'},p?.reference_date?`${period} · referencia ${p.reference_date}`:period),el('td',{},valueLabel(p)),el('td',{},p?.population||'No documentada'));
 const source=el('td');if(p){source.append(el('span',{},`${p.source_id||'Fuente no indicada'} · ${p.methodology_flag||'Método no documentado'}`));if(safeSourceURL(p.source_url))source.append(el('br'),el('a',{href:safeSourceURL(p.source_url),target:'_blank',rel:'noopener noreferrer'},'Fuente original ↗'));if(p.limits)source.append(el('p',{class:'hint'},p.limits));}else source.textContent='Sin dato publicado (no es cero).';row.append(source);body.append(row);}
 table.append(body);wrap.append(table);root.append(wrap);
 if(plan.withheld.length)root.append(el('p',{class:'warning'},'No representados por metadatos inválidos o insuficientes: '+plan.withheld.join(', ')));
 if(plan.isolated.length)root.append(el('p',{class:'hint'},'Trimestres documentados aislados, sin línea inferida: '+plan.isolated.join(', ')));
 if((data.ambiguous_periods||[]).length)root.append(el('p',{class:'warning'},'Trimestres excluidos por duplicidad: '+data.ambiguous_periods.join(', ')));
}
function selectedQuarter(){
 const select=$('quarter-select'),raw=select.value;
 if(select.disabled||!/^\d+$/.test(raw))return null;
 const item=quarterCatalog[Number(raw)];
 return item&&[...select.options].some(option=>option.value===raw)?item:null;
}
function rememberQuarterSelection(){
 const item=selectedQuarter();
 state.quarterSelection=item?{indicator_id:item.indicator_id,subgroup:item.subgroup,geo_code:item.geo_code}:null;
 updateURL();
}
/** Quarterly payloads are bound to the selected catalog entry. An old or
 * inconsistent response must not be rendered under a different territory or
 * indicator; missing quarters are not invented and zero remains a measurement. */
function validateQuarterlySeriesPayload(data,item){
 if(!data||typeof data!=='object'||Array.isArray(data)||
    data.indicator!==item.indicator_id||data.subgroup!==item.subgroup||
    data.geo!==item.geo_code||!data.points||
    typeof data.points!=='object'||Array.isArray(data.points))
  throw new Error('La serie trimestral no corresponde a la selección actual.');
 const periods=Object.keys(data.points);
 if(periods.length>499)throw new Error('Serie trimestral excesiva o incompleta.');
 const latest=todayMadrid(),year=Number(latest.slice(0,4)),month=Number(latest.slice(5,7));
 const latestIndex=year*4+Math.ceil(month/3)-1;
 for(const period of periods){
  if(!/^(19|20)\d{2}-Q[1-4]$/.test(period)||quarterIndex(period)>latestIndex)
   throw new Error('La API devolvió un trimestre inválido o futuro.');
  const point=data.points[period];if(point===null)continue;
  if(!point||typeof point!=='object'||Array.isArray(point)||
     point.geo_code!==item.geo_code||point.period!==period||
     point.subgroup!==item.subgroup||measuredValue(point)===null)
   throw new Error('Observación trimestral incompatible con el territorio, periodo o subgrupo.');
 }
 return data;
}
async function loadQuarterlySeries(){
 const item=selectedQuarter();if(!item)return;
 const rid=++quarterRequestId;
 rememberQuarterSelection();
 note('quarter-results','Consultando observaciones trimestrales revisadas…');
 $('quarter-results').setAttribute('aria-busy','true');
 try{
  const data=await api('/api/v1/npl/quarterly',{indicator:item.indicator_id,group:item.subgroup,geo:item.geo_code});
  if(rid===quarterRequestId&&selectedQuarter()===item)
   renderQuarterly(validateQuarterlySeriesPayload(data,item));
 }catch(e){if(rid===quarterRequestId&&selectedQuarter()===item)note('quarter-results',e.message,'error');}
 finally{if(rid===quarterRequestId&&selectedQuarter()===item)$('quarter-results').setAttribute('aria-busy','false');}
}
function renderQuarterChoices(){
 const select=$('quarter-select'),button=$('quarter-load'),search=$('quarter-search');
 const previous=select.value;
 quarterRequestId++; // A response for a previous search or selection must never reappear.
 clear('quarter-results');
 const needle=search.value.trim().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
 const matching=[];
 quarterCatalog.forEach((item,index)=>{
  const text=[item.name||item.indicator_id,item.subgroup,NAMES[item.geo_code]||item.geo_code]
   .join(' ').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  if(!needle||text.includes(needle))matching.push(index);
 });
 select.replaceChildren();
 if(!matching.length){
  select.append(el('option',{value:''},'Sin series que coincidan con la búsqueda'));
  select.disabled=true;button.disabled=true;
  state.quarterSelection=null;updateURL();
  $('quarter-status').textContent=`Sin coincidencias entre ${quarterCatalog.length} series revisadas. Prueba otro indicador o territorio.`;
  return;
 }
 for(const i of matching){const item=quarterCatalog[i];
  select.append(el('option',{value:String(i)},`${item.name||item.indicator_id} · ${item.subgroup} · ${NAMES[item.geo_code]||item.geo_code}`));
 }
 const deepLink=state.quarterSelection;
 const deepIndex=matching.find(i=>deepLink&&quarterCatalog[i].indicator_id===deepLink.indicator_id&&
  quarterCatalog[i].subgroup===deepLink.subgroup&&quarterCatalog[i].geo_code===deepLink.geo_code);
 const preferred=deepIndex??(matching.some(i=>String(i)===previous)?Number(previous):
  (matching.find(i=>quarterCatalog[i].geo_code==='ES')??matching[0]));
 select.value=String(preferred);select.disabled=false;button.disabled=false;
 rememberQuarterSelection();
 $('quarter-status').textContent=`${matching.length} de ${quarterCatalog.length} combinaciones revisadas visibles. No se incluyen series pendientes de aprobación.`;
}
async function loadQuarterlyCatalog(){
 const select=$('quarter-select'),button=$('quarter-load');select.disabled=true;button.disabled=true;
 const catalogRid=++quarterCatalogRequestId;quarterRequestId++;clear('quarter-results');
 try{
  // Fetch the entire reviewed quarterly catalogue in bounded pages, rather than
  // silently losing series beyond the first 1000 combinations.
  const seen=new Set();let offset=0,complete=false,snapshot=null;
  quarterCatalog=[];
  for(let page=0;page<81;page++){
   const data=await api('/api/v1/npl/quarterly',{catalog:1,offset,page_size:500,snapshot});
   if(catalogRid!==quarterCatalogRequestId)return;
   const pageSize=data.page_size??250;
   if(data.offset!==offset||![250,500].includes(pageSize)||!Array.isArray(data.catalog)||data.catalog.length>pageSize)throw new Error('Paginación trimestral inconsistente.');
   if(data.snapshot!==undefined){
    if(!/^[a-f0-9]{64}$/.test(data.snapshot)||(snapshot!==null&&snapshot!==data.snapshot))
     throw new Error('El catálogo trimestral cambió durante la consulta; vuelve a cargar.');
    snapshot=data.snapshot;
   }else if(snapshot!==null)throw new Error('Falta la huella de catálogo trimestral.');
   for(const item of data.catalog){
    const key=JSON.stringify([item.indicator_id,item.subgroup,item.geo_code]);
    if(seen.has(key))throw new Error('El catálogo trimestral cambió durante la consulta; actualiza la página.');
    seen.add(key);quarterCatalog.push(item);
   }
   if(!data.has_more){complete=true;break;}
   if(!Number.isInteger(data.next_offset)||data.next_offset!==offset+pageSize||data.catalog.length!==pageSize)throw new Error('Cursor de catálogo trimestral inválido.');
   offset=data.next_offset;
   $('quarter-status').textContent=`Cargando series trimestrales revisadas: ${quarterCatalog.length}…`;
  }
  if(!complete)throw new Error('Catálogo trimestral superior al límite de seguridad; usa una consulta más específica.');
  select.replaceChildren();
  if(!quarterCatalog.length){state.quarterSelection=null;updateURL();select.append(el('option',{value:''},'Sin series trimestrales revisadas'));$('quarter-status').textContent='La base puede contener series trimestrales preparadas, pero no se mostrarán hasta que estén publicadas y revisadas.';return;}
  $('quarter-search').disabled=false;
  renderQuarterChoices();
  await loadQuarterlySeries();
 }catch(e){if(catalogRid!==quarterCatalogRequestId)return;quarterCatalog=[];quarterRequestId++;select.disabled=true;button.disabled=true;$('quarter-search').disabled=true;select.replaceChildren(el('option',{value:''},'No disponible'));clear('quarter-results');$('quarter-status').textContent='No se pudo verificar el catálogo trimestral: '+e.message;}
}

// V60: correlation uses only published, comparable regional observations from the API.
// No client-side statistical calculation or implicit imputation is performed.
let corrGroupsRid=0,corrResultRid=0,corrGroupsYear=null,corrGroupsIndicator='',corrQuery=null;
function resetCorrelationForMapChange(){
 corrGroupsRid++;corrResultRid++;corrGroupsYear=null;corrGroupsIndicator='';corrQuery=null;
 const group=$('corr-group'),run=$('corr-run');
 if(group){group.disabled=true;group.replaceChildren(el('option',{value:''},'Actualiza los subgrupos para el año actual'));}
 if(run)run.disabled=true;
 if($('corr-result'))clear('corr-result');
 if($('corr-status'))$('corr-status').textContent='Los filtros del mapa cambiaron. Vuelve a consultar subgrupos y calcula de nuevo.';
}
function correlationStatus(message){$('corr-status').textContent=message;}
function initCorrelationCatalog(items){
 const select=$('corr-indicator');select.replaceChildren(el('option',{value:''},'Selecciona un indicador distinto del mapa'));
 for(const item of items){const id=item.id??item.indicator_id;
  if(typeof id==='string'&&/^[A-Za-z0-9_-]{2,100}$/.test(id))select.append(el('option',{value:id},item.name??item.label_es??id));
 }
 $('corr-load-groups').addEventListener('click',loadCorrelationGroups);
 select.addEventListener('change',()=>{resetCorrelationForMapChange();correlationStatus('Pulsa Buscar subgrupos disponibles para el indicador B.');});
 $('corr-group').addEventListener('change',()=>{corrResultRid++;corrQuery=null;clear('corr-result');updateURL();});
 $('corr-run').addEventListener('click',runCorrelation);
}
async function loadCorrelationGroups(){
 const indicator=$('corr-indicator').value,year=state.year,rid=++corrGroupsRid;
 corrResultRid++;corrQuery=null;clear('corr-result');$('corr-run').disabled=true;$('corr-group').disabled=true;updateURL();
 if(!indicator||indicator===state.indicator){correlationStatus('Elige un indicador B distinto del indicador del mapa.');return;}
 if(!displayedFiltersMatchLoaded()){correlationStatus('Actualiza primero el mapa para el indicador, subgrupo y año visibles.');return;}
 correlationStatus('Comprobando la cobertura publicada del indicador B…');
 let offset=0,snapshot=null,done=false;const groups=new Set(),seen=new Set();
 try{
  for(let page=0;page<101;page++){
   const data=await api('/api/v1/npl/coverage',{indicator,offset,page_size:500,snapshot});
   if(rid!==corrGroupsRid||year!==state.year||indicator!==$('corr-indicator').value)return;
   const pageSize=data.page_size??200;
   if(data.offset!==offset||![200,500].includes(pageSize)||!Array.isArray(data.coverage)||data.coverage.length>pageSize)
    throw new Error('Cobertura secundaria incompleta.');
   if(typeof data.snapshot==='string'){
    if(!/^[a-f0-9]{64}$/.test(data.snapshot)||(snapshot!==null&&snapshot!==data.snapshot))throw new Error('La cobertura cambió durante la consulta.');
    snapshot=data.snapshot;
   }else if(snapshot!==null)throw new Error('Falta la huella de cobertura.');
   for(const cell of data.coverage){
    const key=JSON.stringify([cell.year,cell.group]);if(seen.has(key))throw new Error('Cobertura secundaria duplicada.');seen.add(key);
    if(Number(cell.year)===year&&Number.isInteger(Number(cell.territories))&&Number(cell.territories)>=8&&typeof cell.group==='string')groups.add(cell.group);
   }
   if(!data.has_more){done=true;break;}
   if(data.next_offset!==offset+pageSize||data.coverage.length!==pageSize)throw new Error('Paginación secundaria inválida.');
   offset=data.next_offset;
  }
  if(!done)throw new Error('Demasiadas combinaciones de subgrupos para verificar.');
  if(rid!==corrGroupsRid)return;
  const select=$('corr-group');select.replaceChildren();
  for(const group of groups)select.append(el('option',{value:group},group));
  if(!groups.size){select.append(el('option',{value:''},'Sin cobertura territorial suficiente'));correlationStatus('No hay subgrupos B con al menos ocho ámbitos publicados en este año.');return;}
  select.disabled=false;$('corr-run').disabled=false;corrGroupsYear=year;corrGroupsIndicator=indicator;
  correlationStatus(`${groups.size} subgrupos B candidatos en ${year}. La API comprobará homogeneidad, denominadores y al menos ocho territorios válidos.`);
 }catch(error){if(rid===corrGroupsRid)correlationStatus('No se pudo verificar la cobertura del indicador B: '+error.message);}
}
function correlationSource(root,label,source){
 const row=el('p',{},label+': ');const url=safeSourceURL(source?.source_url);
 if(url){const link=el('a',{href:url,target:'_blank',rel:'noopener noreferrer'},source?.source_id||'Fuente oficial');row.append(link);}
 else row.append(document.createTextNode('fuente no enlazable; requiere revisión'));
 if(source?.measurement_window_type)row.append(document.createTextNode(' · ventana: '+source.measurement_window_type));
 if(source?.reference_date)row.append(document.createTextNode(' · referencia: '+source.reference_date));
 root.append(row);
}
/** Transparent territorial scatterplot: every dot is a reviewed API pair.
 * The accompanying native HTML table makes all points available to keyboard
 * and screen-reader users; no regression line, prediction or imputation. */
function renderCorrelationScatter(root,data,n){
 const rows=data.paired_observations;
 if(!Array.isArray(rows)||rows.length!==n||n>19)return false;
 const codes=new Set(),clean=[];
 for(const item of rows){
  const x=measuredValue({value:item?.x}),y=measuredValue({value:item?.y});
  if(!GEOS.includes(item?.geo_code)||codes.has(item.geo_code)||x===null||y===null)return false;
  codes.add(item.geo_code);clean.push({geo:item.geo_code,x,y});
 }
 const axis=(values)=>{const scale=Math.max(...values.map(v=>Math.abs(v)));
  if(!Number.isFinite(scale)||scale===0)return null;
  const scaled=values.map(v=>v/scale),min=Math.min(...scaled),max=Math.max(...scaled);
  return max>min?{scale,min,max,position:(v)=>(v/scale-min)/(max-min)}:null;};
 const ax=axis(clean.map(p=>p.x)),ay=axis(clean.map(p=>p.y));if(!ax||!ay)return false;
 const fig=el('figure',{class:'corr-figure'}),caption=el('figcaption',{},'Cada punto representa un territorio con ambas mediciones revisadas. No se dibuja una tendencia causal.');
 const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');
 const node=(tag,attrs={},text)=>{const item=document.createElementNS(ns,tag);for(const [k,v] of Object.entries(attrs))item.setAttribute(k,String(v));if(text!==undefined)item.textContent=String(text);return item;};
 svg.setAttribute('viewBox','0 0 760 420');svg.setAttribute('role','img');svg.setAttribute('aria-label','Dispersión de '+n+' territorios. La tabla siguiente contiene los valores exactos.');
 const left=75,right=710,top=32,bottom=345;
 svg.append(node('line',{x1:left,y1:bottom,x2:right,y2:bottom,class:'corr-axis'}),node('line',{x1:left,y1:top,x2:left,y2:bottom,class:'corr-axis'}));
 for(const t of [0,.5,1]){
  const xx=left+t*(right-left),yy=bottom-t*(bottom-top);
  svg.append(node('text',{x:xx,y:bottom+23,'text-anchor':'middle',class:'corr-tick'},fmt((ax.min+t*(ax.max-ax.min))*ax.scale)),
   node('text',{x:left-9,y:yy+4,'text-anchor':'end',class:'corr-tick'},fmt((ay.min+t*(ay.max-ay.min))*ay.scale)));
 }
 svg.append(node('text',{x:(left+right)/2,y:410,'text-anchor':'middle',class:'corr-axis-label'},'Indicador A ('+(data.sources?.x?.unit??'unidad documentada')+')'),
  node('text',{x:left,y:18,class:'corr-axis-label'},'Indicador B ('+(data.sources?.y?.unit??'unidad documentada')+')'));
 for(const row of clean){
  const circle=node('circle',{cx:left+ax.position(row.x)*(right-left),cy:bottom-ay.position(row.y)*(bottom-top),r:6,class:'corr-dot'});
  circle.append(node('title',{},NAMES[row.geo]+': A = '+fmt(row.x)+'; B = '+fmt(row.y)));svg.append(circle);
 }
 fig.append(caption,svg);root.append(fig);
 const details=el('details',{class:'corr-data-table'});details.append(el('summary',{},'Consultar los '+n+' pares de datos territoriales'));
 const table=el('table'),head=el('thead'),hrow=el('tr');
 for(const label of ['Territorio','Indicador A','Indicador B'])hrow.append(el('th',{scope:'col'},label));head.append(hrow);table.append(head);
 const body=el('tbody');for(const row of clean){const tr=el('tr');tr.append(el('th',{scope:'row'},NAMES[row.geo]),el('td',{},fmt(row.x)),el('td',{},fmt(row.y)));body.append(tr);}table.append(body);details.append(table);root.append(details);
 return true;
}
/** Recompute the two published coefficients from the actual plotted pairs.
 * The API is the source of record, but a malformed/stale/cached payload must
 * not show a convincing number that contradicts the accompanying evidence.
 * No inference, p-value, population weighting or causality is performed. */
function correlationRanks(values){
 const sorted=values.map((value,index)=>({value,index})).sort((a,b)=>a.value-b.value);
 const ranks=Array(values.length);
 for(let i=0;i<sorted.length;){
  let j=i+1;while(j<sorted.length&&sorted[j].value===sorted[i].value)j++;
  const rank=(i+1+j)/2;for(let k=i;k<j;k++)ranks[sorted[k].index]=rank;
  i=j;
 }
 return ranks;
}
function correlationPearson(x,y){
 if(x.length!==y.length||x.length<2)return null;
 const sx=Math.max(...x.map(Math.abs)),sy=Math.max(...y.map(Math.abs));
 if(!Number.isFinite(sx)||!Number.isFinite(sy)||sx===0||sy===0)return null;
 const ax=x.map(v=>v/sx),ay=y.map(v=>v/sy);
 const mx=ax.reduce((a,b)=>a+b,0)/ax.length,my=ay.reduce((a,b)=>a+b,0)/ay.length;
 let cross=0,vx=0,vy=0;
 for(let i=0;i<ax.length;i++){const dx=ax[i]-mx,dy=ay[i]-my;cross+=dx*dy;vx+=dx*dx;vy+=dy*dy;}
 return vx>0&&vy>0?Math.max(-1,Math.min(1,(cross/Math.sqrt(vx))/Math.sqrt(vy))):null;
}
function validateCorrelationPayload(data,query){
 if(!data||data.status!=='ok'||data.x!==query.x||data.y!==query.y||
    String(data.period)!==String(query.period)||!Array.isArray(data.subgroups)||
    data.subgroups.length!==2||data.subgroups[0]!==query.group_x||data.subgroups[1]!==query.group_y)
  throw new Error('La correlación no corresponde a los filtros consultados.');
 const n=reviewedCount(data.n),rows=data.paired_observations;
 if(n===null||n<8||n>GEOS.length||!Array.isArray(rows)||rows.length!==n)
  throw new Error('La correlación no contiene un conjunto territorial verificable.');
 const codes=new Set(),x=[],y=[];
 for(const item of rows){
  if(!item||!GEOS.includes(item.geo_code)||codes.has(item.geo_code)||
     typeof item.x!=='number'||!Number.isFinite(item.x)||
     typeof item.y!=='number'||!Number.isFinite(item.y))
   throw new Error('La correlación contiene pares duplicados o valores inválidos.');
  codes.add(item.geo_code);x.push(item.x);y.push(item.y);
 }
 if(!Array.isArray(data.territories)||data.territories.length!==n||
    new Set(data.territories).size!==n||data.territories.some(geo=>!codes.has(geo)))
  throw new Error('La lista de territorios no coincide con los pares estadísticos.');
 if(!Array.isArray(data.excluded)||data.excluded.length!==GEOS.length-n||
    new Set(data.excluded).size!==data.excluded.length||
    data.excluded.some(geo=>!GEOS.includes(geo)||codes.has(geo)))
  throw new Error('La cobertura declarada contradice los pares disponibles.');
 const coverage=data.coverage;
 if(!coverage||reviewedCount(coverage.matched_territories)!==n||
    reviewedCount(coverage.possible_territories)!==GEOS.length||
    coverage.population_representative!==false||coverage.complete!==(n===GEOS.length))
  throw new Error('La cobertura de la correlación es incoherente.');
 const r=data.pearson_r,rho=data.spearman_rho;
 if(typeof r!=='number'||typeof rho!=='number'||!Number.isFinite(r)||!Number.isFinite(rho)||
    Math.abs(r)>1+1e-12||Math.abs(rho)>1+1e-12)
  throw new Error('Coeficientes de correlación fuera del intervalo válido.');
 const recomputed=correlationPearson(x,y),recomputedRanks=correlationPearson(correlationRanks(x),correlationRanks(y));
 // Precision allowance covers harmless PHP/JS floating-point rounding; never
 // allows a materially different correlation to be published as the same.
 if(recomputed===null||recomputedRanks===null||
    Math.abs(recomputed-r)>0.000002||Math.abs(recomputedRanks-rho)>0.000002)
  throw new Error('Los coeficientes no coinciden con los pares de datos territoriales.');
 return data;
}
function renderCorrelationResult(data){
 const root=clear('corr-result');const card=el('article',{class:'corr-card'});root.append(card);
 if(data.status!=='ok'){
  card.append(el('h3',{},'Comparación no autorizada por los datos'),el('p',{class:'warning'},({
   insufficient_coverage:'Hay menos de ocho territorios con ambas mediciones revisadas.',
   insufficient_metadata:'Faltan fuentes, poblaciones, denominadores o tipos de medición.',
   incompatible_measurements:'Las observaciones no tienen metodología homogénea.',
   incompatible_time_windows:'Las dos variables tienen ventanas temporales distintas (por ejemplo, acumulado semestral frente a año completo).',
   constant_series:'Una variable no varía entre territorios y la correlación no está definida.'
  })[data.status]||'La API ha bloqueado la comparación.'));
  return;
 }
 const n=reviewedCount(data.n),r=measuredValue({value:data.pearson_r}),rho=measuredValue({value:data.spearman_rho});
 if(n===null||n<8||r===null||rho===null){
  card.append(el('p',{class:'error'},'Respuesta estadística inválida. No se muestran coeficientes.'));return;
 }
 const coverage=data.coverage;
 if(coverage && (reviewedCount(coverage.matched_territories)!==n ||
    reviewedCount(coverage.possible_territories)!==GEOS.length ||
    coverage.population_representative!==false)){
  card.append(el('p',{class:'error'},'La API devolvió metadatos de cobertura incoherentes. No se muestra la correlación.'));
  return;
 }
 card.append(el('h3',{},'Asociación descriptiva territorial'));
 const dl=el('dl');for(const [key,value] of [['Territorios emparejados',String(n)],['Pearson r',fmt(r)],['Spearman ρ',fmt(rho)]]){
  dl.append(el('dt',{},key),el('dd',{},value));
 }card.append(dl);
 card.append(el('p',{class:n<GEOS.length?'warning':'hint'},
  n<GEOS.length
   ?`Cobertura parcial: ${n}/${GEOS.length} territorios con ambos indicadores. No extrapolar la asociación a los territorios sin datos.`
   :`Cobertura territorial completa (${n}/${GEOS.length}), pero el coeficiente ecológico no es una estimación representativa de individuos.`));
 const loo=data.leave_one_territory_out;
 if(loo?.status==='ok'&&Number.isFinite(loo.min_r)&&Number.isFinite(loo.max_r)){
  card.append(el('p',{},`Al excluir una comunidad cada vez, r varía entre ${fmt(loo.min_r)} y ${fmt(loo.max_r)}. No es un intervalo de confianza.`));
  if(loo.fragility_warning)card.append(el('p',{class:'warning'},`Resultado sensible a territorios individuales${loo.most_influential_territory&&NAMES[loo.most_influential_territory]?': '+NAMES[loo.most_influential_territory]:''}. Evita interpretaciones concluyentes.`));
 }
 const rankSensitivity=data.spearman_leave_one_out;
 if(rankSensitivity?.status==='ok'&&rankSensitivity.fragility_warning)card.append(el('p',{class:'warning'},'También la correlación por rangos (Spearman) es sensible a la exclusión de un territorio.'));
 if(!renderCorrelationScatter(card,data,n))card.append(el('p',{class:'warning'},'No se puede dibujar una dispersión fiable con los pares recibidos; revisa la respuesta de la API.'));
 if(Array.isArray(data.excluded)&&data.excluded.length)card.append(el('p',{class:'hint'},`${data.excluded.length} territorios excluidos por ausencia o incompatibilidad de datos.`));
 correlationSource(card,'Indicador A',data.sources?.x);correlationSource(card,'Indicador B',data.sources?.y);
 card.append(el('p',{class:'hint'},'No se han estimado causalidad, significación estadística, efectos individuales ni ajuste por dependencia espacial.'));
}
async function runCorrelation(){
 const x=state.indicator,y=$('corr-indicator').value,groupY=$('corr-group').value,period=state.year,rid=++corrResultRid;
 if(!displayedFiltersMatchLoaded()||!x||!y||x===y||!state.group||!groupY||corrGroupsYear!==period||corrGroupsIndicator!==y){
  correlationStatus('Actualiza el mapa y verifica los subgrupos del indicador B antes de calcular.');return;
 }
 corrQuery=null;updateURL();correlationStatus('Calculando asociación con observaciones revisadas…');clear('corr-result');
 try{
  const data=await api('/api/v1/npl/correlation',{x,y,period,group_x:state.group,group_y:groupY});
  if(rid!==corrResultRid||period!==state.year||!displayedFiltersMatchLoaded()||y!==$('corr-indicator').value||groupY!==$('corr-group').value)return;
  if(data.status==='ok')validateCorrelationPayload(data,{x,y,period,group_x:state.group,group_y:groupY});
  renderCorrelationResult(data);corrQuery=data.status==='ok'?{indicator:y,group:groupY,year:period}:null;updateURL();
  correlationStatus(data.status==='ok'?'Asociación calculada con limitaciones metodológicas explícitas.':'La API ha bloqueado una asociación no comparable.');
 }catch(error){if(rid===corrResultRid){note('corr-result',error.message,'error');correlationStatus('No se pudo verificar la asociación.');}}
}
async function init(){const q=new URL(location.href).searchParams;
 const from=Number(q.get('from')),to=Number(q.get('to')),maxTimelineYear=new Date().getFullYear();
 if(q.has('from')&&q.has('to')&&Number.isInteger(from)&&Number.isInteger(to)&&from>=1900&&to<=maxTimelineYear&&from<=to&&to-from<=29){$('from').value=String(from);$('to').value=String(to);}
 const qIndicator=q.get('qindicator'),qGroup=q.get('qgroup'),qGeo=q.get('qgeo');
 if(qIndicator&&qGroup&&qGeo&&ALL_GEOS.includes(qGeo)&&qIndicator.length<=100&&qGroup.length<=200)
  state.quarterSelection={indicator_id:qIndicator,subgroup:qGroup,geo_code:qGeo};
 const selected=q.get('geo');if(NAMES[selected])state.selected=selected;const compare=q.get('compare');if(NAMES[compare]&&compare!==state.selected)state.compare=compare;const maxYear=new Date().getFullYear();for(const field of ['year','from','to'])$(field).max=String(maxYear);const year=Number(q.get('year'));if(Number.isInteger(year)&&year>=1900&&year<=maxYear)state.year=year;$('year').value=state.year;for(const [code,name] of Object.entries(NAMES)){$('compare').append(el('option',{value:code},name));$('territory').append(el('option',{value:code},name));}$('compare').value=state.compare;$('territory').value=state.selected;$('indicator').addEventListener('change',async()=>{state.indicator=$('indicator').value;const requestedIndicator=state.indicator;state.group='';invalidateObservations();setStatus('Cargando cobertura del indicador…');try{await loadIndicatorGroups(false);if(requestedIndicator!==state.indicator)return;await loadObservations();}catch(e){if(requestedIndicator===state.indicator)setStatus(e.message);}});
$('zoom-in').addEventListener('click',()=>setZoom(state.zoom+.3));$('zoom-out').addEventListener('click',()=>setZoom(state.zoom-.3));$('zoom-reset').addEventListener('click',()=>setZoom(1));$('territory').addEventListener('change',()=>selectTerritory($('territory').value));$('group').addEventListener('change',()=>{state.group=$('group').value;chooseYearForGroup(false);loadObservations();});$('year').addEventListener('change',()=>{state.year=Number($('year').value);chooseYearForGroup(true);loadObservations();});$('apply').addEventListener('click',loadObservations);$('order').addEventListener('change',renderRanking);$('compare').addEventListener('change',()=>{state.timelineRequestId++;state.compare=$('compare').value===state.selected?'':$('compare').value;$('compare').value=state.compare;note('timeline','Consulta la evolución para los territorios elegidos.');updateURL();renderComparison();});$('timeline-btn').addEventListener('click',loadTimeline);
 for(const field of ['from','to'])$(field).addEventListener('change',()=>{state.timelineRequestId++;note('timeline','Intervalo modificado. Pulsa Consultar evolución para cargar la nueva serie.');updateURL();});
 $('ask-form').addEventListener('submit',askQuestion);
function findTerritory(){
 const result=resolveTerritorySearch($('search').value);
 if(result.status==='match'){
  selectTerritory(result.code);setStatus('Territorio seleccionado: '+NAMES[result.code]+'.');return;
 }
 const message={too_short:'Escribe al menos tres caracteres para buscar un territorio.',
  ambiguous:'Búsqueda ambigua: '+result.candidates.map(code=>NAMES[code]).join(', ')+'. Especifica el nombre completo o utiliza el selector.',
  unsupported_geography:'Especifica una comunidad autónoma, no una ciudad o provincia. Los datos regionales no describen automáticamente un municipio.',
  not_found:'Territorio no encontrado; utiliza el selector de comunidades y ciudades autónomas.'};
 setStatus(message[result.status]||'Búsqueda no disponible.');
}
$('search').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();findTerritory();}});$('search-btn').addEventListener('click',findTerritory);
$('quarter-search').addEventListener('input',()=>{renderQuarterChoices();note('quarter-results','Selecciona una serie filtrada y pulsa Consultar trimestres.');});$('quarter-load').addEventListener('click',loadQuarterlySeries);$('quarter-select').addEventListener('change',loadQuarterlySeries);loadQuarterlyCatalog();$('export-csv').disabled=true;$('export-csv').addEventListener('click',exportReviewedCSV);$('share').addEventListener('click',async()=>{if(!displayedFiltersMatchLoaded()){setStatus('Actualiza primero el mapa: los filtros visibles no corresponden todavía a datos cargados.');return;}updateURL();try{await navigator.clipboard.writeText(location.href);setStatus('Enlace copiado con indicador, año y territorios.');}catch{setStatus('Copia la URL de la barra de direcciones para compartir esta vista.');}});try{await loadMapSVG();}catch(e){note('map',e.message,'error');}try{const data=await api('/api/v1/npl');const items=Array.isArray(data.items)?data.items:[];initCorrelationCatalog(items);$('catalog-status').textContent=catalogSummary(data);const select=$('indicator');select.replaceChildren();for(const item of items){const id=item.id??item.indicator_id;if(id)select.append(el('option',{value:id},item.name??item.label_es??id));}const requested=q.get('indicator');state.indicator=items.some(i=>(i.id??i.indicator_id)===requested)?requested:select.value;select.value=state.indicator;state.group=q.get('group')||'';if(!items.length){setStatus('No hay indicadores revisados disponibles. El archivo histórico no equivale a datos publicados.');return;}await loadIndicatorGroups(q.has('year'));await loadObservations();
 const qCorrIndicator=q.get('corr_indicator'),qCorrGroup=q.get('corr_group');
 if(qCorrIndicator&&qCorrGroup&&qCorrIndicator!==state.indicator&&qCorrGroup.length<=200&&
    items.some(i=>(i.id??i.indicator_id)===qCorrIndicator)&&displayedFiltersMatchLoaded()){
  $('corr-indicator').value=qCorrIndicator;
  await loadCorrelationGroups();
  if([...$('corr-group').options].some(option=>option.value===qCorrGroup)){
   $('corr-group').value=qCorrGroup;await runCorrelation();
  }
 }
 }catch(e){setStatus('No se pudo cargar el catálogo de datos.');note('ranking',e.message,'error');}

}
window.addEventListener('DOMContentLoaded',init);
