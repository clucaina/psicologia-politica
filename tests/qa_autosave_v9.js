/* Regression of per-article autosave recovery without importing React bundle. */
const fs=require('node:fs');const vm=require('node:vm');const assert=require('node:assert/strict');
const source=fs.readFileSync('assets/chunk-KECI7H7X.js','utf8');
const start=source.indexOf('let ppRecoverPost=');const end=source.indexOf(';(0,n.useEffect)',start);
assert(start>0&&end>start,'Missing recovery helper in author editor');
const helper=source.slice(start,end+1);
function test({savedAt,localId,confirm=true}){
 let state={},askCount=0;
 const post={id:41,updated_at:'2026-10-07T15:00:00.000Z',content:'SERVER'};
 const local={savedAt,draft:{id:localId,updated_at:'2026-10-07T14:00:00.000Z',content:'LOCAL'}};
 const sandbox={post,Date,h:p=>'pp-autosave:'+p.id,
  localStorage:{getItem:key=>key==='pp-autosave:41'?JSON.stringify(local):null},
  window:{confirm:()=>{askCount++;return confirm}},
  m:v=>state.post=v,b:v=>state.dirty=v,_:v=>state.savedAt=v,l:v=>state.message=v};
 vm.runInNewContext(helper+'\nppRecoverPost(post);',sandbox);
 return {...state,askCount};
}
let x=test({savedAt:'2026-10-07T16:00:00.000Z',localId:41});
assert.equal(x.post.content,'LOCAL');assert.equal(x.post.updated_at,'2026-10-07T15:00:00.000Z');assert.equal(x.dirty,true);assert.equal(x.askCount,1);
x=test({savedAt:'2026-10-07T13:00:00.000Z',localId:41});assert.equal(x.post.content,'SERVER');assert.equal(x.dirty,false);assert.equal(x.askCount,0);
x=test({savedAt:'2026-10-07T16:00:00.000Z',localId:42});assert.equal(x.post.content,'SERVER');assert.equal(x.askCount,0);
x=test({savedAt:'2026-10-07T16:00:00.000Z',localId:41,confirm:false});assert.equal(x.post.content,'SERVER');assert.equal(x.askCount,1);
console.log('V9 autosave recovery PASS: newer local copy is offered, stale/wrong-ID copy is ignored, server revision is preserved.');
