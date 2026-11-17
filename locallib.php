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

/**
 * Library of internal classes and functions for module SELEDASCORM
 *
 * @package    mod_seledascorm
 * @copyright  1999 onwards Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/seledascorm/lib.php");
require_once("$CFG->libdir/filelib.php");

// Constants and settings for module seledascorm.
define('SELEDASCORM_UPDATE_NEVER', '0');
define('SELEDASCORM_UPDATE_EVERYDAY', '2');
define('SELEDASCORM_UPDATE_EVERYTIME', '3');

define('SELEDASCORM_SKIPVIEW_NEVER', '0');
define('SELEDASCORM_SKIPVIEW_FIRST', '1');
define('SELEDASCORM_SKIPVIEW_ALWAYS', '2');

define('SELEDASCORM_SCO_ALL', 0);
define('SELEDASCORM_SCO_DATA', 1);
define('SELEDASCORM_SCO_ONLY', 2);

define('SELEDASCORM_GRADESCOES', '0');
define('SELEDASCORM_GRADEHIGHEST', '1');
define('SELEDASCORM_GRADEAVERAGE', '2');
define('SELEDASCORM_GRADESUM', '3');

define('SELEDASCORM_HIGHESTATTEMPT', '0');
define('SELEDASCORM_AVERAGEATTEMPT', '1');
define('SELEDASCORM_FIRSTATTEMPT', '2');
define('SELEDASCORM_LASTATTEMPT', '3');

define('SELEDASCORM_TOCJSLINK', 1);
define('SELEDASCORM_TOCFULLURL', 2);

define('SELEDASCORM_FORCEATTEMPT_NO', 0);
define('SELEDASCORM_FORCEATTEMPT_ONCOMPLETE', 1);
define('SELEDASCORM_FORCEATTEMPT_ALWAYS', 2);

// Local Library of functions for module seledascorm.

/**
 * @package   mod_seledascorm
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seledascorm_package_file_info extends file_info_stored {
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

/**
 * Returns an array of the popup options for SELEDASCORM and each options default value
 *
 * @return array an array of popup options as the key and their defaults as the value
 */
function seledascorm_get_popup_options_array() {
    $cfgseledascorm = get_config('seledascorm');

    return array('scrollbars' => isset($cfgseledascorm->scrollbars) ? $cfgseledascorm->scrollbars : 0,
                 'directories' => isset($cfgseledascorm->directories) ? $cfgseledascorm->directories : 0,
                 'location' => isset($cfgseledascorm->location) ? $cfgseledascorm->location : 0,
                 'menubar' => isset($cfgseledascorm->menubar) ? $cfgseledascorm->menubar : 0,
                 'toolbar' => isset($cfgseledascorm->toolbar) ? $cfgseledascorm->toolbar : 0,
                 'status' => isset($cfgseledascorm->status) ? $cfgseledascorm->status : 0);
}

/**
 * Returns an array of the array of what grade options
 *
 * @return array an array of what grade options
 */
function seledascorm_get_grade_method_array() {
    return array (SELEDASCORM_GRADESCOES => get_string('gradescoes', 'mod_seledascorm'),
                  SELEDASCORM_GRADEHIGHEST => get_string('gradehighest', 'mod_seledascorm'),
                  SELEDASCORM_GRADEAVERAGE => get_string('gradeaverage', 'mod_seledascorm'),
                  SELEDASCORM_GRADESUM => get_string('gradesum', 'mod_seledascorm'));
}

/**
 * Returns an array of the array of what grade options
 *
 * @return array an array of what grade options
 */
function seledascorm_get_what_grade_array() {
    return array (SELEDASCORM_HIGHESTATTEMPT => get_string('SELEDASCORM_HIGHESTATTEMPT', 'mod_seledascorm'),
                  SELEDASCORM_AVERAGEATTEMPT => get_string('SELEDASCORM_AVERAGEATTEMPT', 'mod_seledascorm'),
                  SELEDASCORM_FIRSTATTEMPT => get_string('firstattempt', 'mod_seledascorm'),
                  SELEDASCORM_LASTATTEMPT => get_string('lastattempt', 'mod_seledascorm'));
}

/**
 * Returns an array of the array of skip view options
 *
 * @return array an array of skip view options
 */
function seledascorm_get_skip_view_array() {
    return array(SELEDASCORM_SKIPVIEW_NEVER => get_string('never'),
                 SELEDASCORM_SKIPVIEW_FIRST => get_string('firstaccess', 'mod_seledascorm'),
                 SELEDASCORM_SKIPVIEW_ALWAYS => get_string('always'));
}

/**
 * Returns an array of the array of hide table of contents options
 *
 * @return array an array of hide table of contents options
 */
function seledascorm_get_hidetoc_array() {
     return array(SELEDASCORM_TOC_SIDE => get_string('sided', 'mod_seledascorm'),
                  SELEDASCORM_TOC_HIDDEN => get_string('hidden', 'mod_seledascorm'),
                  SELEDASCORM_TOC_POPUP => get_string('popupmenu', 'mod_seledascorm'),
                  SELEDASCORM_TOC_DISABLED => get_string('disabled', 'mod_seledascorm'));
}

/**
 * Returns an array of the array of update frequency options
 *
 * @return array an array of update frequency options
 */
function seledascorm_get_updatefreq_array() {
    return array(SELEDASCORM_UPDATE_NEVER => get_string('never'),
                 SELEDASCORM_UPDATE_EVERYDAY => get_string('everyday', 'mod_seledascorm'),
                 SELEDASCORM_UPDATE_EVERYTIME => get_string('everytime', 'mod_seledascorm'));
}

/**
 * Returns an array of the array of popup display options
 *
 * @return array an array of popup display options
 */
function seledascorm_get_popup_display_array() {
    return array(0 => get_string('currentwindow', 'mod_seledascorm'),
                 1 => get_string('popup', 'mod_seledascorm'));
}

/**
 * Returns an array of the array of navigation buttons display options
 *
 * @return array an array of navigation buttons display options
 */
function seledascorm_get_navigation_display_array() {
    return array(SELEDASCORM_NAV_DISABLED => get_string('no'),
                 SELEDASCORM_NAV_UNDER_CONTENT => get_string('undercontent', 'mod_seledascorm'),
                 SELEDASCORM_NAV_FLOATING => get_string('floating', 'mod_seledascorm'));
}

/**
 * Returns an array of the array of attempt options
 *
 * @return array an array of attempt options
 */
function seledascorm_get_attempts_array() {
    $attempts = array(0 => get_string('nolimit', 'mod_seledascorm'),
                      1 => get_string('attempt1', 'mod_seledascorm'));

    for ($i = 2; $i <= 6; $i++) {
        $attempts[$i] = get_string('attemptsx', 'mod_seledascorm', $i);
    }

    return $attempts;
}

/**
 * Returns an array of the attempt status options
 *
 * @return array an array of attempt status options
 */
function seledascorm_get_attemptstatus_array() {
    return array(SELEDASCORM_DISPLAY_ATTEMPTSTATUS_NO => get_string('no'),
                 SELEDASCORM_DISPLAY_ATTEMPTSTATUS_ALL => get_string('attemptstatusall', 'mod_seledascorm'),
                 SELEDASCORM_DISPLAY_ATTEMPTSTATUS_MY => get_string('attemptstatusmy', 'mod_seledascorm'),
                 SELEDASCORM_DISPLAY_ATTEMPTSTATUS_ENTRY => get_string('attemptstatusentry', 'mod_seledascorm'));
}

/**
 * Returns an array of the force attempt options
 *
 * @return array an array of attempt options
 */
function seledascorm_get_forceattempt_array() {
    return array(SELEDASCORM_FORCEATTEMPT_NO => get_string('no'),
                 SELEDASCORM_FORCEATTEMPT_ONCOMPLETE => get_string('forceattemptoncomplete', 'mod_seledascorm'),
                 SELEDASCORM_FORCEATTEMPT_ALWAYS => get_string('forceattemptalways', 'mod_seledascorm'));
}

/**
 * Extracts scrom package, sets up all variables.
 * Called whenever seledascorm changes
 * @param object $seledascorm instance - fields are updated and changes saved into database
 * @param bool $full force full update if true
 * @return void
 */
function seledascorm_parse($seledascorm, $full) {
    global $CFG, $DB;
    $cfgseledascorm = get_config('seledascorm');

    if (!isset($seledascorm->cmid)) {
        $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);
        $seledascorm->cmid = $cm->id;
    }
    $context = context_module::instance($seledascorm->cmid);
    $newhash = $seledascorm->sha1hash;

    if ($seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCAL || $seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCALSYNC
        || $seledascorm->seledascormtype === SELEDASCORM_TYPE_EMBEDDED) {

        $fs = get_file_storage();
        $packagefile = false;
        $packagefileimsmanifest = false;

        if ($seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCAL || $seledascorm->seledascormtype === SELEDASCORM_TYPE_EMBEDDED) {
            if ($packagefile = $fs->get_file($context->id, 'mod_seledascorm', 'package', 0, '/', $seledascorm->reference)) {
                if ($packagefile->is_external_file()) { // Get zip file so we can check it is correct.
                    $packagefile->import_external_file_contents();
                    $errors = \mod_seledascorm\seledascorm_package::validate_file_list($packagefile);
                    if (! empty($errors)) {
                        // Invalid external file. Nothing to do.
                        return;
                    }

                }
                $newhash = $packagefile->get_contenthash();
                if (strtolower($packagefile->get_filename()) == 'imsmanifest.xml') {
                    $packagefileimsmanifest = true;
                }
            } else {
                $newhash = null;
            }
        } else {
            if (!$cfgseledascorm->allowtypelocalsync) {
                // Sorry - localsync disabled.
                return;
            }
            if ($seledascorm->reference !== '') {
                $fs->delete_area_files($context->id, 'mod_seledascorm', 'package');
                $filerecord = array('contextid' => $context->id, 'component' => 'mod_seledascorm', 'filearea' => 'package',
                                    'itemid' => 0, 'filepath' => '/');
                if ($packagefile = $fs->create_file_from_url($filerecord, $seledascorm->reference, ['calctimeout' => true], true)) {
                    $newhash = $packagefile->get_contenthash();
                } else {
                    $newhash = null;
                }
            }
        }

        if ($packagefile) {

            if (!$full && $packagefile && $seledascorm->sha1hash === $newhash) {
                if (strpos($seledascorm->version, 'SCORM') !== false) {
                    if ($packagefileimsmanifest ||
                        $fs->get_file($context->id, 'mod_seledascorm', 'content', 0, '/', 'imsmanifest.xml')
                    ) {
                        // No need to update.
                        return;
                    }
                } else if (strpos($seledascorm->version, 'AICC') !== false) {
                    // TODO: add more sanity checks - something really exists in seledascorm_content area.
                    return;
                }
            }
            if (!$packagefileimsmanifest) {
                // Now extract files.
                $fs->delete_area_files($context->id, 'mod_seledascorm', 'content');

                $packer = get_file_packer('application/zip');
                $packagefile->extract_to_storage($packer, $context->id, 'mod_seledascorm', 'content', 0, '/');
            }

        } else if (!$full) {
            return;
        }
        if ($packagefileimsmanifest) {
            require_once("$CFG->dirroot/mod/seledascorm/datamodels/scormlib.php");
            // Direct link to imsmanifest.xml file.
            if (!seledascorm_parse_scorm($seledascorm, $packagefile)) {
                $seledascorm->version = 'ERROR';
            }

        } else if ($manifest = $fs->get_file($context->id, 'mod_seledascorm', 'content', 0, '/', 'imsmanifest.xml')) {
            require_once("$CFG->dirroot/mod/seledascorm/datamodels/scormlib.php");
            // SELEDASCORM.
            if (!seledascorm_parse_scorm($seledascorm, $manifest)) {
                $seledascorm->version = 'ERROR';
            }
        } else {
            require_once("$CFG->dirroot/mod/seledascorm/datamodels/aicclib.php");
            // AICC.
            $result = seledascorm_parse_aicc($seledascorm);
            if (!$result) {
                $seledascorm->version = 'ERROR';
            } else {
                $seledascorm->version = 'AICC';
            }
        }

    } else if ($seledascorm->seledascormtype === SELEDASCORM_TYPE_EXTERNAL && $cfgseledascorm->allowtypeexternal) {
        require_once("$CFG->dirroot/mod/seledascorm/datamodels/scormlib.php");
        // SELEDASCORM only, AICC can not be external.
        if (!seledascorm_parse_scorm($seledascorm, $seledascorm->reference)) {
            $seledascorm->version = 'ERROR';
        }
        $newhash = sha1($seledascorm->reference);

    } else if ($seledascorm->seledascormtype === SELEDASCORM_TYPE_AICCURL  && $cfgseledascorm->allowtypeexternalaicc) {
        require_once("$CFG->dirroot/mod/seledascorm/datamodels/aicclib.php");
        // AICC.
        $result = seledascorm_parse_aicc($seledascorm);
        if (!$result) {
            $seledascorm->version = 'ERROR';
        } else {
            $seledascorm->version = 'AICC';
        }

    } else {
        // Sorry, disabled type.
        return;
    }

    $seledascorm->revision++;
    $seledascorm->sha1hash = $newhash;
    $DB->update_record('seledascorm', $seledascorm);
}


