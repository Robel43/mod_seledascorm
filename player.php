<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// This page prints a particular instance of aicc/seledascorm package.

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('cm', '', PARAM_INT);                          // Course Module ID, or...
$a = optional_param('a', '', PARAM_INT);                            // Exelearning ID.
$scoid = required_param('scoid', PARAM_INT);                        // Sco ID.
$mode = optional_param('mode', 'normal', PARAM_ALPHA);              // Navigation mode.
$currentorg = optional_param('currentorg', '', PARAM_RAW);          // Selected organization.
$newattempt = optional_param('newattempt', 'off', PARAM_ALPHA);     // The user request to start a new attempt.
$displaymode = optional_param('display', '', PARAM_ALPHA);

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('seledascorm', $id, 0, true)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $seledascorm = $DB->get_record("seledascorm", array("id" => $cm->instance))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else if (!empty($a)) {
    if (! $seledascorm = $DB->get_record("seledascorm", array("id" => $a))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $seledascorm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("seledascorm", $seledascorm->id, $course->id, true)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else {
    throw new \moodle_exception('missingparameter');
}

// PARAM_RAW is used for $currentorg, validate it against records stored in the table.
if (!empty($currentorg)) {
    if (!$DB->record_exists('seledascorm_scoes', array('seledascorm' => $seledascorm->id, 'identifier' => $currentorg))) {
        $currentorg = '';
    }
}

// If new attempt is being triggered set normal mode and increment attempt number.
$attempt = seledascorm_get_last_attempt($seledascorm->id, $USER->id);

// Check mode is correct and set/validate mode/attempt/newattempt (uses pass by reference).
seledascorm_check_mode($seledascorm, $newattempt, $attempt, $USER->id, $mode);

if (!empty($scoid)) {
    $scoid = seledascorm_check_launchable_sco($seledascorm, $scoid);
}

$url = new moodle_url('/mod/seledascorm/player.php', array('scoid' => $scoid, 'cm' => $cm->id));
if ($mode !== 'normal') {
    $url->param('mode', $mode);
}
if ($currentorg !== '') {
    $url->param('currentorg', $currentorg);
}
if ($newattempt !== 'off') {
    $url->param('newattempt', $newattempt);
}
if ($displaymode !== '') {
    $url->param('display', $displaymode);
}
$PAGE->set_url($url);
// Moodle 4.0+ only.
if ($CFG->version >= 2022041900) {
    $PAGE->set_secondary_active_tab('modulepage');
}

$forcejs = get_config('seledascorm', 'forcejavascript');
if (!empty($forcejs)) {
    $PAGE->add_body_class('forcejavascript');
}
$collapsetocwinsize = get_config('seledascorm', 'collapsetocwinsize');
if (empty($collapsetocwinsize)) {
    // Set as default window size to collapse TOC.
    $collapsetocwinsize = 767;
} else {
    $collapsetocwinsize = intval($collapsetocwinsize);
}

require_login($course, false, $cm);

$strseledascorms = get_string('modulenameplural', 'mod_seledascorm');
$strseledascorm = get_string('modulename', 'mod_seledascorm');
$strpopup = get_string('popup', 'mod_seledascorm');
$strexit = get_string('exitactivity', 'mod_seledascorm');

$coursecontext = context_course::instance($course->id);

if ($displaymode == 'popup') {
    $PAGE->set_pagelayout('embedded');
} else {
    $shortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $pagetitle = strip_tags("$shortname: ".format_string($seledascorm->name));
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);
}
if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', context_module::instance($cm->id))) {
    echo $OUTPUT->header();
    notice(get_string("activityiscurrentlyhidden"));
    echo $OUTPUT->footer();
    die;
}

// Check if SELEDASCORM available.
list($available, $warnings) = seledascorm_get_availability_status($seledascorm);
if (!$available) {
    $reason = current(array_keys($warnings));
    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string($reason, "seledascorm", $warnings[$reason]), "generalbox boxaligncenter");
    echo $OUTPUT->footer();
    die;
}

// TOC processing.
$seledascorm->version = strtolower(clean_param($seledascorm->version, PARAM_SAFEDIR));   // Just to be safe.
if (!file_exists($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'lib.php')) {
    $seledascorm->version = 'SCORM_12';
}
require_once($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'lib.php');

