import assert from 'node:assert/strict';
import {GEOS,pearson,spearman,verifyCorrelationPayload} from '../assets/observatorio/correlation-integrity-v73.mjs';

const query={x:'IND_A',y:'IND_B',period:2025,group_x:'Personas',group_y:'Hogares'};
const pairs=GEOS.slice(0,10).map((geo_code,i)=>({geo_code,
  x:[0,1,1,3,4,5,6,7,8,9][i],y:[9,8,8,6,5,4,3,2,1,0][i]}));
const xs=pairs.map(p=>p.x),ys=pairs.map(p=>p.y);
const good={status:'ok',x:query.x,y:query.y,period:query.period,
  subgroups:[query.group_x,query.group_y],n:pairs.length,
  territories:pairs.map(p=>p.geo_code),excluded:GEOS.slice(10),
  coverage:{matched_territories:10,possible_territories:19,complete:false,population_representative:false},
  paired_observations:pairs,pearson_r:pearson(xs,ys),spearman_rho:spearman(xs,ys)};
assert.equal(verifyCorrelationPayload(good,query).pairs,10);
const bad=(change,label)=>{const input=structuredClone(good);change(input);
  assert.throws(()=>verifyCorrelationPayload(input,query),undefined,label);};
bad(x=>x.pearson_r=1.1,'impossible Pearson');
bad(x=>x.spearman_rho=0.3,'fabricated Spearman');
bad(x=>x.paired_observations[0].x=null,'null must not count as zero');
bad(x=>x.paired_observations[0].geo_code=x.paired_observations[1].geo_code,'duplicate region');
bad(x=>x.paired_observations[0].geo_code='ES','national data not regional');
bad(x=>x.y='IND_STALE','stale series');
bad(x=>x.period=2024,'stale period');
bad(x=>x.subgroups[0]='other','stale group');
bad(x=>x.territories[0]='ES64','mismatched codes');
bad(x=>x.excluded[0]=x.territories[0],'exclusion mismatch');
bad(x=>x.n=11,'mismatched count');
bad(x=>x.coverage.complete=true,'false full coverage');
bad(x=>x.coverage.population_representative=true,'not individual population');
assert.equal(pearson(Array(10).fill(3),ys),null,'constant series not correlatable');
const v=Array.from({length:10},(_,i)=>(i+1)*1e307);
assert.ok(Math.abs(pearson(v,v)-1)<1e-10,'stable extreme magnitudes');
assert.ok(spearman([1,1,2,3],[4,4,3,2])<0,'midranks handle ties');
console.log('PASS: V73 correlation payload integrity, 13 hostile mutations, zero, ties, extremes, constant');
