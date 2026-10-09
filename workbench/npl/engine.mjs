/* NPL safe workbench · not connected to production */
const GEO={
  "ES": "España",
  "ES11": "Galicia",
  "ES12": "Asturias",
  "ES13": "Cantabria",
  "ES21": "País Vasco",
  "ES22": "Navarra",
  "ES23": "La Rioja",
  "ES24": "Aragón",
  "ES30": "Madrid",
  "ES41": "Castilla y León",
  "ES42": "Castilla-La Mancha",
  "ES43": "Extremadura",
  "ES51": "Cataluña",
  "ES52": "Comunitat Valenciana",
  "ES53": "Illes Balears",
  "ES61": "Andalucía",
  "ES62": "Murcia",
  "ES63": "Ceuta",
  "ES64": "Melilla",
  "ES70": "Canarias"
};
const REQUIRED=["indicator_id","source_id","source_url","geo_id","period","unit","measure","universe","definition_version","kind"];
const MISSING=["not_collected","suppressed","not_applicable","not_published","unknown"];
function stableStringify(o){if(Array.isArray(o))return "["+o.map(stableStringify).join(",")+"]";if(o&&typeof o==="object")return "{"+Object.keys(o).sort().map(k=>JSON.stringify(k)+":"+stableStringify(o[k])).join(",")+"}";return JSON.stringify(o)}

function observationKey(o){return [o.indicator_id,o.source_id,o.geo_id,o.period,o.subgroup||"total",o.definition_version,o.measure].map(x=>encodeURIComponent(String(x))).join("|")}

function validateObservation(o){const e=[];if(!o||typeof o!=="object"||Array.isArray(o))return ["La observación debe ser objeto"];for(const k of REQUIRED)if(typeof o[k]!=="string"||!o[k].trim())e.push("Campo requerido: "+k);if(!("value" in o))e.push("value debe existir");else if(o.value!==null&&(typeof o.value!=="number"||!Number.isFinite(o.value)))e.push("value no es número finito");if(o.value===null&&!MISSING.includes(o.missing_reason))e.push("Valor nulo requiere missing_reason");if(o.value!==null&&o.missing_reason)e.push("Valor numérico no admite missing_reason");if(o.geo_id&&!Object.hasOwn(GEO,o.geo_id))e.push("Geografía no reconocida");if(o.period&&!/^\d{4}(?:-(?:Q[1-4]|(?:0[1-9]|1[0-2])))?$/.test(o.period))e.push("Periodo inválido");if(o.source_url&&!/^https:\/\/[a-z0-9.-]+(?:\/|$)/i.test(o.source_url))e.push("source_url debe ser HTTPS");if(o.kind&&!["raw","derived"].includes(o.kind))e.push("kind inválido");if(o.kind==="derived"&&(!Array.isArray(o.derived_from)||!o.derived_from.length))e.push("Derivado requiere derived_from");if(o.kind==="raw"&&o.derived_from)e.push("Bruto no debe incluir derived_from");if(o.measure&&!["count","rate","percentage","mean","median","index","total","other"].includes(o.measure))e.push("measure inválido");if(o.subgroup!==undefined&&typeof o.subgroup!=="string")e.push("subgroup inválido");if(o.method_flag!==undefined&&(!Array.isArray(o.method_flag)||o.method_flag.some(x=>typeof x!=="string")))e.push("method_flag inválido");if(o.value!==null&&o.measure==="percentage"&&(o.value<0||o.value>100))e.push("Porcentaje fuera de 0–100");return e}

