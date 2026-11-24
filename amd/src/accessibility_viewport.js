// GPL-3.0-or-later. Keep the existing dock inside the visible iframe area.
export const init = () => {
    const page = document.getElementById('seledascormpage');
    if (!page || page.dataset.seledaDockViewport) return;
    page.dataset.seledaDockViewport = '1';
    let frame, doc, innerObserver, pending = false;
    const set = (node, name, value) => {
        if (node.style.getPropertyValue(name) !== value || node.style.getPropertyPriority(name) !== 'important') {
            node.style.setProperty(name,value,'important');
        }
    };
    function loaded() { doc = null; schedule(); }
    function schedule() {
        if (pending) return;
        pending = true; requestAnimationFrame(() => { pending = false; update(); });
    }
    function update() {
        const next = document.getElementById('seledascorm_object');
        if (next !== frame) {
            frame?.removeEventListener('load',loaded); innerObserver?.disconnect(); doc = null;
            frame = next; frame?.addEventListener('load',loaded);
        }
        if (!frame) return;
        try {
            if (frame.contentDocument !== doc) {
                innerObserver?.disconnect(); doc = frame.contentDocument;
                if (doc?.body) {
                    innerObserver = new MutationObserver(schedule);
                    innerObserver.observe(doc.body,{subtree:true,childList:true,attributes:true,attributeFilter:['style','class']});
                }
            }
            const dock = doc?.getElementById('eXeAtools');
            if (!dock) return;
            const rect = frame.getBoundingClientRect();
            const viewport = window.visualViewport;
            const start = Math.max(rect.top,viewport?.offsetTop || 0);
            const end = Math.min(rect.bottom,(viewport?.offsetTop || 0) + (viewport?.height || innerHeight));
            if (end <= start || !rect.height) return;
            const scale = frame.clientHeight / rect.height;
            const top = (start-rect.top)*scale, bottom = (end-rect.top)*scale;
            const panel = doc.getElementById('eXeAtoolsSet');
            const open = doc.body.classList.contains('exe-atools-on');
            const stack = open ? (panel?.querySelectorAll('button').length || 7)*36+42 : 24;
            const anchor = Math.max(top+24,Math.min((top+bottom)/2,bottom-stack));
            set(dock,'--seleda-access-top',Math.round(Math.min(bottom-22,anchor))+'px');
            if (panel) set(panel,'max-height',Math.max(40,Math.floor(bottom-anchor-34))+'px');
        } catch (_) { /* Cross-origin providers cannot expose their toolbar. */ }
    }
    window.addEventListener('scroll',schedule,{capture:true,passive:true});
    window.addEventListener('resize',schedule,{passive:true});
    window.visualViewport?.addEventListener('resize',schedule,{passive:true});
    window.visualViewport?.addEventListener('scroll',schedule,{passive:true});
    document.addEventListener('fullscreenchange',schedule);
    new MutationObserver(schedule).observe(page,{childList:true,subtree:true});
    schedule();
};
