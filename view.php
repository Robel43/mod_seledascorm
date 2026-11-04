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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/seledascorm/lib.php');
require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');
require_once($CFG->dirroot.'/course/lib.php');

$id = optional_param('id', '', PARAM_INT);       // Course Module ID, or...
$a = optional_param('a', '', PARAM_INT);         // Exescorm ID.
$organization = optional_param('organization', '', PARAM_INT); // Organization ID.
$action = optional_param('action', '', PARAM_ALPHA);
$preventskip = optional_param('preventskip', '', PARAM_INT); // Prevent Skip view, set by javascript redirects.

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

$url = new moodle_url('/mod/seledascorm/view.php', array('id' => $cm->id));
if ($organization !== '') {
    $url->param('organization', $organization);
}
$PAGE->set_url($url);
$forcejs = get_config('seledascorm', 'forcejavascript');
if (!empty($forcejs)) {
    $PAGE->add_body_class('forcejavascript');
}

require_login($course, false, $cm);

$context = context_course::instance($course->id);
$contextmodule = context_module::instance($cm->id);

// Check if package is an .elpx file (eXeLearning project, not yet converted to SCORM).
$iselpx = !empty($seledascorm->reference) && strtolower(pathinfo($seledascorm->reference, PATHINFO_EXTENSION)) === 'elpx';

$launch = false; // Does this automatically trigger a launch based on skipview.
if (!empty($seledascorm->popup) && !$iselpx) {
    $scoid = 0;
    $orgidentifier = '';

    $result = seledascorm_get_toc($USER, $seledascorm, $cm->id, SELEDASCORM_TOCFULLURL);
    // Set last incomplete sco to launch first.
    if (!empty($result->sco->id)) {
        $sco = $result->sco;
    } else {
        $sco = seledascorm_get_sco($seledascorm->launch, SELEDASCORM_SCO_ONLY);
    }
    if (!empty($sco)) {
        $scoid = $sco->id;
        if (($sco->organization == '') && ($sco->launch == '')) {
            $orgidentifier = $sco->identifier;
        } else {
            $orgidentifier = $sco->organization;
        }
    }

    if (empty($preventskip) && $seledascorm->skipview >= SELEDASCORM_SKIPVIEW_FIRST &&
        has_capability('mod/seledascorm:skipview', $contextmodule) &&
        !has_capability('mod/seledascorm:viewreport', $contextmodule)) { // Don't skip users with the capability to view reports.

        // Do we launch immediately and redirect the parent back ?
        if ($seledascorm->skipview == SELEDASCORM_SKIPVIEW_ALWAYS || !seledascorm_has_tracks($seledascorm->id, $USER->id)) {
            $launch = true;
        }
    }
    // Redirect back to the section with one section per page ?

    $courseformat = course_get_format($course)->get_course();
    if ($courseformat->format == 'singleactivity') {
        $courseurl = $url->out(false, array('preventskip' => '1'));
    } else {
        $courseurl = course_get_url($course, $cm->sectionnum)->out(false);
    }
    $PAGE->requires->data_for_js('seledascormplayerdata', Array('launch' => $launch,
                                                           'currentorg' => $orgidentifier,
                                                           'sco' => $scoid,
                                                           'seledascorm' => $seledascorm->id,
                                                           'courseurl' => $courseurl,
                                                           'cwidth' => $seledascorm->width,
                                                           'cheight' => $seledascorm->height,
                                                           'popupoptions' => $seledascorm->options), true);
    $PAGE->requires->string_for_js('popupsblocked', 'mod_seledascorm');
    $PAGE->requires->string_for_js('popuplaunched', 'mod_seledascorm');
    $PAGE->requires->js('/mod/seledascorm/view.js', true);
}

if (isset($SESSION->seledascorm)) {
    unset($SESSION->seledascorm);
}

$strseledascorms = get_string("modulenameplural", "mod_seledascorm");
$strseledascorm = get_string("modulename", "mod_seledascorm");

$shortname = format_string($course->shortname, true, array('context' => $context));
$pagetitle = strip_tags($shortname.': '.format_string($seledascorm->name));

// Trigger module viewed event.
seledascorm_view($seledascorm, $course, $cm, $contextmodule);

