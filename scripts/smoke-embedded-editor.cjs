'use strict';
// Run against the actual extracted bundle, not mocked editor modules.
const fs = require('node:fs'), path = require('node:path'), http = require('node:http');
const {chromium} = require('playwright');
const root = path.resolve(process.argv[2] || 'dist/static');
const mime = {'.js':'application/javascript','.css':'text/css','.html':'text/html','.json':'application/json',
    '.svg':'image/svg+xml','.woff2':'font/woff2','.png':'image/png'};
(async() => {
    const server = http.createServer((req, res) => {
        const name = decodeURIComponent(new URL(req.url, 'http://fixture').pathname);
        const file = path.resolve(root, '.' + (name === '/' ? '/index.html' : name));
        if (!file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
            res.writeHead(404); return res.end('Not found');
        }
        res.setHeader('Content-Type', mime[path.extname(file)] || 'application/octet-stream');
        fs.createReadStream(file).pipe(res);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const browser = await chromium.launch({headless:true});
    try {
        const page = await browser.newPage();
        const errors = [], missing = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('response', response => {if (response.status() === 404) missing.push(response.url());});
        const origin = 'http://127.0.0.1:' + server.address().port;
        await page.goto(origin + '/index.html', {waitUntil:'domcontentloaded'});
        await page.waitForFunction(() => window.eXeLearning?.app?.project &&
            (() => {const screen=document.querySelector('#load-screen-main');return !screen || screen.dataset.visible === 'false' || getComputedStyle(screen).display === 'none';})(), null, {timeout:90000});
        await page.waitForTimeout(1000);
        if (missing.length || errors.length) throw Error(JSON.stringify({missing, errors}, null, 2));
        console.log('PASS: actual embedded editor starts without missing assets or uncaught JavaScript errors');
    } finally {
        await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