function seledascorm_array_search($item, $needle, $haystacks, $strict=false) {
    if (!empty($haystacks)) {
        foreach ($haystacks as $key => $element) {
            if ($strict) {
                if ($element->{$item} === $needle) {
                    return $key;
                }
            } else {
                if ($element->{$item} == $needle) {
                    return $key;
                }
            }
        }
    }
    return false;
}

function seledascorm_repeater($what, $times) {
    if ($times <= 0) {
        return null;
    }
    $return = '';
    for ($i = 0; $i < $times; $i++) {
        $return .= $what;
    }
    return $return;
}

function seledascorm_external_link($link) {
    // Check if a link is external.
    $result = false;
    $link = strtolower($link);
    if (substr($link, 0, 7) == 'http://') {
        $result = true;
    } else if (substr($link, 0, 8) == 'https://') {
        $result = true;
    } else if (substr($link, 0, 4) == 'www.') {
        $result = true;
    }
    return $result;
}

/**
 * Returns an object containing all datas relative to the given sco ID
 *
 * @param integer $id The sco ID
 * @return mixed (false if sco id does not exists)
 */
function seledascorm_get_sco($id, $what=SELEDASCORM_SCO_ALL) {
    global $DB;

    if ($sco = $DB->get_record('seledascorm_scoes', array('id' => $id))) {
        $sco = ($what == SELEDASCORM_SCO_DATA) ? new stdClass() : $sco;
        if (($what != SELEDASCORM_SCO_ONLY) && ($scodatas = $DB->get_records('seledascorm_scoes_data', array('scoid' => $id)))) {
            foreach ($scodatas as $scodata) {
                $sco->{$scodata->name} = $scodata->value;
            }
        } else if (($what != SELEDASCORM_SCO_ONLY) &&
            (!($scodatas = $DB->get_records('seledascorm_scoes_data', array('scoid' => $id))))
        ) {
            $sco->parameters = '';
        }
        return $sco;
    } else {
        return false;
    }
}

/**
 * Returns an object (array) containing all the scoes data related to the given sco ID
 *
 * @param integer $id The sco ID
 * @param integer $organisation an organisation ID - defaults to false if not required
 * @return mixed (false if there are no scoes or an array)
 */
function seledascorm_get_scoes($id, $organisation=false) {
    global $DB;

    $queryarray = array('seledascorm' => $id);
    if (!empty($organisation)) {
        $queryarray['organization'] = $organisation;
    }
    if ($scoes = $DB->get_records('seledascorm_scoes', $queryarray, 'sortorder, id')) {
        // Drop keys so that it is a simple array as expected.
        $scoes = array_values($scoes);
        foreach ($scoes as $sco) {
            if ($scodatas = $DB->get_records('seledascorm_scoes_data', array('scoid' => $sco->id))) {
                foreach ($scodatas as $scodata) {
                    $sco->{$scodata->name} = $scodata->value;
                }
            }
        }
        return $scoes;
    } else {
        return false;
    }
}

function seledascorm_insert_track($userid, $seledascormid, $scoid, $attempt, $element,
                                        $value, $forcecompleted=false, $trackdata = null) {
    global $DB, $CFG;

    $id = null;

    if ($forcecompleted) {
        // TODO - this could be broadened to encompass SCORM 2004 in future.
        if (($element == 'cmi.core.lesson_status') && ($value == 'incomplete')) {
            if ($track = $DB->get_record_select('seledascorm_scoes_track',
                                                'userid=? AND seledascormid=? AND scoid=? AND attempt=? '.
                                                'AND element=\'cmi.core.score.raw\'',
                                                array($userid, $seledascormid, $scoid, $attempt))) {
                $value = 'completed';
            }
        }
        if ($element == 'cmi.core.score.raw') {
            if ($tracktest = $DB->get_record_select('seledascorm_scoes_track',
                                                    'userid=? AND seledascormid=? AND scoid=? AND attempt=? '.
                                                    'AND element=\'cmi.core.lesson_status\'',
                                                    array($userid, $seledascormid, $scoid, $attempt))) {
                if ($tracktest->value == "incomplete") {
                    $tracktest->value = "completed";
                    $DB->update_record('seledascorm_scoes_track', $tracktest);
                }
            }
        }
        if (($element == 'cmi.success_status') && ($value == 'passed' || $value == 'failed')) {
            if ($DB->get_record('seledascorm_scoes_data', array('scoid' => $scoid, 'name' => 'objectivesetbycontent'))) {
                $objectiveprogressstatus = true;
                $objectivesatisfiedstatus = false;
                if ($value == 'passed') {
                    $objectivesatisfiedstatus = true;
                }

                if ($track = $DB->get_record('seledascorm_scoes_track', array('userid' => $userid,
                                                                        'seledascormid' => $seledascormid,
                                                                        'scoid' => $scoid,
                                                                        'attempt' => $attempt,
                                                                        'element' => 'objectiveprogressstatus'))) {
                    $track->value = $objectiveprogressstatus;
                    $track->timemodified = time();
                    $DB->update_record('seledascorm_scoes_track', $track);
                    $id = $track->id;
                } else {
                    $track = new stdClass();
                    $track->userid = $userid;
                    $track->seledascormid = $seledascormid;
                    $track->scoid = $scoid;
                    $track->attempt = $attempt;
                    $track->element = 'objectiveprogressstatus';
                    $track->value = $objectiveprogressstatus;
                    $track->timemodified = time();
                    $id = $DB->insert_record('seledascorm_scoes_track', $track);
                }
                if ($objectivesatisfiedstatus) {
                    if ($track = $DB->get_record('seledascorm_scoes_track', array('userid' => $userid,
                                                                            'seledascormid' => $seledascormid,
                                                                            'scoid' => $scoid,
                                                                            'attempt' => $attempt,
                                                                            'element' => 'objectivesatisfiedstatus'))) {
                        $track->value = $objectivesatisfiedstatus;
                        $track->timemodified = time();
                        $DB->update_record('seledascorm_scoes_track', $track);
                        $id = $track->id;
                    } else {
                        $track = new stdClass();
                        $track->userid = $userid;
                        $track->seledascormid = $seledascormid;
                        $track->scoid = $scoid;
                        $track->attempt = $attempt;
                        $track->element = 'objectivesatisfiedstatus';
                        $track->value = $objectivesatisfiedstatus;
                        $track->timemodified = time();
                        $id = $DB->insert_record('seledascorm_scoes_track', $track);
                    }
                }
            }
        }

    }

    $track = null;
    if ($trackdata !== null) {
        if (isset($trackdata[$element])) {
            $track = $trackdata[$element];
        }
    } else {
        $track = $DB->get_record('seledascorm_scoes_track', array('userid' => $userid,
                                                            'seledascormid' => $seledascormid,
                                                            'scoid' => $scoid,
                                                            'attempt' => $attempt,
                                                            'element' => $element));
    }
    if ($track) {
        if ($element != 'x.start.time' ) { // Don't update x.start.time - keep the original value.
            if ($track->value != $value) {
                $track->value = $value;
                $track->timemodified = time();
                $DB->update_record('seledascorm_scoes_track', $track);
            }
            $id = $track->id;
        }
    } else {
        $track = new stdClass();
        $track->userid = $userid;
        $track->seledascormid = $seledascormid;
        $track->scoid = $scoid;
        $track->attempt = $attempt;
        $track->element = $element;
        $track->value = $value;
        $track->timemodified = time();
        $id = $DB->insert_record('seledascorm_scoes_track', $track);
        $track->id = $id;
    }

    // Trigger updating grades based on a given set of SELEDASCORM CMI elements.
    $seledascorm = false;
    if (in_array($element, array('cmi.core.score.raw', 'cmi.score.raw')) ||
        (in_array($element, array('cmi.completion_status', 'cmi.core.lesson_status', 'cmi.success_status'))
         && in_array($track->value, array('completed', 'passed')))) {
        $seledascorm = $DB->get_record('seledascorm', array('id' => $seledascormid));
        include_once($CFG->dirroot.'/mod/seledascorm/lib.php');
        seledascorm_update_grades($seledascorm, $userid);
    }

    // Trigger CMI element events.
    if (in_array($element, array('cmi.core.score.raw', 'cmi.score.raw')) ||
        (in_array($element, array('cmi.completion_status', 'cmi.core.lesson_status', 'cmi.success_status'))
        && in_array($track->value, array('completed', 'failed', 'passed')))) {
        if (!$seledascorm) {
            $seledascorm = $DB->get_record('seledascorm', array('id' => $seledascormid));
        }
        $cm = get_coursemodule_from_instance('seledascorm', $seledascormid);
        $data = array(
            'other' => array('attemptid' => $attempt, 'cmielement' => $element, 'cmivalue' => $track->value),
            'objectid' => $seledascorm->id,
            'context' => context_module::instance($cm->id),
            'relateduserid' => $userid
        );
        if (in_array($element, array('cmi.core.score.raw', 'cmi.score.raw'))) {
            // Create score submitted event.
            $event = \mod_seledascorm\event\scoreraw_submitted::create($data);
        } else {
            // Create status submitted event.
            $event = \mod_seledascorm\event\status_submitted::create($data);
        }
        // Fix the missing track keys when the SELEDASCORM track record already exists, see $trackdata in datamodel.php.
        // There, for performances reasons, columns are limited to: element, id, value, timemodified.
        // Missing fields are: userid, seledascormid, scoid, attempt.
        $track->userid = $userid;
        $track->seledascormid = $seledascormid;
        $track->scoid = $scoid;
        $track->attempt = $attempt;
        // Trigger submitted event.
        $event->add_record_snapshot('seledascorm_scoes_track', $track);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('seledascorm', $seledascorm);
        $event->trigger();
    }

    return $id;
}

/**
 * simple quick function to return true/false if this user has tracks in this seledascorm
 *
 * @param integer $seledascormid The seledascorm ID
 * @param integer $userid the users id
 * @return boolean (false if there are no tracks)
 */
function seledascorm_has_tracks($seledascormid, $userid) {
    global $DB;
    return $DB->record_exists('seledascorm_scoes_track', array('userid' => $userid, 'seledascormid' => $seledascormid));
}

function seledascorm_get_tracks($scoid, $userid, $attempt='') {
    // Gets all tracks of specified sco and user.
    global $DB;

    if (empty($attempt)) {
        if ($seledascormid = $DB->get_field('seledascorm_scoes', 'seledascorm', array('id' => $scoid))) {
            $attempt = seledascorm_get_last_attempt($seledascormid, $userid);
        } else {
            $attempt = 1;
        }
    }
    if ($tracks = $DB->get_records('seledascorm_scoes_track', array('userid' => $userid, 'scoid' => $scoid,
                                                              'attempt' => $attempt), 'element ASC')) {
        $usertrack = seledascorm_format_interactions($tracks);
        $usertrack->userid = $userid;
        $usertrack->scoid = $scoid;

        return $usertrack;
    } else {
        return false;
    }
}
/**
 * helper function to return a formatted list of interactions for reports.
 *
 * @param array $trackdata the records from seledascorm_scoes_track table
 * @return object formatted list of interactions
 */
