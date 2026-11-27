'use strict';
const test = require('node:test'), assert = require('node:assert/strict');
const path = require('node:path'), {spawnSync} = require('node:child_process');
const {chromium} = require('playwright');
let browser, html;
test.before(async() => {
    const result = spawnSync(process.env.TIMHIRT_PHP || 'php',
        [...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS || '[]'), path.join(__dirname, 'dashboard.php'), '--html'], {encoding:'utf8'});
    assert.equal(result.status, 0, result.stdout + result.stderr);
    html = result.stdout;
    browser = await chromium.launch({headless:true, ...(process.env.TIMHIRT_BROWSER_CHANNEL ? {channel:process.env.TIMHIRT_BROWSER_CHANNEL} : {})});
});
test.after(async() => browser?.close());
test('actual dashboard renders safe labels, focused figures and accessible navigation', async() => {
    const page = await browser.newPage({viewport:{width:1440,height:1100}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent(html);
    assert.equal(await page.locator('.sd-kpi').count(), 5);
    assert.match(await page.locator('.sd-kpi').nth(3).innerText(), /00:00:20/);
    assert.equal(await page.locator('.sd-learner-name').count(), 3);
    assert.equal(await page.evaluate(() => window.injected), undefined);
    assert.equal(await page.locator('.sd-table img').count(), 0);
    assert.equal(await page.locator('.sd-filter-bar select:visible').count(),5);
    assert.equal(await page.locator('.sd-catalogue').count(),0);
    assert.equal(await page.locator('.sd-filter-bar details').count(),0);
    assert.ok(await page.locator('.sd-page-bar svg').count()>0);
    assert.ok(await page.locator('th[aria-sort="descending"]').count());
    assert.match(await page.getByRole('link',{name:'Progress',exact:true}).getAttribute('href'),/sort=progress/);
    const url = await page.locator('.sd-learner-name').last().getAttribute('href');
    assert.match(url, /learner=4/);
    assert.match(url, /attempt=2/);
    assert.deepEqual(errors, []);
    if (process.env.SELEDA_DASHBOARD_SCREENSHOTS) {
        await page.screenshot({path:path.join(process.env.SELEDA_DASHBOARD_SCREENSHOTS,'dashboard-desktop.png'),fullPage:true});
    }
    await page.close();
});
test('dashboard fits smaller viewports while only the learner table scrolls horizontally', async() => {
    const page = await browser.newPage();
    for (const width of [320,375,768,1024]) {
        await page.setViewportSize({width,height:1000});
        await page.setContent(html);
        const sizes = await page.evaluate(() => ({width:innerWidth,body:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll("body *")].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,15).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width,text:e.textContent.slice(0,40)}))}));
        assert.ok(sizes.body <= sizes.width + 1, JSON.stringify(sizes));
        assert.equal(await page.locator('.sd-learner-table').count(), 1);
        assert.equal(await page.locator('.sd-filter-bar').count(),1);
    }
    if (process.env.SELEDA_DASHBOARD_SCREENSHOTS) {
        await page.setViewportSize({width:375,height:900});
        await page.screenshot({path:path.join(process.env.SELEDA_DASHBOARD_SCREENSHOTS,'dashboard-mobile.png'),fullPage:true});
    }
    await page.close();
});
