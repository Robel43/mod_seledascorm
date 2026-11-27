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

// This script uses installed report plugins to print seledascorm reports.

require_once("../../config.php");
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');
require_once($CFG->dirroot.'/mod/seledascorm/reportsettings_form.php');
require_once($CFG->dirroot.'/mod/seledascorm/report/reportlib.php');
require_once($CFG->libdir.'/formslib.php');

define('SELEDASCORM_REPORT_DEFAULT_PAGE_SIZE', 20);
define('SELEDASCORM_REPORT_ATTEMPTS_ALL_STUDENTS', 0);
define('SELEDASCORM_REPORT_ATTEMPTS_STUDENTS_WITH', 1);
define('SELEDASCORM_REPORT_ATTEMPTS_STUDENTS_WITH_NO', 2);

$id = required_param('id', PARAM_INT);// Course Module ID, or ...
$download = optional_param('download', '', PARAM_RAW);
$mode = optional_param('mode', '', PARAM_ALPHA); // Report mode.

$cm = get_coursemodule_from_id('seledascorm', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$seledascorm = $DB->get_record('seledascorm', array('id' => $cm->instance), '*', MUST_EXIST);

$contextmodule = context_module::instance($cm->id);
$reportlist = seledascorm_report_list($contextmodule);

$url = new moodle_url('/mod/seledascorm/report.php');

$url->param('id', $id);
if (empty($mode)) {
    $mode = reset($reportlist);
} else if (!in_array($mode, $reportlist)) {
    throw new \moodle_exception('erroraccessingreport', 'mod_seledascorm');
}
$url->param('mode', $mode);

$PAGE->set_url($url);

require_login($course, false, $cm);
$PAGE->set_pagelayout('report');

require_capability('mod/seledascorm:viewreport', $contextmodule);

// Activate the secondary nav tab.
navigation_node::override_active_url(new moodle_url('/mod/seledascorm/report.php', ['id' => $id]));

if (count($reportlist) < 1) {
    throw new \moodle_exception('erroraccessingreport', 'seledascorm');
}

// Trigger a report viewed event.
$event = \mod_seledascorm\event\report_viewed::create(array(
    'context' => $contextmodule,
    'other' => array(
        'seledascormid' => $seledascorm->id,
        'mode' => $mode
    )
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('seledascorm', $seledascorm);
$event->trigger();

$userdata = null;
if (!empty($download)) {
    $noheader = true;
}
// Print the page header.
if (empty($noheader)) {
    $strreport = get_string('report', 'mod_seledascorm');
    $strattempt = get_string('attempt', 'mod_seledascorm');

    $PAGE->set_title("$course->shortname: ".format_string($seledascorm->name));
    $PAGE->set_heading($course->fullname);
    if ($CFG->version >= 2022041900) { // Moodle 4+.
        $PAGE->activityheader->set_attrs([
            'hidecompletion' => true,
            'description' => ''
        ]);
    }
    $PAGE->navbar->add($strreport, new moodle_url('/mod/seledascorm/report.php', array('id' => $cm->id)));

    echo $OUTPUT->header();
    if ($CFG->version < 2022041900) { // Moodle prior to 4.
        echo $OUTPUT->heading(format_string($seledascorm->name));
        $currenttab = 'reports';
        require($CFG->dirroot . '/mod/seledascorm/tabs.php');
    }

}

// Open the selected Scorm report and display it.
$classname = "seledascormreport_{$mode}\\report";
$legacyclassname = "seledascorm_{$mode}_report";
$report = class_exists($classname) ? new $classname() : new $legacyclassname();
$report->display($seledascorm, $cm, $course, $download); // Run the report!

// Print footer.

if (empty($noheader)) {
    echo $OUTPUT->footer();
}
