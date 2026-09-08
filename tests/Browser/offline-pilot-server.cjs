'use strict';
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const root=process.env.ALIGNEX_OFFLINE_SERVER_ROOT;
if(!root) throw new Error('Explicit offline source root required.');
const requireOffline=require('node:module').createRequire(path.join(root,'package.json'));
const express=requireOffline('express'),Database=requireOffline('better-sqlite3');
const {installPilot}=require(path.join(root,'dist/server/adaptive-pilot.cjs'));
const engine=require(path.join(root,'dist/server/adaptive-kernel.cjs'));
const db=new Database(':memory:');
db.exec("CREATE TABLE local_users(id TEXT PRIMARY KEY,name TEXT,email TEXT,password_hash TEXT,password_salt TEXT,role TEXT,status TEXT)");
db.prepare('INSERT INTO local_users VALUES(?,?,?,?,?,?,?)').run('admin','Admin','admin@test.local','','','admin','active');
const now=Date.now(), secret='browser-test-only-secret'.repeat(3);
const config={version:engine.VERSION,starts_at:now-1000,closes_at:now+3600000,duration_ms:600000,start_difficulty:'medium',max_levels:2,penalty_bps:1000,min_budget:1,mastery_bps:7000,min_evidence:1,cooldown_ms:0,
    areas:[{key:'area',count:3,budget:600,topics:[]}],items:Array.from({length:12},(_,i)=>({id:'item-'+i,area:'area',difficulty:['easy','medium','hard'][i%3],topic:null,type:'single_choice',stem:'Pilot question '+i,
    options:[{id:'right',label:'A',text:'Correct fixture choice',correct:true},{id:'wrong',label:'B',text:'Incorrect fixture choice',correct:false}]}))};
const payload={contract:'alignex.diagnostic-offline.v1',id:'browser-package',title:'Browser pilot exam',device_id:'browser-center',config,
    candidates:[{id:'browser-candidate',lease_id:'browser-lease',registration:'BROWSER1',name:'Browser Candidate',access_code:'BROWSER-CODE'}]};
const pilot=installPilot({connection:db,getConfig:()=>({sync_token:secret,device_id:'browser-center',portal_url:'http://127.0.0.1'}),
    authenticateAdmin:(_email,password)=>{if(password!=='test-password') throw new Error('Invalid password');}});
const body=Buffer.from(JSON.stringify(payload)).toString('base64');
pilot.importEnvelope({contract:payload.contract,body,mac:crypto.createHmac('sha256',secret).update(body).digest('hex')});
db.prepare('UPDATE adaptive_pilot_packages SET paused=0').run();
const app=express();app.use(express.json());app.use('/api/adaptive-pilot',pilot.router);app.use(express.static(path.join(root,'dist/renderer')));
const http=app.listen(0,'127.0.0.1',()=>process.stdout.write(JSON.stringify({port:http.address().port})+'\n'));
process.on('SIGTERM',()=>http.close(()=>{db.close();process.exit(0);}));