if (empty($preventskip) && empty($launch) && !$iselpx && (has_capability('mod/seledascorm:skipview', $contextmodule))) {
    seledascorm_simple_play($seledascorm, $USER, $contextmodule, $cm->id);
}

// Print the page header.

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
// Let the module handle the display.
if ($CFG->version >= 2022041900) { // Moodle 4+.
    if (!empty($action) && $action == 'delete' && confirm_sesskey() &&
        has_capability('mod/seledascorm:deleteownresponses', $contextmodule)
    ) {
        $PAGE->activityheader->disable();
    } else {
        $PAGE->activityheader->set_description('');
    }
}
echo $OUTPUT->header();
if (!empty($action) && confirm_sesskey() && has_capability('mod/seledascorm:deleteownresponses', $contextmodule)) {
    if ($action == 'delete') {
        $confirmurl = new moodle_url($PAGE->url, array('action' => 'deleteconfirm'));
        echo $OUTPUT->confirm(get_string('deleteuserattemptcheck', 'mod_seledascorm'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        exit;
    } else if ($action == 'deleteconfirm') {
        // Delete this users attempts.
        $DB->delete_records('seledascorm_scoes_track', array('userid' => $USER->id, 'seledascormid' => $seledascorm->id));
        seledascorm_update_grades($seledascorm, $USER->id, true);
        echo $OUTPUT->notification(get_string('seledascormresponsedeleted', 'mod_seledascorm'), 'notifysuccess');
    }
}
if ($CFG->version < 2022041900) { // Moodle prior to 4.
    echo $OUTPUT->heading(format_string($seledascorm->name));
    $currenttab = 'info';
    require($CFG->dirroot . '/mod/seledascorm/tabs.php');
}
// Print the main part of the page.
$attemptstatus = '';
if (empty($launch) && !$iselpx && ($seledascorm->displayattemptstatus == SELEDASCORM_DISPLAY_ATTEMPTSTATUS_ALL ||
         $seledascorm->displayattemptstatus == SELEDASCORM_DISPLAY_ATTEMPTSTATUS_ENTRY)) {
    $attemptstatus = seledascorm_get_attempt_status($USER, $seledascorm, $cm);
}
echo $OUTPUT->box(format_module_intro('seledascorm', $seledascorm, $cm->id), '', 'intro');

// Show embedded editor button if available and user has capability.
// Show for embedded type OR when the package is an .elpx file (needs editor to convert to SCORM).
if (($seledascorm->seledascormtype === SELEDASCORM_TYPE_EMBEDDED || $iselpx)
    && seledascorm_embedded_editor_available()
    && has_capability('moodle/course:update', context_course::instance($course->id))) {
    $editorurl = new moodle_url('/mod/seledascorm/editor/index.php', [
        'id' => $cm->id,
        'sesskey' => sesskey(),
    ]);
    echo html_writer::start_div('d-flex mb-2');
    echo html_writer::tag('button', get_string('editwithbrand', 'mod_seledascorm', seledascorm_get_brand_name()), [
        'type' => 'button',
        'class' => 'btn btn-primary',
        'data-action' => 'mod_seledascorm/editor-open',
        'data-cmid' => $cm->id,
        'data-editorurl' => $editorurl->out(false),
        'data-activityname' => format_string($seledascorm->name),
    ]);
    echo html_writer::end_div();
    $PAGE->requires->js_call_amd('mod_seledascorm/editor_modal', 'init');
}

if ($iselpx) {
    echo $OUTPUT->notification(get_string('elpxneedsconversion', 'mod_seledascorm'), 'warning');
}

// Check if SELEDASCORM available. No need to display warnings because activity dates are displayed at the top of the page.
list($available, $warnings) = seledascorm_get_availability_status($seledascorm);

if ($available && empty($launch) && !$iselpx) {
    seledascorm_print_launch($USER, $seledascorm, 'view.php?id='.$cm->id, $cm);
}

echo $OUTPUT->box($attemptstatus);

if (!empty($forcejs)) {
    $message = $OUTPUT->box(get_string("forcejavascriptmessage", "mod_seledascorm"), "forcejavascriptmessage");
    echo html_writer::tag('noscript', $message);
}

if (!empty($seledascorm->popup)) {
    $PAGE->requires->js_init_call('M.mod_seledascormform.init');
}

echo $OUTPUT->footer();
