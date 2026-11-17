'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function api(mode = 'normal') {
    const source = fs.readFileSync(path.resolve(__dirname, '../../datamodels/scorm_12.js'), 'utf8');
    const fields = [...source.matchAll(/def\[scoid\]\['([^']+)'\]/g)].map(match => match[1]);
    const defaults = Object.fromEntries(fields.map(key => [key, '']));
    Object.assign(defaults, {'cmi.core.lesson_mode': mode, 'cmi.core.student_id': 'learner-one',
        'cmi.core.total_time': '00:00:00', 'cmi.core.lesson_status': 'not attempted'});
    const requests = [];
    const sandbox = {console, URLSearchParams, Promise, M: {}, seledascorm_current_node: {scoid: 7},
        fetch: async(url, options) => {requests.push({url, options}); return {ok: true, text: async() => 'true\n0'};}};
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox);
    sandbox.definitions = {7: defaults, 8: {...defaults}};
    vm.runInContext("API = new scormAPI1_2(definitions,{7:''},{7:''},'^[^]{0,255}$','^[^]{0,4096}$',false,4,9,'https://moodle.example','key',7,1,'normal',1,'',false,false,'0');", sandbox);
    return {sandbox, requests};
}

test('usage sender stays bound to its learner, SCO and attempt after Moodle navigation', async() => {
    const {sandbox, requests} = api();
    const sender = sandbox.API.SeledaCreateUsageSender();
    sandbox.seledascorm_current_node.scoid = 8;
    assert.equal(await sender({v: 1, blocks: []}, true), true);
    const request = requests[0];
    const params = new URLSearchParams(request.options.body);
    assert.equal(params.get('scoid'), '7');
    assert.equal(params.get('attempt'), '1');
    assert.equal(params.get('seleda_student'), 'learner-one');
    assert.equal(params.get('sesskey'), 'key');
    assert.equal(request.options.keepalive, true);
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(params.has('cmi__core__lesson_status'), false);
});

test('review and browse modes cannot obtain a usage sender', () => {
    for (const mode of ['review', 'browse']) {
        assert.equal(api(mode).sandbox.API.SeledaCreateUsageSender(), null);
    }
});

test('transport failures report failure so the package can retry', async() => {
    const {sandbox} = api();
    sandbox.fetch = async() => {throw Error('offline');};
    assert.equal(await sandbox.API.SeledaCreateUsageSender()({v: 1}, false), false);
});
