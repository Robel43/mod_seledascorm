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
    const tocRequests = [], tocResponses = [], transport = {ok:true,bodies:[]};
    const sandbox = {console, URLSearchParams, Promise, M: {}, seledascorm_current_node: {scoid: 7},
        fetch: async(url, options) => {requests.push({url, options}); return {ok: true, text: async() => 'true\n0'};}};
    sandbox.window = sandbox;
    const y = {log(){},one(){},io(url,options){tocRequests.push({url,options});}};
    sandbox.YUI = () => ({use(name,callback){callback(y);}});
    sandbox.underscore = value => String(value).replace(/\./g,'__');
    sandbox.NewHttpReq = () => ({});
    sandbox.DoRequest = (request,url,body) => {transport.bodies.push(body);if(transport.throws)throw Error('offline');return transport.ok?'true\n0':'false\n101';};
    sandbox.M.mod_seledascorm = {connectPrereqCallback:{success(id,response){assert.equal(this,y);tocResponses.push(response.responseText);}}};
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox);
    sandbox.definitions = {7: defaults, 8: {...defaults}};
    vm.runInContext("API = new scormAPI1_2(definitions,{7:''},{7:''},'^[^]{0,255}$','^[^]{0,4096}$',false,4,9,'https://moodle.example','key',7,1,'normal',1,'',false,false,'0');", sandbox);
    return {sandbox, requests, tocRequests, tocResponses, transport};
}

test('completion survives revisiting a SCO and only the newest successful refresh changes the tree',()=>{
    const {sandbox:s,tocRequests,tocResponses}=api();
    assert.equal(s.API.LMSInitialize(''),'true');
    assert.equal(s.API.LMSSetValue('cmi.core.lesson_status','incomplete'),'true');
    assert.equal(s.API.LMSCommit(''),'true');
    assert.equal(s.API.LMSSetValue('cmi.core.lesson_status','completed'),'true');
    assert.equal(s.API.LMSSetValue('cmi.core.score.raw','80'),'true');
    assert.equal(s.API.LMSSetValue('cmi.suspend_data','saved progress'),'true');
    assert.equal(s.API.LMSCommit(''),'true');
    assert.equal(tocRequests.length,2);
    tocRequests[1].options.on.success(2,{responseText:'new completed tree'});
    tocRequests[0].options.on.success(1,{responseText:'old incomplete tree'});
    assert.deepEqual(tocResponses,['new completed tree']);
    assert.equal(s.API.LMSFinish(''),'true');
    s.seledascorm_current_node.scoid=8;
    assert.equal(s.API.LMSInitialize(''),'true');
    assert.equal(s.API.LMSSetValue('cmi.core.lesson_status','incomplete'),'true');
    assert.equal(s.API.LMSFinish(''),'true');
    s.seledascorm_current_node.scoid=7;
    assert.equal(s.API.LMSInitialize(''),'true');
    assert.equal(s.API.LMSGetValue('cmi.core.lesson_status'),'completed');
    assert.equal(Number(s.API.LMSGetValue('cmi.core.score.raw')),80);
    assert.equal(s.API.LMSGetValue('cmi.suspend_data'),'saved progress');
});

test('failed commits do not refresh completion and retry keeps the pending status',()=>{
    const {sandbox:s,tocRequests,transport}=api();
    s.API.LMSInitialize('');s.API.LMSSetValue('cmi.core.lesson_status','incomplete');s.API.LMSCommit('');
    transport.ok=false;s.API.LMSSetValue('cmi.core.lesson_status','completed');
    assert.equal(s.API.LMSCommit(''),'false');assert.equal(tocRequests.length,1);
    transport.ok=true;assert.equal(s.API.LMSCommit(''),'true');assert.equal(tocRequests.length,2);
    assert.match(transport.bodies.at(-1),/cmi__core__lesson_status=completed/);
    transport.throws=true;s.API.LMSSetValue('cmi.core.score.raw','90');
    assert.equal(s.API.LMSCommit(''),'false');assert.equal(tocRequests.length,2);
    transport.throws=false;assert.equal(s.API.LMSCommit(''),'true');
    assert.match(transport.bodies.at(-1),/cmi__core__score__raw=90/);
});

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
