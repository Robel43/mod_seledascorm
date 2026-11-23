'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path');
const {chromium}=require('playwright');
test('HTML activity checks save real answers and keys, keep samples ungraded, and retry safely',async()=>{
    const browser=await chromium.launch({headless:true,...(process.env.TIMHIRT_BROWSER_CHANNEL?{channel:process.env.TIMHIRT_BROWSER_CHANNEL}:{})});
    try {
        const page=await browser.newPage();
        await page.setContent('<iframe id="seledascorm_object"></iframe>');
        await page.evaluate(()=>{window.saved=[];window.API={SeledaCreateUsageSender:()=>async p=>{window.saved.push(JSON.parse(JSON.stringify(p)));return true;}};});
        const frame=page.frames()[1];
        await frame.setContent(`<div class="idevice_node" id="block1"><h2>Vocabulary</h2><div id="matching-activity">
          <button class="left-item" data-key="L1"><span class="badge">1</span>has won</button>
          <button class="left-item" data-key="L2">have crossed</button>
          <button class="right-item" data-key="R1">win</button><button class="right-item" data-key="R2">cross</button>
          <button id="checkBtn">Check</button><button id="resetBtn">Reset</button></div>
          <div id="readingActivity"><div class="question" data-answer="a prize|abdi has won a prize" data-correct-text="Abdi has won a prize.">
          <div class="q-text">What has Abdi won?</div><textarea class="text-answer"></textarea><button class="check-btn">Check Answer</button></div></div>
          <div id="writingActivity"><div class="task"><h2>Write sentences</h2>
          <div class="item fixed" data-answers="has won"><div class="sentence-line">He ___ a prize.</div><input><button class="check">Check Answer</button></div>
          <div class="item open-item"><label class="open-label">Use win</label><textarea></textarea><div class="sample">Sample answer: She has won a medal.</div><button class="open-check">Check</button></div></div></div>
          <script>var correctMap={L1:'R1',L2:'R2'};</script></div>`);
        await page.addScriptTag({path:path.resolve(__dirname,'../../custom-activity-reporting.js')});
        const click=selector=>frame.locator(selector).click();
        await click('.left-item[data-key=L1]');await click('.right-item[data-key=R2]');await click('#checkBtn');
        assert.equal(await page.evaluate(()=>saved.length),0,'Incomplete matching must not be graded');
        await click('.right-item[data-key=R1]');await click('.left-item[data-key=L2]');await click('#checkBtn');
        await page.waitForFunction(()=>saved.flatMap(p=>p.blocks.flatMap(b=>b.events)).length===2);
        let events=await page.evaluate(()=>saved.flatMap(p=>p.blocks.flatMap(b=>b.events)));
        assert.deepEqual(events.map(e=>[e.label,e.value,e.expected,e.result]),[['has won','cross','win','incorrect'],['have crossed','win','cross','incorrect']]);
        await click('#resetBtn');await click('#checkBtn');
        await click('.left-item[data-key=L1]');await click('.right-item[data-key=R1]');
        await click('.left-item[data-key=L2]');await click('.right-item[data-key=R2]');await click('#checkBtn');
        await frame.locator('.text-answer').fill('  A PRIZE.  ');await click('.check-btn');
        await frame.locator('.fixed input').fill('insult');await click('.fixed .check');
        await frame.locator('.open-item textarea').fill('My own sentence');await click('.open-check');
        await page.waitForFunction(()=>saved.flatMap(p=>p.blocks.flatMap(b=>b.events)).length===7);
        events=await page.evaluate(()=>saved.flatMap(p=>p.blocks.flatMap(b=>b.events)));
        assert.deepEqual(events.slice(2).map(e=>e.result),['correct','correct','correct','incorrect','neutral']);
        assert.equal(events.at(-1).expected,'Sample answer: She has won a medal.');
        assert.equal(events[4].value,'  A PRIZE.  ');
        assert.equal(await page.evaluate(()=>saved.every(p=>p.blocks.every(b=>b.ms===0&&b.visits===0))),true);
        assert.equal(new Set(events.map(e=>e.n)).size,7);
        await page.close();
    } finally { await browser.close(); }
});
