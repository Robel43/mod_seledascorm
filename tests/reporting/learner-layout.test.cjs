'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
let browser;
test.before(async()=>browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})}));
test.after(async()=>browser?.close());
async function setup(width=390) {
    const page=await browser.newPage({viewport:{width,height:800}});
    const tree=Array.from({length:22},(_,i)=>`<li class="yui3-treeview-node ${i===1?'yui3-treeview-selected':''}"><div class="yui3-treeview-row"><span class="yui3-treeview-indicator"><s></s></span><span class="yui3-treeview-icon"></span><span class="yui3-treeview-label"><a href="#lesson${i}" ${i===1?'aria-current="page"':''}><i class="icon ${['fa-check-square-o','fa-pause','fa-square-o','fa-times'][i%4]}" aria-label="Status"></i>${['Vocabulary Activity (Score: 70)','Reading Activity','Writing Activity','Listening Activity','Speaking Activity','Quiz 1'][i%6]}</a></span></div></li>`).join('');
    await page.setContent(`<body id="page-mod-seledascorm-player" class="yui3-skin-sam"><div id="seledascormpage"><div id="toctree"><div class="tocbox_action-buttons"><button id="toggleFullscreen">Fullscreen</button></div><div id="seledascorm_toc_title">Topic 1 Intermediate English</div><div id="seledascorm_layout"><div id="seledascorm_toc" role="navigation" aria-label="Contents"><div id="seledascorm_tree"><ul class="yui3-treeview-children"><li class="yui3-treeview-node yui3-treeview-has-children" aria-expanded="true"><div class="yui3-treeview-row"><span class="yui3-treeview-indicator"><s></s></span><span class="yui3-treeview-icon"></span><span class="yui3-treeview-label"><a href="#first">Lesson 1.1: The Present Perfect Simple, experiences or repeated actions before now when the exact past time is not stated</a></span></div><ul class="yui3-treeview-children">${tree}</ul></li></ul></div></div><div id="seledascorm_toc_toggle"><button id="seledascorm_toc_toggle_btn">Menu</button></div><div id="seledascorm_content"><iframe id="seledascorm_object" title="Lesson"></iframe><div id="seledascorm_navpanel"></div></div></div></div></div></body>`);
    await page.addStyleTag({content:'body{margin:0;font-family:system-ui}button{cursor:pointer}ul{list-style:none}#seledascormpage{box-sizing:border-box}'});
    await page.addStyleTag({path:path.join(root,'styles.css')});
    await page.addStyleTag({path:path.join(root,'learner-layout.css')});
    await page.addScriptTag({path:path.join(__dirname,'../fixtures/native-quiz/jquery.min.js')});
    await page.evaluate(()=>{
        window.modules={};window.define=(name,deps,factory)=>modules[name]=factory(jQuery,{}, {}, {});
        const toc=document.getElementById('seledascorm_toc');
        document.getElementById('seledascorm_toc_toggle_btn').addEventListener('click',()=>toc.classList.toggle('disabled'));
        toc.addEventListener('click',e=>{if(e.target.closest('a'))e.preventDefault();const indicator=e.target.closest('.yui3-treeview-indicator');if(indicator){const li=indicator.closest('li');if(li.classList.contains('yui3-treeview-has-children')){const open=li.getAttribute('aria-expanded')!=='true';li.setAttribute('aria-expanded',String(open));li.querySelector(':scope > ul').hidden=!open;}}});
    });
    await page.addScriptTag({path:path.join(root,'amd/build/learner_layout.min.js')});
    await page.addScriptTag({path:path.join(root,'amd/build/fullscreen.min.js')});
    await page.addScriptTag({path:path.join(root,'amd/build/accessibility_viewport.min.js')});
    await page.evaluate(()=>{modules['mod_seledascorm/learner_layout'].init();modules['mod_seledascorm/fullscreen'].init();modules['mod_seledascorm/accessibility_viewport'].init();});
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
        assert.equal(await page.locator('#seledascorm_toc').evaluate(el=>el.scrollWidth<=el.clientWidth),true,JSON.stringify(await page.locator('#seledascorm_toc').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,overflow:[...el.querySelectorAll('*')].filter(n=>n.getBoundingClientRect().right>el.getBoundingClientRect().right).slice(0,8).map(n=>({class:n.className,tag:n.tagName,width:n.getBoundingClientRect().width,css:getComputedStyle(n).cssText,text:n.textContent.slice(0,70)}))}))));
        const indicator=page.locator('.yui3-treeview-has-children > .yui3-treeview-row > .yui3-treeview-indicator').first();
        const label=page.locator('.yui3-treeview-has-children > .yui3-treeview-row > .yui3-treeview-label').first();
        assert.ok((await indicator.boundingBox()).x>(await label.boundingBox()).x);
        await indicator.click();assert.equal(await page.locator('.yui3-treeview-has-children').first().getAttribute('aria-expanded'),'false');
        await indicator.click();assert.equal(await page.locator('.yui3-treeview-has-children').first().getAttribute('aria-expanded'),'true');
        for(const state of ['complete','inprogress','notstarted','failed'])assert.ok(await page.locator('.seleda-status[data-state="'+state+'"]').count());
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
    await page.locator('#seledascorm_tree a[href="#lesson1"]').click();
    await page.waitForFunction(()=>!document.getElementById('seledascormpage').classList.contains('seleda-drawer-open'));
    await page.setViewportSize({width:1440,height:900});
    await page.getByRole('button',{name:'Open course contents',exact:true}).click();
    const desktop=await page.locator('#seledascorm_toc').boundingBox(),content=await page.locator('#seledascorm_content').boundingBox();
    assert.ok(desktop.x+desktop.width<=content.x+1);assert.equal(await page.locator('#seledascorm_toc').getAttribute('role'),'navigation');
    if(process.env.SELEDA_LAYOUT_SCREENSHOTS) await page.screenshot({path:path.join(process.env.SELEDA_LAYOUT_SCREENSHOTS,'learner-sidebar-desktop.png')});
    await page.close();
});
test('normal page scrolling keeps the accessibility dock and open tools within the visible content',async()=>{
    const {page,frame}=await setup(1440);
    for(const y of [0,900,1800,2600]) {
        await page.evaluate(y=>window.scrollTo(0,y),y);
        await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
        const dock=await frame.locator('#eXeAtoolsBtnSet').boundingBox();
        const iframe=await page.locator('#seledascorm_object').boundingBox();
        const screenY=dock.y;
        assert.ok(screenY>=0&&screenY+dock.height<=800,`Dock disappeared at outer scroll ${y}: ${screenY}`);
    }
    await frame.evaluate(()=>{document.body.classList.add('exe-atools-on');document.getElementById('eXeAtoolsSet').hidden=false;});
    await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    const panel=await frame.locator('#eXeAtoolsSet').boundingBox(),iframe=await page.locator('#seledascorm_object').boundingBox();
    assert.ok(panel.y>=0&&panel.y+panel.height<=800);
    await page.close();
});
test('the native completion callback replaces the in-progress badge without navigating the learner',async()=>{
    const {page}=await setup(1440);
    const inline=await page.locator('.icon.fa-pause').first().elementHandle();
    await inline.evaluate(el=>el.className='icon fa-check-square-o');
    await page.waitForFunction(el=>el.dataset.state==='complete',inline);
    await page.evaluate(()=>window.M={});
    await page.addScriptTag({path:path.join(root,'module.js')});
    await page.evaluate(()=>{
        const old={id:'old-node',title:'a=9&scoid=7',scoid:7,state:{selected:true}};
        let selected=old,nodes=[];
        window.seledascorm_current_node=old;
        window.seledascorm_tree_node={rootNode:{children:[]},getSelectedNodes:()=>selected?[selected]:[],
            createNode:()=>({children:[]}),clear(){old.state.destroyed=true;selected=null;nodes=[];},
            appendNode(root,items){nodes=items;root.children=items;},
            render(){document.getElementById('seledascorm_tree').innerHTML='<ul>'+nodes.map(n=>n.html).join('')+'</ul>';},
            openAll(){},getNodeByAttribute:(key,value)=>nodes.find(n=>n[key]===value)};
        window.mod_seledascorm_parse_toc_tree=source=>[...source.children].map(li=>{
            const a=li.querySelector('a');const n={id:li.id,title:a.getAttribute('title'),scoid:Number(a.dataset.scoid),html:li.outerHTML,state:{selected:false}};
            n.select=()=>{selected=n;n.state.selected=true;};return n;
        });
        const y={one:selector=>selector==='#seledascorm_toc'?{hasClass:name=>document.getElementById('seledascorm_toc').classList.contains(name)}:document.querySelector(selector)};
        window.nativeTreeContext=y;
        const response='<ul><li id="fresh-node" class="yui3-treeview-node"><div class="yui3-treeview-row"><span class="yui3-treeview-label"><a title="a=9&amp;scoid=7" data-scoid="7" href="#reading"><i class="icon fa-check-square-o" aria-label="Completed"></i>Reading Activity</a></span></div></li></ul>';
        M.mod_seledascorm.connectPrereqCallback.success.call(y,1,{responseText:response});
    });
    await page.waitForFunction(()=>document.querySelector('#fresh-node .seleda-status[data-state="complete"]'));
    assert.equal(await page.locator('#fresh-node a').getAttribute('aria-current'),'page');
    assert.equal(await page.evaluate(()=>seledascorm_current_node===seledascorm_tree_node.getSelectedNodes()[0]),true);
    await page.evaluate(()=>M.mod_seledascorm.connectPrereqCallback.success.call(nativeTreeContext,2,{responseText:'<form>Login expired</form>'}));
    assert.equal(await page.locator('#fresh-node').count(),1,'A non-tree response must not erase progress');
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
