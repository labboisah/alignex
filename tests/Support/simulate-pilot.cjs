'use strict';
const e=require('../../services/adaptive-pilot/kernel.cjs');
let input='';process.stdin.on('data',b=>input+=b);
process.stdin.on('end',()=>{
 const {config,candidate,at}=JSON.parse(input);let state=e.initial(config,candidate),commands=[],time=at;
 const send=(op,data={})=>{const c={key:'simulation-'+commands.length,op,at:++time,...data};state=e.transition(config,state,c);commands.push(c);};
 send('start');
 while(state.status!=='closed'){
  if(state.status==='between_levels')send('next-level');
  else {const item=config.items.find(i=>i.id===state.current.id);const option=item.options.find(o=>o.correct===(state.levels.length>1));send('commit',{item_id:item.id,option_ids:[option.id],state_version:state.state_version});}
 }
 process.stdout.write(JSON.stringify({commands,state_hash:e.digest(state),result:e.summary(state)}));
});