function seledascorm_format_interactions($trackdata) {
    $usertrack = new stdClass();

    // Defined in order to unify seledascorm1.2 and seledascorm2004.
    $usertrack->score_raw = '';
    $usertrack->status = '';
    $usertrack->total_time = '00:00:00';
    $usertrack->session_time = '00:00:00';
    $usertrack->timemodified = 0;

    foreach ($trackdata as $track) {
        $element = $track->element;
        $usertrack->{$element} = $track->value;
        switch ($element) {
            case 'cmi.core.lesson_status':
            case 'cmi.completion_status':
                if ($track->value == 'not attempted') {
                    $track->value = 'notattempted';
                }
                $usertrack->status = $track->value;
                break;
            case 'cmi.core.score.raw':
            case 'cmi.score.raw':
                $usertrack->score_raw = (float) sprintf('%2.2f', $track->value);
                break;
            case 'cmi.core.session_time':
            case 'cmi.session_time':
                $usertrack->session_time = $track->value;
                break;
            case 'cmi.core.total_time':
            case 'cmi.total_time':
                $usertrack->total_time = $track->value;
                break;
        }
        if (isset($track->timemodified) && ($track->timemodified > $usertrack->timemodified)) {
            $usertrack->timemodified = $track->timemodified;
        }
    }

    return $usertrack;
}
/* Find the start and finsh time for a a given SCO attempt
 *
 * @param int $seledascormid SELEDASCORM Id
 * @param int $scoid SCO Id
 * @param int $userid User Id
 * @param int $attemt Attempt Id
 *
 * @return object start and finsh time EPOC secods
 *
 */
function seledascorm_get_sco_runtime($seledascormid, $scoid, $userid, $attempt=1) {
    global $DB;

    $timedata = new stdClass();
    $params = array('userid' => $userid, 'seledascormid' => $seledascormid, 'attempt' => $attempt);
    if (!empty($scoid)) {
        $params['scoid'] = $scoid;
    }
    $tracks = $DB->get_records('seledascorm_scoes_track', $params, "timemodified ASC");
    if ($tracks) {
        $tracks = array_values($tracks);
    }

    if ($tracks) {
        $timedata->start = $tracks[0]->timemodified;
    } else {
        $timedata->start = false;
    }
    if ($tracks && $track = array_pop($tracks)) {
        $timedata->finish = $track->timemodified;
    } else {
        $timedata->finish = $timedata->start;
    }
    return $timedata;
}

function seledascorm_grade_user_attempt($seledascorm, $userid, $attempt=1) {
    global $DB;
    $attemptscore = new stdClass();
    $attemptscore->scoes = 0;
    $attemptscore->values = 0;
    $attemptscore->max = 0;
    $attemptscore->sum = 0;
    $attemptscore->lastmodify = 0;

    if (!$scoes = $DB->get_records('seledascorm_scoes', array('seledascorm' => $seledascorm->id), 'sortorder, id')) {
        return null;
    }

    foreach ($scoes as $sco) {
        if ($userdata = seledascorm_get_tracks($sco->id, $userid, $attempt)) {
            if (($userdata->status == 'completed') || ($userdata->status == 'passed')) {
                $attemptscore->scoes++;
            }
            // A raw score of 0 is a real score, so only SCOes that reported no score at all must be
            // skipped (seledascorm_format_interactions() defaults score_raw to an empty string in that case).
            if (isset($userdata->score_raw) && $userdata->score_raw !== '') {
                $attemptscore->values++;
                $attemptscore->sum += $userdata->score_raw;
                $attemptscore->max = ($userdata->score_raw > $attemptscore->max) ? $userdata->score_raw : $attemptscore->max;
                if (isset($userdata->timemodified) && ($userdata->timemodified > $attemptscore->lastmodify)) {
                    $attemptscore->lastmodify = $userdata->timemodified;
                }
            }
        }
    }
    switch ($seledascorm->grademethod) {
        case SELEDASCORM_GRADEHIGHEST:
            $score = (float) $attemptscore->max;
        break;
        case SELEDASCORM_GRADEAVERAGE:
            if ($attemptscore->values > 0) {
                $score = $attemptscore->sum / $attemptscore->values;
            } else {
                $score = 0;
            }
        break;
        case SELEDASCORM_GRADESUM:
            $score = $attemptscore->sum;
        break;
        case SELEDASCORM_GRADESCOES:
            $score = $attemptscore->scoes;
        break;
        default:
            $score = $attemptscore->max;   // Remote Learner SELEDASCORM_GRADEHIGHEST is default.
    }

    return $score;
}

function seledascorm_grade_user($seledascorm, $userid) {

    // Ensure we dont grade user beyond $seledascorm->maxattempt settings.
    $lastattempt = seledascorm_get_last_attempt($seledascorm->id, $userid);
    if ($seledascorm->maxattempt != 0 && $lastattempt >= $seledascorm->maxattempt) {
        $lastattempt = $seledascorm->maxattempt;
    }

    switch ($seledascorm->whatgrade) {
        case SELEDASCORM_FIRSTATTEMPT:
            return seledascorm_grade_user_attempt($seledascorm, $userid, seledascorm_get_first_attempt($seledascorm->id, $userid));
        break;
        case SELEDASCORM_LASTATTEMPT:
            return seledascorm_grade_user_attempt($seledascorm,
                                                $userid,
                                                seledascorm_get_last_completed_attempt($seledascorm->id, $userid)
                                            );
        break;
        case SELEDASCORM_HIGHESTATTEMPT:
            $maxscore = 0;
            for ($attempt = 1; $attempt <= $lastattempt; $attempt++) {
                $attemptscore = seledascorm_grade_user_attempt($seledascorm, $userid, $attempt);
                $maxscore = $attemptscore > $maxscore ? $attemptscore : $maxscore;
            }
            return $maxscore;

        break;
        case SELEDASCORM_AVERAGEATTEMPT:
            $attemptcount = seledascorm_get_attempt_count($userid, $seledascorm, true, true);
            if (empty($attemptcount)) {
                return 0;
            } else {
                $attemptcount = count($attemptcount);
            }
            $lastattempt = seledascorm_get_last_attempt($seledascorm->id, $userid);
            $sumscore = 0;
            for ($attempt = 1; $attempt <= $lastattempt; $attempt++) {
                $attemptscore = seledascorm_grade_user_attempt($seledascorm, $userid, $attempt);
                $sumscore += $attemptscore;
            }

            return round($sumscore / $attemptcount);
        break;
    }
}

function seledascorm_count_launchable($seledascormid, $organization='') {
    global $DB;

    $sqlorganization = '';
    $params = array($seledascormid);
    if (!empty($organization)) {
        $sqlorganization = " AND organization=?";
        $params[] = $organization;
    }
    return $DB->count_records_select('seledascorm_scoes', "seledascorm = ? $sqlorganization AND ".
                                        $DB->sql_isnotempty('seledascorm_scoes', 'launch', false, true),
                                        $params);
}

/**
 * Returns the last attempt used - if no attempts yet, returns 1 for first attempt
 *
 * @param int $seledascormid the id of the seledascorm.
 * @param int $userid the id of the user.
 *
 * @return int The attempt number to use.
 */
function seledascorm_get_last_attempt($seledascormid, $userid) {
    global $DB;

    // Find the last attempt number for the given user id and seledascorm id.
    $sql = "SELECT MAX(attempt)
              FROM {seledascorm_scoes_track}
             WHERE userid = ? AND seledascormid = ?";
    $lastattempt = $DB->get_field_sql($sql, array($userid, $seledascormid));
    if (empty($lastattempt)) {
        return '1';
    } else {
        return $lastattempt;
    }
}

/**
 * Returns the first attempt used - if no attempts yet, returns 1 for first attempt.
 *
 * @param int $seledascormid the id of the seledascorm.
 * @param int $userid the id of the user.
 *
 * @return int The first attempt number.
 */
function seledascorm_get_first_attempt($seledascormid, $userid) {
    global $DB;

    // Find the first attempt number for the given user id and seledascorm id.
    $sql = "SELECT MIN(attempt)
              FROM {seledascorm_scoes_track}
             WHERE userid = ? AND seledascormid = ?";

    $lastattempt = $DB->get_field_sql($sql, array($userid, $seledascormid));
    if (empty($lastattempt)) {
        return '1';
    } else {
        return $lastattempt;
    }
}

/**
 * Returns the last completed attempt used - if no completed attempts yet, returns 1 for first attempt
 *
 * @param int $seledascormid the id of the seledascorm.
 * @param int $userid the id of the user.
 *
 * @return int The attempt number to use.
 */
function seledascorm_get_last_completed_attempt($seledascormid, $userid) {
    global $DB;

    // Find the last completed attempt number for the given user id and seledascorm id.
    $sql = "SELECT MAX(attempt)
              FROM {seledascorm_scoes_track}
             WHERE userid = ? AND seledascormid = ?
               AND (".$DB->sql_compare_text('value')." = ".$DB->sql_compare_text('?')." OR ".
                      $DB->sql_compare_text('value')." = ".$DB->sql_compare_text('?').")";
    $lastattempt = $DB->get_field_sql($sql, array($userid, $seledascormid, 'completed', 'passed'));
    if (empty($lastattempt)) {
        return '1';
    } else {
        return $lastattempt;
    }
}

/**
 * Returns the full list of attempts a user has made.
 *
 * @param int $seledascormid the id of the seledascorm.
 * @param int $userid the id of the user.
 *
 * @return array array of attemptids
 */
function seledascorm_get_all_attempts($seledascormid, $userid) {
    global $DB;
    $attemptids = array();
    $sql = "SELECT DISTINCT attempt FROM {seledascorm_scoes_track} WHERE userid = ? AND seledascormid = ? ORDER BY attempt";
    $attempts = $DB->get_records_sql($sql, array($userid, $seledascormid));
    foreach ($attempts as $attempt) {
        $attemptids[] = $attempt->attempt;
    }
    return $attemptids;
}

/**
 * Displays the entry form and toc if required.
 *
 * @param  stdClass $user   user object
 * @param  stdClass $seledascorm  seledascorm object
 * @param  string   $action base URL for the organizations select box
 * @param  stdClass $cm     course module object
 */
function seledascorm_print_launch ($user, $seledascorm, $action, $cm) {
    global $CFG, $DB, $OUTPUT;

    if ($seledascorm->updatefreq == SELEDASCORM_UPDATE_EVERYTIME) {
        seledascorm_parse($seledascorm, false);
    }

    $organization = optional_param('organization', '', PARAM_INT);

    if ($seledascorm->displaycoursestructure == 1) {
        echo $OUTPUT->box_start('generalbox boxaligncenter toc', 'toc');
        echo html_writer::div(get_string('contents', 'mod_seledascorm'), 'structurehead');
    }
    if (empty($organization)) {
        $organization = $seledascorm->launch;
    }
    if ($orgs = $DB->get_records_select_menu('seledascorm_scoes', 'seledascorm = ? AND '.
                                         $DB->sql_isempty('seledascorm_scoes', 'launch', false, true).' AND '.
                                         $DB->sql_isempty('seledascorm_scoes', 'organization', false, false),
                                         array($seledascorm->id), 'sortorder, id', 'id,title')) {
        if (count($orgs) > 1) {
            $select = new single_select(new moodle_url($action), 'organization', $orgs, $organization, null);
            $select->label = get_string('organizations', 'mod_seledascorm');
            $select->class = 'seledascorm-center';
            echo $OUTPUT->render($select);
        }
    }
    $orgidentifier = '';
    if ($sco = seledascorm_get_sco($organization, SELEDASCORM_SCO_ONLY)) {
        if (($sco->organization == '') && ($sco->launch == '')) {
            $orgidentifier = $sco->identifier;
        } else {
            $orgidentifier = $sco->organization;
        }
    }

    $seledascorm->version = strtolower(clean_param($seledascorm->version, PARAM_SAFEDIR));   // Just to be safe.
    if (!file_exists($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'lib.php')) {
        $seledascorm->version = 'SCORM_12';
    }
    require_once($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'lib.php');

    $result = seledascorm_get_toc($user, $seledascorm, $cm->id, SELEDASCORM_TOCFULLURL, $orgidentifier);
    $incomplete = $result->incomplete;
    // Get latest incomplete sco to launch first if force new attempt isn't set to always.
    if (!empty($result->sco->id) && $seledascorm->forcenewattempt != SELEDASCORM_FORCEATTEMPT_ALWAYS) {
        $launchsco = $result->sco->id;
    } else {
        // Use launch defined by SELEDASCORM package.
        $launchsco = $seledascorm->launch;
    }

    // Do we want the TOC to be displayed?
    if ($seledascorm->displaycoursestructure == 1) {
        echo $result->toc;
        echo $OUTPUT->box_end();
    }

    // Is this the first attempt ?
    $attemptcount = seledascorm_get_attempt_count($user->id, $seledascorm);

    // Do not give the player launch FORM if the SELEDASCORM object is locked after the final attempt.
    if ($seledascorm->lastattemptlock == 0 || $result->attemptleft > 0) {
            echo html_writer::start_div('seledascorm-center');
            echo html_writer::start_tag('form', array('id' => 'seledascormviewform',
                                                        'method' => 'post',
                                                        'action' => $CFG->wwwroot.'/mod/seledascorm/player.php'));
        if ($seledascorm->hidebrowse == 0) {
            echo html_writer::tag('button', get_string('browse', 'mod_seledascorm'),
                    ['class' => 'btn btn-secondary mr-1', 'name' => 'mode',
                        'type' => 'submit', 'id' => 'b', 'value' => 'browse'])
                . html_writer::end_tag('button');
        } else {
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mode', 'value' => 'normal'));
        }
        echo html_writer::tag('button', get_string('enter', 'mod_seledascorm'),
                ['class' => 'btn btn-primary mx-1', 'name' => 'mode',
                    'type' => 'submit', 'id' => 'n', 'value' => 'normal'])
             . html_writer::end_tag('button');
        if (!empty($seledascorm->forcenewattempt)) {
            if ($seledascorm->forcenewattempt == SELEDASCORM_FORCEATTEMPT_ALWAYS ||
                    ($seledascorm->forcenewattempt == SELEDASCORM_FORCEATTEMPT_ONCOMPLETE && $incomplete === false)) {
                echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'newattempt', 'value' => 'on'));
            }
        } else if (
            !empty($attemptcount) &&
            ($incomplete === false) &&
            (($result->attemptleft > 0)||($seledascorm->maxattempt == 0))
        ) {
            echo html_writer::start_div('pt-1');
            echo html_writer::checkbox('newattempt', 'on', false, '', array('id' => 'a'));
            echo html_writer::label(get_string('newattempt', 'mod_seledascorm'), 'a', true, ['class' => 'pl-1']);
            echo html_writer::end_div();
        }
        if (!empty($seledascorm->popup)) {
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'display', 'value' => 'popup'));
        }

        echo html_writer::empty_tag('br');
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scoid', 'value' => $launchsco));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cm', 'value' => $cm->id));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'currentorg', 'value' => $orgidentifier));
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    }
}

