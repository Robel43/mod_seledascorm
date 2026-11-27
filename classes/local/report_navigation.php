<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;

defined('MOODLE_INTERNAL') || die();

/** Global reports-link policy; access is still enforced through Moodle capabilities. */
final class report_navigation {
    const SITE_ADMIN = -1;

    /** List every current Moodle role, including custom roles. */
    public static function choices(): array {
        return [self::SITE_ADMIN => get_string('rptnavsiteadmins', 'mod_seledascorm')] +
            role_get_names(null, ROLENAME_ORIGINAL, true);
    }

    /** Safe upgrade default: administrators and roles already allowed at system level. */
    public static function defaults(): array {
        global $DB;
        $defaults = [self::SITE_ADMIN];
        $roles = $DB->get_records('role_capabilities', ['contextid'=>\context_system::instance()->id,
            'capability'=>'mod/seledascorm:viewreport', 'permission'=>CAP_ALLOW]);
        foreach ($roles as $role) $defaults[] = (int)$role->roleid;
        return array_values(array_unique($defaults));
    }

    /** Match any selected role held anywhere on the site, after the access gate. */
    public static function visible(): bool {
        global $CFG, $DB, $USER;
        if (!reporting_dashboard::can_open()) {
            return false;
        }
        $configured = get_config('mod_seledascorm', 'reportaccessroles');
        if ($configured === false) {
            $configured = get_config('mod_seledascorm', 'reportlinkroles');
        }
        // Preserve existing navigation until the new setting has been saved.
        if ($configured === false) {
            return true;
        }
        $ids = [];
        foreach (explode(',', (string)$configured) as $value) {
            $value = trim($value);
            if ($value === (string)self::SITE_ADMIN && is_siteadmin()) {
                return true;
            }
            if (ctype_digit($value) && (int)$value > 0) {
                $ids[(int)$value] = (int)$value;
            }
        }
        if (!$ids) {
            return false;
        }
        // These default roles are effective without explicit role_assignments rows.
        foreach (['defaultuserroleid', 'defaultfrontpageroleid'] as $key) {
            if (!empty($CFG->$key) && isset($ids[(int)$CFG->$key])) {
                return true;
            }
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED, 'reportnavrole');
        $params['reportnavuser'] = $USER->id;
        return $DB->record_exists_select('role_assignments', 'userid = :reportnavuser AND roleid ' . $insql, $params);
    }
}
