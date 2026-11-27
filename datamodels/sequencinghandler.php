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

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');

$id = optional_param('id', '', PARAM_INT);  // Course Module ID, or...
$a = optional_param('a', '', PARAM_INT);  // Exescorm ID.
$scoid = required_param('scoid', PARAM_INT);  // Sco ID.
$attempt = required_param('attempt', PARAM_INT);  // Attempt number.
$function = required_param('function', PARAM_RAW);  // Function to call.
$request = optional_param('request', '', PARAM_RAW);  // Scorm ID.

if (!empty($id)) {
    $cm = get_coursemodule_from_id('seledascorm', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record("course", array("id" => $cm->course), '*', MUST_EXIST);
    $seledascorm = $DB->get_record("seledascorm", array("id" => $cm->instance), '*', MUST_EXIST);
} else if (!empty($a)) {
    $seledascorm = $DB->get_record("seledascorm", array("id" => $a), '*', MUST_EXIST);
    $course = $DB->get_record("course", array("id" => $seledascorm->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance("seledascorm", $seledascorm->id, $course->id, false, MUST_EXIST);
} else {
    throw new \moodle_exception('missingparameter');
}

$PAGE->set_url('/mod/seledascorm/datamodels/sequencinghandler.php',
    array('scoid' => $scoid, 'attempt' => $attempt, 'id' => $cm->id, 'function' => $function, 'request' => $request));

require_login($course, false, $cm);

if (!empty($scoid) && !empty($function)) {
    require_once($CFG->dirroot.'/mod/seledascorm/datamodels/scorm_13lib.php');

    if (has_capability('mod/seledascorm:savetrack', context_module::instance($cm->id))) {
        $result = null;
        switch ($function) {
            case 'seledascorm_seq_flow' :
                if ($request == 'forward' || $request == 'backward') {
                    $seq = seledascorm_seq_navigation ($scoid, $USER->id, $request.'_', $attempt);
                    $sco = seledascorm_get_sco($scoid);
                    $seq = seledascorm_seq_flow($sco, $request, $seq, true, $USER->id);
                    if (!empty($seq->nextactivity)) {
                        seledascorm_seq_end_attempt($sco, $USER->id, $seq);
                    }
                }
                echo json_encode($seq);
                break;
        }
    }
}