function seledascorm_simple_play($seledascorm, $user, $context, $cmid) {
    global $DB;

    $result = false;

    if (has_capability('mod/seledascorm:viewreport', $context)) {
        // If this user can view reports, don't skipview so they can see links to reports.
        return $result;
    }

    if ($seledascorm->updatefreq == SELEDASCORM_UPDATE_EVERYTIME) {
        seledascorm_parse($seledascorm, false);
    }
    $scoes = $DB->get_records_select('seledascorm_scoes', 'seledascorm = ? AND '.
        $DB->sql_isnotempty('seledascorm_scoes', 'launch', false, true), array($seledascorm->id), 'sortorder, id', 'id');

    if ($scoes) {
        $orgidentifier = '';
        if ($sco = seledascorm_get_sco($seledascorm->launch, SELEDASCORM_SCO_ONLY)) {
            if (($sco->organization == '') && ($sco->launch == '')) {
                $orgidentifier = $sco->identifier;
            } else {
                $orgidentifier = $sco->organization;
            }
        }
        if ($seledascorm->skipview >= SELEDASCORM_SKIPVIEW_FIRST) {
            $sco = current($scoes);
            $result = seledascorm_get_toc($user, $seledascorm, $cmid, SELEDASCORM_TOCFULLURL, $orgidentifier);
            $url = new moodle_url('/mod/seledascorm/player.php', array('a' => $seledascorm->id, 'currentorg' => $orgidentifier));

            // Set last incomplete sco to launch first if forcenewattempt not set to always.
            if (!empty($result->sco->id) && $seledascorm->forcenewattempt != SELEDASCORM_FORCEATTEMPT_ALWAYS) {
                $url->param('scoid', $result->sco->id);
            } else {
                $url->param('scoid', $sco->id);
            }

            if ($seledascorm->skipview == SELEDASCORM_SKIPVIEW_ALWAYS || !seledascorm_has_tracks($seledascorm->id, $user->id)) {
                if ($seledascorm->forcenewattempt == SELEDASCORM_FORCEATTEMPT_ALWAYS ||
                   ($result->incomplete === false && $seledascorm->forcenewattempt == SELEDASCORM_FORCEATTEMPT_ONCOMPLETE)) {

                    $url->param('newattempt', 'on');
                }
                redirect($url);
            }
        }
    }
    return $result;
}

function seledascorm_get_count_users($seledascormid, $groupingid=null) {
    global $CFG, $DB;

    if (!empty($groupingid)) {
        $sql = "SELECT COUNT(DISTINCT st.userid)
                FROM {seledascorm_scoes_track} st
                    INNER JOIN {groups_members} gm ON st.userid = gm.userid
                    INNER JOIN {groupings_groups} gg ON gm.groupid = gg.groupid
                WHERE st.seledascormid = ? AND gg.groupingid = ?
                ";
        $params = array($seledascormid, $groupingid);
    } else {
        $sql = "SELECT COUNT(DISTINCT st.userid)
                FROM {seledascorm_scoes_track} st
                WHERE st.seledascormid = ?
                ";
        $params = array($seledascormid);
    }

    return ($DB->count_records_sql($sql, $params));
}

/**
 * Build up the JavaScript representation of an array element
 *
 * @param string $sversion SELEDASCORM API version
 * @param array $userdata User track data
 * @param string $elementname Name of array element to get values for
 * @param array $children list of sub elements of this array element that also need instantiating
 * @return Javascript array elements
 */
function seledascorm_reconstitute_array_element($sversion, $userdata, $elementname, $children) {
    // Reconstitute comments_from_learner and comments_from_lms.
    $current = '';
    $currentsubelement = '';
    $currentsub = '';
    $count = 0;
    $countsub = 0;
    $seledascormseperator = '_';
    $return = '';
    if (seledascorm_version_check($sversion, SELEDASCORM_SCORM_13)) { // Scorm 1.3 elements use a . instead of an _ .
        $seledascormseperator = '.';
    }
    // Filter out the ones we want.
    $elementlist = array();
    foreach ($userdata as $element => $value) {
        if (substr($element, 0, strlen($elementname)) == $elementname) {
            $elementlist[$element] = $value;
        }
    }

    // Sort elements in .n array order.
    uksort($elementlist, "seledascorm_element_cmp");

    // Generate JavaScript.
    foreach ($elementlist as $element => $value) {
        if (seledascorm_version_check($sversion, SELEDASCORM_SCORM_13)) {
            $element = preg_replace('/\.(\d+)\./', ".N\$1.", $element);
            preg_match('/\.(N\d+)\./', $element, $matches);
        } else {
            $element = preg_replace('/\.(\d+)\./', "_\$1.", $element);
            preg_match('/\_(\d+)\./', $element, $matches);
        }
        if (count($matches) > 0 && $current != $matches[1]) {
            if ($countsub > 0) {
                $return .= '    '.$elementname.$seledascormseperator.$current.'.'.$currentsubelement.'._count = '.$countsub.";\n";
            }
            $current = $matches[1];
            $count++;
            $currentsubelement = '';
            $currentsub = '';
            $countsub = 0;
            $end = strpos($element, $matches[1]) + strlen($matches[1]);
            $subelement = substr($element, 0, $end);
            $return .= '    '.$subelement." = new Object();\n";
            // Now add the children.
            foreach ($children as $child) {
                $return .= '    '.$subelement.".".$child." = new Object();\n";
                $return .= '    '.$subelement.".".$child."._children = ".$child."_children;\n";
            }
        }

        // Now - flesh out the second level elements if there are any.
        if (seledascorm_version_check($sversion, SELEDASCORM_SCORM_13)) {
            $element = preg_replace('/(.*?\.N\d+\..*?)\.(\d+)\./', "\$1.N\$2.", $element);
            preg_match('/.*?\.N\d+\.(.*?)\.(N\d+)\./', $element, $matches);
        } else {
            $element = preg_replace('/(.*?\_\d+\..*?)\.(\d+)\./', "\$1_\$2.", $element);
            preg_match('/.*?\_\d+\.(.*?)\_(\d+)\./', $element, $matches);
        }

        // Check the sub element type.
        if (count($matches) > 0 && $currentsubelement != $matches[1]) {
            if ($countsub > 0) {
                $return .= '    '.$elementname.$seledascormseperator.$current.'.'.$currentsubelement.'._count = '.$countsub.";\n";
            }
            $currentsubelement = $matches[1];
            $currentsub = '';
            $countsub = 0;
            $end = strpos($element, $matches[1]) + strlen($matches[1]);
            $subelement = substr($element, 0, $end);
            $return .= '    '.$subelement." = new Object();\n";
        }

        // Now check the subelement subscript.
        if (count($matches) > 0 && $currentsub != $matches[2]) {
            $currentsub = $matches[2];
            $countsub++;
            $end = strrpos($element, $matches[2]) + strlen($matches[2]);
            $subelement = substr($element, 0, $end);
            $return .= '    '.$subelement." = new Object();\n";
        }

        $return .= '    '.$element.' = '.json_encode($value).";\n";
    }
    if ($countsub > 0) {
        $return .= '    '.$elementname.$seledascormseperator.$current.'.'.$currentsubelement.'._count = '.$countsub.";\n";
    }
    if ($count > 0) {
        $return .= '    '.$elementname.'._count = '.$count.";\n";
    }
    return $return;
}

/**
 * Build up the JavaScript representation of an array element
 *
 * @param string $a left array element
 * @param string $b right array element
 * @return comparator - 0,1,-1
 */
function seledascorm_element_cmp($a, $b) {
    preg_match('/.*?(\d+)\./', $a, $matches);
    $left = intval($matches[1]);
    preg_match('/.?(\d+)\./', $b, $matches);
    $right = intval($matches[1]);
    if ($left < $right) {
        return -1; // Smaller.
    } else if ($left > $right) {
        return 1;  // Bigger.
    } else {
        // Look for a second level qualifier eg cmi.interactions_0.correct_responses_0.pattern.
        if (preg_match('/.*?(\d+)\.(.*?)\.(\d+)\./', $a, $matches)) {
            $leftterm = intval($matches[2]);
            $left = intval($matches[3]);
            if (preg_match('/.*?(\d+)\.(.*?)\.(\d+)\./', $b, $matches)) {
                $rightterm = intval($matches[2]);
                $right = intval($matches[3]);
                if ($leftterm < $rightterm) {
                    return -1; // Smaller.
                } else if ($leftterm > $rightterm) {
                    return 1;  // Bigger.
                } else {
                    if ($left < $right) {
                        return -1; // Smaller.
                    } else if ($left > $right) {
                        return 1;  // Bigger.
                    }
                }
            }
        }
        // Fall back for no second level matches or second level matches are equal.
        return 0;  // Equal to.
    }
}

/**
 * Generate the user attempt status string
 *
 * @param object $user Current context user
 * @param object $seledascorm a moodle scrom object - mdl_seledascorm
 * @param stdClass|null $cm Course module object (optional).
 * @return string - Attempt status string
 */
