 'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs'),{spawnSync}=require('node:child_process');
const {chromium}=require('playwright');
function fixture(mode){const r=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(__dirname,'dashboard.php'),mode],{encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout;}
let browser;
test.before(async()=>browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})}));
test.after(async()=>browser?.close());
async function setup(page){
 await page.route('http://report.test/**',route=>route.fulfill({contentType:'text/html',body:fixture('--html')}));
 await page.goto('http://report.test/mod/seledascorm/dashboard.php?id=1&search=teacher&role=all');
 const fragment=fixture('--detailfragment');
 await page.evaluate(({fragment})=>{
  window.calls=[];window.failReport=false;window.delayReport=false;window.pendingReports=[];
  window.define=(name,deps,factory)=>{
   const Ajax={call:requests=>requests.map(request=>{
    window.calls.push(request);
    if(window.failReport)return Promise.reject(Error('Access refused'));
    if(window.delayReport)return new Promise(resolve=>window.pendingReports.push(()=>resolve({html:fragment})));
    return Promise.resolve({html:fragment});
   })};
   const Str={get_strings:()=>Promise.resolve(['Learner report','Close learner report','Loading learner report…','The learner report could not be loaded. Try again or open the full page report.','Open full page report','Try again'])};
   window.drawerInit=factory(Ajax,Str).init();
  };
 },{fragment});
 await page.addScriptTag({content:fs.readFileSync(path.join(__dirname,'../../amd/build/reporting_drawer.min.js'),'utf8')});
 await page.evaluate(()=>window.drawerInit);
}
test('drawer opens actual report fragment, navigates tabs/attempts, traps focus and returns it on Escape',async()=>{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await setup(page);
 const trigger=page.locator('.sd-learner-name[href*="learner=4"]');
 await trigger.click();await page.locator('dialog .sd-learner-header').waitFor();
 assert.equal(await page.locator('dialog').evaluate(e=>e.open),true);
 assert.equal(await page.locator('dialog .sd-learner-pages').count(),1);
 const first=await page.evaluate(()=>window.calls[0]);
 assert.equal(first.methodname,'mod_seledascorm_get_learner_report');assert.equal(first.args.cmid,1);
 assert.equal(first.args.userid,4);assert.equal(first.args.attempt,2);
 await page.getByRole('link',{name:'Technical details',exact:true}).click();
 await page.waitForFunction(()=>window.calls.length===2);
 assert.equal(await page.evaluate(()=>window.calls[1].args.tab),'technical');
 await page.locator('dialog .sd-attempt select').selectOption('1');
 await page.locator('dialog .sd-attempt button').click();await page.waitForFunction(()=>window.calls.length===3);
 assert.equal(await page.evaluate(()=>window.calls[2].args.attempt),1);
 const full=await page.getByRole('link',{name:'Open full page report'}).getAttribute('href');
 assert.match(full,/attempt=1/);assert.match(full,/tab=technical/);
 await page.getByRole('button',{name:'Close learner report'}).focus();
 for(let i=0;i<35;i++){await page.keyboard.press('Tab');assert.equal(await page.evaluate(()=>document.querySelector('dialog').contains(document.activeElement)),true);}
 await page.keyboard.press('Escape');assert.equal(await page.locator('dialog').evaluate(e=>e.open),false);
 assert.equal(await trigger.evaluate(e=>e===document.activeElement),true);
 assert.equal(await page.evaluate(()=>document.documentElement.style.overflow),'');assert.deepEqual(errors,[]);await page.close();
});
test('drawer errors preserve full-page fallback, retry and ignore late responses after closing',async()=>{
 const page=await browser.newPage();await setup(page);await page.evaluate(()=>window.failReport=true);
 await page.locator('.sd-learner-name[href*="learner=4"]').click();await page.getByRole('button',{name:'Try again',exact:true}).waitFor();
 assert.match(await page.getByRole('link',{name:'Open full page report'}).getAttribute('href'),/learner=4/);
 await page.evaluate(()=>window.failReport=false);await page.getByRole('button',{name:'Try again',exact:true}).click();
 await page.locator('dialog .sd-learner-header').waitFor();await page.keyboard.press('Escape');
 await page.evaluate(()=>window.delayReport=true);await page.locator('.sd-learner-name[href*="learner=4"]').click();
 await page.waitForFunction(()=>window.pendingReports.length===1);await page.keyboard.press('Escape');
 await page.evaluate(()=>window.pendingReports.shift()());
 assert.equal(await page.locator('dialog .sd-learner-header').count(),0);await page.close();
});
test('course cards list all courses, show honest progress coverage and drill into activity links without JavaScript',async()=>{
 const context=await browser.newContext({javaScriptEnabled:false}),page=await context.newPage();
 for(const width of [320,375,768,1024]){
  await page.setViewportSize({width,height:1000});await page.setContent(fixture('--coursehtml'));
  assert.equal(await page.locator('.sd-course-card').count(),2);
  assert.doesNotMatch(await page.locator('.sd-course-browser').innerText(),/Average page-completion progress across/);
  const spacing=await page.locator('.sd-course-card').first().evaluate(card=>({
   titleBottom:card.querySelector('.sd-course-card-heading').getBoundingClientRect().bottom,
   progressTop:card.querySelector('.sd-course-card-progress').getBoundingClientRect().top,
   display:getComputedStyle(card).display
  }));
  assert.equal(spacing.display,'flex');assert.ok(spacing.progressTop-spacing.titleBottom>=16);
  assert.equal(await page.locator('.sd-course-card').first().evaluate(card=>getComputedStyle(card).textDecorationLine),'none');
  assert.match(await page.locator('.sd-course-browser').innerText(),/Average progress:\s*33.3%/);
  assert.match(await page.locator('.sd-course-browser').innerText(),/3 of 4/);
  assert.match(await page.locator('.sd-course-browser').innerText(),/Average progress:\s*—/);
  assert.match(await page.locator('.sd-course-card').first().getAttribute('href'),/course=1/);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 await page.setContent(fixture('--activitylisthtml'));assert.equal(await page.locator('.sd-course-card').count(),1);
 assert.match(await page.locator('.sd-course-card').getAttribute('href'),/id=1/);
 const separated=await page.locator('.sd-course-card').evaluate(card=>
  card.querySelector('.sd-course-card-progress').getBoundingClientRect().top-card.querySelector('.sd-course-card-title').getBoundingClientRect().bottom);
 assert.ok(separated>=16);
 await page.setContent(fixture('--html'));assert.match(await page.locator('.sd-learner-name[href*="learner=4"]').getAttribute('href'),/learner=4/);
 await context.close();
});
test('drawer fits small screens and retains a reachable close control',async()=>{
 const page=await browser.newPage();await setup(page);
 for(const width of [320,375,768,1024]){
  await page.setViewportSize({width,height:900});await page.locator('.sd-learner-name[href*="learner=4"]').click();
  await page.locator('dialog .sd-learner-header').waitFor();
  assert.ok(await page.locator('dialog').evaluate(e=>e.getBoundingClientRect().width<=innerWidth));
  assert.equal(await page.getByRole('button',{name:'Close learner report'}).isVisible(),true);
  await page.keyboard.press('Escape');
 }
 await page.close();
});
