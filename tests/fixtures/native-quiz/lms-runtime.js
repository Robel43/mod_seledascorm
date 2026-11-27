/* Seleda Studio LMS Reporting Manager v1
 *
 * Shared tracking layer for Seleda Studio Content Blocks. Graded activities
 * delegate to eXeLearning's existing gamification SCORM/xAPI aggregator.
 * Seleda adds completion-only tracking, required/optional activities, score
 * policies, compact resume state, and detailed SCORM interactions.
 */
(function (root) {
    'use strict';

    if (!root) return;
    if (root.SeledaLMS || root.TimhirtLMS) {
        root.SeledaLMS = root.SeledaLMS || root.TimhirtLMS;
        return;
    }

    var VERSION = 1;
    var PREFIX = 'TS1:';
    var SEP = '.\t';
    var MAX_SUSPEND = 3900;
    var registry = {};
    var state = { v: VERSION, b: {} };
    var restored = false;
    var gamificationPatched = false;
    var unloadPatched = false;
    var warnedLimit = false;
    var lastReport = null;
    var usageSender = null;
    function prepareUsage() {
        if (!usageSender) {
            var raw = rawScormApi();
            if (raw && typeof raw.handle.SeledaCreateUsageSender === 'function') usageSender = raw.handle.SeledaCreateUsageSender();
        }
        return !!usageSender;
    }

    function clamp(value, min, max) {
        var n = parseFloat(value);
        if (isNaN(n)) n = min;
        return Math.max(min, Math.min(max, n));
    }

    function toInt(value, fallback) {
        var n = parseInt(value, 10);
        return isNaN(n) ? fallback : n;
    }

    function toBool(value, fallback) {
        if (value === undefined || value === null) return !!fallback;
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function normaliseSettings(input, defaultMode) {
        var source = input || {};
        var mode = source.mode || defaultMode || 'completion';
        if (['off', 'completion', 'graded'].indexOf(mode) < 0) mode = defaultMode || 'completion';
        var policy = source.scorePolicy || 'latest';
        if (['latest', 'best', 'first'].indexOf(policy) < 0) policy = 'latest';
        return {
            mode: mode,
            required: mode === 'off' ? false : toBool(source.required, true),
            weight: clamp(source.weight == null ? 1 : source.weight, 1, 100),
            passingScore: clamp(source.passingScore == null ? 50 : source.passingScore, 0, 100),
            scorePolicy: policy,
            detailedInteractions: toBool(source.detailedInteractions, mode === 'graded'),
            resume: toBool(source.resume, false),
        };
    }

    function modeCode(mode) {
        return mode === 'graded' ? 'g' : mode === 'completion' ? 'c' : 'o';
    }

    function policyCode(policy) {
        return policy === 'best' ? 'b' : policy === 'first' ? 'f' : 'l';
    }

    function policyName(code) {
        return code === 'b' ? 'best' : code === 'f' ? 'first' : 'latest';
    }

    function hasTrackedBlocks() {
        return Object.keys(state.b || {}).some(function (id) {
            return state.b[id] && state.b[id].m !== 'o';
        });
    }

    function isScormPage() {
        try {
            return !!(root.document && root.document.body && root.document.body.classList.contains('exe-scorm'));
        } catch (e) {
            return false;
        }
    }

    function rawScormApi() {
        if (root.SeledaPageUI && root.SeledaPageUI.sessionFinished) return null;
        function find(win) {
            var tries = 0;
            while (win && tries < 20) {
                try {
                    if (win.API_1484_11) return { handle: win.API_1484_11, version: '2004' };
                    if (win.API) return { handle: win.API, version: '1.2' };
                    if (!win.parent || win.parent === win) break;
                    win = win.parent;
                } catch (e) {
                    break;
                }
                tries++;
            }
            return null;
        }
        var found = find(root);
        if (found) return found;
        try {
            if (root.top && root.top.opener) return find(root.top.opener);
            if (root.opener) return find(root.opener);
        } catch (e) {}
        return null;
    }

    function scormApi() {
        if (root.SeledaPageUI && root.SeledaPageUI.sessionFinished) return null;
        try {
            if (root.pipwerks && root.pipwerks.SCORM) {
                var api = root.pipwerks.SCORM;
                if (typeof api.init === 'function' && !(api.connection && api.connection.isActive)) {
                    // eXeSCORM resets its in-memory model even on a rejected
                    // duplicate LMSInitialize. Probe first; never retry init
                    // merely because this wrapper did not open the session.
                    var raw = rawScormApi();
                    if (raw && raw.handle) {
                        var handle = api.API && typeof api.API.getHandle === 'function' ? api.API.getHandle() : raw.handle;
                        if (handle === raw.handle) {
                            var status = raw.version === '1.2' ? handle.LMSGetValue('cmi.core.lesson_status') : handle.GetValue('cmi.completion_status');
                            var error = raw.version === '1.2' ? handle.LMSGetLastError() : handle.GetLastError();
                            if (String(error) === '0' && api.connection) {
                                api.connection.isActive = true;
                                if (api.data) api.data.completionStatus = status;
                            } else if (String(error) === '301' || String(error) === '132') {
                                try { api.init(); } catch (e) {}
                            }
                        }
                    }
                }
                return api;
            }
        } catch (e) {}
        return null;
    }

    function rawGet(name) {
        var raw = rawScormApi();
        if (!raw || !raw.handle) return '';
        try {
            if (raw.version === '1.2' && typeof raw.handle.LMSGetValue === 'function') {
                var value12 = raw.handle.LMSGetValue(name);
                if (typeof raw.handle.LMSGetLastError === 'function' && String(raw.handle.LMSGetLastError()) === '301'
                    && typeof raw.handle.LMSInitialize === 'function') {
                    try { raw.handle.LMSInitialize(''); } catch (e) {}
                    value12 = raw.handle.LMSGetValue(name);
                }
                return value12 == null ? '' : String(value12);
            }
            if (raw.version === '2004' && typeof raw.handle.GetValue === 'function') {
                var value04 = raw.handle.GetValue(name);
                if (typeof raw.handle.GetLastError === 'function' && String(raw.handle.GetLastError()) === '132'
                    && typeof raw.handle.Initialize === 'function') {
                    try { raw.handle.Initialize(''); } catch (e) {}
                    value04 = raw.handle.GetValue(name);
                }
                return value04 == null ? '' : String(value04);
            }
        } catch (e) {}
        return '';
    }

    function scormGet(name) {
        var api = scormApi();
        if (api && typeof api.get === 'function') {
            try {
                var value = api.get(name);
                if (value !== null && value !== undefined && value !== '' && value !== 'null' && value !== 'undefined') return value;
            } catch (e) {}
        }
        return rawGet(name);
    }

    function rawSet(name, value) {
        var raw = rawScormApi();
        if (!raw || !raw.handle) return false;
        var stringValue = String(value);
        try {
            if (raw.version === '1.2' && typeof raw.handle.LMSSetValue === 'function') {
                var result12 = raw.handle.LMSSetValue(name, stringValue);
                if (String(result12).toLowerCase() !== 'true'
                    && typeof raw.handle.LMSGetLastError === 'function'
                    && String(raw.handle.LMSGetLastError()) === '301'
                    && typeof raw.handle.LMSInitialize === 'function') {
                    try { raw.handle.LMSInitialize(''); } catch (e) {}
                    result12 = raw.handle.LMSSetValue(name, stringValue);
                }
                return String(result12).toLowerCase() === 'true';
            }
            if (raw.version === '2004' && typeof raw.handle.SetValue === 'function') {
                var result04 = raw.handle.SetValue(name, stringValue);
                if (String(result04).toLowerCase() !== 'true'
                    && typeof raw.handle.GetLastError === 'function'
                    && String(raw.handle.GetLastError()) === '132'
                    && typeof raw.handle.Initialize === 'function') {
                    try { raw.handle.Initialize(''); } catch (e) {}
                    result04 = raw.handle.SetValue(name, stringValue);
                }
                return String(result04).toLowerCase() === 'true';
            }
        } catch (e) {}
        return false;
    }

    function scormSet(name, value) {
        var stringValue = String(value);
        var api = scormApi();
        if (api) {
            try {
                if (typeof api.set === 'function' && String(api.set(name, stringValue)).toLowerCase() === 'true') return true;
            } catch (e) {}
            try {
                var handle = api.API && typeof api.API.getHandle === 'function' ? api.API.getHandle() : null;
                if (handle) {
                    if ((api.version === '1.2' || !api.version) && typeof handle.LMSSetValue === 'function'
                        && String(handle.LMSSetValue(name, stringValue)).toLowerCase() === 'true') return true;
                    if (api.version === '2004' && typeof handle.SetValue === 'function'
                        && String(handle.SetValue(name, stringValue)).toLowerCase() === 'true') return true;
                }
            } catch (e) {}
        }
        return rawSet(name, stringValue);
    }

    function rawSave() {
        var raw = rawScormApi();
        if (!raw || !raw.handle) return false;
        try {
            if (raw.version === '1.2' && typeof raw.handle.LMSCommit === 'function') {
                var result12 = raw.handle.LMSCommit('');
                if (String(result12).toLowerCase() !== 'true'
                    && typeof raw.handle.LMSGetLastError === 'function'
                    && String(raw.handle.LMSGetLastError()) === '301'
                    && typeof raw.handle.LMSInitialize === 'function') {
                    try { raw.handle.LMSInitialize(''); } catch (e) {}
                    result12 = raw.handle.LMSCommit('');
                }
                return String(result12).toLowerCase() === 'true';
            }
            if (raw.version === '2004' && typeof raw.handle.Commit === 'function') {
                var result04 = raw.handle.Commit('');
                if (String(result04).toLowerCase() !== 'true'
                    && typeof raw.handle.GetLastError === 'function'
                    && String(raw.handle.GetLastError()) === '132'
                    && typeof raw.handle.Initialize === 'function') {
                    try { raw.handle.Initialize(''); } catch (e) {}
                    result04 = raw.handle.Commit('');
                }
                return String(result04).toLowerCase() === 'true';
            }
        } catch (e) {}
        return false;
    }

    function scormSave() {
        var api = scormApi();
        if (api) {
            try {
                if (typeof api.save === 'function' && String(api.save()).toLowerCase() === 'true') return true;
            } catch (e) {}
            try {
                var handle = api.API && typeof api.API.getHandle === 'function' ? api.API.getHandle() : null;
                if (handle) {
                    if ((api.version === '1.2' || !api.version) && typeof handle.LMSCommit === 'function'
                        && String(handle.LMSCommit('')).toLowerCase() === 'true') return true;
                    if (api.version === '2004' && typeof handle.Commit === 'function'
                        && String(handle.Commit('')).toLowerCase() === 'true') return true;
                }
            } catch (e) {}
        }
        return rawSave();
    }

    function localKey() {
        var path = '';
        try { path = root.location ? (root.location.pathname || root.location.href || '') : ''; } catch (e) {}
        return 'timhirt-lms:' + path;
    }

    function readLocal() {
        try {
            if (!root.localStorage) return null;
            var raw = root.localStorage.getItem(localKey());
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            return parsed && parsed.v === VERSION && parsed.b ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function writeLocal() {
        try {
            if (root.localStorage) root.localStorage.setItem(localKey(), JSON.stringify(state));
        } catch (e) {}
    }

    function splitSuspend(raw) {
        var parts = String(raw || '').split(SEP);
        var base = [];
        var fragment = '';
        parts.forEach(function (part) {
            if (part.indexOf(PREFIX) === 0) fragment = part.substring(PREFIX.length);
            else if (part !== '') base.push(part);
        });
        return { base: base.join(SEP), fragment: fragment };
    }

    function parseRemote(raw) {
        var fragment = splitSuspend(raw).fragment;
        if (!fragment) return null;
        try {
            var parsed = JSON.parse(fragment);
            return parsed && parsed.v === VERSION && parsed.b ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function restore() {
        if (restored) return;
        restored = true;
        // Moodle owns learner/attempt identity. A pathname-only browser cache
        // must never complete a new attempt or seed another learner's grade.
        if (!isScormPage() && !scormApi() && !rawScormApi()) {
            var local = readLocal();
            if (local) state = local;
        }
        var remote = parseRemote(scormGet('cmi.suspend_data'));
        if (remote) state = remote;
        if (!state || !state.b) state = { v: VERSION, b: {} };
    }

    function compact(includeResume) {
        var output = { v: VERSION, b: {} };
        Object.keys(state.b || {}).forEach(function (id) {
            var item = state.b[id] || {};
            if (item.m === 'o') return;
            var c = {
                m: item.m || 'c',
                r: item.r ? 1 : 0,
                w: clamp(item.w == null ? 1 : item.w, 1, 100),
                p: clamp(item.p == null ? 50 : item.p, 0, 100),
                q: item.q || 'l',
                c: item.c ? 1 : 0,
                a: Math.max(0, toInt(item.a, 0)),
            };
            ['s', 'l', 'b', 'f'].forEach(function (key) {
                if (item[key] != null) c[key] = clamp(item[key], 0, 100);
            });
            if (item.d) c.d = item.d;
            if (includeResume && item.x !== undefined) c.x = item.x;
            output.b[id] = c;
        });
        return output;
    }

    function buildSuspend(base, includeResume) {
        var data = compact(includeResume);
        if (!Object.keys(data.b).length) return base || '';
        var fragment = PREFIX + JSON.stringify(data);
        return base ? base + SEP + fragment : fragment;
    }

    function mergeSuspendData(baseData) {
        restore();
        var base = splitSuspend(baseData || '').base;
        var withResume = buildSuspend(base, true);
        if (withResume.length <= MAX_SUSPEND) return withResume;
        var coreOnly = buildSuspend(base, false);
        if (coreOnly.length <= MAX_SUSPEND) {
            if (!warnedLimit && root.console) {
                warnedLimit = true;
                console.warn('[SeledaLMS] suspend_data limit reached; optional resume details were omitted.');
            }
            return coreOnly;
        }
        if (!warnedLimit && root.console) {
            warnedLimit = true;
            console.warn('[SeledaLMS] suspend_data is already too large; Seleda state was not written.');
        }
        return base;
    }

    function persist() {
        restore();
        if (!isScormPage() && !scormApi() && !rawScormApi()) writeLocal();
        if (!hasTrackedBlocks()) return false;
        var api = scormApi();
        if (!api && !rawScormApi()) return false;
        var current = scormGet('cmi.suspend_data');
        var serialized = mergeSuspendData(splitSuspend(current).base);
        var saved = scormSet('cmi.suspend_data', serialized);
        return scormSave() && saved && scormGet('cmi.suspend_data') === serialized;
    }

    function elementFrom(value) {
        if (!value) return null;
        if (value.nodeType === 1) return value;
        if (typeof value === 'string' && root.document) {
            try { return root.document.querySelector(value); } catch (e) { return null; }
        }
        return null;
    }

    function safeId(value) {
        return String(value || '').replace(/[^A-Za-z0-9_.:-]+/g, '-').replace(/^-+|-+$/g, '');
    }

    // Shared by every adapter. Labels stay in exported content, outside the
    // constrained SCORM suspend/response fields. Revision keys prevent an old
    // response being silently labelled with a later edited question.
    function reportHash(value) {
        var text = String(value), hash = 2166136261;
        for (var i = 0; i < text.length; i++) hash = Math.imul(hash ^ text.charCodeAt(i), 16777619);
        return ('00000000' + (hash >>> 0).toString(16)).slice(-8);
    }

    function reportingDefinition(options, element) {
        try {
            var source = options.reportDefinition;
            if (!source && element && element.getAttribute) source = element.getAttribute('data-timhirt-reporting');
            if (!source) return null;
            var raw = typeof source === 'string' ? source : JSON.stringify(source);
            var definition = JSON.parse(raw);
            if (definition.schema !== 1 || !Array.isArray(definition.items)) return null;
            return { definition: definition, revision: reportHash(raw) };
        } catch (e) { return null; }
    }

    function reportingId(block, item, revision) {
        if (!revision) return safeId(block + '-' + item).slice(0, 250);
        return safeId(block).slice(0, 100) + '-r1-' + revision + '-'
            + safeId(item).slice(0, 100) + '-' + reportHash(block + '\u0000' + item);
    }

    function encodeResponse(type, value) {
        if (value == null) return '';
        if (type === 'true-false' && typeof value === 'boolean') return value ? 't' : 'f';
        if (!Array.isArray(value)) return String(value);
        return value.map(function (part) {
            if (type === 'matching' && part && typeof part === 'object') {
                if (part.source == null || part.target == null) throw new Error('Matching responses require source and target IDs');
                return String(part.source) + '.' + String(part.target);
            }
            if (part == null || typeof part === 'object') throw new Error('Response lists require scalar values');
            return String(part);
        }).join(',');
    }

    function identity(options) {
        var element = elementFrom(options.element);
        var node = element && element.closest ? element.closest('.idevice_node') : null;
        var id = safeId(options.id || (node && node.id) || (element && element.id));
        if (!id) id = 'timhirt-' + Math.random().toString(36).slice(2, 10);
        if (element && !element.id) element.id = 'timhirt-track-' + id;
        var title = options.title || '';
        if (!title && node) {
            var heading = node.querySelector('header .box-title, .box-title');
            if (heading) title = (heading.textContent || '').trim();
        }
        return { id: id, element: element, node: node, title: title || options.type || 'Content Block' };
    }

    function gameFor(record) {
        if (!record.element) return null;
        if (!record.element.id) record.element.id = 'timhirt-track-' + record.id;
        return {
            main: record.element.id,
            idevice: record.type,
            ideviceType: record.type,
            isScorm: 1,
            textButtonScorm: '',
            weighted: record.settings.weight,
            scorerp: 0,
            gameStarted: false,
            gameOver: false,
            repeatActivity: true,
            userName: '',
            previousScore: '0',
            msgs: {
                msgYouScore: 'Score', msgScore: 'Score', msgWeight: 'Weight',
                msgScoreScorm: 'Score reporting is available in SCORM exports.',
                msgYouLastScore: 'Previous score', msgSaveAuto: 'Score saved automatically.',
                msgPlaySeveralTimes: '', msgOnlySaveAuto: 'Score saved automatically.',
                msgActityComply: 'Activity completed.', msgSeveralScore: '',
                msgOnlySaveScore: 'Score saved.',
                msgEndGameScore: 'Complete the activity before reporting a score.',
            },
        };
    }

    function registerWithExe(record) {
        if (record.settings.mode !== 'graded' || record.exeRegistered) return;
        try {
            var g = root.$exeDevices && root.$exeDevices.iDevice && root.$exeDevices.iDevice.gamification;
            if (!g || !g.scorm || typeof g.scorm.registerActivity !== 'function') return;
            record.game = gameFor(record);
            if (!record.game) return;
            g.scorm.registerActivity(record.game);
            record.exeRegistered = true;
            var previous = parseFloat(record.game.previousScore);
            if (!isNaN(previous) && previous > 0 && state.b[record.id] && state.b[record.id].s == null) {
                state.b[record.id].s = clamp(previous * 10, 0, 100);
            }
        } catch (e) {
            if (root.console) console.warn('[SeledaLMS] Could not register graded block with eXe tracking.', e);
        }
    }

    function applyScorePolicy(entry, incoming) {
        var score = clamp(incoming, 0, 100);
        if (entry.f == null) entry.f = score;
        entry.l = score;
        entry.b = entry.b == null ? score : Math.max(entry.b, score);
        var policy = policyName(entry.q);
        return policy === 'best' ? entry.b : policy === 'first' ? entry.f : entry.l;
    }

    function reportRawGrade() {
        // eXe's sendScoreNew exits when pipwerks is unavailable. Some Moodle
        // players expose only window.API. Reuse the SAME upstream parser,
        // serializer and weighted aggregation with our working transport.
        var g = root.$exeDevices && root.$exeDevices.iDevice && root.$exeDevices.iDevice.gamification;
        var tools = g && g.scorm;
        if (!rawScormApi() || !tools || typeof tools.parseSuspendData !== 'function'
            || typeof tools.convertToLineFormat !== 'function' || typeof tools.getFinalScore !== 'function') return false;
        var data = tools.parseSuspendData(scormGet('cmi.suspend_data'));
        var serializerGame = null;
        var nodes = root.document ? Array.prototype.slice.call(root.document.querySelectorAll('.idevice_node')) : [];
        Object.keys(registry).forEach(function (id) {
            var record = registry[id];
            if (record.settings.mode !== 'graded') return;
            var entry = state.b[id];
            var number = record.game && record.game.ideviceNumber;
            if (!(number > 0)) number = nodes.indexOf(record.node) + 1;
            if (!(number > 0)) return;
            serializerGame = record.game || gameFor(record);
            data[number] = { title: record.title, score: entry.s == null ? 0 : entry.s, weighted: record.settings.weight };
        });
        if (!serializerGame) return false;
        var suspendSaved = scormSet('cmi.suspend_data', mergeSuspendData(tools.convertToLineFormat(data, serializerGame)));
        var version = scormVersion();
        var prefix = version === '2004' ? 'cmi.score.' : 'cmi.core.score.';
        var minSaved = scormSet(prefix + 'min', 0);
        var maxSaved = scormSet(prefix + 'max', 100);
        var scoreSaved = scormSet(prefix + 'raw', tools.getFinalScore(data));
        return scormSave() && suspendSaved && minSaved && maxSaved && scoreSaved;
    }

    function gradeReachedLms(record, score) {
        try {
            var g = root.$exeDevices.iDevice.gamification.scorm;
            var data = g.parseSuspendData(scormGet('cmi.suspend_data'));
            var entry = record.game && data[record.game.ideviceNumber];
            var field = scormVersion() === '2004' ? 'cmi.score.raw' : 'cmi.core.score.raw';
            return !!entry && Number(entry.score) === score
                && parseFloat(scormGet(field)) === g.getFinalScore(data);
        } catch (e) { return false; }
    }

    function friendlyState(id) {
        restore();
        var item = state.b[id];
        if (!item) return null;
        return {
            completed: !!item.c,
            attempts: Math.max(0, toInt(item.a, 0)),
            score: item.s == null ? null : clamp(item.s, 0, 100),
            passed: item.m === 'g' && item.s != null ? item.s >= clamp(item.p == null ? 50 : item.p, 0, 100) : null,
            resume: item.x === undefined ? null : item.x,
        };
    }

    function register(options) {
        restore();
        options = options || {};
        var ident = identity(options);
        var settings = normaliseSettings(options.settings, options.defaultMode || 'completion');
        var record = {
            id: ident.id, element: ident.element, node: ident.node, title: ident.title,
            type: options.type || 'timhirt-content-block', settings: settings,
            game: null, exeRegistered: false,
            reporting: reportingDefinition(options, ident.element),
        };
        registry[record.id] = record;

        if (settings.mode !== 'off') {
            var entry = state.b[record.id] || {};
            var revision = record.reporting && record.reporting.revision;
            var previousRevision = entry.d || (entry.x && entry.x.r);
            if (revision && previousRevision && previousRevision !== revision) entry = { a: entry.a || 0 };
            if (revision) entry.d = revision;
            entry.m = modeCode(settings.mode);
            entry.r = settings.required ? 1 : 0;
            entry.w = settings.weight;
            entry.p = settings.passingScore;
            entry.q = policyCode(settings.scorePolicy);
            if (entry.c == null) entry.c = 0;
            if (entry.a == null) entry.a = 0;
            state.b[record.id] = entry;
            registerWithExe(record);
            persist();
            reconcileStatus();
        }

        return {
            id: record.id,
            settings: settings,
            getState: function () { return friendlyState(record.id); },
            getResume: function () {
                var current = friendlyState(record.id);
                return current ? current.resume : null;
            },
            report: function (update) { return report(record.id, update); },
            interaction: function (interaction) { return recordInteraction(record.id, interaction); },
        };
    }

    function report(id, update) {
        restore();
        var record = registry[id];
        var entry = state.b[id];
        if (!record || !entry || record.settings.mode === 'off') return null;
        update = update || {};
        var gradeSaved = null;

        if (update.completed !== undefined) entry.c = update.completed ? 1 : 0;
        if (update.attempt != null) entry.a = Math.max(entry.a || 0, toInt(update.attempt, 0));
        else if (update.incrementAttempt) entry.a = Math.max(0, toInt(entry.a, 0)) + 1;
        if (record.settings.resume && update.resume !== undefined) entry.x = update.resume;

        if (record.settings.mode === 'graded' && update.score != null) {
            entry.s = applyScorePolicy(entry, update.score);
            registerWithExe(record);
            if (record.game) {
                try {
                    var g = root.$exeDevices && root.$exeDevices.iDevice && root.$exeDevices.iDevice.gamification;
                    record.game.scorerp = entry.s / 10;
                    record.game.gameStarted = true;
                    record.game.gameOver = !!entry.c;
                    record.game.weighted = record.settings.weight;
                    if (g && g.scorm && typeof g.scorm.sendScoreNew === 'function') {
                        g.scorm.sendScoreNew(true, record.game);
                        gradeSaved = gradeReachedLms(record, entry.s);
                    }
                } catch (e) {
                    if (root.console) console.warn('[SeledaLMS] Could not delegate graded result to eXe tracking.', e);
                }
            }
            if (!gradeSaved) {
                gradeSaved = reportRawGrade();
            }
        }

        var stateSaved = persist();
        var status = reconcileStatus();
        if (gradeSaved !== null) gradeSaved = gradeSaved && stateSaved && gradeReachedLms(record, entry.s);
        lastReport = { id: id, gradeSaved: gradeSaved, stateSaved: stateSaved, status: status };
        dispatch('timhirt-lms-report', { id: id, type: record.type, update: update, state: friendlyState(id), settings: record.settings });
        return friendlyState(id);
    }

    function interactionType(value) {
        var allowed = ['true-false', 'choice', 'fill-in', 'matching', 'performance', 'sequencing', 'likert', 'numeric'];
        return allowed.indexOf(value) >= 0 ? value : 'performance';
    }

    function resultValue(value) {
        if (typeof value === 'number' && isFinite(value)) return String(clamp(value, 0, 1));
        var r = String(value || '').toLowerCase();
        return ['correct', 'wrong', 'unanticipated', 'neutral'].indexOf(r) >= 0 ? r : 'neutral';
    }

    function nowTime() {
        var d = new Date();
        function pad(n) { return String(n).padStart(2, '0'); }
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    function recordInteraction(id, interaction) {
        var record = registry[id];
        if (!record || record.settings.mode === 'off' || !record.settings.detailedInteractions) return false;
        if (!scormApi() && !rawScormApi()) return false;
        interaction = interaction || {};
        try {
            var item = record.reporting && record.reporting.definition.items.find(function (candidate) {
                return String(candidate.id) === String(interaction.id);
            });
            var type = interactionType(interaction.type || (item && item.type));
            var response = interaction.response == null ? null : encodeResponse(type, interaction.response);
            var correct = interaction.correctResponse == null ? null : encodeResponse(type, interaction.correctResponse);
            var alternatives = Array.isArray(interaction.correctResponses) ? interaction.correctResponses.map(function(value) { return encodeResponse(type, value); }) : (correct == null ? [] : [correct]);
            // Never silently turn a long learner response into a different one.
            if ((response && response.length > 255) || alternatives.length > 20 || alternatives.some(function(value) { return value.length > 255; })) return false;
            var index = Math.max(0, toInt(scormGet('cmi.interactions._count'), 0));
            var base = 'cmi.interactions.' + index;
            var saved = true;
            function set(field, value) { saved = scormSet(base + field, value) && saved; }
            var activityAttempt = Math.max(0, toInt(interaction.attempt, 0));
            set('.id', reportingId(id, interaction.id || ('i' + index), item && record.reporting.revision) + (activityAttempt ? '-a' + activityAttempt : ''));
            set('.type', type);
            if (response != null) set('.student_response', response);
            alternatives.forEach(function(value, n) { set('.correct_responses.' + n + '.pattern', value); });
            set('.result', resultValue(interaction.result));
            if (interaction.weighting != null) set('.weighting', clamp(interaction.weighting, 0, 100));
            set('.time', interaction.time || nowTime());
            saved = scormSave() && saved;
            if (!saved) return false;
            dispatch('timhirt-lms-interaction', { id: id, type: record.type, interaction: interaction });
            return true;
        } catch (e) {
            if (root.console) console.warn('[SeledaLMS] Could not record SCORM interaction.', e);
            return false;
        }
    }

    function hasPendingRequired() {
        restore();
        return Object.keys(state.b || {}).some(function (id) {
            var item = state.b[id] || {};
            return item.m !== 'o' && !!item.r && !item.c;
        });
    }

    function requiredGradedFailed() {
        return Object.keys(state.b || {}).some(function (id) {
            var item = state.b[id] || {};
            return item.m === 'g' && !!item.r && !!item.c && item.s != null && item.s < clamp(item.p == null ? 50 : item.p, 0, 100);
        });
    }

    function hasTimhirtGraded() {
        return Object.keys(state.b || {}).some(function (id) {
            return state.b[id] && state.b[id].m === 'g';
        });
    }

    function scormVersion() {
        try {
            var api = scormApi();
            if (api && api.version) return api.version;
        } catch (e) {}
        var raw = rawScormApi();
        return raw ? raw.version : null;
    }

    function reconcileStatus(scoreHint) {
        restore();
        if (!hasTrackedBlocks()) return null;
        if (!scormApi() && !rawScormApi()) return null;

        var version = scormVersion() || '1.2';
        var rawScoreField = version === '2004' ? 'cmi.score.raw' : 'cmi.core.score.raw';
        var raw = parseFloat(scoreHint);
        if (isNaN(raw)) raw = parseFloat(scormGet(rawScoreField));
        var status;
        if (hasPendingRequired()) {
            status = 'incomplete';
        } else if (hasTimhirtGraded() && isNaN(raw)) {
            status = 'incomplete';
        } else if (hasTimhirtGraded() || !isNaN(raw)) {
            status = (!requiredGradedFailed() && (isNaN(raw) || raw >= 50)) ? 'passed' : 'failed';
        } else {
            status = 'completed';
        }

        var statusSaved;
        if (version === '2004') {
            statusSaved = scormSet('cmi.completion_status', status === 'incomplete' ? 'incomplete' : 'completed');
            statusSaved = scormSet('cmi.success_status', status === 'passed' ? 'passed' : status === 'failed' ? 'failed' : 'unknown') && statusSaved;
        } else {
            statusSaved = scormSet('cmi.core.lesson_status', status);
        }
        return scormSave() && statusSaved ? status : null;
    }

    function dispatch(name, detail) {
        try {
            if (typeof root.CustomEvent === 'function' && root.dispatchEvent) {
                root.dispatchEvent(new root.CustomEvent(name, { detail: detail }));
            }
        } catch (e) {}
    }

    function patchGamification() {
        if (gamificationPatched) return;
        try {
            var g = root.$exeDevices && root.$exeDevices.iDevice && root.$exeDevices.iDevice.gamification;
            var tools = g && g.scorm;
            if (!tools) return;

            if (typeof tools.convertToLineFormat === 'function' && !tools.convertToLineFormat._timhirtWrapped) {
                var originalConvert = tools.convertToLineFormat;
                var wrappedConvert = function () {
                    return api.mergeSuspendData(originalConvert.apply(this, arguments));
                };
                wrappedConvert._timhirtWrapped = true;
                tools.convertToLineFormat = wrappedConvert;
            }

            if (typeof tools.showFinalScore === 'function' && !tools.showFinalScore._timhirtWrapped) {
                var originalShow = tools.showFinalScore;
                var wrappedShow = function () {
                    var result = originalShow.apply(this, arguments);
                    api.reconcileStatus();
                    return result;
                };
                wrappedShow._timhirtWrapped = true;
                tools.showFinalScore = wrappedShow;
            }
            gamificationPatched = true;
        } catch (e) {
            if (root.console) console.warn('[SeledaLMS] Could not attach to eXe gamification tracking.', e);
        }
    }

    function patchUnload() {
        if (unloadPatched || typeof root.unloadPage !== 'function') return;
        var originalUnload = root.unloadPage;
        root.unloadPage = function () {
            if (root.SeledaPageUI && root.SeledaPageUI.sessionFinished) return;
            if (!hasTrackedBlocks()) return originalUnload.apply(this, arguments);
            api.persist();
            api.reconcileStatus();
            if (api.hasPendingRequired() && typeof root.doQuit === 'function') return root.doQuit();
            return originalUnload.apply(this, arguments);
        };
        root.unloadPage._timhirtWrapped = true;
        unloadPatched = true;
    }

    var api = {
        version: VERSION,
        reporting: { hash: reportHash, interactionId: reportingId, encodeResponse: encodeResponse },
        defaults: normaliseSettings,
        register: register,
        report: report,
        recordInteraction: recordInteraction,
        // Usage stays outside suspend data and the grade/interaction aggregator.
        // Moodle's authenticated bridge binds it to the current learner/SCO/attempt.
        saveUsage: function (payload, closing) {
            // Never initialize or probe the SCORM session just for analytics.
            // A sender is bound to this document before Moodle advances SCOs.
            // Never bind for the first time during teardown: the player may
            // already have selected the next SCO by then.
            if (!usageSender && closing) return Promise.resolve(false);
            return prepareUsage() ? usageSender(payload, !!closing) : Promise.resolve(false);
        },
        prepareUsage: prepareUsage,
        getState: friendlyState,
        hasPendingRequired: hasPendingRequired,
        reconcileStatus: reconcileStatus,
        mergeSuspendData: mergeSuspendData,
        persist: persist,
        _splitSuspend: splitSuspend,
        _state: function () { restore(); return state; },
        diagnostics: function () {
            var raw = rawScormApi();
            var api = scormApi();
            var version = scormVersion();
            var statusField = version === '2004' ? 'cmi.success_status' : 'cmi.core.lesson_status';
            var scoreField = version === '2004' ? 'cmi.score.raw' : 'cmi.core.score.raw';
            var lastError = null;
            try {
                if (raw && raw.handle) {
                    if (raw.version === '1.2' && typeof raw.handle.LMSGetLastError === 'function') lastError = raw.handle.LMSGetLastError();
                    if (raw.version === '2004' && typeof raw.handle.GetLastError === 'function') lastError = raw.handle.GetLastError();
                }
            } catch (e) {}
            return {
                runtimeVersion: 'seleda-lms-1.5.0',
                version: version,
                isScormPage: isScormPage(),
                hasPipwerks: !!api,
                hasRawApi: !!raw,
                rawApiVersion: raw ? raw.version : null,
                connectionActive: !!(api && api.connection && api.connection.isActive),
                status: scormGet(statusField),
                score: scormGet(scoreField),
                lastError: lastError,
                lastReport: lastReport,
            };
        },
        _patch: function () { patchGamification(); patchUnload(); },
    };

    root.SeledaLMS = api;
    root.TimhirtLMS = api; // Compatibility with saved HTML and earlier custom blocks.
    patchGamification();
    patchUnload();
    // Restore lazily when a block registers, after export scripts have loaded.
    // Opening a raw session here can race the upstream pipwerks lifecycle.

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', function () {
            patchGamification();
            patchUnload();
            if (hasTrackedBlocks()) reconcileStatus();
        });
    }
    if (root.addEventListener) {
        root.addEventListener('load', function () { patchGamification(); patchUnload(); });
        root.addEventListener('pagehide', function () {
            if (!(root.SeledaPageUI && root.SeledaPageUI.sessionFinished) && hasTrackedBlocks()) { persist(); reconcileStatus(); }
        });
    }
})(typeof window !== 'undefined' ? window : this);