function seledascorm_get_attempt_status($user, $seledascorm, $cm = null) {
    global $DB, $PAGE, $OUTPUT;

    $attempts = seledascorm_get_attempt_count($user->id, $seledascorm, true);
    if (empty($attempts)) {
        $attemptcount = 0;
    } else {
        $attemptcount = count($attempts);
    }

    $result = html_writer::start_tag('p').get_string('noattemptsallowed', 'mod_seledascorm').': ';
    if ($seledascorm->maxattempt > 0) {
        $result .= $seledascorm->maxattempt . html_writer::empty_tag('br');
    } else {
        $result .= get_string('unlimited').html_writer::empty_tag('br');
    }
    $result .= get_string('noattemptsmade', 'mod_seledascorm').': ' . $attemptcount . html_writer::empty_tag('br');

    if ($seledascorm->maxattempt == 1) {
        switch ($seledascorm->grademethod) {
            case SELEDASCORM_GRADEHIGHEST:
                $grademethod = get_string('gradehighest', 'mod_seledascorm');
            break;
            case SELEDASCORM_GRADEAVERAGE:
                $grademethod = get_string('gradeaverage', 'mod_seledascorm');
            break;
            case SELEDASCORM_GRADESUM:
                $grademethod = get_string('gradesum', 'mod_seledascorm');
            break;
            case SELEDASCORM_GRADESCOES:
                $grademethod = get_string('gradescoes', 'mod_seledascorm');
            break;
        }
    } else {
        switch ($seledascorm->whatgrade) {
            case SELEDASCORM_HIGHESTATTEMPT:
                $grademethod = get_string('SELEDASCORM_HIGHESTATTEMPT', 'mod_seledascorm');
            break;
            case SELEDASCORM_AVERAGEATTEMPT:
                $grademethod = get_string('SELEDASCORM_AVERAGEATTEMPT', 'mod_seledascorm');
            break;
            case SELEDASCORM_FIRSTATTEMPT:
                $grademethod = get_string('firstattempt', 'mod_seledascorm');
            break;
            case SELEDASCORM_LASTATTEMPT:
                $grademethod = get_string('lastattempt', 'mod_seledascorm');
            break;
        }
    }

    if (!empty($attempts)) {
        $i = 1;
        foreach ($attempts as $attempt) {
            $gradereported = seledascorm_grade_user_attempt($seledascorm, $user->id, $attempt->attemptnumber);
            if ($seledascorm->grademethod !== SELEDASCORM_GRADESCOES && !empty($seledascorm->maxgrade)) {
                $gradereported = $gradereported / $seledascorm->maxgrade;
                $gradereported = number_format($gradereported * 100, 0) .'%';
            }
            $result .= get_string('gradeforattempt', 'mod_seledascorm').' ' . $i . ': ' . $gradereported .html_writer::empty_tag('br');
            $i++;
        }
    }
    $calculatedgrade = seledascorm_grade_user($seledascorm, $user->id);
    if ($seledascorm->grademethod !== SELEDASCORM_GRADESCOES && !empty($seledascorm->maxgrade)) {
        $calculatedgrade = $calculatedgrade / $seledascorm->maxgrade;
        $calculatedgrade = number_format($calculatedgrade * 100, 0) .'%';
    }
    $result .= get_string('grademethod', 'mod_seledascorm'). ': ' . $grademethod;
    if (empty($attempts)) {
        $result .= html_writer::empty_tag('br').get_string('gradereported', 'mod_seledascorm').
                    ': '.get_string('none').html_writer::empty_tag('br');
    } else {
        $result .= html_writer::empty_tag('br').get_string('gradereported', 'mod_seledascorm').
                    ': '.$calculatedgrade.html_writer::empty_tag('br');
    }
    $result .= html_writer::end_tag('p');
    if ($attemptcount >= $seledascorm->maxattempt && $seledascorm->maxattempt > 0) {
        $result .= html_writer::tag('p', get_string('exceededmaxattempts', 'mod_seledascorm'), ['class' => 'exceededmaxattempts']);
    }
    if (!empty($cm)) {
        $context = context_module::instance($cm->id);
        if (has_capability('mod/seledascorm:deleteownresponses', $context) &&
            $DB->record_exists('seledascorm_scoes_track', array('userid' => $user->id, 'seledascormid' => $seledascorm->id))) {
            // Check to see if any data is stored for this user.
            $deleteurl = new moodle_url($PAGE->url, array('action' => 'delete', 'sesskey' => sesskey()));
            $result .= $OUTPUT->single_button($deleteurl, get_string('deleteallattempts', 'mod_seledascorm'));
        }
    }

    return $result;
}

/**
 * Get SELEDASCORM attempt count
 *
 * @param object $user Current context user
 * @param object $seledascorm a moodle scrom object - mdl_seledascorm
 * @param bool $returnobjects if true returns a object with attempts, if false returns count of attempts.
 * @param bool $ignoremissingcompletion - ignores attempts that haven't reported a grade/completion.
 * @return array|int - no. of attempts so far
 */
function seledascorm_get_attempt_count($userid, $seledascorm, $returnobjects = false, $ignoremissingcompletion = false) {
    global $DB;

    // Historically attempts that don't report these elements haven't been included in the average attempts grading method
    // we may want to change this in future, but to avoid unexpected grade decreases we're leaving this in. MDL-43222 .
    if (seledascorm_version_check($seledascorm->version, SELEDASCORM_SCORM_13)) {
        $element = 'cmi.score.raw';
    } else if ($seledascorm->grademethod == SELEDASCORM_GRADESCOES) {
        $element = 'cmi.core.lesson_status';
    } else {
        $element = 'cmi.core.score.raw';
    }

    if ($returnobjects) {
        $params = array('userid' => $userid, 'seledascormid' => $seledascorm->id);
        if ($ignoremissingcompletion) { // Exclude attempts that don't have the completion element requested.
            $params['element'] = $element;
        }
        $attempts = $DB->get_records('seledascorm_scoes_track', $params, 'attempt', 'DISTINCT attempt AS attemptnumber');
        return $attempts;
    } else {
        $params = array($userid, $seledascorm->id);
        $sql = "SELECT COUNT(DISTINCT attempt)
                  FROM {seledascorm_scoes_track}
                 WHERE userid = ? AND seledascormid = ?";
        if ($ignoremissingcompletion) { // Exclude attempts that don't have the completion element requested.
            $sql .= ' AND element = ?';
            $params[] = $element;
        }

        $attemptscount = $DB->count_records_sql($sql, $params);
        return $attemptscount;
    }
}

/**
 * Figure out with this is a debug situation
 *
 * @param object $seledascorm a moodle scrom object - mdl_seledascorm
 * @return boolean - debugging true/false
 */
function seledascorm_debugging($seledascorm) {
    global $USER;
    $cfgseledascorm = get_config('seledascorm');

    if (!$cfgseledascorm->allowapidebug) {
        return false;
    }
    $identifier = $USER->username.':'.$seledascorm->name;
    $test = $cfgseledascorm->apidebugmask;
    // Check the regex is only a short list of safe characters.
    if (!preg_match('/^[\w\s\*\.\?\+\:\_\\\]+$/', $test)) {
        return false;
    }

    if (preg_match('/^'.$test.'/', $identifier)) {
        return true;
    }
    return false;
}

/**
 * Delete Scorm tracks for selected users
 *
 * @param array $attemptids list of attempts that need to be deleted
 * @param stdClass $seledascorm instance
 *
 * @return bool true deleted all responses, false failed deleting an attempt - stopped here
 */
