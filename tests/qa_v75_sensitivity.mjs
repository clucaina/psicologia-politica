import assert from 'node:assert/strict';
import fs from 'node:fs';import vm from 'node:vm';
const src=fs.readFileSync(new URL('../assets/observatorio/mapa-espana.js',import.meta.url),'utf8');
const ctx={Intl,Date,Number,String,URL,document:{getElementById(){return null}},window:{addEventListener(){}}};
vm.runInNewContext(src+'\nglobalThis.inspectV75={validateCorrelationPayload,correlationPearson,correlationRanks,correlationLeaveOneOut,GEOS};',ctx);
const {validateCorrelationPayload:validate,correlationPearson:pearson,correlationRanks:ranks,correlationLeaveOneOut:loo,GEOS}=ctx.inspectV75;
const query={x:'A',y:'B',period:2025,group_x:'Total',group_y:'Total'};
const pairs=GEOS.slice(0,10).map((geo_code,i)=>({geo_code,x:[0,1,1,3,4,5,6,7,8,9][i],y:[9,8,8,6,5,4,3,2,1,0][i]}));
const x=pairs.map(p=>p.x),y=pairs.map(p=>p.y),codes=pairs.map(p=>p.geo_code);
const payload={status:'ok',x:query.x,y:query.y,period:query.period,subgroups:[query.group_x,query.group_y],n:10,
 territories:codes,excluded:GEOS.slice(10),coverage:{matched_territories:10,possible_territories:19,complete:false,population_representative:false},
 paired_observations:pairs,pearson_r:pearson(x,y),spearman_rho:pearson(ranks(x),ranks(y)),
 leave_one_territory_out:{status:'ok',min_r:9000,max_r:9001,fragility_warning:true},
 spearman_leave_one_out:{status:'ok',most_influential_territory:'FAKE',fragility_warning:true}};
const out=validate(payload,query);
assert.equal(out.leave_one_territory_out.verified_from_paired_observations,true);
assert.equal(out.spearman_leave_one_out.verified_from_paired_observations,true);
assert.ok(Math.abs(out.leave_one_territory_out.min_r)<=1);
assert.ok(GEOS.includes(out.spearman_leave_one_out.most_influential_territory));
assert.ok(Math.abs(out.spearman_leave_one_out.min_r-loo(x,y,codes,true).min_r)<1e-10);
assert.equal(loo(Array(8).fill(1),y.slice(0,8),codes.slice(0,8)).status,'constant_series');
console.log('PASS V75 sensitivity recalculated; fake API warnings rejected; re-ranked Spearman');
