// GPL-3.0-or-later. Adapters for the authored HTML activity contracts.
(function(host) {
    'use strict';
    const wired = new WeakSet();
    function attach(win, owner) {
        let doc;
        try { doc = win.document; if (!doc.body || wired.has(doc)) return; } catch (_) { return; }
        wired.add(doc);
        const send = host.API?.SeledaCreateUsageSender?.();
        if (!send) return; // The API disallows review and browse modes.
        const session = Array.from(host.crypto.getRandomValues(new Uint8Array(12)), n => n.toString(16).padStart(2,'0')).join('');
        const started = Date.now(), records = new Map();
        let saving = false, stopped = false;
        const text = node => (node?.textContent || '').replace(/\s+/g,' ').trim();
        const cleanItem = node => { if (!node) return ''; const copy = node.cloneNode(true); copy.querySelectorAll('.badge,.dot').forEach(n=>n.remove()); return text(copy); };
        function record(node, label, value, expected, result) {
            const block = node.closest('.idevice_node') || owner;
            if (!block?.id) return;
            let r = records.get(block.id);
            if (!r) { r = {id:block.id,title:text(block.querySelector('h1,h2,h3,h4')) || 'Custom activity',type:'text',ms:0,visits:0,actions:{answer:0},dropped:0,events:[]}; records.set(block.id,r); }
            r.actions.answer++;
            if (r.events.length >= 200) { r.events.shift(); r.dropped++; }
            r.events.push({n:r.actions.answer,at:Date.now()-started,action:'answer',label:label.slice(0,160),value:value.slice(0,1000),expected:expected.slice(0,1000),result});
            flush();
        }
        async function flush(closing = false) {
            if (saving) return;
            saving = true;
            try {
                for (const r of records.values()) {
                    while (r.events.length) {
                        const batch = {...r,events:r.events.slice(0,8)};
                        if (!await send({v:1,session,blocks:[batch]},closing)) return;
                        const n = batch.events.at(-1).n;
                        r.events = r.events.filter(e=>e.n>n);
                    }
                }
            } finally { saving = false; }
        }
        // These normalizers deliberately match the supplied activity checkers.
        const reading = v => String(v).trim().toLowerCase().replace(/[’‘]/g,"'").replace(/[“”]/g,'"').replace(/[.,!?;:]+$/g,'').replace(/\s+/g,' ');
        const writing = v => String(v).toLowerCase().replace(/[’]/g,"'").replace(/[.,!?;:]+$/g,'').replace(/\s+/g,' ').trim();
        const matching = doc.getElementById('matching-activity');
        let map = null, left = null, right = null, pairs = {};
        if (matching) {
            // Read only a literal key-to-key object; never evaluate authored JavaScript.
            const source = [...doc.scripts].map(s=>s.textContent).find(s=>/var\s+correctMap\s*=/.test(s)) || '';
            const literal = source.match(/var\s+correctMap\s*=\s*\{([^{}]*)\}/);
            if (literal && /^\s*(?:[A-Za-z]\w*\s*:\s*['"][A-Za-z]\w*['"]\s*,?\s*)+$/.test(literal[1])) {
                map = Object.fromEntries([...literal[1].matchAll(/([A-Za-z]\w*)\s*:\s*['"]([A-Za-z]\w*)['"]/g)].map(m=>[m[1],m[2]]));
                if (![...matching.querySelectorAll('.left-item')].every(n=>map[n.dataset.key] && [...matching.querySelectorAll('.right-item')].some(r=>r.dataset.key===map[n.dataset.key]))) map = null;
            }
        }
        doc.addEventListener('click', e => {
            const button = e.target.closest('button'); if (!button) return;
            if (map && matching.contains(button)) {
                if (button.matches('.left-item')) left = button;
                if (button.matches('.right-item')) right = button;
                if (left && right) {
                    for (const k of Object.keys(pairs)) if (pairs[k] === right) delete pairs[k];
                    pairs[left.dataset.key] = right; left = right = null;
                }
                if (button.id === 'resetBtn') { pairs = {}; left = right = null; }
                if (button.id === 'checkBtn') {
                    const items = [...matching.querySelectorAll('.left-item')];
                    if (items.every(n=>pairs[n.dataset.key])) for (const item of items) {
                        const answer = pairs[item.dataset.key];
                        const expected = [...matching.querySelectorAll('.right-item')].find(n=>n.dataset.key===map[item.dataset.key]);
                        record(item,cleanItem(item),cleanItem(answer),cleanItem(expected),answer===expected?'correct':'incorrect');
                    }
                }
                return;
            }
            const question = button.closest('#readingActivity .question[data-answer]');
            if (question && button.matches('.check-btn')) {
                const value = question.querySelector('.text-answer')?.value || '';
                if (reading(value)) record(question,text(question.querySelector('.q-text')),value,question.dataset.correctText || question.dataset.answer.split('|')[0],question.dataset.answer.split('|').map(reading).includes(reading(value))?'correct':'incorrect');
            }
            const item = button.closest('#writingActivity .item');
            if (item && button.matches('.check,.open-check')) {
                const value = item.querySelector('input,textarea')?.value || '';
                if (!writing(value)) return;
                const task = text(item.closest('.task')?.querySelector('h2'));
                const prompt = text(item.querySelector('.sentence-line,.wrong-sentence,.open-label'));
                if (item.matches('.fixed[data-answers]')) record(item,task+' · '+prompt,value,item.dataset.answers.split('||')[0],item.dataset.answers.split('||').map(writing).includes(writing(value))?'correct':'incorrect');
                else if (item.matches('.open-item')) record(item,task+' · '+prompt,value,text(item.querySelector('.sample')),'neutral');
            }
        },true);
        function scan() { doc.querySelectorAll('iframe').forEach(frame=> { if (!frame.dataset.seledaAssessmentWired) { frame.dataset.seledaAssessmentWired='1'; frame.addEventListener('load',()=>attach(frame.contentWindow,frame.closest('.idevice_node') || owner)); } attach(frame.contentWindow,frame.closest('.idevice_node') || owner); }); }
        scan();
        const observer = new MutationObserver(scan); observer.observe(doc.body,{childList:true,subtree:true});
        const timer = host.setInterval(()=>{ if (!stopped) flush(); },10000);
        win.addEventListener('pagehide',()=>{ stopped=true; observer.disconnect(); host.clearInterval(timer); flush(true); },{once:true});
    }
    function scan() { host.document.querySelectorAll('#seledascorm_object').forEach(frame=>{
        if (!frame.dataset.seledaAssessmentHost) { frame.dataset.seledaAssessmentHost='1'; frame.addEventListener('load',()=>attach(frame.contentWindow)); }
        attach(frame.contentWindow);
    }); }
    if (host.document.readyState === 'loading') host.document.addEventListener('DOMContentLoaded',scan,{once:true}); else scan();
    new MutationObserver(scan).observe(host.document.documentElement,{childList:true,subtree:true});
})(window);
