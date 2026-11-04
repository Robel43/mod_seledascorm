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
 * SELEDASCORM external functions and service definitions.
 *
 * @package    mod_seledascorm
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_seledascorm_manage_embedded_editor_action' => [
        'classname'   => 'mod_seledascorm\external\manage_embedded_editor',
        'methodname'  => 'execute_action',
        'description' => 'Execute an install, update, repair, or uninstall action on the embedded eXeLearning editor.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/site:config, mod/seledascorm:manageembeddededitor',
    ],
    'mod_seledascorm_manage_embedded_editor_status' => [
        'classname'   => 'mod_seledascorm\external\manage_embedded_editor',
        'methodname'  => 'get_status',
        'description' => 'Return the current installation status of the embedded eXeLearning editor.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'moodle/site:config, mod/seledascorm:manageembeddededitor',
    ],

    'mod_seledascorm_view_seledascorm' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'view_seledascorm',
        'description' => 'Trigger the course module viewed event.',
        'type' => 'write',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_get_seledascorm_attempt_count' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'get_seledascorm_attempt_count',
        'description' => 'Return the number of attempts done by a user in the given SELEDASCORM.',
        'type' => 'read',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_get_seledascorm_scoes' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'get_seledascorm_scoes',
        'description' => 'Returns a list containing all the scoes data related to the given seledascorm id',
        'type' => 'read',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_get_seledascorm_user_data' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'get_seledascorm_user_data',
        'description' => 'Retrieves user tracking and SCO data and default SELEDASCORM values',
        'type' => 'read',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_insert_seledascorm_tracks' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'insert_seledascorm_tracks',
        'description' => 'Saves a seledascorm tracking record.
                          It will overwrite any existing tracking data for this attempt.
                          Validation should be performed before running the function to ensure the user will not lose any existing
                          attempt data.',
        'type' => 'write',
        'capabilities' => 'mod/seledascorm:savetrack',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_get_seledascorm_sco_tracks' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'get_seledascorm_sco_tracks',
        'description' => 'Retrieves SCO tracking data for the given user id and attempt number',
        'type' => 'read',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_get_seledascorms_by_courses' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'get_seledascorms_by_courses',
        'description' => 'Returns a list of seledascorm instances in a provided set of courses, if
                            no courses are provided then all the seledascorm instances the user has access to will be returned.',
        'type' => 'read',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_launch_sco' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'launch_sco',
        'description' => 'Trigger the SCO launched event.',
        'type' => 'write',
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_seledascorm_get_seledascorm_access_information' => [
        'classname' => 'mod_seledascorm_external',
        'methodname' => 'get_seledascorm_access_information',
        'description' => 'Return capabilities information for a given seledascorm.',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
