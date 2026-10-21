/* NPL V47 admin-only review console. All state stays in memory; no localStorage,
 * no silent publication, no client-side claims of scientific verification. */
'use strict';
(() => {
  const $ = id => document.getElementById(id);
  const state = {indicator:'', offset:0, total:0, limit:25, rows:[], current:null, busy:false, dirty:false, request:0};
  const CSRF = $('pp-csrf')?.value || '';
  const fieldNames = ['source_url','locator','retrieved_at','measurement_type','population',
    'denominator','frequency','definition_version','measurement_period_start',
    'measurement_period_end','series_key','citation','limits',
    'revision_review_result','revision_reference_url','revision_review_note',
    'source_conflict_resolution','source_conflict_resolution_url'];
  const input = name => $('review-form').elements.namedItem(name);
  const status = (id, text, error=false) => {$(id).textContent=text; $(id).classList.toggle('error',error);};
  const node = (tag, text='', cls='') => {const el=document.createElement(tag);el.textContent=String(text);if(cls)el.className=cls;return el;};
  // A submitted decision belongs to the selected immutable observation/revision.
  // Never permit switching documents while the server is deciding its outcome.
  function canSwitch(){
    if(state.busy){status('list-status','Espera a que termine el guardado antes de cambiar de observación.',true);return false;}
    return !state.dirty || window.confirm('Hay cambios sin guardar. ¿Descartarlos para cambiar de registro?');
  }
  function lockEditor(locked){
    for(const id of ['indicator','review-filter','load'])$(id).disabled=locked;
    $('previous').disabled=locked||state.offset===0;
    $('next').disabled=locked||state.offset+state.limit>=state.total;
    $('review-form').querySelectorAll('input,textarea,select,button').forEach(el=>{el.disabled=locked;});
    $('records').setAttribute('aria-busy',String(locked));
    $('review-form').setAttribute('aria-busy',String(locked));
  }
  async function getJSON(params) {
    const url=new URL('/api/v1/npl',document.baseURI);
    for(const [key,value] of Object.entries(params))if(value!==''&&value!==null)url.searchParams.set(key,String(value));
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
    let data;try{data=await response.json();}catch{throw new Error('La API no ha devuelto JSON válido.');}
    if(!response.ok)throw new Error(data.error||`HTTP ${response.status}`);
    return data;
  }
  async function loadCatalog(){
    try{
      const data=await getJSON({editorial:1});
      const select=$('indicator');select.replaceChildren(node('option','Selecciona un indicador'));
      select.firstChild.value='';
      for(const item of data.items||[]){const opt=node('option',`${item.name||item.label_es||item.id} (${item.observation_count??0})`);opt.value=item.id;select.append(opt);}
      status('list-status',`${(data.items||[]).length} indicadores disponibles para revisión; no se han cargado observaciones.`);
    }catch(error){status('list-status',`No se pudo cargar el catálogo: ${error.message}`,true);}
  }
  async function loadRecords(offset=0,force=false){
    if(!force&&!canSwitch())return false;
    const indicator=$('indicator').value;
    if(!indicator){status('list-status','Selecciona un indicador.');return false;}
    const request=++state.request;
    state.indicator=indicator;state.offset=offset;state.current=null;state.dirty=false;
    $('review-section').hidden=true;$('records').replaceChildren();
    status('list-status','Consultando observaciones…');
    try{
      const data=await getJSON({editorial:1,indicator,offset,limit:state.limit,review:$('review-filter').value});
      if(request!==state.request)return;
      state.rows=Array.isArray(data.rows)?data.rows:[];state.total=Number(data.total)||0;
      $('paging').textContent=state.total?`${offset+1}–${Math.min(offset+state.limit,state.total)} de ${state.total}`:'0 resultados';
      $('previous').disabled=offset===0;$('next').disabled=offset+state.limit>=state.total;
      for(const row of state.rows){
        const button=node('button','', 'record');button.type='button';button.dataset.observationId=String(row.observation_id);
        button.setAttribute('aria-pressed','false');
        const title=node('strong',`${row.period||'?'} · ${row.geography||row.geo_code||'?'} · ${row.subgroup||'Subgrupo no documentado'}`);
        const detail=node('small',`ID ${row.observation_id} · ${row.value===null||row.value===undefined?'Sin valor':row.value} ${row.unit||''} · ${row.review?.status||'review'}`);
        button.append(title,detail);button.addEventListener('click',()=>selectRecord(row,button));$('records').append(button);
      }
      status('list-status',state.rows.length?'Selecciona una observación para revisar su evidencia.':'No hay observaciones con esos filtros.');
      return true;
    }catch(error){if(request===state.request)status('list-status',`No se pudo consultar: ${error.message}`,true);return false;}
  }
  function isECV2025(row){
    if(String(row.source_id||'').trim().toUpperCase()!=='INE'||String(row.period)!=='2025'||
       !String(row.dataset_code||'').trim().toUpperCase().startsWith('ECV2025'))return false;
    try{
      const url=new URL(row.source_url);
      return url.protocol==='https:'&&['ine.es','www.ine.es'].includes(url.hostname.toLowerCase())&&
        !url.username&&!url.password;
    }catch{return false;}
  }
  function conflictPresent(row){return !['','false','0','null'].includes(String(row.source_conflict_flag??'').trim().toLowerCase());}
  function selectRecord(row,button){
    if(!canSwitch())return;
    state.current=row;state.dirty=false;
    document.querySelectorAll('.record').forEach(el=>el.setAttribute('aria-pressed',String(el===button)));
    const summary=$('selected');summary.replaceChildren(
      node('strong',`ID ${row.observation_id} · ${row.indicator_id} · ${row.period} · ${row.geography||row.geo_code}`),
      node('p',`${row.value===null||row.value===undefined?'Sin valor':row.value} ${row.unit||''} · ${row.subgroup||'Población no documentada'} · ${row.source_id||'Fuente desconocida'}`),
      node('p',`Revisión actual: ${row.review?.status||'review'} · revisión del documento: ${row._revision??0}`)
    );
    const evidence=row.review?.evidence||{};
    for(const name of fieldNames){const field=input(name);if(!field)continue;const raw=evidence[name]??row[name]??'';field.value=typeof raw==='string'?raw:String(raw??'');}
    input('reason').value='';input('source_verification_confirmed').checked=false;input('source_revision_checked').checked=false;
    const isModule=String(row.dataset_code||'').toUpperCase().includes('MODULE');
    $('ecv-revision').hidden=!isECV2025(row);
    $('ecv-scope-warning').hidden=!isModule;
    const scopeNotAffected=input('revision_review_result').querySelector('option[value="notice_scope_checked_not_affected"]');
    if(scopeNotAffected){scopeNotAffected.disabled=!isModule;if(!isModule&&input('revision_review_result').value==='notice_scope_checked_not_affected')input('revision_review_result').value='';}
    $('conflict-resolution').hidden=!conflictPresent(row);
    $('review-section').hidden=false;
    status('review-status','Revisión cargada. Ningún cambio ha sido enviado.');
    $('review-heading').focus?.();
  }
  function readPayload(action){
    const form=$('review-form');const data=Object.fromEntries(new FormData(form));
    for(const name of fieldNames)if(!Object.hasOwn(data,name))data[name]='';
    return {...data,entity:'observation',action:'review',status:action,
      id:String(state.current.observation_id),expected_revision:Number(state.current._revision??0),
      source_verification_confirmed:input('source_verification_confirmed').checked,
      source_revision_checked:input('source_revision_checked').checked};
  }
  function publicationPreflight(payload){
    if(!payload.source_verification_confirmed)throw new Error('Debes verificar personalmente la fuente primaria.');
    for(const key of ['source_url','locator','retrieved_at','measurement_type','population','denominator','frequency','definition_version','series_key','citation','limits']){
      // The backend decides whether denominator/definition are mandatory for this observation.
      if(key==='denominator'||key==='definition_version')continue;
      if(!String(payload[key]||'').trim())throw new Error(`Falta documentar: ${key}.`);
    }
    const unit=String(state.current?.unit||'').toLowerCase();
    const measure=String(payload.measurement_type||'').toLowerCase();
    const rateUnit= /[%‰]|\b(?:por|per)\s+(?:cada\s+)?(?:[0-9][0-9.,]*|mil|mill[oó]n(?:es)?)\b|\/\s*[0-9][0-9.,]*/i.test(unit);
    if((rateUnit||['rate','ratio','prevalence','percentage'].some(x=>measure.includes(x)))&&!payload.denominator.trim())
      throw new Error('Esta tasa o porcentaje necesita un denominador documentado.');
    if(Boolean(payload.measurement_period_start)!==Boolean(payload.measurement_period_end))
      throw new Error('El intervalo medido necesita ambas fechas.');
    if($('ecv-revision').hidden===false&&(!payload.source_revision_checked||!payload.revision_review_result||!payload.revision_reference_url||payload.revision_review_note.trim().length<60))
      throw new Error('Falta cotejar y justificar la revisión ECV 2025 con fuente oficial.');
    if($('conflict-resolution').hidden===false&&(!payload.source_conflict_resolution_url||payload.source_conflict_resolution.trim().length<60))
      throw new Error('Debes conciliar documentalmente la discrepancia entre fuentes.');
  }
  async function submit(action){
    if(state.busy||!state.current)return;
    const form=$('review-form');
    if(!form.reportValidity())return;
    const payload=readPayload(action);
    if(payload.reason.trim().length<10){status('review-status','Justifica la decisión con al menos 10 caracteres.',true);input('reason').focus();return;}
    try{
      if(action==='published')publicationPreflight(payload);
      if(action==='published'&&!window.confirm('¿Has contrastado esta cifra con la fuente primaria y deseas PUBLICAR esta observación?'))return;
      if(action==='rejected'&&!window.confirm('¿Rechazar esta observación y conservar la justificación en el historial?'))return;
    }catch(error){status('review-status',error.message,true);return;}
    state.busy=true;lockEditor(true);
    status('review-status',`Enviando decisión «${action}»…`);
    try{
      const response=await fetch('/api/v1/npl',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(payload)});
      let result;try{result=await response.json();}catch{throw new Error('La API devolvió una respuesta no JSON.');}
      if(!response.ok){if(response.status===409)throw new Error(`Conflicto de revisión (409): ${result.error||'recarga el registro antes de reintentar'}`);throw new Error(result.error||`HTTP ${response.status}`);}
      // POST is committed according to the API. A subsequent GET failure must
      // NEVER be reported as a failed POST: the editor might repeat a decision.
      state.dirty=false;
      status('review-status',`Decisión «${action}» confirmada por el servidor. Actualizando la lista…`);
      const refreshed=await loadRecords(state.offset,true);
      if(refreshed){
        status('list-status',`Decisión «${action}» confirmada. Selecciona el registro actualizado para continuar.`);
      }else{
        status('list-status',`Decisión «${action}» CONFIRMADA, pero la lista no se ha podido actualizar. Recarga antes de otra decisión.`,true);
      }
    }catch(error){
      // Network failure can occur AFTER a server commit; never invite a blind retry.
      status('review-status',`No se conoce el resultado del envío: ${error.message}. Consulta el registro actualizado antes de repetir la acción.`,true);
    }finally{state.busy=false;lockEditor(false);}
  }
  $('load').addEventListener('click',()=>loadRecords(0));
  $('previous').addEventListener('click',()=>loadRecords(Math.max(0,state.offset-state.limit)));
  $('next').addEventListener('click',()=>loadRecords(state.offset+state.limit));
  $('review-form').addEventListener('input',()=>{state.dirty=true;});
  $('review-form').addEventListener('change',()=>{state.dirty=true;});
  $('review-form').addEventListener('submit',event=>{event.preventDefault();submit('review');});
  $('reject-review').addEventListener('click',()=>submit('rejected'));
  $('publish-review').addEventListener('click',()=>submit('published'));
  window.addEventListener('beforeunload',event=>{if(state.dirty||state.busy){event.preventDefault();event.returnValue='';}});
  loadCatalog();
})();
