/** V73 isolated Conflictómetro data-integrity engine.
 * No request is published or treated as causal evidence.
 * Integrate this validated module into the future V72 map build after staging.
 */
export const GEOS = Object.freeze(['ES11','ES12','ES13','ES21','ES22','ES23','ES24','ES30','ES41','ES42','ES43','ES51','ES52','ES53','ES61','ES62','ES70','ES63','ES64']);
export function averageRanks(values){
  const sorted=values.map((value,index)=>({value,index})).sort((a,b)=>a.value-b.value);
  const ranks=new Array(values.length);
  for(let i=0;i<sorted.length;){
    let j=i+1;
    while(j<sorted.length && sorted[j].value===sorted[i].value)j++;
    const rank=(i+1+j)/2;
    for(let k=i;k<j;k++)ranks[sorted[k].index]=rank;
    i=j;
  }
  return ranks;
}
export function pearson(x,y){
  if(!Array.isArray(x)||!Array.isArray(y)||x.length!==y.length||x.length<2||
     !x.every(v=>typeof v==='number'&&Number.isFinite(v))||
     !y.every(v=>typeof v==='number'&&Number.isFinite(v)))return null;
  const sx=Math.max(...x.map(Math.abs)),sy=Math.max(...y.map(Math.abs));
  if(!sx||!sy)return null;
  const a=x.map(v=>v/sx),b=y.map(v=>v/sy);
  const mx=a.reduce((p,q)=>p+q,0)/a.length,my=b.reduce((p,q)=>p+q,0)/b.length;
  let xy=0,xx=0,yy=0;
  for(let i=0;i<a.length;i++){
    const dx=a[i]-mx,dy=b[i]-my;
    xy+=dx*dy;xx+=dx*dx;yy+=dy*dy;
  }
  return xx>0&&yy>0?Math.max(-1,Math.min(1,(xy/Math.sqrt(xx))/Math.sqrt(yy))):null;
}
export function spearman(x,y){
  return pearson(averageRanks(x),averageRanks(y));
}
function isCount(n){return Number.isInteger(n)&&n>=0;}
export function verifyCorrelationPayload(data,query){
  if(!data||data.status!=='ok'||typeof query!=='object'||
     data.x!==query.x||data.y!==query.y||String(data.period)!==String(query.period)||
     !Array.isArray(data.subgroups)||data.subgroups.length!==2||
     data.subgroups[0]!==query.group_x||data.subgroups[1]!==query.group_y)
    throw Error('Respuesta no corresponde a los filtros seleccionados');
  const rows=data.paired_observations,n=data.n;
  if(!isCount(n)||n<8||n>GEOS.length||!Array.isArray(rows)||rows.length!==n)
    throw Error('Cobertura territorial insuficiente o incoherente');
  const codes=new Set(),x=[],y=[];
  for(const row of rows){
    if(!row||!GEOS.includes(row.geo_code)||codes.has(row.geo_code)||
       typeof row.x!=='number'||!Number.isFinite(row.x)||
       typeof row.y!=='number'||!Number.isFinite(row.y))
      throw Error('Observaciones duplicadas, inválidas o ausentes');
    codes.add(row.geo_code);x.push(row.x);y.push(row.y);
  }
  if(!Array.isArray(data.territories)||data.territories.length!==n||
     data.territories.some(z=>!codes.has(z))||new Set(data.territories).size!==n)
    throw Error('Lista de territorios incompatible');
  if(!Array.isArray(data.excluded)||data.excluded.length!==GEOS.length-n||
     data.excluded.some(z=>!GEOS.includes(z)||codes.has(z))||
     new Set(data.excluded).size!==data.excluded.length)
    throw Error('Territorios excluidos incoherentes');
  const c=data.coverage;
  if(!c||c.matched_territories!==n||c.possible_territories!==GEOS.length||
     c.population_representative!==false||c.complete!==(n===GEOS.length))
    throw Error('Cobertura no verificable');
  const r=pearson(x,y),rho=spearman(x,y);
  if(r===null||rho===null||
     typeof data.pearson_r!=='number'||!Number.isFinite(data.pearson_r)||
     typeof data.spearman_rho!=='number'||!Number.isFinite(data.spearman_rho)||
     Math.abs(data.pearson_r)>1+1e-12||Math.abs(data.spearman_rho)>1+1e-12||
     Math.abs(r-data.pearson_r)>0.000002||Math.abs(rho-data.spearman_rho)>0.000002)
    throw Error('Coeficientes no reproducibles desde las observaciones');
  return Object.freeze({pairs:n,pearson:r,spearman:rho,complete:n===GEOS.length,
    interpretation:'Asociación descriptiva territorial, no causalidad ni representatividad individual'});
}