function prepareBatch(rows,existing={}){if(!Array.isArray(rows))throw Error("Lote no es lista");const seen=new Map(),insert=[],duplicate=[],conflict=[],invalid=[];for(let i=0;i<rows.length;i++){const record=rows[i],errors=validateObservation(record);if(errors.length){invalid.push({index:i,errors});continue}const key=observationKey(record),payload=stableStringify(record),old=seen.get(key)??existing[key];if(old===undefined){seen.set(key,payload);insert.push({key,record})}else if(old===payload)duplicate.push({index:i,key});else conflict.push({index:i,key,reason:"Misma clave con valor o metadatos distintos; requiere revisión"})}return {insert,duplicate,conflict,invalid,canApply:conflict.length===0&&invalid.length===0}}

function assertComparable(a,b){const fields=["indicator_id","unit","measure","universe","definition_version","kind"];const differences=fields.filter(k=>a[k]!==b[k]);if(a.method_flag?.includes("break_in_series")||b.method_flag?.includes("break_in_series"))differences.push("break_in_series");return {comparable:differences.length===0,differences}}

function per100k(num,pop){if(num.geo_id!==pop.geo_id||num.period!==pop.period)throw Error("Geografía y periodo incompatibles");if(pop.value===null||pop.value<=0||pop.unit!=="persons"||pop.measure!=="count"||pop.universe!=="resident_population")throw Error("Denominador poblacional inválido");if(num.value===null||num.measure!=="count")throw Error("Numerador no es recuento");return {value:num.value/pop.value*100000,unit:"eventos por 100.000 residentes",kind:"derived",derived_from:[observationKey(num),observationKey(pop)],method_flag:["event_rate_not_person_risk"],note:"Eventos por población; no equivale a personas afectadas."}}

function rank(rows,mode="desc"){const usable=rows.filter(r=>validateObservation(r).length===0&&r.value!==null);if(!usable.length)return {items:[],missing:rows.length,warning:"Sin datos comparables"};const base=usable[0],items=usable.filter(r=>assertComparable(base,r).comparable&&r.period===base.period);return {items:items.sort((a,b)=>mode==="asc"?a.value-b.value:b.value-a.value),missing:rows.length-items.length,warning:items.length!==rows.length?"Se excluyeron ausencias o definiciones incompatibles":null}}

function series(rows,geo_id){const subset=rows.filter(r=>r.geo_id===geo_id&&validateObservation(r).length===0);if(!subset.length)return {items:[],warning:"No hay serie"};const base=subset[0],items=subset.filter(r=>assertComparable(base,r).comparable).sort((a,b)=>a.period.localeCompare(b.period));return {items,warning:items.length<subset.length?"Hay ruptura o cambio de definición":null}}

function pearsonPairs(a,b){if(a.length!==b.length||a.length<5)throw Error("Se requieren cinco pares completos");const n=a.length,ma=a.reduce((s,x)=>s+x,0)/n,mb=b.reduce((s,x)=>s+x,0)/n;let cov=0,va=0,vb=0;for(let i=0;i<n;i++){if(!Number.isFinite(a[i])||!Number.isFinite(b[i]))throw Error("Par no finito");const x=a[i]-ma,y=b[i]-mb;cov+=x*y;va+=x*x;vb+=y*y}if(va===0||vb===0)throw Error("Sin variabilidad");return {r:cov/Math.sqrt(va*vb),n,warning:"Asociación descriptiva, no causalidad. Revisar confusores y autocorrelación."}}

function encodeState(state){return "?"+["indicator","geo","period","compare","view"].filter(k=>state[k]!==undefined&&state[k]!==null&&state[k]!=="").map(k=>encodeURIComponent(k)+"="+encodeURIComponent(String(state[k]))).join("&")}

function decodeState(query){const out={};for(const pair of query.replace(/^\?/,"").split("&")){if(!pair)continue;const [k,v=""]=pair.split("=");const key=decodeURIComponent(k);if(["indicator","geo","period","compare","view"].includes(key))out[key]=decodeURIComponent(v.replace(/\+/g," "))}return out}
export {GEO,REQUIRED,MISSING,stableStringify,observationKey,validateObservation,prepareBatch,assertComparable,per100k,rank,series,pearsonPairs,encodeState,decodeState};
