'use strict';
const {spawn}=require('node:child_process'),path=require('node:path'),{createRequire}=require('node:module');
const {chromium}=require('@playwright/test');
const root=process.env.ALIGNEX_OFFLINE_SERVER_ROOT || path.resolve(__dirname,'../../../offline-server');
const offlineRequire=createRequire(path.join(root,'package.json'));
const child=spawn(offlineRequire('electron'),[path.join(__dirname,'offline-pilot-server.cjs')],{
    env:{...process.env,ELECTRON_RUN_AS_NODE:'1',ALIGNEX_OFFLINE_SERVER_ROOT:root},windowsHide:true,stdio:['ignore','pipe','pipe']});
(async()=>{
 let browser;
 try {
    const port=await new Promise((resolve,reject)=>{
        const timeout=setTimeout(()=>reject(new Error('Pilot test server startup timed out')),15000);
        child.once('error',reject);child.stderr.on('data',b=>process.stderr.write(b));
        child.stdout.once('data',b=>{clearTimeout(timeout);try{resolve(JSON.parse(b.toString().trim()).port);}catch(e){reject(e);}});
    });
    browser=await chromium.launch({channel:'msedge',headless:true});
    const context=await browser.newContext();const page=await context.newPage();page.on('dialog',d=>d.accept());page.on('pageerror',e=>console.error('BROWSER:',e.message));
    await page.goto('http://127.0.0.1:'+port+'/adaptive.html');
    await page.getByRole('combobox').selectOption('browser-package');
    await page.getByLabel('Registration number',{exact:true}).fill('BROWSER1');
    await page.getByLabel('Pilot access code').fill('BROWSER-CODE');
    await page.getByRole('button',{name:'Continue',exact:true}).click();
    await page.getByRole('button',{name:'Start level',exact:true}).click();
    await page.getByRole('radio',{name:'B. Incorrect fixture choice'}).check();
    await page.reload();
    await page.getByRole('radio',{name:'B. Incorrect fixture choice'}).waitFor();
    if(!await page.getByRole('radio',{name:'B. Incorrect fixture choice'}).isChecked())throw new Error('Local draft did not survive reload.');
    // Simulate a lost command response after the server durably committed it.
    let dropped=false;
    await page.route('**/api/adaptive-pilot/command',async route=>{
        if(!dropped&&route.request().postDataJSON().op==='commit'){dropped=true;await route.fetch();await route.abort('failed');}
        else await route.continue();
    });
    await page.getByRole('button',{name:'Confirm and continue'}).click();
    await page.getByRole('button',{name:'Retry request',exact:true}).waitFor();
    await page.getByRole('button',{name:'Retry request',exact:true}).click();
    await page.getByRole('radio',{name:'B. Incorrect fixture choice'}).waitFor();
    for(let i=0;i<2;i++){await page.getByRole('radio',{name:'B. Incorrect fixture choice'}).check();await page.getByRole('button',{name:'Confirm and continue'}).click();await page.getByText('Working...', {exact:true}).waitFor({state:'hidden'});}
    await page.getByRole('button',{name:'Start recovery level'}).click();
    for(let i=0;i<3;i++){await page.getByRole('radio',{name:'A. Correct fixture choice'}).check();await page.getByRole('button',{name:'Confirm and continue'}).click();await page.getByText('Working...', {exact:true}).waitFor({state:'hidden'});}
    await page.getByText('The progression is complete', {exact:false}).waitFor();
    console.log('PASS: browser pilot login, durable draft, lost-response retry, weakness recovery and completion.');
 } finally {if(browser)await browser.close();child.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
