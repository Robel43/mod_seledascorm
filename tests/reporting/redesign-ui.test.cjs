'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path'),{spawnSync}=require('node:child_process');
const {chromium}=require('playwright');
function fixture(mode){const r=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(__dirname,'dashboard.php'),mode],{encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout;}
let browser;
test.before(async()=>browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})}));
test.after(async()=>browser?.close());
test('learner details show summary, all pages and keyboard-operable answer history in that order',async()=>{
 const page=await browser.newPage({viewport:{width:1440,height:1100}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(fixture('--detailhtml'));
 const positions=await page.evaluate(()=>['.sd-learner-header','.sd-learner-pages','.sd-learner-tabs','.sd-answers'].map(s=>document.querySelector(s).getBoundingClientRect().top));
 assert.ok(positions.every((n,i)=>!i||n>positions[i-1]),JSON.stringify(positions));
 assert.equal(await page.locator('.sd-learner-pages > .sd-table-scroll > table > tbody > tr').count(),2);
 assert.match(await page.locator('.sd-learner-pages').innerText(),/Not started/);
 const block=page.locator('.sd-learner-pages summary').first();await block.focus();await page.keyboard.press('Enter');
 assert.match(await page.locator('.sd-learner-pages').innerText(),/No activity recorded/);
 const earlier=page.getByText('Earlier submissions (1)',{exact:true});await earlier.focus();await page.keyboard.press('Enter');
 assert.equal(await earlier.evaluate(e=>e.parentElement.open),true);
 assert.match(await page.locator('.sd-answers').innerText(),/Retry 2/);
 assert.match(await page.getByRole('link',{name:'Technical details',exact:true}).getAttribute('href'),/tab=technical/);
 assert.deepEqual(errors,[]);
 await page.close();
});
test('visible filters and learner details work without JavaScript and fit small viewports',async()=>{
 const context=await browser.newContext({javaScriptEnabled:false}),page=await context.newPage();
 for(const width of [320,375,768,1024]){
  await page.setViewportSize({width,height:1000});await page.setContent(fixture('--detailhtml'));
  assert.equal(await page.locator('.sd-filter-bar select:visible').count(),5);
  assert.equal(await page.getByRole('button',{name:'Apply',exact:true}).count(),2);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  const summary=page.locator('.sd-learner-pages summary').first();await summary.click();assert.equal(await summary.evaluate(e=>e.parentElement.open),true);
 }
 await context.close();
});
test('Content and Insights show the requested panels without exposing small-sample averages',async()=>{
 const page=await browser.newPage({viewport:{width:1440,height:1100}});
 await page.setContent(fixture('--contenthtml'));
 for(const name of ['Pages','Hardest questions','Time by page','Most used content blocks'])assert.equal(await page.getByRole('heading',{name,exact:true}).count(),1);
 assert.match(await page.getByRole('link',{name:'Interactions',exact:true}).getAttribute('href'),/measure=interactions/);
 for(const width of [320,375,768,1024]){
  await page.setViewportSize({width,height:1000});await page.setContent(fixture('--insightshtml'));
  for(const name of ['Participation','Completion by activity','Score distribution','Median active time by activity','Pages to investigate','Hardest questions','Retry outcomes','Tracking coverage'])assert.equal(await page.getByRole('heading',{name,exact:true}).count(),1);
  assert.match(await page.locator('.sd-main').innerText(),/Not enough data \(Participants: 1\)/);
  assert.equal(await page.getByText('Unique people',{exact:true}).count(),1);
  assert.equal(await page.getByText('Participations',{exact:true}).count(),1);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 await page.close();
});
