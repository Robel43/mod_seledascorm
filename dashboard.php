<?php
// This file is part of Moodle - https://moodle.org/
// SPDX-License-Identifier: GPL-3.0-or-later

require('../../config.php');
require_once($CFG->dirroot . '/mod/seledascorm/locallib.php');
require_once($CFG->dirroot . '/mod/seledascorm/report/timhirt_reporting.php');
require_once($CFG->dirroot . '/mod/seledascorm/report/seleda_usage.php');

use mod_seledascorm\local\reporting_dashboard as service;
use mod_seledascorm\local\seleda_usage;
use mod_seledascorm\local\timhirt_reporting;

require_login();
if (isguestuser()) {
    throw new required_capability_exception(context_system::instance(), 'mod/seledascorm:viewreport', 'nopermissions', '');
}
$filters = [
    'course' => optional_param('course', 0, PARAM_INT),
    'from' => optional_param('from', '', PARAM_TEXT),
    'to' => optional_param('to', '', PARAM_TEXT),
    'staff' => optional_param('staff', 0, PARAM_BOOL),
    'block' => optional_param('block', '', PARAM_ALPHANUM),
    'type' => optional_param('type', '', PARAM_ALPHANUMEXT),
    'id' => max(0, optional_param('id', 0, PARAM_INT)),
    'q' => optional_param('q', '', PARAM_TEXT),
    'catalogpage' => max(0, optional_param('catalogpage', 0, PARAM_INT)),
    'group' => max(0, optional_param('group', 0, PARAM_INT)),
    'search' => optional_param('search', '', PARAM_TEXT),
    'state' => optional_param('state', '', PARAM_ALPHA),
    'page' => max(0, optional_param('page', 0, PARAM_INT)),
    'learner' => max(0, optional_param('learner', 0, PARAM_INT)),
    'attempt' => max(0, optional_param('attempt', 0, PARAM_INT)),
];
$options = $filters;
foreach (['from', 'to'] as $datekey) {
    if ($filters[$datekey] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$datekey], core_date::get_user_timezone_object());
        if (!$date || $date->format('Y-m-d') !== $filters[$datekey]) { throw new moodle_exception('invalidparameter'); }
        $options[$datekey === 'from' ? 'fromts' : 'tots'] = ($datekey === 'to' ? $date->modify('+1 day') : $date)->getTimestamp();
    }
}
if (!empty($options['fromts']) && !empty($options['tots']) && $options['fromts'] >= $options['tots']) { throw new moodle_exception('invalidparameter'); }
$download = optional_param('download', '', PARAM_ALPHA);
$model = ['filters' => $filters, 'activity' => null];
if ($filters['id']) {
    [$activity, $cm, $course] = service::activity($filters['id']);
    $metrics = service::overview($activity, $cm, $filters['group'], $options);
    $model['activity'] = $activity;
    $model['coursename'] = $course->fullname;
    $titlepages = array_intersect_key($metrics['pages'], array_flip(array_column(array_filter($metrics['blocks'], function($b) { return !empty($b['generic']); }), 'scoid')));
    $titles = \mod_seledascorm\output\dashboard_detail::titles($cm->context, $titlepages);
    // Keys are hashes for links; resolve package IDs to the same hash namespace.
    foreach ($titles as $key => $title) { foreach ($metrics['blocks'] as &$block) { if (!empty($block['generic']) && $block['key'] === hash('sha256', $key)) { $block['title'] = $title; } } unset($block); }
    $model['metrics'] = $metrics;
    $matching = array_filter($metrics['learners'], function($learner) use ($filters) {
        return ($filters['state'] === '' || $filters['state'] === $learner['status']) &&
            ($filters['search'] === '' || mb_stripos($learner['name'], $filters['search'], 0, 'UTF-8') !== false);
    });
    if ($download === 'csv') {
        require_once($CFG->libdir . '/csvlib.class.php');
        $csv = new csv_export_writer();
        $csv->set_filename('seleda-dashboard-' . $activity->id);
        $csv->add_data(array_map(function($key) { return get_string('dash' . $key, 'mod_seledascorm'); },
            ['activity', 'course', 'learner', 'status', 'attempts', 'activetime', 'interactions', 'firstaccess', 'lastaccess']));
        foreach ($matching as $learner) {
            $csv->add_data(array_map([timhirt_reporting::class, 'exportcell'], [
                $activity->name, $course->fullname, $learner['name'], get_string('dash' . $learner['status'], 'mod_seledascorm'),
                $learner['attemptcount'], $learner['tracked'] ? seleda_usage::duration($learner['ms']) : '',
                $learner['tracked'] ? $learner['interactions'] : '', userdate($learner['first']), userdate($learner['last']),
            ]));
        }
        $csv->download_file();
        exit;
    }
    $model['filteredcount'] = count($matching);
    $model['rows'] = array_slice(array_values($matching), $filters['page'] * 25, 25);
    if ($filters['learner']) {
        $learner = $metrics['learners'][$filters['learner']] ?? null;
        if (!$learner) {
            throw new moodle_exception('nopermissiontoshow');
        }
        $attempt = $filters['attempt'] ?: $learner['latest'];
        if (!isset($learner['attempts'][$attempt])) {
            throw new moodle_exception('nopermissiontoshow');
        }
        $model['filters']['attempt'] = $attempt;
        $tracks = service::detail($activity, $cm, $learner['id'], $attempt, $filters['group']);
        $pages = array_map(function($p) { return $p->title; }, $metrics['pages']);
        $catalogues = [];
        foreach ($tracks as $track) {
            if (!array_key_exists($track->scoid, $catalogues)) {
                $catalogues[$track->scoid] = timhirt_reporting::packagecatalog($cm->context, $activity, (int)$track->scoid);
            }
        }
        $questions = timhirt_reporting::rows($tracks, $catalogues);
        foreach ($questions as &$question) {
            $question['page'] = $pages[$question['scoid']] ?? '';
        }
        unset($question);
        $grade = seledascorm_grade_user_attempt($activity, $learner['id'], $attempt);
        $hasscore = array_filter($tracks, function($track) {
            return in_array($track->element, ['cmi.core.score.raw', 'cmi.score.raw']) && is_numeric($track->value);
        });
        if (!$hasscore && $activity->grademethod != SELEDASCORM_GRADESCOES) {
            $grade = null;
        }
        $usage = seleda_usage::summary($tracks);
        foreach ($usage['blocks'] as &$block) { $key = $block['scoid'] . ':' . $block['id']; if (isset($titles[$key]) && in_array(strtolower($block['title']), ['text','untitled',''])) { $block['title'] = $titles[$key]; } } unset($block);
        $model['detail'] = ['name' => $learner['name'], 'attempts' => array_keys($learner['attempts']),
            'active' => $usage['available'] ? seleda_usage::duration($usage['ms']) : get_string('dashnodata','mod_seledascorm'),
            'completion' => \mod_seledascorm\output\dashboard_detail::completion($tracks, $metrics['pages']),
            'visited' => count(array_filter(array_unique(array_map(function($t) { return strpos($t->element, 'seleda.usage.v1.') === 0 ? $t->scoid : null; }, $tracks)))), 'questionsanswered' => count(array_filter($questions, function($q) { return trim($q['response']) !== ''; })),
            'graded' => count(array_filter($questions, function($q) { return in_array($q['result'], [get_string('reportingcorrect','mod_seledascorm'),get_string('reportingincorrect','mod_seledascorm')]); })),
            'correct' => count(array_filter($questions, function($q) { return $q['result'] === get_string('reportingcorrect', 'mod_seledascorm'); })),
            'score' => $grade === false || $grade === null ? null : $grade,
            'usagehtml' => \mod_seledascorm\output\dashboard_detail::usage($usage, $pages), 'questions' => $questions];
    }
} else if (!service::can_open()) {
    throw new required_capability_exception(context_system::instance(), 'mod/seledascorm:viewreport', 'nopermissions', '');
}
$model['catalogue'] = service::catalogue($filters['q'], $filters['catalogpage'], 30, $filters['course']);
$model['courses'] = [];
foreach (service::catalogue('', 0, PHP_INT_MAX)['items'] as $item) { $model['courses'][$item->course] = $item->coursename; }
$PAGE->set_url(new moodle_url('/mod/seledascorm/dashboard.php', $model['filters']));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('dashboardtitle', 'mod_seledascorm'));
$PAGE->set_heading(get_string('dashboardtitle', 'mod_seledascorm'));
$PAGE->requires->css(new moodle_url('/mod/seledascorm/dashboard.css'));
$PAGE->requires->js_call_amd('mod_seledascorm/reporting_dashboard', 'init');
$PAGE->set_cacheable(false);
header('Cache-Control: private, no-store');
echo $OUTPUT->header();
echo \mod_seledascorm\output\reporting_dashboard::render($model);
echo $OUTPUT->footer();
