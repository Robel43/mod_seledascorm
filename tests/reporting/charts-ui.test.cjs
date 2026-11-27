 'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs'),{spawnSync}=require('node:child_process');
const {chromium}=require('playwright');
function fixture(mode){const r=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(__dirname,'dashboard.php'),mode],{encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout;}
let browser;
test.before(async()=>browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})}));
test.after(async()=>browser?.close());
test('forty learning pages stay compact, searchable, sortable and expandable',async()=>{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(fixture('--compacthtml'));
 assert.equal(await page.locator('.sd-page-bar:visible').count(),6);
 assert.equal(await page.locator('.sd-page-progress').evaluate(e=>e.getBoundingClientRect().height<760),true);
 const more=page.locator('.sd-more-pages > summary');await more.focus();await page.keyboard.press('Enter');
 assert.equal(await page.locator('.sd-page-bar:visible').count(),40);
 await page.waitForFunction(()=>document.querySelector('.sd-page-visible-count').textContent==='Showing 40 of 40 pages');
 await more.click();assert.equal(await page.locator('.sd-page-bar:visible').count(),6);
 await page.getByLabel('Find a page',{exact:true}).fill('Page 30');
 assert.equal(await page.locator('.sd-page-bar:visible').count(),1);
 assert.match(await page.locator('.sd-page-bar:visible').innerText(),/Page 30/);
 await page.locator('.sd-page-bar:visible > summary').click();
 assert.equal(await page.locator('.sd-page-breakdown:visible').count(),1);
 await page.getByLabel('Find a page',{exact:true}).fill('no such page');
 assert.equal(await page.locator('.sd-page-bar:visible').count(),0);
 assert.match(await page.locator('.sd-page-visible-count').innerText(),/Showing 0 of 0 pages/);
 await page.getByLabel('Find a page',{exact:true}).fill('');
 await page.getByLabel('Sort pages',{exact:true}).selectOption('unfinished');
 assert.match(await page.locator('.sd-page-preview .sd-page-name').first().innerText(),/Page 02/);
 await page.getByLabel('Sort pages',{exact:true}).selectOption('order');
 assert.match(await page.locator('.sd-page-preview .sd-page-name').first().innerText(),/Page 01/);
 assert.deepEqual(errors,[]);
 await page.close();
});
test('completion doughnut segments and legend navigate to matching learner filters',async()=>{
 const page=await browser.newPage();const html=fixture('--html');
 await page.route('http://reports.test/**',route=>route.fulfill({contentType:'text/html',body:html}));
 await page.goto('http://reports.test/mod/seledascorm/dashboard.php?id=1');
 const slice=page.locator('.sd-completion-donut a[href*="state=completed"]');
 assert.match(await slice.getAttribute('aria-label'),/Completed: 1 \/ 3/);
 await slice.focus();await page.keyboard.press('Enter');await page.waitForURL(/state=completed/);
 const legend=page.locator('.sd-donut-legend a[href*="state=inprogress"]');
 await legend.click();await page.waitForURL(/state=inprogress/);
 await page.close();
});
test('bar charts have no duplicate list and support hover, click, keyboard and deselection',async()=>{
 const page=await browser.newPage({viewport:{width:1024,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(fixture('--contenthtml'));
 assert.equal(await page.locator('.sd-chart-values').count(),0);
 const chart=page.locator('[data-rpt-chart]').first(),row=chart.locator('[data-rpt-chart-item]').first(),detail=chart.locator('.sd-chart-selection');
 assert.equal(await row.getAttribute('role'),'button');
 assert.equal(await chart.locator('.sd-rpt-chart').getAttribute('role'),'group');
 const name=await row.getAttribute('data-detail');assert.match(name,/Share of displayed total:/);
 await row.hover();assert.equal(await detail.innerText(),name);
 await row.click();assert.equal(await row.getAttribute('aria-pressed'),'true');
 await page.mouse.move(0,0);assert.equal(await detail.isVisible(),true);
 await row.focus();await page.keyboard.press('Enter');assert.equal(await row.getAttribute('aria-pressed'),'false');
 await page.keyboard.press('Space');assert.equal(await row.getAttribute('aria-pressed'),'true');
 assert.deepEqual(errors,[]);await page.close();
});
test('compact charts fit narrow screens; native page expansion and chart labels work without JavaScript',async()=>{
 const context=await browser.newContext({javaScriptEnabled:false}),page=await context.newPage();
 for(const width of [320,375,768,1024]){
  await page.setViewportSize({width,height:1000});await page.setContent(fixture('--compacthtml'));
  assert.equal(await page.locator('.sd-page-tools:visible').count(),0);
  assert.equal(await page.locator('.sd-page-bar:visible').count(),6);
  await page.locator('.sd-more-pages > summary').click();assert.equal(await page.locator('.sd-page-bar:visible').count(),40);
  assert.match(await page.locator('.sd-completion-donut a').first().getAttribute('href'),/state=completed/);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.setContent(fixture('--contenthtml'));
  assert.equal(await page.locator('.sd-chart-values').count(),0);
  assert.ok(await page.locator('.sd-chart-item-label:visible').count()>0);
  assert.ok(await page.locator('.sd-chart-item-label').first().evaluate(e=>parseFloat(getComputedStyle(e).fontSize)>=11));
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 await context.close();
});
