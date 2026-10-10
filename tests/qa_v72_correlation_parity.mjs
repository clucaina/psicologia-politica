import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const src=fs.readFileSync(new URL('../assets/observatorio/mapa-espana.js',import.meta.url),'utf8');
const ctx={Intl,Date,Number,String,URL,document:{getElementById(){return null}},window:{addEventListener(){}}};
vm.runInNewContext(src+'\nglobalThis.v72={validateCorrelationPayload,correlationPearson,correlationRanks,GEOS};',ctx);
const {validateCorrelationPayload:validate,correlationPearson:pearson,correlationRanks:ranks,GEOS}=ctx.v72;
const query={x:'INDICADOR_A',y:'INDICADOR_B',period:2025,group_x:'Personas',group_y:'Hogares'};
const clone=o=>JSON.parse(JSON.stringify(o));
const pairs=GEOS.slice(0,10).map((geo_code,i)=>({geo_code,x:[0,1,1,3,4,5,6,7,8,9][i],y:[9,8,8,6,5,4,3,2,1,0][i]}));
const sample={status:'ok',x:query.x,y:query.y,period:query.period,subgroups:[query.group_x,query.group_y],
 n:pairs.length,territories:pairs.map(p=>p.geo_code),excluded:GEOS.slice(10),
 coverage:{matched_territories:10,possible_territories:19,complete:false,population_representative:false},
 paired_observations:pairs,
 pearson_r:pearson(pairs.map(p=>p.x),pairs.map(p=>p.y)),
 spearman_rho:pearson(ranks(pairs.map(p=>p.x)),ranks(pairs.map(p=>p.y)))};
assert.equal(validate(sample,query),sample,'valid 10-region correlation with ties and measured zero');
const bad=(mutate,reason)=>{const s=clone(sample);mutate(s);assert.throws(()=>validate(s,query),undefined,reason);};
bad(s=>s.pearson_r=1.1,'impossible Pearson');
bad(s=>s.spearman_rho=-1.1,'impossible Spearman');
bad(s=>s.pearson_r=0.25,'fabricated Pearson');
bad(s=>s.spearman_rho=0.25,'fabricated Spearman');
bad(s=>s.paired_observations[0].y=100,'pair drift');
bad(s=>s.paired_observations[0].geo_code=s.paired_observations[1].geo_code,'duplicate geo');
bad(s=>s.paired_observations[0].geo_code='ES','national unit mixed with CCAA');
bad(s=>s.paired_observations[0].x=null,'missing not zero');
bad(s=>s.n=11,'misreported count');
bad(s=>s.coverage.complete=true,'false complete coverage');
bad(s=>s.coverage.population_representative=true,'false population representativeness');
bad(s=>s.territories[0]='ES64','mismatched territory list');
bad(s=>s.excluded[0]=s.territories[0],'excluded pair contradiction');
bad(s=>s.period=2024,'stale period');
bad(s=>s.subgroups[1]='Otro subgrupo','stale subgroup');
bad(s=>s.y='OTRO_INDICADOR','stale indicator');
const nearMax=[1e308,1e308*.9,1e308*.8,1e308*.7,1e308*.6,1e308*.5,1e308*.4,1e308*.3];
assert.ok(Math.abs(pearson(nearMax,nearMax)-1)<1e-10,'stable large values');
assert.equal(pearson(Array(8).fill(0),[1,2,3,4,5,6,7,8]),null,'constant axis must be blocked');
console.log('V72 CORRELATION PARITY PASS: 16 hostile payloads, ties, zero, extreme magnitude and constant axis.');
