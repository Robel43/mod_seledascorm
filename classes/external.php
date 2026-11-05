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
 * SELEDASCORM module external API
 *
 * @package    mod_seledascorm
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

use core_course\external\helper_for_get_mods_by_courses;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/seledascorm/lib.php');
require_once($CFG->dirroot . '/mod/seledascorm/locallib.php');

/**
 * SELEDASCORM module external functions
 *
 * @package    mod_seledascorm
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_seledascorm_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_seledascorm_parameters() {
        return new external_function_parameters(
            array(
                'seledascormid' => new external_value(PARAM_INT, 'seledascorm instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event.
     *
     * @param int $seledascormid the seledascorm instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_seledascorm($seledascormid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/seledascorm/lib.php');

        $params = self::validate_parameters(self::view_seledascorm_parameters(),
                                            array(
                                                'seledascormid' => $seledascormid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $seledascorm = $DB->get_record('seledascorm', array('id' => $params['seledascormid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($seledascorm, 'seledascorm');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Call the seledascorm/lib API.
        seledascorm_view($seledascorm, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_seledascorm_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_seledascorm_attempt_count.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_seledascorm_attempt_count_parameters() {
        return new external_function_parameters(
            array(
                'seledascormid' => new external_value(PARAM_INT, 'SELEDASCORM instance id'),
                'userid' => new external_value(PARAM_INT, 'User id'),
                'ignoremissingcompletion' => new external_value(PARAM_BOOL,
                                                'Ignores attempts that haven\'t reported a grade/completion',
                                                VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Return the number of attempts done by a user in the given SELEDASCORM.
     *
     * @param int $seledascormid the seledascorm id
     * @param int $userid the user id
     * @param bool $ignoremissingcompletion ignores attempts that haven't reported a grade/completion
     * @return array of warnings and the attempts count
     * @since Moodle 3.0
     */
    public static function get_seledascorm_attempt_count($seledascormid, $userid, $ignoremissingcompletion = false) {
        global $USER, $DB;

        $params = self::validate_parameters(self::get_seledascorm_attempt_count_parameters(),
                                            array('seledascormid' => $seledascormid, 'userid' => $userid,
                                                'ignoremissingcompletion' => $ignoremissingcompletion));

        $attempts = array();
        $warnings = array();

        $seledascorm = $DB->get_record('seledascorm', array('id' => $params['seledascormid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/seledascorm:viewreport', $context);
        }

        // If the SELEDASCORM is not open this function will throw exceptions.
        seledascorm_require_available($seledascorm);

        $attemptscount = seledascorm_get_attempt_count($user->id, $seledascorm, false, $params['ignoremissingcompletion']);

        $result = array();
        $result['attemptscount'] = $attemptscount;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_seledascorm_attempt_count return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_seledascorm_attempt_count_returns() {

        return new external_single_structure(
            array(
                'attemptscount' => new external_value(PARAM_INT, 'Attempts count'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_seledascorm_scoes.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_seledascorm_scoes_parameters() {
        return new external_function_parameters(
            array(
                'seledascormid' => new external_value(PARAM_INT, 'seledascorm instance id'),
                'organization' => new external_value(PARAM_RAW, 'organization id', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Returns a list containing all the scoes data related to the given seledascorm id
     *
     * @param int $seledascormid the seledascorm id
     * @param string $organization the organization id
     * @return array warnings and the scoes data
     * @since Moodle 3.0
     */
    public static function get_seledascorm_scoes($seledascormid, $organization = '') {
        global $DB;

        $params = self::validate_parameters(self::get_seledascorm_scoes_parameters(),
                                            array('seledascormid' => $seledascormid, 'organization' => $organization));

        $scoes = array();
        $warnings = array();

        $seledascorm = $DB->get_record('seledascorm', array('id' => $params['seledascormid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check settings / permissions to view the SELEDASCORM.
        seledascorm_require_available($seledascorm, true, $context);

        if (!$scoes = seledascorm_get_scoes($seledascorm->id, $params['organization'])) {
            // Function seledascorm_get_scoes return false, not an empty array.
            $scoes = array();
        } else {
            $scoreturnstructure = self::get_seledascorm_scoes_returns();
            foreach ($scoes as $sco) {
                $extradata = array();
                foreach ($sco as $element => $value) {
                    // Check if the element is extra data (not a basic SCO element).
                    if (!isset($scoreturnstructure->keys['scoes']->content->keys[$element])) {
                        $extradata[] = array(
                            'element' => $element,
                            'value' => $value
                        );
                    }
                }
                $sco->extradata = $extradata;
            }
        }

        $result = array();
        $result['scoes'] = $scoes;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_seledascorm_scoes return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_seledascorm_scoes_returns() {

        return new external_single_structure(
            array(
                'scoes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'sco id'),
                            'seledascorm' => new external_value(PARAM_INT, 'seledascorm id'),
                            'manifest' => new external_value(PARAM_NOTAGS, 'manifest id'),
                            'organization' => new external_value(PARAM_NOTAGS, 'organization id'),
                            'parent' => new external_value(PARAM_NOTAGS, 'parent'),
                            'identifier' => new external_value(PARAM_NOTAGS, 'identifier'),
                            'launch' => new external_value(PARAM_NOTAGS, 'launch file'),
                            'seledascormtype' => new external_value(PARAM_ALPHA, 'seledascorm type (asset, sco)'),
                            'title' => new external_value(PARAM_NOTAGS, 'sco title'),
                            'sortorder' => new external_value(PARAM_INT, 'sort order'),
                            'extradata' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'element' => new external_value(PARAM_RAW, 'element name'),
                                        'value' => new external_value(PARAM_RAW, 'element value')
                                    )
                                ), 'Additional SCO data', VALUE_OPTIONAL
                            )
                        ), 'SELEDASCORM SCO data'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_seledascorm_user_data.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_seledascorm_user_data_parameters() {
        return new external_function_parameters(
            array(
                'seledascormid' => new external_value(PARAM_INT, 'seledascorm instance id'),
                'attempt' => new external_value(PARAM_INT, 'attempt number')
            )
        );
    }

    /**
     * Retrieves user tracking and SCO data and default SELEDASCORM values
     *
     * @param int $seledascormid the seledascorm id
     * @param int $attempt the attempt number
     * @return array warnings and the scoes data
     * @throws  moodle_exception
     * @since Moodle 3.0
     */
    public static function get_seledascorm_user_data($seledascormid, $attempt) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::get_seledascorm_user_data_parameters(),
                                            array('seledascormid' => $seledascormid, 'attempt' => $attempt));

        $data = array();
        $warnings = array();

        $seledascorm = $DB->get_record('seledascorm', array('id' => $params['seledascormid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        seledascorm_require_available($seledascorm, true, $context);

        $seledascorm->version = strtolower(clean_param($seledascorm->version, PARAM_SAFEDIR));
        if (!file_exists($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'lib.php')) {
            $seledascorm->version = 'SCORM_12';
        }
        require_once($CFG->dirroot.'/mod/seledascorm/datamodels/'.$seledascorm->version.'lib.php');

        if ($scoes = seledascorm_get_scoes($seledascorm->id)) {
            $def = new stdClass();
            $user = new stdClass();

            foreach ($scoes as $sco) {
                $def->{$sco->id} = new stdClass();
                $user->{$sco->id} = new stdClass();
                // We force mode normal, this can be override by the client at any time.
                $def->{$sco->id} = get_seledascorm_default($user->{$sco->id}, $seledascorm, $sco->id, $params['attempt'], 'normal');

                $userdata = array();
                $defaultdata = array();

                foreach ((array) $user->{$sco->id} as $key => $val) {
                    $userdata[] = array(
                        'element' => $key,
                        'value' => $val
                    );
                }
                foreach ($def->{$sco->id} as $key => $val) {
                    $defaultdata[] = array(
                        'element' => $key,
                        'value' => $val
                    );
                }

                $data[] = array(
                    'scoid' => $sco->id,
                    'userdata' => $userdata,
                    'defaultdata' => $defaultdata,
                );
            }
        }

        $result = array();
        $result['data'] = $data;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_seledascorm_user_data return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_seledascorm_user_data_returns() {

        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'scoid' => new external_value(PARAM_INT, 'sco id'),
                            'userdata' => new external_multiple_structure(
                                            new external_single_structure(
                                                array(
                                                    'element' => new external_value(PARAM_RAW, 'element name'),
                                                    'value' => new external_value(PARAM_RAW, 'element value')
                                                )
                                            )
                                          ),
                            'defaultdata' => new external_multiple_structure(
                                                new external_single_structure(
                                                    array(
                                                        'element' => new external_value(PARAM_RAW, 'element name'),
                                                        'value' => new external_value(PARAM_RAW, 'element value')
                                                    )
                                                )
                                             ),
                        ), 'SCO data'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for insert_seledascorm_tracks.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function insert_seledascorm_tracks_parameters() {
        return new external_function_parameters(
            array(
                'scoid' => new external_value(PARAM_INT, 'SCO id'),
                'attempt' => new external_value(PARAM_INT, 'attempt number'),
                'tracks' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'element' => new external_value(PARAM_RAW, 'element name'),
                            'value' => new external_value(PARAM_RAW, 'element value')
                        )
                    )
                ),
            )
        );
    }

    /**
     * Saves a SELEDASCORM tracking record.
     * It will overwrite any existing tracking data for this attempt.
     * Validation should be performed before running the function to ensure the user will not lose any existing attempt data.
     *
     * @param int $scoid the SCO id
     * @param string $attempt the attempt number
     * @param array $tracks the track records to be stored
     * @return array warnings and the scoes data
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function insert_seledascorm_tracks($scoid, $attempt, $tracks) {
        global $USER, $DB;

        $params = self::validate_parameters(self::insert_seledascorm_tracks_parameters(),
                                            array('scoid' => $scoid, 'attempt' => $attempt, 'tracks' => $tracks));

        $trackids = array();
        $warnings = array();

        $sco = seledascorm_get_sco($params['scoid'], SELEDASCORM_SCO_ONLY);
        if (!$sco) {
            throw new moodle_exception('cannotfindsco', 'seledascorm');
        }

        $seledascorm = $DB->get_record('seledascorm', array('id' => $sco->seledascorm), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check settings / permissions to view the SELEDASCORM.
        require_capability('mod/seledascorm:savetrack', $context);

        // Check settings / permissions to view the SELEDASCORM.
        seledascorm_require_available($seledascorm);

        foreach ($params['tracks'] as $track) {
            $element = $track['element'];
            $value = $track['value'];
            $trackid = seledascorm_insert_track($USER->id, $seledascorm->id, $sco->id, $params['attempt'], $element, $value,
                                            $seledascorm->forcecompleted);

            if ($trackid) {
                $trackids[] = $trackid;
            } else {
                $warnings[] = array(
                    'item' => 'seledascorm',
                    'itemid' => $seledascorm->id,
                    'warningcode' => 1,
                    'message' => 'Element: ' . $element . ' was not saved'
                );
            }
        }

        $result = array();
        $result['trackids'] = $trackids;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the insert_seledascorm_tracks return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function insert_seledascorm_tracks_returns() {

        return new external_single_structure(
            array(
                'trackids' => new external_multiple_structure(new external_value(PARAM_INT, 'track id')),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_seledascorm_sco_tracks.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_seledascorm_sco_tracks_parameters() {
        return new external_function_parameters(
            array(
                'scoid' => new external_value(PARAM_INT, 'sco id'),
                'userid' => new external_value(PARAM_INT, 'user id'),
                'attempt' => new external_value(PARAM_INT, 'attempt number (0 for last attempt)', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Retrieves SCO tracking data for the given user id and attempt number
     *
     * @param int $scoid the sco id
     * @param int $userid the user id
     * @param int $attempt the attempt number
     * @return array warnings and the scoes data
     * @since Moodle 3.0
     */
    public static function get_seledascorm_sco_tracks($scoid, $userid, $attempt = 0) {
        global $USER, $DB;

        $params = self::validate_parameters(self::get_seledascorm_sco_tracks_parameters(),
                                            array('scoid' => $scoid, 'userid' => $userid, 'attempt' => $attempt));

        $tracks = array();
        $warnings = array();

        $sco = seledascorm_get_sco($params['scoid'], SELEDASCORM_SCO_ONLY);
        if (!$sco) {
            throw new moodle_exception('cannotfindsco', 'seledascorm');
        }

        $seledascorm = $DB->get_record('seledascorm', array('id' => $sco->seledascorm), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/seledascorm:viewreport', $context);
        }

        seledascorm_require_available($seledascorm, true, $context);

        if (empty($params['attempt'])) {
            $params['attempt'] = seledascorm_get_last_attempt($seledascorm->id, $user->id);
        }

        $attempted = false;
        if ($seledascormtracks = seledascorm_get_tracks($sco->id, $params['userid'], $params['attempt'])) {
            // Check if attempted.
            if ($seledascormtracks->status != '') {
                $attempted = true;
                foreach ($seledascormtracks as $element => $value) {
                    $tracks[] = array(
                        'element' => $element,
                        'value' => $value,
                    );
                }
            }
        }

        if (!$attempted) {
            $warnings[] = array(
                'item' => 'attempt',
                'itemid' => $params['attempt'],
                'warningcode' => 'notattempted',
                'message' => get_string('notattempted', 'mod_seledascorm')
            );
        }

        $result = array();
        $result['data']['attempt'] = $params['attempt'];
        $result['data']['tracks'] = $tracks;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_seledascorm_sco_tracks return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_seledascorm_sco_tracks_returns() {

        return new external_single_structure(
            array(
                'data' => new external_single_structure(
                    array(
                        'attempt' => new external_value(PARAM_INT, 'Attempt number'),
                        'tracks' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'element' => new external_value(PARAM_RAW, 'Element name'),
                                    'value' => new external_value(PARAM_RAW, 'Element value')
                                ), 'Tracks data'
                            )
                        ),
                    ), 'SCO data'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_seledascorms_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_seledascorms_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of seledascorms in a provided list of courses,
     * if no list is provided all seledascorms that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array the seledascorm details
     * @since Moodle 3.0
     */
    public static function get_seledascorms_by_courses($courseids = array()) {
        global $CFG;

        $returnedseledascorms = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_seledascorms_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the seledascorms in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $seledascorms = get_all_instances_in_courses("seledascorm", $courses);

            $fs = get_file_storage();
            foreach ($seledascorms as $seledascorm) {

                $context = context_module::instance($seledascorm->coursemodule);

                // Entry to return.
                $module = helper_for_get_mods_by_courses::standard_coursemodule_element_values($seledascorm, 'mod_seledascorm');

                // Check if the SELEDASCORM open and return warnings if so.
                list($open, $openwarnings) = seledascorm_get_availability_status($seledascorm, true, $context);

                if (!$open) {
                    foreach ($openwarnings as $warningkey => $warningdata) {
                        $warnings[] = array(
                            'item' => 'seledascorm',
                            'itemid' => $seledascorm->id,
                            'warningcode' => $warningkey,
                            'message' => get_string($warningkey, 'mod_seledascorm', $warningdata)
                        );
                    }
                } else {
                    $module['packagesize'] = 0;
                    // SELEDASCORM size.
                    if (
                        $seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCAL ||
                        $seledascorm->seledascormtype === SELEDASCORM_TYPE_LOCALSYNC
                    ) {
                        if ($packagefile = $fs->get_file($context->id,
                                            'mod_seledascorm',
                                            'package',
                                            0,
                                            '/',
                                            $seledascorm->reference)
                        ) {
                            $module['packagesize'] = $packagefile->get_filesize();
                            // Download URL.
                            $module['packageurl'] = moodle_url::make_webservice_pluginfile_url(
                                                        $context->id, 'mod_seledascorm', 'package', 0, '/', $seledascorm->reference
                                                    )->out(false);
                        }
                    }

                    $module['protectpackagedownloads'] = get_config('seledascorm', 'protectpackagedownloads');

                    $viewablefields = array('version', 'maxgrade', 'grademethod', 'whatgrade', 'maxattempt', 'forcecompleted',
                                            'forcenewattempt', 'lastattemptlock', 'displayattemptstatus', 'displaycoursestructure',
                                            'sha1hash', 'md5hash', 'revision', 'launch', 'skipview', 'hidebrowse', 'hidetoc', 'nav',
                                            'navpositionleft', 'navpositiontop', 'auto', 'popup', 'width', 'height', 'timeopen',
                                            'timeclose', 'seledascormtype', 'reference');

                    // Check additional permissions for returning optional private settings.
                    if (has_capability('moodle/course:manageactivities', $context)) {
                        $additionalfields = array('updatefreq', 'options', 'completionstatusrequired', 'completionscorerequired',
                                                  'completionstatusallscos', 'autocommit', 'timemodified');
                        $viewablefields = array_merge($viewablefields, $additionalfields);
                    }

                    foreach ($viewablefields as $field) {
                        $module[$field] = $seledascorm->{$field};
                    }
                }

                $returnedseledascorms[] = $module;
            }
        }

        $result = array();
        $result['seledascorms'] = $returnedseledascorms;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_seledascorms_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_seledascorms_by_courses_returns() {

        return new external_single_structure(
            array(
                'seledascorms' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                        helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(),
                        [
                            'packagesize' => new external_value(PARAM_INT, 'SELEDASCORM zip package size', VALUE_OPTIONAL),
                            'packageurl' => new external_value(PARAM_URL, 'SELEDASCORM zip package URL', VALUE_OPTIONAL),
                            'version' => new external_value(PARAM_NOTAGS,
                                        'SELEDASCORM version SELEDASCORM_SCORM_12,SELEDASCORM_SCORM_13,SELEDASCORM_SCORM_AICC)',
                                        VALUE_OPTIONAL),
                            'maxgrade' => new external_value(PARAM_INT, 'Max grade', VALUE_OPTIONAL),
                            'grademethod' => new external_value(PARAM_INT, 'Grade method', VALUE_OPTIONAL),
                            'whatgrade' => new external_value(PARAM_INT, 'What grade', VALUE_OPTIONAL),
                            'maxattempt' => new external_value(PARAM_INT, 'Maximum number of attemtps', VALUE_OPTIONAL),
                            'forcecompleted' => new external_value(PARAM_BOOL, 'Status current attempt is forced to "completed"',
                                                                    VALUE_OPTIONAL),
                            'forcenewattempt' => new external_value(PARAM_INT, 'Controls re-entry behaviour',
                                                                    VALUE_OPTIONAL),
                            'lastattemptlock' => new external_value(PARAM_BOOL, 'Prevents to launch new attempts once finished',
                                                                    VALUE_OPTIONAL),
                            'displayattemptstatus' => new external_value(PARAM_INT, 'How to display attempt status',
                                                                            VALUE_OPTIONAL),
                            'displaycoursestructure' => new external_value(PARAM_BOOL, 'Display contents structure',
                                                                            VALUE_OPTIONAL),
                            'sha1hash' => new external_value(PARAM_NOTAGS, 'Package content or ext path hash', VALUE_OPTIONAL),
                            'md5hash' => new external_value(PARAM_NOTAGS, 'MD5 Hash of package file', VALUE_OPTIONAL),
                            'revision' => new external_value(PARAM_INT, 'Revison number', VALUE_OPTIONAL),
                            'launch' => new external_value(PARAM_INT, 'First content to launch', VALUE_OPTIONAL),
                            'skipview' => new external_value(PARAM_INT, 'How to skip the content structure page', VALUE_OPTIONAL),
                            'hidebrowse' => new external_value(PARAM_BOOL, 'Disable preview mode?', VALUE_OPTIONAL),
                            'hidetoc' => new external_value(PARAM_INT, 'How to display the SELEDASCORM structure in player',
                                                            VALUE_OPTIONAL),
                            'nav' => new external_value(PARAM_INT, 'Show navigation buttons', VALUE_OPTIONAL),
                            'navpositionleft' => new external_value(PARAM_INT, 'Navigation position left', VALUE_OPTIONAL),
                            'navpositiontop' => new external_value(PARAM_INT, 'Navigation position top', VALUE_OPTIONAL),
                            'auto' => new external_value(PARAM_BOOL, 'Auto continue?', VALUE_OPTIONAL),
                            'popup' => new external_value(PARAM_INT, 'Display in current or new window', VALUE_OPTIONAL),
                            'width' => new external_value(PARAM_INT, 'Frame width', VALUE_OPTIONAL),
                            'height' => new external_value(PARAM_INT, 'Frame height', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_INT, 'Available from', VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_INT, 'Available to', VALUE_OPTIONAL),
                            'seledascormtype' => new external_value(PARAM_ALPHA, 'SELEDASCORM type', VALUE_OPTIONAL),
                            'reference' => new external_value(PARAM_NOTAGS, 'Reference to the package', VALUE_OPTIONAL),
                            'protectpackagedownloads' => new external_value(PARAM_BOOL, 'Protect package downloads?',
                                                                            VALUE_OPTIONAL),
                            'updatefreq' => new external_value(PARAM_INT, 'Auto-update frequency for remote packages',
                                                                VALUE_OPTIONAL),
                            'options' => new external_value(PARAM_RAW, 'Additional options', VALUE_OPTIONAL),
                            'completionstatusrequired' => new external_value(PARAM_INT, 'Status passed/completed required?',
                                                                                VALUE_OPTIONAL),
                            'completionscorerequired' => new external_value(PARAM_INT, 'Minimum score required', VALUE_OPTIONAL),
                            'completionstatusallscos' => new external_value(PARAM_INT,
                                                                            'Require all scos to return completion status',
                                                                            VALUE_OPTIONAL),
                            'autocommit' => new external_value(PARAM_BOOL, 'Save track data automatically?', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification', VALUE_OPTIONAL),
                        ]
                    ), 'SELEDASCORM')
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function launch_sco_parameters() {
        return new external_function_parameters(
            array(
                'seledascormid' => new external_value(PARAM_INT, 'SELEDASCORM instance id'),
                'scoid' => new external_value(PARAM_INT, 'SCO id (empty for launching the first SCO)', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Trigger the course module viewed event.
     *
     * @param int $seledascormid the SELEDASCORM instance id
     * @param int $scoid the SCO id
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function launch_sco($seledascormid, $scoid = 0) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $params = self::validate_parameters(self::launch_sco_parameters(),
                                            array(
                                                'seledascormid' => $seledascormid,
                                                'scoid' => $scoid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $seledascorm = $DB->get_record('seledascorm', array('id' => $params['seledascormid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($seledascorm, 'seledascorm');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // If the SELEDASCORM is not open this function will throw exceptions.
        seledascorm_require_available($seledascorm);

        if (!empty($params['scoid']) && !($sco = seledascorm_get_sco($params['scoid'], SELEDASCORM_SCO_ONLY))) {
            throw new moodle_exception('cannotfindsco', 'seledascorm');
        }

        // Mark module viewed.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        list($sco, $scolaunchurl) = seledascorm_get_sco_and_launch_url($seledascorm, $params['scoid'], $context);
        // Trigger the SCO launched event.
        seledascorm_launch_sco($seledascorm, $sco, $cm, $context, $scolaunchurl);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function launch_sco_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_seledascorm_access_information.
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function get_seledascorm_access_information_parameters() {
        return new external_function_parameters (
            array(
                'seledascormid' => new external_value(PARAM_INT, 'seledascorm instance id.')
            )
        );
    }

    /**
     * Return access information for a given seledascorm.
     *
     * @param int $seledascormid seledascorm instance id
     * @return array of warnings and the access information
     * @since Moodle 3.7
     * @throws  moodle_exception
     */
    public static function get_seledascorm_access_information($seledascormid) {
        global $DB;

        $params = self::validate_parameters(self::get_seledascorm_access_information_parameters(),
                                            ['seledascormid' => $seledascormid]
                                        );

        // Request and permission validation.
        $seledascorm = $DB->get_record('seledascorm', array('id' => $params['seledascormid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($seledascorm, 'seledascorm');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $result = array();
        // Return all the available capabilities.
        $capabilities = load_capability_def('mod_seledascorm');
        foreach ($capabilities as $capname => $capdata) {
            // Get fields like cansubmit so it is consistent with the access_information function implemented in other modules.
            $field = 'can' . str_replace('mod/seledascorm:', '', $capname);
            $result[$field] = has_capability($capname, $context);
        }

        $result['warnings'] = array();
        return $result;
    }

    /**
     * Describes the get_seledascorm_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.7
     */
    public static function get_seledascorm_access_information_returns() {

        $structure = array(
            'warnings' => new external_warnings()
        );

        $capabilities = load_capability_def('mod_seledascorm');
        foreach ($capabilities as $capname => $capdata) {
            // Get fields like cansubmit so it is consistent with the access_information function implemented in other modules.
            $field = 'can' . str_replace('mod/seledascorm:', '', $capname);
            $structure[$field] = new external_value(PARAM_BOOL, 'Whether the user has the capability ' . $capname . ' allowed.',
                VALUE_OPTIONAL);
        }

        return new external_single_structure($structure);
    }
}