function seledascorm_delete_responses($attemptids, $seledascorm) {
    if (!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach ($attemptids as $num => $attemptid) {
        if (empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    foreach ($attemptids as $attempt) {
        $keys = explode(':', $attempt);
        if (count($keys) == 2) {
            $userid = clean_param($keys[0], PARAM_INT);
            $attemptid = clean_param($keys[1], PARAM_INT);
            if (!$userid || !$attemptid || !seledascorm_delete_attempt($userid, $seledascorm, $attemptid)) {
                    return false;
            }
        } else {
            return false;
        }
    }
    return true;
}

/**
 * Delete Scorm tracks for selected users
 *
 * @param int $userid ID of User
 * @param stdClass $seledascorm Scorm object
 * @param int $attemptid user attempt that need to be deleted
 *
 * @return bool true suceeded
 */
function seledascorm_delete_attempt($userid, $seledascorm, $attemptid) {
    global $DB;

    $DB->delete_records('seledascorm_scoes_track',
                        ['userid' => $userid, 'seledascormid' => $seledascorm->id, 'attempt' => $attemptid]);
    $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);

    // Trigger instances list viewed event.
    $event = \mod_seledascorm\event\attempt_deleted::create(array(
         'other' => array('attemptid' => $attemptid),
         'context' => context_module::instance($cm->id),
         'relateduserid' => $userid
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('seledascorm', $seledascorm);
    $event->trigger();

    include_once('lib.php');
    seledascorm_update_grades($seledascorm, $userid, true);
    return true;
}

/**
 * Converts SELEDASCORM duration notation to human-readable format
 * The function works with both SCORM 1.2 and SCORM 2004 time formats
 * @param $duration string SELEDASCORM duration
 * @return string human-readable date/time
 */
function seledascorm_format_duration($duration) {
    // Fetch date/time strings.
    $stryears = get_string('years');
    $strmonths = get_string('nummonths');
    $strdays = get_string('days');
    $strhours = get_string('hours');
    $strminutes = get_string('minutes');
    $strseconds = get_string('seconds');

    if ($duration[0] == 'P') {
        // If timestamp starts with 'P' - it's a SCORM 2004 format
        // this regexp discards empty sections, takes Month/Minute ambiguity into consideration,
        // and outputs filled sections, discarding leading zeroes and any format literals
        // also saves the only zero before seconds decimals (if there are any) and discards decimals if they are zero.
        $pattern = array( '#([A-Z])0+Y#', '#([A-Z])0+M#', '#([A-Z])0+D#', '#P(|\d+Y)0*(\d+)M#',
                            '#0*(\d+)Y#', '#0*(\d+)D#', '#P#', '#([A-Z])0+H#', '#([A-Z])[0.]+S#',
                            '#\.0+S#', '#T(|\d+H)0*(\d+)M#', '#0*(\d+)H#', '#0+\.(\d+)S#',
                            '#0*([\d.]+)S#', '#T#' );
        $replace = array( '$1', '$1', '$1', '$1$2 '.$strmonths.' ', '$1 '.$stryears.' ', '$1 '.$strdays.' ',
                            '', '$1', '$1', 'S', '$1$2 '.$strminutes.' ', '$1 '.$strhours.' ',
                            '0.$1 '.$strseconds, '$1 '.$strseconds, '');
    } else {
        // Else we have SCORM 1.2 format there
        // first convert the timestamp to some SCORM 2004-like format for conveniency.
        $duration = preg_replace('#^(\d+):(\d+):([\d.]+)$#', 'T$1H$2M$3S', $duration);
        // Then convert in the same way as SCORM 2004.
        $pattern = array( '#T0+H#', '#([A-Z])0+M#', '#([A-Z])[0.]+S#', '#\.0+S#', '#0*(\d+)H#',
                            '#0*(\d+)M#', '#0+\.(\d+)S#', '#0*([\d.]+)S#', '#T#' );
        $replace = array( 'T', '$1', '$1', 'S', '$1 '.$strhours.' ', '$1 '.$strminutes.' ',
                            '0.$1 '.$strseconds, '$1 '.$strseconds, '' );
    }

    $result = preg_replace($pattern, $replace, $duration);

    return $result;
}

function seledascorm_get_toc_object($user, $seledascorm, $currentorg='', $scoid='', $mode='normal', $attempt='',
                                $play=false, $organizationsco=null) {
    global $CFG, $DB, $PAGE, $OUTPUT;

    // Always pass the mode even if empty as that is what is done elsewhere and the urls have to match.
    $modestr = '&mode=';
    if ($mode != 'normal') {
        $modestr = '&mode='.$mode;
    }

    $result = array();
    $incomplete = false;
    $usertracks = array();

    if (!empty($organizationsco)) {
        $result[0] = $organizationsco;
        $result[0]->isvisible = 'true';
        $result[0]->statusicon = '';
        $result[0]->url = '';
    }

    if ($scoes = seledascorm_get_scoes($seledascorm->id, $currentorg)) {
        // Retrieve user tracking data for each learning object.
        foreach ($scoes as $sco) {
            if (!empty($sco->launch)) {
                if ($usertrack = seledascorm_get_tracks($sco->id, $user->id, $attempt)) {
                    if ($usertrack->status == '') {
                        $usertrack->status = 'notattempted';
                    }
                    $usertracks[$sco->identifier] = $usertrack;
                }
            }
        }
        foreach ($scoes as $sco) {
            if (!isset($sco->isvisible)) {
                $sco->isvisible = 'true';
            }

            if (empty($sco->title)) {
                $sco->title = $sco->identifier;
            }

            if (seledascorm_version_check($seledascorm->version, SELEDASCORM_SCORM_13)) {
                $sco->prereq = true;
            } else {
                $sco->prereq = empty($sco->prerequisites) || seledascorm_eval_prerequisites($sco->prerequisites, $usertracks);
            }

            if ($sco->isvisible === 'true') {
                if (!empty($sco->launch)) {
                    // Set first sco to launch if in browse/review mode.
                    if (empty($scoid) && ($mode != 'normal')) {
                        $scoid = $sco->id;
                    }

                    if (isset($usertracks[$sco->identifier])) {
                        $usertrack = $usertracks[$sco->identifier];

                        // Check we have a valid status string identifier.
                        if ($statusstringexists = get_string_manager()->string_exists($usertrack->status, 'mod_seledascorm')) {
                            $strstatus = get_string($usertrack->status, 'mod_seledascorm');
                        } else {
                            $strstatus = get_string('invalidstatus', 'mod_seledascorm');
                        }

                        if ($sco->seledascormtype == 'sco') {
                            // Assume if we didn't get a valid status string, we don't have an icon either.
                            $statusicon = $OUTPUT->pix_icon($statusstringexists ? $usertrack->status : 'incomplete',
                                $strstatus, 'seledascorm');
                        } else {
                            $statusicon = $OUTPUT->pix_icon('asset', get_string('assetlaunched', 'mod_seledascorm'), 'mod_seledascorm');
                        }

                        if (($usertrack->status == 'notattempted') ||
                                ($usertrack->status == 'incomplete') ||
                                ($usertrack->status == 'browsed')) {
                            $incomplete = true;
                            if (empty($scoid)) {
                                $scoid = $sco->id;
                            }
                        }

                        $strsuspended = get_string('suspended', 'mod_seledascorm');

                        $exitvar = 'cmi.core.exit';

                        if (seledascorm_version_check($seledascorm->version, SELEDASCORM_SCORM_13)) {
                            $exitvar = 'cmi.exit';
                        }

                        if ($incomplete && isset($usertrack->{$exitvar}) && ($usertrack->{$exitvar} == 'suspend')) {
                            $statusicon = $OUTPUT->pix_icon('suspend', $strstatus.' - '.$strsuspended, 'seledascorm');
                        }

                    } else {
                        if (empty($scoid)) {
                            $scoid = $sco->id;
                        }

                        $incomplete = true;

                        if ($sco->seledascormtype == 'sco') {
                            $statusicon = $OUTPUT->pix_icon('notattempted',
                                                            get_string('notattempted', 'mod_seledascorm'),
                                                            'seledascorm');
                        } else {
                            $statusicon = $OUTPUT->pix_icon('asset', get_string('asset', 'mod_seledascorm'), 'mod_seledascorm');
                        }
                    }
                }
            }

            if (empty($statusicon)) {
                $sco->statusicon = $OUTPUT->pix_icon('notattempted', get_string('notattempted', 'mod_seledascorm'), 'mod_seledascorm');
            } else {
                $sco->statusicon = $statusicon;
            }

            $sco->url = 'a='.$seledascorm->id.'&scoid='.$sco->id.'&currentorg='.$currentorg.$modestr.'&attempt='.$attempt;
            $sco->incomplete = $incomplete;

            if (!in_array($sco->id, array_keys($result))) {
                $result[$sco->id] = $sco;
            }
        }
    }

    // Get the parent scoes!
    $result = seledascorm_get_toc_get_parent_child($result, $currentorg);

    // Be safe, prevent warnings from showing up while returning array.
    if (!isset($scoid)) {
        $scoid = '';
    }

    return array('scoes' => $result, 'usertracks' => $usertracks, 'scoid' => $scoid);
}

function seledascorm_get_toc_get_parent_child(&$result, $currentorg) {
    $final = array();
    $level = 0;
    // Organization is always the root, prevparent.
    if (!empty($currentorg)) {
        $prevparent = $currentorg;
    } else {
        $prevparent = '/';
    }

    foreach ($result as $sco) {
        if ($sco->parent == '/') {
            $final[$level][$sco->identifier] = $sco;
            $prevparent = $sco->identifier;
            unset($result[$sco->id]);
        } else {
            if ($sco->parent == $prevparent) {
                $final[$level][$sco->identifier] = $sco;
                $prevparent = $sco->identifier;
                unset($result[$sco->id]);
            } else {
                if (!empty($final[$level])) {
                    $found = false;
                    foreach ($final[$level] as $fin) {
                        if ($sco->parent == $fin->identifier) {
                            $found = true;
                        }
                    }

                    if ($found) {
                        $final[$level][$sco->identifier] = $sco;
                        unset($result[$sco->id]);
                        $found = false;
                    } else {
                        $level++;
                        $final[$level][$sco->identifier] = $sco;
                        unset($result[$sco->id]);
                    }
                }
            }
        }
    }

    for ($i = 0; $i <= $level; $i++) {
        if (empty($final[$i])) {
            continue;
        }
        $prevparent = '';
        foreach ($final[$i] as $ident => $sco) {
            if (empty($prevparent)) {
                $prevparent = $ident;
            }
            if (!isset($final[$i][$prevparent]->children)) {
                $final[$i][$prevparent]->children = array();
            }
            if ($sco->parent == $prevparent) {
                $final[$i][$prevparent]->children[] = $sco;
                $prevparent = $ident;
            } else {
                $parent = false;
                foreach ($final[$i] as $identifier => $scoobj) {
                    if ($identifier == $sco->parent) {
                        $parent = $identifier;
                    }
                }

                if ($parent !== false) {
                    $final[$i][$parent]->children[] = $sco;
                }
            }
        }
    }

    $results = array();
    for ($i = 0; $i <= $level; $i++) {
        if (empty($final[$i])) {
            continue;
        }
        $keys = array_keys($final[$i]);
        $results[] = $final[$i][$keys[0]];
    }

    return $results;
}

function seledascorm_format_toc_for_treeview($user, $seledascorm, $scoes, $usertracks, $cmid, $toclink = SELEDASCORM_TOCJSLINK,
                                        $currentorg='', $attempt='', $play=false, $organizationsco=null, $children=false) {
    global $CFG;

    $result = new stdClass();
    $result->prerequisites = true;
    $result->incomplete = true;
    $result->toc = '';

    if (!$children) {
        $attemptsmade = seledascorm_get_attempt_count($user->id, $seledascorm);
        $result->attemptleft = $seledascorm->maxattempt == 0 ? 1 : $seledascorm->maxattempt - $attemptsmade;
    }

    if (!$children) {
        $result->toc = html_writer::start_tag('ul');

        if (!$play && !empty($organizationsco)) {
            $result->toc .= html_writer::start_tag('li').$organizationsco->title.html_writer::end_tag('li');
        }
    }

    $prevsco = null;
    if (!empty($scoes)) {
        foreach ($scoes as $sco) {

            if ($sco->isvisible === 'false') {
                continue;
            }

            $result->toc .= html_writer::start_tag('li');
            $scoid = $sco->id;

            $score = '';

            if (isset($usertracks[$sco->identifier])) {
                $viewscore = has_capability('mod/seledascorm:viewscores', context_module::instance($cmid));
                if (isset($usertracks[$sco->identifier]->score_raw) && $viewscore) {
                    if ($usertracks[$sco->identifier]->score_raw != '') {
                        $score = '('.get_string('score', 'mod_seledascorm').':&nbsp;'.$usertracks[$sco->identifier]->score_raw.')';
                    }
                }
            }

            if (!empty($sco->prereq)) {
                if ($sco->id == $scoid) {
                    $result->prerequisites = true;
                }

                if (!empty($prevsco) &&
                    seledascorm_version_check($seledascorm->version, SELEDASCORM_SCORM_13) &&
                    !empty($prevsco->hidecontinue)
                ) {
                    if ($sco->seledascormtype == 'sco') {
                        $result->toc .= html_writer::span($sco->statusicon.'&nbsp;'.format_string($sco->title));
                    } else {
                        $result->toc .= html_writer::span('&nbsp;'.format_string($sco->title));
                    }
                } else if ($toclink == SELEDASCORM_TOCFULLURL) {
                    $url = $CFG->wwwroot.'/mod/seledascorm/player.php?'.$sco->url;
                    if (!empty($sco->launch)) {
                        if ($sco->seledascormtype == 'sco') {
                            $result->toc .= $sco->statusicon.'&nbsp;';
                            $result->toc .= html_writer::link($url, format_string($sco->title)).$score;
                        } else {
                            $result->toc .= '&nbsp;'.html_writer::link($url, format_string($sco->title),
                                                                        array('data-scoid' => $sco->id)).$score;
                        }
                    } else {
                        if ($sco->seledascormtype == 'sco') {
                            $result->toc .= $sco->statusicon.'&nbsp;'.format_string($sco->title).$score;
                        } else {
                            $result->toc .= '&nbsp;'.format_string($sco->title).$score;
                        }
                    }
                } else {
                    if (!empty($sco->launch)) {
                        if ($sco->seledascormtype == 'sco') {
                            $result->toc .= html_writer::tag('a', $sco->statusicon.
                                                                format_string($sco->title).($score ? '&nbsp;'.$score : ''),
                                                                array('data-scoid' => $sco->id, 'title' => $sco->url,
                                                                    'href' => '#'));
                        } else {
                            $result->toc .= html_writer::tag('a', '&nbsp;'.format_string($sco->title).($score ? '&nbsp;'.$score : ''),
                                                                array('data-scoid' => $sco->id, 'title' => $sco->url,
                                                                    'href' => '#'));
                        }
                    } else {
                        if ($sco->seledascormtype == 'sco') {
                            $result->toc .= html_writer::span($sco->statusicon.'&nbsp;'.format_string($sco->title));
                        } else {
                            $result->toc .= html_writer::span('&nbsp;'.format_string($sco->title));
                        }
                    }
                }

            } else {
                if ($play) {
                    if ($sco->seledascormtype == 'sco') {
                        $result->toc .= html_writer::span($sco->statusicon.'&nbsp;'.format_string($sco->title));
                    } else {
                        $result->toc .= '&nbsp;'.format_string($sco->title).html_writer::end_span();
                    }
                } else {
                    if ($sco->seledascormtype == 'sco') {
                        $result->toc .= $sco->statusicon.'&nbsp;'.format_string($sco->title);
                    } else {
                        $result->toc .= '&nbsp;'.format_string($sco->title);
                    }
                }
            }

            if (!empty($sco->children)) {
                $result->toc .= html_writer::start_tag('ul');
                $childresult = seledascorm_format_toc_for_treeview($user, $seledascorm, $sco->children, $usertracks, $cmid,
                                                                $toclink, $currentorg, $attempt, $play, $organizationsco, true);

                // Is any of the children incomplete?
                $sco->incomplete = $childresult->incomplete;
                $result->toc .= $childresult->toc;
                $result->toc .= html_writer::end_tag('ul');
                $result->toc .= html_writer::end_tag('li');
            } else {
                $result->toc .= html_writer::end_tag('li');
            }
            $prevsco = $sco;
        }
        $result->incomplete = $sco->incomplete;
    }

    if (!$children) {
        $result->toc .= html_writer::end_tag('ul');
    }

    return $result;
}

function seledascorm_format_toc_for_droplist($seledascorm, $scoes, $usertracks, $currentorg='', $organizationsco=null,
                                        $children=false, $level=0, $tocmenus=array()) {
    if (!empty($scoes)) {
        if (!empty($organizationsco) && !$children) {
            $tocmenus[$organizationsco->id] = $organizationsco->title;
        }

        $parents[$level] = '/';
        foreach ($scoes as $sco) {
            if ($parents[$level] != $sco->parent) {
                if ($newlevel = array_search($sco->parent, $parents)) {
                    $level = $newlevel;
                } else {
                    $i = $level;
                    while (($i > 0) && ($parents[$level] != $sco->parent)) {
                        $i--;
                    }

                    if (($i == 0) && ($sco->parent != $currentorg)) {
                        $level++;
                    } else {
                        $level = $i;
                    }

                    $parents[$level] = $sco->parent;
                }
            }

            if ($sco->seledascormtype == 'sco') {
                $tocmenus[$sco->id] = seledascorm_repeater('&minus;', $level) . '&gt;' . format_string($sco->title);
            }

            if (!empty($sco->children)) {
                $tocmenus = seledascorm_format_toc_for_droplist($seledascorm, $sco->children, $usertracks, $currentorg,
                                                            $organizationsco, true, $level, $tocmenus);
            }
        }
    }

    return $tocmenus;
}

function seledascorm_get_toc($user, $seledascorm, $cmid, $toclink=SELEDASCORM_TOCJSLINK, $currentorg='', $scoid='', $mode='normal',
                        $attempt='', $play=false, $tocheader=false) {
    global $CFG, $DB, $OUTPUT;

    if (empty($attempt)) {
        $attempt = seledascorm_get_last_attempt($seledascorm->id, $user->id);
    }

    $result = new stdClass();
    $organizationsco = null;

    if ($tocheader) {
        $result->toc = html_writer::start_div('yui3-g-r', array('id' => 'seledascorm_layout'));
        $result->toc .= html_writer::start_div('yui3-u-1-5 loading', array(
            'id' => 'seledascorm_toc',
            'role' => 'navigation',
            'aria-label' => get_string('contents', 'mod_seledascorm'),
        ));
        $result->toc .= html_writer::start_div('', ['id' => 'seledascorm_tree']);
    }

    if (!empty($currentorg)) {
        $organizationsco = $DB->get_record('seledascorm_scoes', ['seledascorm' => $seledascorm->id, 'identifier' => $currentorg]);
        if (!empty($organizationsco->title)) {
            if ($play) {
                $result->toctitle = $organizationsco->title;
            }
        }
    }

    $scoes = seledascorm_get_toc_object($user, $seledascorm, $currentorg, $scoid, $mode, $attempt, $play, $organizationsco);

    $rootsco = !empty($scoes['scoes']) ? $scoes['scoes'][0] : null;

    if ($rootsco) {
        $treeview = seledascorm_format_toc_for_treeview($user, $seledascorm, $rootsco->children ?? [], $scoes['usertracks'], $cmid,
                                                    $toclink, $currentorg, $attempt, $play, $organizationsco, false);

        if ($tocheader) {
            $result->toc .= $treeview->toc;
        } else {
            $result->toc = $treeview->toc;
        }
    }

    if (!empty($scoes['scoid'])) {
        $scoid = $scoes['scoid'];
    }

    if (empty($scoid)) {
        // If this is a normal package with an org sco and child scos get the first child.
        if ($rootsco && !empty($rootsco->children)) {
            $result->sco = $rootsco->children[0];
        } else if ($rootsco) { // This package only has one sco - it may be a simple external AICC package.
            $result->sco = $rootsco;
        }

    } else {
        $result->sco = seledascorm_get_sco($scoid);
    }

    if ($seledascorm->hidetoc == SELEDASCORM_TOC_POPUP && $rootsco) {
        $tocmenu = seledascorm_format_toc_for_droplist($seledascorm, $rootsco->children ?? [], $scoes['usertracks'],
                                                    $currentorg, $organizationsco);

        $modestr = '';
        if ($mode != 'normal') {
            $modestr = '&mode='.$mode;
        }

        $url = new moodle_url('/mod/seledascorm/player.php?a='.$seledascorm->id.'&currentorg='.$currentorg.$modestr);
        $result->tocmenu = $OUTPUT->single_select($url, 'scoid', $tocmenu, $result->sco->id, null, "tocmenu");
    }

    $result->prerequisites = isset($treeview) ? $treeview->prerequisites : true;
    $result->incomplete = isset($treeview) ? $treeview->incomplete : true;
    $result->attemptleft = isset($treeview) ? $treeview->attemptleft : 1;

    if ($tocheader) {
        $result->toc .= html_writer::end_div().html_writer::end_div();
        $result->toc .= html_writer::start_div('loading', array('id' => 'seledascorm_toc_toggle'));
        $result->toc .= html_writer::tag('button', '',
                        array('id' => 'seledascorm_toc_toggle_btn', 'class' => 'bg-primary',
                            'aria-expanded' => 'true', 'aria-controls' => 'seledascorm_toc')).html_writer::end_div();
        $result->toc .= html_writer::start_div('', array('id' => 'seledascorm_content'));
        $result->toc .= html_writer::div('', '', array('id' => 'seledascorm_navpanel'));
        $result->toc .= html_writer::end_div().html_writer::end_div();
    }

    return $result;
}

function seledascorm_get_adlnav_json ($scoes, &$adlnav = array(), $parentscoid = null) {
    if (is_object($scoes)) {
        $sco = $scoes;
        if (isset($sco->url)) {
            $adlnav[$sco->id]['identifier'] = $sco->identifier;
            $adlnav[$sco->id]['launch'] = $sco->launch;
            $adlnav[$sco->id]['title'] = $sco->title;
            $adlnav[$sco->id]['url'] = $sco->url;
            $adlnav[$sco->id]['parent'] = $sco->parent;
            if (isset($sco->choice)) {
                $adlnav[$sco->id]['choice'] = $sco->choice;
            }
            if (isset($sco->flow)) {
                $adlnav[$sco->id]['flow'] = $sco->flow;
            } else if (isset($parentscoid) && isset($adlnav[$parentscoid]['flow'])) {
                $adlnav[$sco->id]['flow'] = $adlnav[$parentscoid]['flow'];
            }
            if (isset($sco->isvisible)) {
                $adlnav[$sco->id]['isvisible'] = $sco->isvisible;
            }
            if (isset($sco->parameters)) {
                $adlnav[$sco->id]['parameters'] = $sco->parameters;
            }
            if (isset($sco->hidecontinue)) {
                $adlnav[$sco->id]['hidecontinue'] = $sco->hidecontinue;
            }
            if (isset($sco->hideprevious)) {
                $adlnav[$sco->id]['hideprevious'] = $sco->hideprevious;
            }
            if (isset($sco->hidesuspendall)) {
                $adlnav[$sco->id]['hidesuspendall'] = $sco->hidesuspendall;
            }
            if (!empty($parentscoid)) {
                $adlnav[$sco->id]['parentscoid'] = $parentscoid;
            }
            if (isset($adlnav['prevscoid'])) {
                $adlnav[$sco->id]['prevscoid'] = $adlnav['prevscoid'];
                $adlnav[$adlnav['prevscoid']]['nextscoid'] = $sco->id;
                if (isset($adlnav['prevparent']) && $adlnav['prevparent'] == $sco->parent) {
                    $adlnav[$sco->id]['prevsibling'] = $adlnav['prevscoid'];
                    $adlnav[$adlnav['prevscoid']]['nextsibling'] = $sco->id;
                }
            }
            $adlnav['prevscoid'] = $sco->id;
            $adlnav['prevparent'] = $sco->parent;
        }
        if (isset($sco->children)) {
            foreach ($sco->children as $children) {
                seledascorm_get_adlnav_json($children, $adlnav, $sco->id);
            }
        }
    } else {
        foreach ($scoes as $sco) {
            seledascorm_get_adlnav_json ($sco, $adlnav);
        }
        unset($adlnav['prevscoid']);
        unset($adlnav['prevparent']);
    }
    return json_encode($adlnav);
}

/**
 * Check for the availability of a resource by URL.
 *
 * Check is performed using an HTTP HEAD call.
 *
 * @param $url string A valid URL
 * @return bool|string True if no issue is found. The error string message, otherwise
 */
function seledascorm_check_url($url) {
    $curl = new curl;
    // Same options as in {@link download_file_content()}, used in {@link seledascorm_parse_scorm()}.
    $curl->setopt(array('CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 5));
    $cmsg = $curl->head($url);
    $info = $curl->get_info();
    if (empty($info['http_code']) || $info['http_code'] != 200) {
        return get_string('invalidurlhttpcheck', 'mod_seledascorm', array('cmsg' => $cmsg));
    }

    return true;
}

/**
 * Check for a parameter in userdata and return it if it's set
 * or return the value from $ifempty if its empty
 *
 * @param stdClass $userdata Contains user's data
 * @param string $param parameter that should be checked
 * @param string $ifempty value to be replaced with if $param is not set
 * @return string value from $userdata->$param if its not empty, or $ifempty
 */
function seledascorm_isset($userdata, $param, $ifempty = '') {
    if (isset($userdata->$param)) {
        return $userdata->$param;
    } else {
        return $ifempty;
    }
}

/**
 * Check if the current sco is launchable
 * If not, find the next launchable sco
 *
 * @param stdClass $seledascorm Scorm object
 * @param integer $scoid id of seledascorm_scoes record.
 * @return integer scoid of correct sco to launch or empty if one cannot be found, which will trigger first sco.
 */
function seledascorm_check_launchable_sco($seledascorm, $scoid) {
    global $DB;
    if ($sco = seledascorm_get_sco($scoid, SELEDASCORM_SCO_ONLY)) {
        if ($sco->launch == '') {
            // This scoid might be a top level org that can't be launched, find the first launchable sco after this sco.
            $scoes = $DB->get_records_select('seledascorm_scoes',
                                             'seledascorm = ? AND '.$DB->sql_isnotempty('seledascorm_scoes', 'launch', false, true).
                                             ' AND id > ?', array($seledascorm->id, $sco->id), 'sortorder, id', 'id', 0, 1);
            if (!empty($scoes)) {
                $sco = reset($scoes); // Get first item from the list.
                return $sco->id;
            }
        } else {
            return $sco->id;
        }
    }
    // Returning 0 will cause default behaviour which will find the first launchable sco in the package.
    return 0;
}

/**
 * Check if a SELEDASCORM is available for the current user.
 *
 * @param  stdClass  $seledascorm            SELEDASCORM record
 * @param  boolean $checkviewreportcap Check the seledascorm:viewreport cap
 * @param  stdClass  $context          Module context, required if $checkviewreportcap is set to true
 * @param  int  $userid                User id override
 * @return array                       status (available or not and possible warnings)
 * @since  Moodle 3.0
 */
function seledascorm_get_availability_status($seledascorm, $checkviewreportcap = false, $context = null, $userid = null) {
    $open = true;
    $closed = false;
    $warnings = array();

    $timenow = time();
    if (!empty($seledascorm->timeopen) && $seledascorm->timeopen > $timenow) {
        $open = false;
    }
    if (!empty($seledascorm->timeclose) && $timenow > $seledascorm->timeclose) {
        $closed = true;
    }

    if (!$open || $closed) {
        if ($checkviewreportcap && !empty($context) && has_capability('mod/seledascorm:viewreport', $context, $userid)) {
            return array(true, $warnings);
        }

        if (!$open) {
            $warnings['notopenyet'] = userdate($seledascorm->timeopen);
        }
        if ($closed) {
            $warnings['expired'] = userdate($seledascorm->timeclose);
        }
        return array(false, $warnings);
    }

    // Scorm is available.
    return array(true, $warnings);
}

/**
 * Requires a SELEDASCORM package to be available for the current user.
 *
 * @param  stdClass  $seledascorm            SELEDASCORM record
 * @param  boolean $checkviewreportcap Check the seledascorm:viewreport cap
 * @param  stdClass  $context          Module context, required if $checkviewreportcap is set to true
 * @throws moodle_exception
 * @since  Moodle 3.0
 */
function seledascorm_require_available($seledascorm, $checkviewreportcap = false, $context = null) {

    list($available, $warnings) = seledascorm_get_availability_status($seledascorm, $checkviewreportcap, $context);

    if (!$available) {
        $reason = current(array_keys($warnings));
        throw new moodle_exception($reason, 'seledascorm', '', $warnings[$reason]);
    }

}

/**
 * Return a SCO object and the SCO launch URL
 *
 * @param  stdClass $seledascorm SELEDASCORM object
 * @param  int $scoid The SCO id in database
 * @param  stdClass $context context object
 * @return array the SCO object and URL
 * @since  Moodle 3.1
 */
function seledascorm_get_sco_and_launch_url($seledascorm, $scoid, $context) {
    global $CFG, $DB;

    if (!empty($scoid)) {
        // Direct SCO request.
        if ($sco = seledascorm_get_sco($scoid)) {
            if ($sco->launch == '') {
                // Search for the next launchable sco.
                if ($scoes = $DB->get_records_select(
                        'seledascorm_scoes',
                        'seledascorm = ? AND '.$DB->sql_isnotempty('seledascorm_scoes', 'launch', false, true).' AND id > ?',
                        array($seledascorm->id, $sco->id),
                        'sortorder, id')) {
                    $sco = current($scoes);
                }
            }
        }
    }

    // If no sco was found get the first of SELEDASCORM package.
    if (!isset($sco)) {
        $scoes = $DB->get_records_select(
            'seledascorm_scoes',
            'seledascorm = ? AND '.$DB->sql_isnotempty('seledascorm_scoes', 'launch', false, true),
            array($seledascorm->id),
            'sortorder, id'
        );
        $sco = current($scoes);
    }

    $connector = '';
    $scolaunchurl = '';
    $version = substr($seledascorm->version, 0, 4);
    if ((isset($sco->parameters) && (!empty($sco->parameters))) || ($version == 'AICC')) {
        if (stripos($sco->launch, '?') !== false) {
            $connector = '&';
        } else {
            $connector = '?';
        }
        if ((isset($sco->parameters) && (!empty($sco->parameters))) && ($sco->parameters[0] == '?')) {
            $sco->parameters = substr($sco->parameters, 1);
        }
    }

    if ($version == 'AICC') {
        require_once("$CFG->dirroot/mod/seledascorm/datamodels/aicclib.php");
        $aiccsid = seledascorm_aicc_get_hacp_session($seledascorm->id);
        if (empty($aiccsid)) {
            $aiccsid = sesskey();
        }
        $scoparams = '';
        if (isset($sco->parameters) && (!empty($sco->parameters))) {
            $scoparams = '&'. $sco->parameters;
        }
        $launcher = $sco->launch.$connector.'aicc_sid='.$aiccsid.'&aicc_url='.$CFG->wwwroot.'/mod/seledascorm/aicc.php'.$scoparams;
    } else {
        if (isset($sco->parameters) && (!empty($sco->parameters))) {
            $launcher = $sco->launch.$connector.$sco->parameters;
        } else {
            $launcher = $sco->launch;
        }
    }

    if (seledascorm_external_link($sco->launch)) {
        // TODO: does this happen?
        $scolaunchurl = $launcher;
    } else if ($seledascorm->seledascormtype === SELEDASCORM_TYPE_EXTERNAL) {
        // Remote learning activity.
        $scolaunchurl = dirname($seledascorm->reference).'/'.$launcher;
    } else if (
        $seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCAL &&
        strtolower($seledascorm->reference) == 'imsmanifest.xml'
    ) {
        // This SELEDASCORM content sits in a repository that allows relative links.
        $scolaunchurl = "$CFG->wwwroot/pluginfile.php/$context->id/mod_seledascorm/imsmanifest/$seledascorm->revision/$launcher";
    } else if (
        $seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCAL ||
        $seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCALSYNC ||
        $seledascorm->seledascormtype === SELEDASCORM_TYPE_EMBEDDED
    ) {
        // Note: do not convert this to use moodle_url().
        // SELEDASCORM does not work without slasharguments and moodle_url() encodes querystring vars.
        $scolaunchurl = "$CFG->wwwroot/pluginfile.php/$context->id/mod_seledascorm/content/$seledascorm->revision/$launcher";
    }

    // Make the in-package teacher-layer selector available via the package's own URL
    // parameter. eXeLearning core hides the teacher layer by default and opts in to it
    // with ?exe-teacher=1 (upstream exelearning#1772); this replaces the former
    // parent-side CSS injection that hid the in-package teacher-mode toggle. The
    // per-activity teachermodevisible setting alone controls it: when on, the selector
    // is offered to all viewers; it does not gate on role. Appended by hand (not
    // moodle_url()) to preserve slasharguments and any existing query string.
    if (!empty($seledascorm->teachermodevisible) && $scolaunchurl !== '') {
        $scolaunchurl .= (strpos($scolaunchurl, '?') === false ? '?' : '&') . 'exe-teacher=1';
    }

    return array($sco, $scolaunchurl);
}

/**
 * Trigger the seledascorm_launched event.
 *
 * @param  stdClass $seledascorm   seledascorm object
 * @param  stdClass $sco     sco object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 * @param  string $scourl    SCO URL
 * @since Moodle 3.1
 */
function seledascorm_launch_sco($seledascorm, $sco, $cm, $context, $scourl) {

    $event = \mod_seledascorm\event\sco_launched::create(array(
        'objectid' => $sco->id,
        'context' => $context,
        'other' => array('instanceid' => $seledascorm->id, 'loadedcontent' => !empty($scourl) ? $scourl : (string)$sco->launch)
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('seledascorm', $seledascorm);
    $event->add_record_snapshot('seledascorm_scoes', $sco);
    $event->trigger();
}

/**
 * This is really a little language parser for AICC_SCRIPT
 * evaluates the expression and returns a boolean answer
 * see 2.3.2.5.1. Sequencing/Navigation Today  - from the SCORM 1.2 spec (CAM).
 * Also used by AICC packages.
 *
 * @param string $prerequisites the aicc_script prerequisites expression
 * @param array  $usertracks the tracked user data of each SCO visited
 * @return boolean
 */
function seledascorm_eval_prerequisites($prerequisites, $usertracks) {

    // This is really a little language parser - AICC_SCRIPT is the reference
    // see 2.3.2.5.1. Sequencing/Navigation Today  - from the SCORM 1.2 spec.
    $element = '';
    $stack = array();
    $statuses = array(
        'passed' => 'passed',
        'completed' => 'completed',
        'failed' => 'failed',
        'incomplete' => 'incomplete',
        'browsed' => 'browsed',
        'not attempted' => 'notattempted',
        'p' => 'passed',
        'c' => 'completed',
        'f' => 'failed',
        'i' => 'incomplete',
        'b' => 'browsed',
        'n' => 'notattempted'
    );
    $i = 0;

    // Expand the amp entities.
    $prerequisites = preg_replace('/&amp;/', '&', $prerequisites);
    // Find all my parsable tokens.
    $prerequisites = preg_replace('/(&|\||\(|\)|\~)/', '\t$1\t', $prerequisites);
    // Expand operators.
    $prerequisites = preg_replace('/&/', '&&', $prerequisites);
    $prerequisites = preg_replace('/\|/', '||', $prerequisites);
    // Now - grab all the tokens.
    $elements = explode('\t', trim($prerequisites));

    // Process each token to build an expression to be evaluated.
    $stack = array();
    foreach ($elements as $element) {
        $element = trim($element);
        if (empty($element)) {
            continue;
        }
        if (!preg_match('/^(&&|\|\||\(|\))$/', $element)) {
            // Create each individual expression.
            // Search for ~ = <> X*{} .

            // Sets like 3*{S34, S36, S37, S39}.
            if (preg_match('/^(\d+)\*\{(.+)\}$/', $element, $matches)) {
                $repeat = $matches[1];
                $set = explode(',', $matches[2]);
                $count = 0;
                foreach ($set as $setelement) {
                    if (isset($usertracks[$setelement]) &&
                        ($usertracks[$setelement]->status == 'completed' || $usertracks[$setelement]->status == 'passed')) {
                        $count++;
                    }
                }
                if ($count >= $repeat) {
                    $element = 'true';
                } else {
                    $element = 'false';
                }
            } else if ($element == '~') {
                // Not maps ~.
                $element = '!';
            } else if (preg_match('/^(.+)(\=|\<\>)(.+)$/', $element, $matches)) {
                // Other symbols = | <> .
                $element = trim($matches[1]);
                if (isset($usertracks[$element])) {
                    $value = trim(preg_replace('/(\'|\")/', '', $matches[3]));
                    if (isset($statuses[$value])) {
                        $value = $statuses[$value];
                    }

                    $elementprerequisitematch = (strcmp($usertracks[$element]->status, $value) == 0);
                    if ($matches[2] == '<>') {
                        $element = $elementprerequisitematch ? 'false' : 'true';
                    } else {
                        $element = $elementprerequisitematch ? 'true' : 'false';
                    }
                } else {
                    $element = 'false';
                }
            } else {
                // Everything else must be an element defined like S45 ...
                if (isset($usertracks[$element]) &&
                    ($usertracks[$element]->status == 'completed' || $usertracks[$element]->status == 'passed')) {
                    $element = 'true';
                } else {
                    $element = 'false';
                }
            }

        }
        $stack[] = ' '.$element.' ';
    }
    // phpcs:ignore
    return eval('return '.implode($stack).';');
}

/**
 * Update the calendar entries for this seledascorm activity.
 *
 * @param stdClass $seledascorm the row from the database table seledascorm.
 * @param int $cmid The coursemodule id
 * @return bool
 */
function seledascorm_update_calendar(stdClass $seledascorm, $cmid) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    // Scorm start calendar events.
    $event = new stdClass();
    $event->eventtype = SELEDASCORM_EVENT_TYPE_OPEN;
    // The SELEDASCORM_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
    $event->type = empty($seledascorm->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
    if ($event->id = $DB->get_field('event', 'id',
        array('modulename' => 'seledascorm', 'instance' => $seledascorm->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($seledascorm->timeopen)) && ($seledascorm->timeopen > 0)) {
            // Calendar event exists so update it.
            $event->name = get_string('calendarstart', 'mod_seledascorm', $seledascorm->name);
            $event->description = format_module_intro('seledascorm', $seledascorm, $cmid, false);
            $event->format = FORMAT_HTML;
            $event->timestart = $seledascorm->timeopen;
            $event->timesort = $seledascorm->timeopen;
            $event->visible = instance_is_visible('seledascorm', $seledascorm);
            $event->timeduration = 0;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($seledascorm->timeopen)) && ($seledascorm->timeopen > 0)) {
            $event->name = get_string('calendarstart', 'mod_seledascorm', $seledascorm->name);
            $event->description = format_module_intro('seledascorm', $seledascorm, $cmid, false);
            $event->format = FORMAT_HTML;
            $event->courseid = $seledascorm->course;
            $event->groupid = 0;
            $event->userid = 0;
            $event->modulename = 'seledascorm';
            $event->instance = $seledascorm->id;
            $event->timestart = $seledascorm->timeopen;
            $event->timesort = $seledascorm->timeopen;
            $event->visible = instance_is_visible('seledascorm', $seledascorm);
            $event->timeduration = 0;

            calendar_event::create($event, false);
        }
    }

    // Scorm end calendar events.
    $event = new stdClass();
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->eventtype = SELEDASCORM_EVENT_TYPE_CLOSE;
    if ($event->id = $DB->get_field('event', 'id',
        array('modulename' => 'seledascorm', 'instance' => $seledascorm->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($seledascorm->timeclose)) && ($seledascorm->timeclose > 0)) {
            // Calendar event exists so update it.
            $event->name = get_string('calendarend', 'mod_seledascorm', $seledascorm->name);
            $event->description = format_module_intro('seledascorm', $seledascorm, $cmid, false);
            $event->format = FORMAT_HTML;
            $event->timestart = $seledascorm->timeclose;
            $event->timesort = $seledascorm->timeclose;
            $event->visible = instance_is_visible('seledascorm', $seledascorm);
            $event->timeduration = 0;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($seledascorm->timeclose)) && ($seledascorm->timeclose > 0)) {
            $event->name = get_string('calendarend', 'mod_seledascorm', $seledascorm->name);
            $event->description = format_module_intro('seledascorm', $seledascorm, $cmid, false);
            $event->format = FORMAT_HTML;
            $event->courseid = $seledascorm->course;
            $event->groupid = 0;
            $event->userid = 0;
            $event->modulename = 'seledascorm';
            $event->instance = $seledascorm->id;
            $event->timestart = $seledascorm->timeclose;
            $event->timesort = $seledascorm->timeclose;
            $event->visible = instance_is_visible('seledascorm', $seledascorm);
            $event->timeduration = 0;

            calendar_event::create($event, false);
        }
    }

    return true;
}