$result = seledascorm_get_toc($USER, $seledascorm, $cm->id, SELEDASCORM_TOCJSLINK, $currentorg, $scoid,
    $mode, $attempt, true, true);
$sco = $result->sco ?? null;
if (empty($sco)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('packageempty', 'mod_seledascorm'), 'warning');
    echo $OUTPUT->footer();
    exit;
}
if ($seledascorm->lastattemptlock == 1 && $result->attemptleft == 0) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('exceededmaxattempts', 'mod_seledascorm'));
    echo $OUTPUT->footer();
    exit;
}

$scoidstr = '&amp;scoid='.$sco->id;
$modestr = '&amp;mode='.$mode;

$SESSION->seledascorm = new stdClass();
$SESSION->seledascorm->scoid = $sco->id;
$SESSION->seledascorm->seledascormstatus = 'Not Initialized';
$SESSION->seledascorm->seledascormmode = $mode;
$SESSION->seledascorm->attempt = $attempt;

// Mark module viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Generate the exit button.
$exiturl = "";
if (empty($seledascorm->popup) || $displaymode == 'popup') {
    if ($course->format == 'singleactivity' && $seledascorm->skipview == SELEDASCORM_SKIPVIEW_ALWAYS
        && !has_capability('mod/seledascorm:viewreport', context_module::instance($cm->id))) {
        // Redirect students back to site home to avoid redirect loop.
        $exiturl = $CFG->wwwroot;
    } else {
        // Redirect back to the correct section if one section per page is being used.
        $exiturl = course_get_url($course, $cm->sectionnum)->out();
    }
}

// Print the page header.
$stringforjs = ['player:next', 'player:prev', 'player:skipnext', 'player:skipprev', 'player:toogleFullscreen', 'player:up', ];
$PAGE->requires->strings_for_js($stringforjs, 'mod_seledascorm');
$PAGE->requires->data_for_js('seledascormplayerdata', Array('launch' => false,
                                                       'currentorg' => '',
                                                       'sco' => 0,
                                                       'seledascorm' => 0,
                                                       'courseid' => $seledascorm->course,
                                                       'cwidth' => $seledascorm->width,
                                                       'cheight' => $seledascorm->height,
                                                       'popupoptions' => $seledascorm->options), true);
$PAGE->requires->js('/mod/seledascorm/request.js', true);
$PAGE->requires->js('/lib/cookies.js', true);

if (file_exists($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'.js')) {
    $PAGE->requires->js('/mod/seledascorm/datamodels/'.$seledascorm->version.'.js', true);
} else {
    $PAGE->requires->js('/mod/seledascorm/datamodels/scorm_12.js', true);
}
if ($CFG->version >= 2022041900) { // Moodle 4+.
    $activityheader = $PAGE->activityheader;
    $headerconfig = [
        'description' => '',
        'hidecompletion' => true
    ];

    $activityheader->set_attrs($headerconfig);
}
echo $OUTPUT->header();
$PAGE->requires->string_for_js('navigation', 'mod_seledascorm');
$PAGE->requires->string_for_js('toc', 'mod_seledascorm');
$PAGE->requires->string_for_js('hide', 'moodle');
$PAGE->requires->string_for_js('show', 'moodle');
$PAGE->requires->string_for_js('popupsblocked', 'mod_seledascorm');
$PAGE->requires->string_for_js('contenttitle', 'mod_seledascorm');

$name = false;

$renderer = $PAGE->get_renderer('mod_seledascorm');
// Exit button should ONLY be displayed when in the current window.
if ($displaymode !== 'popup') {
    echo $renderer->generate_editexitbar($exiturl, $cm);
}

echo html_writer::start_div('', array('id' => 'seledascormpage'));
echo html_writer::start_div('', array('id' => 'tocbox'));
echo html_writer::div(html_writer::tag('script', '', array('id' => 'external-seledascormapi', 'type' => 'text/JavaScript')), '',
                        array('id' => 'seledascormapi-parent'));


if ($seledascorm->hidetoc == SELEDASCORM_TOC_POPUP || $mode == 'browse' || $mode == 'review') {
    echo html_writer::start_div('pl-2', array('id' => 'seledascormtop'));
    if ($mode == 'browse' || $mode == 'review') {
        echo html_writer::div(get_string("{$mode}mode", 'mod_seledascorm'), 'seledascorm-left h3', ['id' => 'seledascormmode']);
    }
    if ($seledascorm->hidetoc == SELEDASCORM_TOC_POPUP) {
        echo html_writer::div($result->tocmenu, 'seledascorm-right', array('id' => 'seledascormnav'));
    }
    echo html_writer::end_div();
}

