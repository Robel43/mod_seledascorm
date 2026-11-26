'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {spawnSync}=require('node:child_process'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
const php=(...args)=>{const r=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(__dirname,'dashboard.php'),...args],{encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout;};
test('upgraded report and sidebar bypass a previously cached stylesheet and survive later legacy theme rules',async()=>{
 const urls=JSON.parse(php('--asseturls'));
 assert.ok(urls.every(u=>/\?v=[a-f0-9]{16}$/.test(u)));
 const report=php('--html').replace(/<style>[\s\S]*?<\/style>/,'<link rel="stylesheet" href="'+urls[0]+'">');
 const sidebar=`<!doctype html><html><head><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="${urls[1]}"><style>.yui3-skin-sam .yui3-treeview-icon{display:inline-block;background:red;width:20px;height:20px}.yui3-treeview-indicator{float:left}.yui3-treeview-indicator s{display:block;border:5px solid black}ul{padding-left:40px}</style></head><body id="page-mod-seledascorm-player" class="yui3-skin-sam"><div id="seledascormpage"><div id="seledascorm_toc"><div id="seledascorm_tree"><ul class="yui3-treeview-children"><li class="yui3-treeview-node yui3-treeview-has-children" aria-expanded="true"><div class="yui3-treeview-row"><span class="yui3-treeview-indicator"><s></s></span><span class="yui3-treeview-icon"></span><span class="yui3-treeview-label"><a href="#lesson"><span class="seleda-status" data-state="complete"></span>Lesson title with long text that must wrap on smaller screens</a></span></div></li></ul></div></div></div></body></html>`;
 const requests=[];
 const server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname.endsWith('.css')){requests.push(req.url);res.setHeader('Content-Type','text/css');res.setHeader('Cache-Control','public,max-age=31536000');res.end(url.searchParams.has('v')?fs.readFileSync(path.join(root,path.basename(url.pathname))):'.old-style{color:red}');return;}
  res.setHeader('Content-Type','text/html');res.end(url.pathname==='/warm'?'<link rel="stylesheet" href="/mod/seledascorm/dashboard.css"><link rel="stylesheet" href="/mod/seledascorm/learner-layout.css">':url.pathname==='/sidebar'?sidebar:report);
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})});
 try{
  const page=await browser.newPage({viewport:{width:1400,height:900}}),base='http://127.0.0.1:'+server.address().port;
  await page.goto(base+'/warm');await page.goto(base+'/report');
  assert.equal(await page.locator('.sd-tabs').first().evaluate(e=>getComputedStyle(e).display),'flex');
  assert.equal(await page.locator('.sd-visuals svg').count(),2);
  assert.equal(await page.locator('.sd-status-chart').evaluate(e=>getComputedStyle(e).display),'grid');
  assert.equal(await page.locator('select:visible').count(),0);
  await page.goto(base+'/sidebar');
  assert.equal(await page.locator('.yui3-treeview-icon').evaluate(e=>getComputedStyle(e).display),'none');
  const positions=await page.locator('.yui3-treeview-row').evaluate(e=>({arrow:e.querySelector('.yui3-treeview-indicator').getBoundingClientRect().x,label:e.querySelector('.yui3-treeview-label').getBoundingClientRect().right}));
  assert.ok(positions.arrow>=positions.label,JSON.stringify(positions));
  assert.equal(await page.locator('.yui3-treeview-indicator s').evaluate(e=>getComputedStyle(e).display),'none');
  assert.ok(urls.every(u=>requests.includes(u)),JSON.stringify(requests));
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
});
