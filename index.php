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
require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');

$id = required_param('id', PARAM_INT);   // Course id.

$PAGE->set_url('/mod/seledascorm/index.php', array('id' => $id));

if (!empty($id)) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        throw new \moodle_exception('invalidcourseid');
    }
} else {
    throw new \moodle_exception('missingparameter');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// Trigger instances list viewed event.
$event = \mod_seledascorm\event\course_module_instance_list_viewed::create(['context' => context_course::instance($course->id), ]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strseledascorm = get_string("modulename", "mod_seledascorm");
$strseledascorms = get_string("modulenameplural", "mod_seledascorm");
$strname = get_string("name");
$strsummary = get_string("summary");
$strreport = get_string("report", 'mod_seledascorm');
$strlastmodified = get_string("lastmodified");

$PAGE->set_title($strseledascorms);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strseledascorms);
echo $OUTPUT->header();

$usesections = course_format_uses_sections($course->format);

if ($usesections) {
    $sortorder = "cw.section ASC";
} else {
    $sortorder = "m.timemodified DESC";
}

if (! $seledascorms = get_all_instances_in_course("seledascorm", $course)) {
    notice(get_string('thereareno', 'moodle', $strseledascorms), "../../course/view.php?id=$course->id");
    exit;
}

$table = new html_table();

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head = array ($strsectionname, $strname, $strsummary, $strreport);
    $table->align = array ("center", "left", "left", "left");
} else {
    $table->head = array ($strlastmodified, $strname, $strsummary, $strreport);
    $table->align = array ("left", "left", "left", "left");
}

foreach ($seledascorms as $seledascorm) {
    $context = context_module::instance($seledascorm->coursemodule);
    $tt = "";
    if ($usesections) {
        if ($seledascorm->section) {
            $tt = get_section_name($course, $seledascorm->section);
        }
    } else {
        $tt = userdate($seledascorm->timemodified);
    }
    $report = '&nbsp;';
    $reportshow = '&nbsp;';
    if (has_capability('mod/seledascorm:viewreport', $context)) {
        $trackedusers = seledascorm_get_count_users($seledascorm->id, $seledascorm->groupingid);
        if ($trackedusers > 0) {
            $reportshow = html_writer::link('report.php?id='.$seledascorm->coursemodule,
                                                get_string('viewallreports', 'mod_seledascorm', $trackedusers));
        } else {
            $reportshow = get_string('noreports', 'mod_seledascorm');
        }
    } else if (has_capability('mod/seledascorm:viewscores', $context)) {
        require_once('locallib.php');
        $report = seledascorm_grade_user($seledascorm, $USER->id);
        $reportshow = get_string('score', 'mod_seledascorm').": ".$report;
    }
    $options = (object)array('noclean' => true);
    if (!$seledascorm->visible) {
        // Show dimmed if the mod is hidden.
        $table->data[] = array ($tt, html_writer::link('view.php?id='.$seledascorm->coursemodule,
                                                        format_string($seledascorm->name),
                                                        array('class' => 'dimmed')),
                                format_module_intro('seledascorm', $seledascorm, $seledascorm->coursemodule), $reportshow);
    } else {
        // Show normal if the mod is visible.
        $table->data[] = [$tt,
                            html_writer::link('view.php?id='.$seledascorm->coursemodule, format_string($seledascorm->name)),
                            format_module_intro('seledascorm', $seledascorm, $seledascorm->coursemodule),
                            $reportshow,
                        ];
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);

echo $OUTPUT->footer();
