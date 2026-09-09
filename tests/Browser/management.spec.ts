import { test, expect, type Page } from '@playwright/test';

async function admin(page: Page, request: any, owner: string) {
    const response = await request.post('/__browser/fixture', {data:{owner,management:true}});
    expect(response.ok()).toBeTruthy();
    const data=await response.json();
    await page.goto('/login');
    await page.getByLabel('Email',{exact:true}).fill(data.actor_email);
    await page.getByLabel('Password',{exact:true}).fill('password');
    await page.getByRole('button',{name:'Log in',exact:true}).click();
    await page.waitForURL(url=>!url.pathname.startsWith('/login'));
    return data;
}
test.beforeEach(async({page})=>{page.on('dialog',dialog=>dialog.accept());});

test('institution create and complete edit forms work',async({page,request})=>{
    await admin(page,request,'institution');
    await page.goto('/institutions/create');
    const form=page.getByRole('form',{name:'New Institution',exact:true});
    await form.getByLabel('Name',{exact:true}).fill('Browser New Institute');
    await form.getByLabel('Institution type',{exact:true}).fill('College');
    await form.getByLabel('Email',{exact:true}).fill('new-'+Date.now()+'@example.test');
    await form.getByRole('button',{name:'Save changes',exact:true}).click();
    await page.waitForURL(/\/institutions\/\d+$/);
    await page.getByRole('link',{name:'Edit',exact:true}).click();
    const edit=page.getByRole('form',{name:'Edit Institution',exact:true});
    await edit.getByLabel('Name',{exact:true}).fill('Updated Institute');
    await edit.getByLabel('Address',{exact:true}).fill('Updated address');
    await edit.getByRole('button',{name:'Save changes',exact:true}).click();
    await expect(page.getByText('Updated Institute',{exact:true}).first()).toBeVisible();
    await page.getByRole('button',{name:'Deactivate',exact:true}).click();
    await expect(page.getByRole('button',{name:'Reactivate',exact:true})).toBeVisible({timeout:30000});
});

test('professional structure edits and dependency-safe delete work',async({page,request})=>{
    const data=await admin(page,request,'professional_school');
    const base='/professional-schools/'+data.owner_id;
    await page.goto(base+'/programmes');
    let form=page.getByRole('form',{name:'New Programmes'});
    await form.getByLabel('Name',{exact:true}).fill('Programme One');
    await form.getByRole('button',{name:'Save changes',exact:true}).click();
    await expect(page.getByRole('row').filter({hasText:'Programme One'})).toBeVisible();
    await page.goto(base+'/courses');
    form=page.getByRole('form',{name:'New Courses'});
    await form.getByLabel('Name',{exact:true}).fill('Course One');
    await form.getByRole('combobox',{name:'Programme',exact:true}).selectOption({label:'Programme One'});
    await form.getByRole('button',{name:'Save changes',exact:true}).click();
    await expect(page.getByRole('row').filter({hasText:'Course One'})).toBeVisible();
    await page.goto(base+'/programmes');
    await page.getByRole('row').filter({hasText:'Programme One'}).getByRole('button',{name:'Edit',exact:true}).click();
    form=page.getByRole('form',{name:'Edit Programmes'});
    await form.getByLabel('Name',{exact:true}).fill('Renamed Programme');
    await form.getByRole('button',{name:'Save changes',exact:true}).click();
    await page.getByRole('row').filter({hasText:'Renamed Programme'}).getByRole('button',{name:'Delete',exact:true}).click();
    await expect(page.getByText(/still linked to other records/).first()).toBeVisible();
    await expect(page.getByRole('row').filter({hasText:'Renamed Programme'})).toBeVisible();
});

test('secondary session dates are editable and arms page opens',async({page,request})=>{
    const data=await admin(page,request,'secondary_school');
    const base='/secondary-schools/'+data.owner_id;
    await page.goto(base+'/academic-sessions');
    await page.getByLabel('Name',{exact:true}).fill('2030/2031');
    await page.getByRole('button',{name:'Save Session',exact:true}).click();
    await page.getByRole('row').filter({hasText:'2030/2031'}).getByRole('button',{name:'Edit',exact:true}).click();
    const form=page.getByRole('form',{name:'Edit AcademicSessions'});
    await form.getByLabel('Start date',{exact:true}).fill('2030-09-01');
    await form.getByLabel('End date',{exact:true}).fill('2031-07-01');
    await form.getByRole('button',{name:'Save changes',exact:true}).click();
    await page.getByRole('row').filter({hasText:'2030/2031'}).getByRole('button',{name:'Edit',exact:true}).click();
    await expect(page.getByRole('form',{name:'Edit AcademicSessions'}).getByLabel('End date',{exact:true})).toHaveValue('2031-07-01');
    await page.goto(base+'/arms');
    await expect(page.getByRole('form',{name:'New Class arms'})).toBeVisible();
});
