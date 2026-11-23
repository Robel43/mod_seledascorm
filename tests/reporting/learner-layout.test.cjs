'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
let browser;
test.before(async()=>browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})}));
test.after(async()=>browser?.close());
async function setup(width=390) {
    const page=await browser.newPage({viewport:{width,height:800}});
    const tree=Array.from({length:22},(_,i)=>`<li class="${i===1?'yui3-treeview-selected':''}"><div class="yui3-treeview-row"><a href="#lesson${i}" ${i===1?'aria-current="page"':''}>${['Vocabulary Activity (Score: 70)','Reading Activity','Writing Activity','Listening Activity','Speaking Activity','Quiz 1'][i%6]}</a></div></li>`).join('');
    await page.setContent(`<body id="page-mod-seledascorm-player"><div id="seledascormpage"><div id="toctree"><div class="tocbox_action-buttons"><button id="toggleFullscreen">Fullscreen</button></div><div id="seledascorm_toc_title">Topic 1 Intermediate English</div><div id="seledascorm_layout"><div id="seledascorm_toc" role="navigation" aria-label="Contents"><div id="seledascorm_tree"><ul><li><div class="yui3-treeview-row"><a href="#first">Lesson 1.1: The Present Perfect Simple</a></div><ul>${tree}</ul></li></ul></div></div><div id="seledascorm_toc_toggle"><button id="seledascorm_toc_toggle_btn">Menu</button></div><div id="seledascorm_content"><iframe id="seledascorm_object" title="Lesson"></iframe><div id="seledascorm_navpanel"></div></div></div></div></div></body>`);
    await page.addStyleTag({content:'body{margin:0;font-family:system-ui}button{cursor:pointer}ul{list-style:none}#seledascormpage{box-sizing:border-box}'});
    await page.addStyleTag({path:path.join(root,'styles.css')});
    await page.addStyleTag({path:path.join(root,'learner-layout.css')});
    await page.addScriptTag({path:path.join(__dirname,'../fixtures/native-quiz/jquery.min.js')});
    await page.evaluate(()=>{
        window.modules={};window.define=(name,deps,factory)=>modules[name]=factory(jQuery,{}, {}, {});
        const toc=document.getElementById('seledascorm_toc');
        document.getElementById('seledascorm_toc_toggle_btn').addEventListener('click',()=>toc.classList.toggle('disabled'));
        toc.addEventListener('click',e=>{if(e.target.closest('a'))e.preventDefault();});
    });
    await page.addScriptTag({path:path.join(root,'amd/build/learner_layout.min.js')});
    await page.addScriptTag({path:path.join(root,'amd/build/fullscreen.min.js')});
    await page.evaluate(()=>{modules['mod_seledascorm/learner_layout'].init();modules['mod_seledascorm/fullscreen'].init();});
    const frame=page.frames()[1];
    await frame.setContent('<body><h1>Lesson content</h1><p>Read and complete the activities.</p><div style="height:3800px;background:linear-gradient(white,#edf2fc)"></div><div id="eXeAtools"><div id="eXeAtoolsBtnSet"><button id="eXeAtoolsBtn" aria-label="Accessibility tools">A</button></div><div id="eXeAtoolsSet" hidden><button>Text size</button></div></div></body>');
    await frame.addStyleTag({path:path.join(__dirname,'../fixtures/learner/toolbar.css')});
    // Reproduce the native player assigning content-height inline after load.
    await page.evaluate(()=>{document.getElementById('seledascorm_object').style.height='4000px';document.getElementById('seledascorm_layout').style.height='4000px';});
    return {page,frame};
}
test('small-screen menu slides from the side without shrinking content, and closes accessibly',async()=>{
    const {page}=await setup();
    for(const width of [320,390,768]) {
        await page.setViewportSize({width,height:800});
        const before=await page.locator('#seledascorm_content').boundingBox();
        await page.getByRole('button',{name:'Open course contents',exact:true}).click();
        await page.waitForFunction(()=>document.getElementById('seledascormpage').classList.contains('seleda-drawer-open'));
        await page.locator('#seledascorm_toc').evaluate(el=>el.getAnimations().forEach(a=>a.finish()));
        const toc=await page.locator('#seledascorm_toc').boundingBox(),after=await page.locator('#seledascorm_content').boundingBox();
        assert.equal(toc.x,0);assert.equal(toc.y,0);assert.equal(toc.height,800);assert.ok(toc.width<width);
        assert.equal(after.width,before.width);assert.equal(after.y,before.y);
        assert.equal(await page.locator('#seledascorm_content').evaluate(el=>el.inert),true);
        assert.equal(await page.locator('#seledascorm_toc').getAttribute('role'),'dialog');
        if(width===390 && process.env.SELEDA_LAYOUT_SCREENSHOTS) await page.screenshot({path:path.join(process.env.SELEDA_LAYOUT_SCREENSHOTS,'learner-sidebar-mobile.png')});
        await page.keyboard.press('Escape');
        await page.waitForFunction(()=>!document.getElementById('seledascormpage').classList.contains('seleda-drawer-open'));
        assert.equal(await page.locator('#seledascorm_content').evaluate(el=>el.inert),false);
        assert.equal(await page.locator('#seledascorm_toc_toggle_btn').evaluate(el=>el===document.activeElement),true);
    }
    await page.getByRole('button',{name:'Open course contents',exact:true}).click();
    await page.locator('.seleda-toc-backdrop').click({position:{x:380,y:500}});
    await page.waitForFunction(()=>!document.getElementById('seledascormpage').classList.contains('seleda-drawer-open'));
    await page.getByRole('button',{name:'Open course contents',exact:true}).click();
    await page.getByRole('link',{name:'Reading Activity',exact:true}).first().click();
    await page.waitForFunction(()=>!document.getElementById('seledascormpage').classList.contains('seleda-drawer-open'));
    await page.setViewportSize({width:1440,height:900});
    await page.getByRole('button',{name:'Open course contents',exact:true}).click();
    const desktop=await page.locator('#seledascorm_toc').boundingBox(),content=await page.locator('#seledascorm_content').boundingBox();
    assert.ok(desktop.x+desktop.width<=content.x+1);assert.equal(await page.locator('#seledascorm_toc').getAttribute('role'),'navigation');
    if(process.env.SELEDA_LAYOUT_SCREENSHOTS) await page.screenshot({path:path.join(process.env.SELEDA_LAYOUT_SCREENSHOTS,'learner-sidebar-desktop.png')});
    await page.close();
});
test('real fullscreen keeps the accessibility dock in view while long content scrolls',async()=>{
    for (const width of [390,1440]) {
        const {page,frame}=await setup(width);
        await page.getByRole('button',{name:'Fullscreen',exact:true}).click();
        await page.waitForFunction(()=>document.fullscreenElement?.id==='seledascormpage');
        await page.waitForFunction(()=>document.getElementById('seledascormpage').classList.contains('fullscreen'));
        // Duplicate vendor events must not toggle the fullscreen class back off.
        await page.evaluate(()=>document.dispatchEvent(new Event('webkitfullscreenchange')));
        assert.equal(await page.locator('#seledascormpage').evaluate(el=>el.classList.contains('fullscreen')),true);
        const iframe=await page.locator('#seledascorm_object').boundingBox();
        assert.ok(iframe.height>300&&iframe.height<800,'Iframe needs a screen-sized viewport');
        const before=await frame.locator('#eXeAtoolsBtnSet').boundingBox();
        await frame.evaluate(()=>window.scrollTo(0,1800));
        await frame.waitForFunction(()=>scrollY>1000);
        const after=await frame.locator('#eXeAtoolsBtnSet').boundingBox();
        assert.ok(Math.abs(before.y-after.y)<1,'Dock must stay fixed within the scrolling iframe');
        assert.ok(after.y>=0&&after.y+after.height<=iframe.height);
        assert.equal(await page.locator('#seledascormpage').evaluate(el=>el.scrollTop),0);
        if(process.env.SELEDA_LAYOUT_SCREENSHOTS) await page.screenshot({path:path.join(process.env.SELEDA_LAYOUT_SCREENSHOTS,`learner-fullscreen-${width}.png`)});
        await page.evaluate(()=>document.exitFullscreen());
        await page.waitForFunction(()=>!document.getElementById('seledascormpage').classList.contains('fullscreen'));
        await page.close();
    }
});