echo html_writer::start_div('', array('id' => 'toctree'));

// ADD actions button.
echo $renderer->generate_tocbox_action_buttons();


echo html_writer::div('', '', array('id' => 'seledascorm_toc_title'));

if (empty($seledascorm->popup) || $displaymode == 'popup') {
    echo $result->toc;
} else {
    // Added incase javascript popups are blocked we don't provide a direct link
    // to the pop-up as JS communication can fail - the user must disable their pop-up blocker.
    $linkcourse = html_writer::link($CFG->wwwroot.'/course/view.php?id='.
                    $seledascorm->course, get_string('finishseledascormlinkname', 'mod_seledascorm'));
    echo $OUTPUT->box(get_string('finishseledascorm', 'mod_seledascorm', $linkcourse), 'generalbox', 'altfinishlink');
}
echo html_writer::end_div(); // Toc tree ends.
echo html_writer::end_div(); // Toc box ends.
echo html_writer::tag('noscript', html_writer::div(get_string('noscriptnoseledascorm', 'mod_seledascorm'), '', ['id' => 'noscript']));

if ($result->prerequisites) {
    if ($seledascorm->popup != 0 && $displaymode !== 'popup') {
        // Clean the name for the window as IE is fussy.
        $name = preg_replace("/[^A-Za-z0-9]/", "", $seledascorm->name);
        if (!$name) {
            $name = 'DefaultPlayerWindow';
        }
        $name = 'seledascorm_'.$name;
        echo html_writer::script('', $CFG->wwwroot.'/mod/seledascorm/player.js');
        $url = new moodle_url($PAGE->url, array('scoid' => $sco->id, 'display' => 'popup', 'mode' => $mode));
        echo html_writer::script(
            js_writer::function_call('seledascorm_openpopup', Array($url->out(false),
                                                       $name, $seledascorm->options,
                                                       $seledascorm->width, $seledascorm->height)));
        echo html_writer::tag('noscript', html_writer::tag('iframe', '',
                array('id' => 'main', 'class' => 'scoframe', 'name' => 'main',
                'src' => 'loadSCO.php?id='.$cm->id.$scoidstr.$modestr)));
    }
} else {
    echo $OUTPUT->box(get_string('noprerequisites', 'mod_seledascorm'));
}
echo html_writer::end_div(); // Scorm page ends.

$scoes = seledascorm_get_toc_object($USER, $seledascorm, $currentorg, $sco->id, $mode, $attempt);
$adlnav = seledascorm_get_adlnav_json($scoes['scoes']);

if (empty($seledascorm->popup) || $displaymode == 'popup') {
    if (!isset($result->toctitle)) {
        $result->toctitle = get_string('toc', 'mod_seledascorm');
    }
    $jsmodule = array(
        'name' => 'mod_seledascorm',
        'fullpath' => '/mod/seledascorm/module.js',
        'requires' => array('json'),
    );
    $seledascorm->nav = intval($seledascorm->nav);
    $PAGE->requires->js_init_call('M.mod_seledascorm.init', [$seledascorm->nav, $seledascorm->navpositionleft,
                        $seledascorm->navpositiontop, $seledascorm->hidetoc, $collapsetocwinsize, $result->toctitle,
                        $name, $sco->id, $adlnav], false, $jsmodule);
}
if (!empty($forcejs)) {
    $message = $OUTPUT->box(get_string("forcejavascriptmessage", "mod_seledascorm"),
                            "generalbox boxaligncenter forcejavascriptmessage");
    echo html_writer::tag('noscript', $message);
}

if (file_exists($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'.php')) {
    include_once($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'.php');
} else {
    include_once($CFG->dirroot.'/mod/seledascorm/datamodels/scorm_12.php');
}

// Add the keepalive system to keep checking for a connection.
\core\session\manager::keepalive('networkdropped', 'mod_seledascorm', 30, 10);

$PAGE->requires->js_call_amd('mod_seledascorm/fullscreen', 'init');

echo $OUTPUT->footer();

// Set the start time of this SCO.
seledascorm_insert_track($USER->id, $seledascorm->id, $scoid, $attempt, 'x.start.time', time());
