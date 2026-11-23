'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path');
const {spawnSync}=require('node:child_process'),{chromium}=require('playwright');
const fixture=path.resolve(__dirname,'../fixtures/native-quiz');
test('existing JSON quiz exports produce readable questions, choices, retries and graded scores',async()=>{
    const browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})});
    try {
        const page=await browser.newPage();await page.setContent('<body class="exe-export exe-scorm"><main></main></body>');
        await page.evaluate(()=>{
            window.values={'cmi.core.lesson_mode':'normal','cmi.core.lesson_status':'incomplete','cmi.interactions._count':'0'};
            window.API={LMSGetValue:k=>values[k]||'',LMSGetLastError:()=>0,LMSSetValue:(k,v)=>{
                values[k]=String(v);const m=k.match(/^cmi.interactions\.(\d+)\.id$/);if(m)values['cmi.interactions._count']=String(Number(m[1])+1);return 'true';
            },LMSCommit:()=> 'true'};
        });
        await page.evaluate(()=>window.eXeLearning={});
        await page.addScriptTag({path:path.join(fixture,'jquery.min.js')});
        await page.addScriptTag({path:path.join(fixture,'common.js')});
        await page.addScriptTag({path:path.join(fixture,'lms-runtime.js')});
        await page.addScriptTag({path:path.join(fixture,'mcq-feedback.js')});
        const html=await page.evaluate(()=>{
            const data={ideviceId:'idevice-1795417967460-z505cd28',mcqTracking:{mode:'graded',detailedInteractions:true},mcqSettings:{},mcqQuestions:[
                {id:'q-4c68c4a1-421c-4ab2-83b3-262f66c8d6bf',type:'single',text:'<p>Who has won? አበበ &amp; “friends” 😀</p>',choices:[{id:'c-8748fc3f-95f5-4ae6-a569-e7c5ca419993',text:'<b>Abdi &amp; classmates</b>',correct:true},{id:'c-other',text:'Other',correct:false}]},
                {id:'q-many',type:'multiple',text:'Choose two achievements',choices:[{id:'c-first',text:'Won a prize',correct:true},{id:'c-second',text:'Crossed the river',correct:true},{id:'c-no',text:'None',correct:false}]}
            ]};
            const node=document.createElement('div');node.className='idevice_node';node.id=data.ideviceId;node.dataset.ideviceType='mcq-feedback';node.dataset.ideviceJsonData=JSON.stringify(data);
            node.innerHTML='<h2 class="box-title">Quiz 1</h2>';document.querySelector('main').append(node);
            const raw=node.outerHTML;node.insertAdjacentHTML('beforeend',$mcqfeedback.renderView(data,null,'{content}'));
            $mcqfeedback.renderBehaviour();return raw;
        });
        await page.locator('[data-cid="c-8748fc3f-95f5-4ae6-a569-e7c5ca419993"] input').check();
        await page.locator('[data-cid="c-first"] input').check();await page.locator('.mcqf-finish').click();
        const saved=await page.evaluate(()=>({...values}));assert.equal(saved['cmi.core.score.raw'],'75');
        function decode(values,source=html){
            const tracks=Object.entries(values).map(([element,value])=>({scoid:7,element,value}));
            const r=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(__dirname,'native-quiz.php')],{encoding:'utf8',input:JSON.stringify({html:source,tracks})});
            assert.equal(r.status,0,r.stderr+r.stdout);return JSON.parse(r.stdout.replace(/^\uFEFF/,''));
        }
        const result=decode(saved);
        assert.equal(result.rows[0].resolved,true);assert.equal(result.rows[0].block,'Quiz 1');
        assert.equal(result.rows[0].question,'Who has won? አበበ & “friends” 😀');
        assert.equal(result.rows[0].response,'Abdi & classmates');assert.equal(result.rows[0].correct,'Abdi & classmates');
        assert.equal(result.rows[1].response,'Won a prize');assert.equal(result.rows[1].correct,'Won a prize; Crossed the river');
        assert.equal(result.rows[1].graded,true);assert.match(result.rows[1].result,/50%/);
        assert.equal(result.blocks[0].mode,'graded');assert.equal(result.blocks[0].score,75);
        assert.match(result.html,/>Graded</);assert.match(result.html,/75%/);assert.doesNotMatch(result.html,/not graded/i);
        const report=await browser.newPage();await report.setContent(result.usagehtml);
        assert.equal(await report.locator('.sd-block-detail').count(),1);
        const displayed=await report.locator('.sd-submitted-results tbody tr').allTextContents();
        assert.equal(displayed.length,result.rows.length);
        for(let i=0;i<result.rows.length;i++) {
            const cells=await report.locator('.sd-submitted-results tbody tr').nth(i).locator('td').allTextContents();
            assert.deepEqual(cells,['question','response','correct','result'].map(key=>result.rows[i][key]));
        }
        assert.equal(await report.locator('.sd-interaction-history').count(),1);
        assert.match(await report.locator('.sd-interaction-history').textContent(),/Selected option.*on/s);
        assert.doesNotMatch(await report.locator('.sd-submitted-results').textContent(),/no assessment result|Checked|Selected option/);
        await report.close();
        // The configured mode is visible before a submission, but never invents a score.
        const pending=decode({});assert.equal(pending.blocks[0].mode,'graded');assert.equal(pending.blocks[0].score,null);
        // A saved zero is a legitimate graded result; completion/off are distinct modes.
        const zero=decode({'cmi.suspend_data':'other.\tTS1:'+JSON.stringify({v:1,b:{'idevice-1795417967460-z505cd28':{m:'g',s:0,c:1}}})});assert.equal(zero.blocks[0].score,0);
        const completion=decode({'cmi.suspend_data':'TS1:'+JSON.stringify({v:1,b:{'idevice-1795417967460-z505cd28':{m:'c',s:50}}})});assert.equal(completion.blocks[0].mode,'completion');assert.equal(completion.blocks[0].score,null);
        const off=decode({},html.replace('&quot;mode&quot;:&quot;graded&quot;','&quot;mode&quot;:&quot;off&quot;'));assert.equal(off.blocks[0].mode,'off');
        const oldcatalog=Object.fromEntries(Object.entries(result.catalog).map(([key,d])=>{const copy={...d};delete copy.blockid;delete copy.tracking;return [key,copy];}));
        const historical=decode({...saved,'timhirt.reporting.catalog.v1':JSON.stringify(oldcatalog)});
        assert.equal(historical.blocks[0].mode,'graded');assert.equal(historical.blocks[0].score,75);assert.equal(historical.rows[0].question,result.rows[0].question);
        const edited=html.replace('Who has won?','A DIFFERENT QUESTION');assert.equal(decode(saved,edited).rows[0].resolved,false,'Never map an old revision to edited wording');
        await page.locator('.mcqf-retry').click();
        await page.locator('[data-cid="c-other"] input').check();await page.locator('[data-cid="c-no"] input').check();await page.locator('.mcqf-finish').click();
        const retry=decode(await page.evaluate(()=>({...values})));assert.equal(retry.blocks[0].score,0);assert.equal(retry.rows[2].question,result.rows[0].question);assert.equal(retry.rows[2].result,'Incorrect');
    } finally {await browser.close();}
});
