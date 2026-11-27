<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;

defined('MOODLE_INTERNAL') || die();

/** One role selector controls navigation and its own reversible capability grants. */
final class report_role_setting extends \admin_setting_configmultiselect {
    const CAPABILITY = 'mod/seledascorm:viewreport';

    public function __construct() {
        parent::__construct('mod_seledascorm/reportaccessroles',
            get_string('rptaccessroles', 'mod_seledascorm'),
            get_string('rptaccessroles_desc', 'mod_seledascorm'),
            report_navigation::defaults(), report_navigation::choices());
        $this->set_updatedcallback('purge_all_caches');
    }

    public function write_setting($data) {
        if (!is_array($data)) return '';
        $factory = \core\lock\lock_config::get_lock_factory('mod_seledascorm');
        $lock = $factory->get_lock('report-role-grants', 10);
        if (!$lock) return get_string('rptgrantbusy', 'mod_seledascorm');
        try {
            return $this->save_roles($data);
        } finally {
            $lock->release();
        }
    }

    private function save_roles(array $data): string {
        global $DB;
        $choices = report_navigation::choices();
        $this->choices = $choices;
        $selected = [];
        foreach ($data as $id) if (is_scalar($id) && array_key_exists($id, $choices)) $selected[(int)$id] = (int)$id;
        $context = \context_system::instance();
        $stored = get_config('mod_seledascorm', 'reportrolegrants');
        $managed = $stored === false ? [] : json_decode($stored, true);
        if (!is_array($managed)) return get_string('rptgrantstateerror', 'mod_seledascorm');
        foreach ($managed as $id=>$before) {
            if (!ctype_digit((string)$id) || (int)$id < 1 ||
                    ($before !== null && (!is_int($before) || !in_array($before, [CAP_INHERIT, CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT], true)))) {
                return get_string('rptgrantstateerror', 'mod_seledascorm');
            }
        }
        $permissions = [];
        foreach (array_unique(array_merge(array_keys($managed), array_keys($selected))) as $id) {
            if ($id <= 0) continue;
            $record = $DB->get_record('role_capabilities', ['contextid'=>$context->id,
                'roleid'=>$id, 'capability'=>self::CAPABILITY]);
            $permissions[$id] = $record ? (int)$record->permission : null;
            if (isset($selected[$id]) && $permissions[$id] === CAP_PROHIBIT) {
                return get_string('rptgrantprohibited', 'mod_seledascorm', $choices[$id]);
            }
        }
        $changes = [];
        foreach ($managed as $id=>$before) {
            if (!isset($selected[$id])) {
                // Preserve permissions subsequently changed by another administrator.
                if (($permissions[$id] ?? null) === CAP_ALLOW) $changes[$id] = $before;
                unset($managed[$id]);
            }
        }
        foreach ($selected as $id) {
            if ($id <= 0 || $permissions[$id] === CAP_ALLOW) continue;
            $managed[$id] = $permissions[$id];
            $changes[$id] = CAP_ALLOW;
        }
        // Editing plugin settings alone must not allow somebody to escalate roles.
        if ($changes) require_capability('moodle/role:manage', $context);
        $transaction = $DB->start_delegated_transaction();
        foreach ($changes as $id=>$permission) {
            if (!$DB->record_exists('role', ['id'=>$id])) continue;
            if ($permission === null) unassign_capability(self::CAPABILITY, $id, $context);
            else assign_capability(self::CAPABILITY, $permission, $id, $context, true);
        }
        $error = parent::write_setting(array_values($selected));
        if ($error !== '') $transaction->rollback(new \moodle_exception('errorsetting', 'admin'));
        if (!set_config('reportrolegrants', json_encode((object)$managed), 'mod_seledascorm')) {
            $transaction->rollback(new \moodle_exception('errorsetting', 'admin'));
        }
        $transaction->allow_commit();
        return '';
    }
}
