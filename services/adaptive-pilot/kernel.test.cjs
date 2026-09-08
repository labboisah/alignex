'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),e=require('./kernel.cjs');
function config() {
    return {version:e.VERSION,starts_at:1000,closes_at:10000000,duration_ms:60000,start_difficulty:'medium',max_levels:3,
        penalty_bps:1000,min_budget:1,mastery_bps:7000,min_evidence:1,cooldown_ms:0,
        areas:[{key:'a',count:3,budget:10001,topics:[]}],
        items:Array.from({length:15},(_,i)=>({id:'i'+i,area:'a',difficulty:['easy','medium','hard'][i%3],topic:null,type:'single_choice',stem:'Question '+i,
            options:[{id:'right',label:'A',text:'Yes',correct:true},{id:'wrong',label:'B',text:'No',correct:false}]}))};
}
function run(c,s,op,fields={}) {return e.transition(c,s,{key:'command-'+s.state_version,op,at:s.last_at+1,...fields});}
test('exact ledger, fresh questions, recovery penalty, replay and candidate secrecy',()=>{
    const c=config();let s=e.initial(c,'candidate');s=run(c,s,'start');
    const first=[];
    while(s.status==='active') {first.push(s.current.id);s=run(c,s,'commit',{item_id:s.current.id,option_ids:['wrong'],state_version:s.state_version});}
    assert.equal(s.status,'between_levels');
    s=run(c,s,'next-level');assert.equal(s.balances[0].penalty,1000);
    while(s.status==='active') {assert(!first.includes(s.current.id));s=run(c,s,'commit',{item_id:s.current.id,option_ids:['right'],state_version:s.state_version});}
    assert.equal(s.status,'closed');assert.equal(s.balances[0].earned,9001);assert.equal(s.balances[0].remaining,0);
    const view=JSON.stringify(e.view(c,s,s.last_at));assert(!view.includes('correct'));assert(!view.includes('earned'));assert(!view.includes('balances'));
    assert.equal(e.summary(s).consequential_approved,false);
});
test('idempotent committed retries reject changed intent and stale commands',()=>{
    const c=config();let s=run(c,e.initial(c,'c'),'start');
    const command={key:'answer-123',op:'commit',at:s.last_at+1,item_id:s.current.id,option_ids:['right'],state_version:s.state_version};
    const next=e.transition(c,s,command);
    assert.deepEqual(e.transition(c,next,{...command,at:command.at+10}),next);
    assert.throws(()=>e.transition(c,next,{...command,option_ids:['wrong']}),/reused/);
    assert.throws(()=>e.transition(c,next,{...command,key:'different-key'}),/Stale/);
});
test('deadline authority, clock rollback and recovery failure preserve balances',()=>{
    const c=config();let s=run(c,e.initial(c,'c'),'start');
    assert.throws(()=>e.transition(c,s,{key:'backwards',op:'tick',at:0}),/clock/);
    s=e.transition(c,s,{key:'expired-1',op:'commit',at:s.levels[0].due_at+1,item_id:s.current.id,option_ids:['right'],state_version:s.state_version});
    assert.equal(s.balances[0].earned,0);assert.equal(s.levels[0].reason,'timeout');
    const small={...c,items:c.items.filter(i=>s.used.includes(i.id))};
    const before=JSON.stringify(s);assert.throws(()=>run(small,s,'next-level'),/fresh/);assert.equal(JSON.stringify(s),before);
});
test('zero remaining budget, supervisor end, disqualification and topic coverage',()=>{
    const c=config();c.areas[0].topics=['required'];c.items[0].topic='required';
    let s=run(c,e.initial(c,'c'),'start');assert.equal(s.current.id,'i0');
    s=run(c,s,'disqualified');assert.equal(s.status,'closed');assert.equal(s.closed_reason,'disqualified');
    assert.equal(s.balances[0].closed,10001);assert.throws(()=>run(c,s,'next-level'));
});
module.exports={config};

test('completed transcript rejects late events and preserves accepted retry identity',()=>{
    const c=config(); let s=run(c,e.initial(c,'c'),'start');
    const end={key:'complete-123',op:'supervisor_end',at:s.last_at+1};
    s=e.transition(c,s,end); const hash=e.digest(s);
    for(const op of ['event','submit','supervisor_end','disqualified']) {
        assert.throws(()=>e.transition(c,s,{key:'late-command',op,at:s.last_at+1,event_name:'focus'}),/immutable/);
        assert.equal(e.digest(s),hash);
    }
    assert.deepEqual(e.transition(c,s,{...end,at:s.last_at+100}),s);
});
