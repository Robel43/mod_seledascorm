'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs');
const {chromium}=require('playwright');
test('chalkboard defaults replace product icons without modifying authored logos or looping',async()=>{
    const icon=fs.readFileSync(path.resolve(__dirname,'../../pix/icon.svg'),'utf8');
    assert.match(icon,/data:image\/png;base64,/);
    assert.equal(icon,fs.readFileSync(path.resolve(__dirname,'../../pix/monologo.svg'),'utf8'));
    const browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})});
    try {
        const page=await browser.newPage();
        await page.setContent('<link rel="icon" href="old.svg"><img class="exe-logo" src="old.svg"><div class="idevice_node"><img class="exe-logo" src="authored.svg"></div>');
        await page.evaluate(()=>{window.__MOODLE_SELEDA_BRANDING__={name:'Seleda',logoUrl:'/mod/seledascorm/pix/icon.svg'};window.mutations=0;new MutationObserver(m=>window.mutations+=m.length).observe(document.documentElement,{childList:true,subtree:true});});
        await page.addScriptTag({path:path.resolve(__dirname,'../../editor/branding.js')});
        assert.equal(await page.title(),'Seleda');
        assert.equal(await page.locator('link[rel=icon]').getAttribute('href'),'/mod/seledascorm/pix/icon.svg');
        assert.equal(await page.locator('body > img').getAttribute('src'),'/mod/seledascorm/pix/icon.svg');
        assert.equal(await page.locator('.idevice_node img').getAttribute('src'),'authored.svg');
        const before=await page.evaluate(()=>window.mutations);
        await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
        assert.equal(await page.evaluate(()=>window.mutations),before,'Branding must converge');
    } finally {await browser.close();}
});
