<?php
// This file is part of Moodle - https://moodle.org/
// SPDX-License-Identifier: GPL-3.0-or-later

require('../../config.php');
require_once($CFG->dirroot . '/mod/seledascorm/locallib.php');
require_once($CFG->dirroot . '/mod/seledascorm/report/timhirt_reporting.php');
require_once($CFG->dirroot . '/mod/seledascorm/report/seleda_usage.php');

use mod_seledascorm\local\reporting_dashboard as service;
use mod_seledascorm\local\seleda_usage;
use mod_seledascorm\local\report_view;
use mod_seledascorm\local\timhirt_reporting;

require_login();
if (isguestuser()) {
    throw new required_capability_exception(context_system::instance(), 'mod/seledascorm:viewreport', 'nopermissions', '');
}
$filters = [
    'measure' => optional_param('measure', 'time', PARAM_ALPHA),
    'role' => optional_param('role', 'all', PARAM_ALPHA),
    'sort' => optional_param('sort', 'last', PARAM_ALPHA),
    'dir' => optional_param('dir', 'desc', PARAM_ALPHA),
    'tab' => optional_param('tab', 'answers', PARAM_ALPHA),
    'view' => optional_param('view', 'teacher', PARAM_ALPHA),
    'panel' => optional_param('panel', 'overview', PARAM_ALPHA),
    'diagnostics' => optional_param('diagnostics', 0, PARAM_BOOL),
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
if (!in_array($filters['view'], ['teacher','analytics'], true) || !in_array($filters['panel'], ['overview','questions'], true)) { throw new moodle_exception('invalidparameter'); }
foreach (['measure'=>['time','interactions'], 'role'=>['all','learner','staff'], 'sort'=>['name','status','progress','score','time','attempts','last'],
    'dir'=>['asc','desc'], 'tab'=>['answers','attempts','technical'],
    'state'=>['','completed','inprogress','notstarted','unknown','attention','failed']] as $key=>$allowed) {
    if (!in_array($filters[$key],$allowed,true)) throw new moodle_exception('invalidparameter');
}
$options = $filters;
$options['questions'] = !$filters['learner'];
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
if ($filters['id'] && $filters['view'] === 'teacher') {
    [$activity, $cm, $course] = service::activity($filters['id']);
    $metrics = service::overview($activity, $cm, $filters['group'], $options);
    $model['activity'] = $activity;
    $model['coursename'] = $course->fullname;
    $titlepages = array_intersect_key($metrics['pages'], array_flip(array_column(array_filter($metrics['blocks'], function($b) { return !empty($b['generic']); }), 'scoid')));
    $titles = \mod_seledascorm\output\dashboard_detail::titles($cm->context, $titlepages);
    // Keys are hashes for links; resolve package IDs to the same hash namespace.
    foreach ($titles as $key => $title) { foreach ($metrics['blocks'] as &$block) { if (!empty($block['generic']) && $block['key'] === hash('sha256', $key)) { $block['title'] = $title['title']; } } unset($block); }
    $model['metrics'] = $metrics;
    $model['updated']=$metrics['learning']['learners']?max(array_column($metrics['learning']['learners'],'last')):null;
    foreach ($metrics['blocks'] as $block) if ($block['key']===$filters['block']) $model['blocktitle']=$block['title'];
    $matching = report_view::table($metrics['learning']['learners'],$filters);
    if ($download === 'csv') {
        require_once($CFG->libdir . '/csvlib.class.php');
        $csv = new csv_export_writer();
        $csv->set_filename('seleda-dashboard-' . $activity->id);
        foreach (\mod_seledascorm\local\report_export::people($matching,$activity->name,$course->fullname) as $row) $csv->add_data($row);
        $csv->download_file();
        exit;
    }
    $model['filteredcount'] = count($matching);
    $model['rows'] = array_slice(array_values($matching), $filters['page'] * 25, 25);
    if ($filters['learner']) {
        $learner = $metrics['learning']['learners'][$filters['learner']] ?? null;
        if (!$learner) {
            throw new moodle_exception('nopermissiontoshow');
        }
        $attempt = $filters['attempt'] ?: $learner['latest'];
        if (!isset($learner['attempts'][$attempt])) {
            throw new moodle_exception('nopermissiontoshow');
        }
        $model['filters']['attempt'] = $attempt;
        $model['detail']=\mod_seledascorm\local\learner_report::build($activity,$cm,$learner,$attempt,$metrics['pages'],$filters['group'],$filters['tab'],(bool)$filters['diagnostics']);
        if ($filters['diagnostics']) $model['filters']['tab']='technical';

    }
} else if (!service::can_open()) {
    throw new required_capability_exception(context_system::instance(), 'mod/seledascorm:viewreport', 'nopermissions', '');
}
if ($filters['view'] === 'analytics') {
    $model['comparisons'] = [];
    // Bound aggregation to the displayed page. Every activity rechecks course/module/group access.
    $catalogue = $filters['id'] ? ['items'=>[(object)['id'=>$filters['id']]],'more'=>false] : service::catalogue($filters['q'], $filters['catalogpage'], 10, $filters['course']);
    foreach ($catalogue['items'] as $item) {
        [$activity, $cm, $course] = service::activity($item->id);
        $data = service::overview($activity, $cm, $filters['id'] ? $filters['group'] : 0, array_merge($options, ['questions'=>true]));
        if ($filters['id']) { $model['activity']=$activity; $model['coursename']=$course->fullname; $model['metrics']=$data; }
        $model['comparisons'][] = ['id'=>$item->id, 'name'=>$activity->name, 'course'=>$course->fullname, 'data'=>$data['learning']];
    }
    $model['comparisonmore'] = $catalogue['more'];
    $model['insights'] = report_view::comparison($model['comparisons']);
    if ($download === 'csv') {
        require_once($CFG->libdir . '/csvlib.class.php');
        $csv = new csv_export_writer(); $csv->set_filename('seleda-activity-comparison');
        foreach (\mod_seledascorm\local\report_export::insights($model['comparisons'],$model['insights']) as $row) $csv->add_data($row);
        $csv->download_file(); exit;
    }
}
$model['catalogue'] = service::activities_for_filter($filters['course'],200);
if (!$filters['id'] && $filters['view']==='teacher') {
    $model['quickcounts']=[];
    foreach (array_slice($model['catalogue']['items'],0,6) as $item) {
        [$quickactivity,$quickcm]=service::activity($item->id);
        $quick=service::overview($quickactivity,$quickcm,0,array_merge($options,['questions'=>false]));
        $model['quickcounts'][$item->id]=$quick['learning']['started'];
    }
}
$model['courses'] = [];
foreach (service::catalogue('', 0, PHP_INT_MAX)['items'] as $item) { $model['courses'][$item->course] = $item->coursename; }
$PAGE->set_url(new moodle_url('/mod/seledascorm/dashboard.php', $model['filters']));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('dashboardtitle', 'mod_seledascorm'));
$PAGE->set_heading(get_string('dashboardtitle', 'mod_seledascorm'));
$PAGE->requires->css(\mod_seledascorm\local\presentation_assets::css('dashboard.css'));
$PAGE->requires->js_call_amd('mod_seledascorm/reporting_dashboard', 'init');
$PAGE->set_cacheable(false);
header('Cache-Control: private, no-store');
echo $OUTPUT->header();
echo \mod_seledascorm\output\reporting_dashboard::render($model);
echo $OUTPUT->footer();
