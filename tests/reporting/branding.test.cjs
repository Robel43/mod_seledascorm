'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path'),fs=require('node:fs');
const {chromium}=require('playwright');
test('Moodle chooser and course-list icons retain full colour despite purpose filters',async()=>{
    const browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})});
    try {
        const page=await browser.newPage();
        const svg=fs.readFileSync(path.resolve(__dirname,'../../pix/icon.svg'),'utf8');
        await page.route('https://moodle.example.test/**',route=>route.fulfill({contentType:route.request().resourceType()==='image'?'image/svg+xml':'text/html',body:route.request().resourceType()==='image'?svg:'<html><body></body></html>'}));
        await page.goto('https://moodle.example.test/');
        await page.addStyleTag({content:'.activityiconcontainer{display:inline-flex;padding:12px}.activityiconcontainer img{width:40px;height:40px}.activityiconcontainer.content:not(.isbranded) .activityicon:not(.nofilter),.activityiconcontainer.content:not(.isbranded) .icon:not(.nofilter){filter:brightness(0) saturate(100%) invert(40%) sepia(95%) saturate(1500%) hue-rotate(145deg)}'});
        await page.evaluate(()=>document.body.innerHTML=`
            <div class="activityiconcontainer content modicon_seledascorm"><img class="activityicon" alt="Seleda chooser" src="/theme/image.php/boost/seledascorm/123/monologo"></div>
            <div class="activityiconcontainer content"><img class="icon" alt="Seleda course" src="/theme/image.php/boost/seledascorm/123/icon"></div>
            <div class="activityiconcontainer content"><img class="icon" alt="Other activity" src="/theme/image.php/boost/assign/123/icon"></div>`);
        await page.addStyleTag({path:path.resolve(__dirname,'../../styles.css')});
        for(const name of ['Seleda chooser','Seleda course']) {
            const icon=page.getByAltText(name);await icon.evaluate(img=>img.decode());
            assert.equal(await icon.evaluate(img=>getComputedStyle(img).filter),'none');
            assert.ok(await icon.evaluate(img=>img.naturalWidth>0 && img.naturalHeight>0));
        }
        assert.notEqual(await page.getByAltText('Other activity').evaluate(img=>getComputedStyle(img).filter),'none');
        if(process.env.SELEDA_BRANDING_SCREENSHOT) await page.screenshot({path:process.env.SELEDA_BRANDING_SCREENSHOT});
    } finally {await browser.close();}
});
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
