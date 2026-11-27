<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm;
defined('MOODLE_INTERNAL') || die();

/** Add reporting to Moodle's main navigation, independently of course pages. */
final class hook_callbacks {
    public static function extend_primary(\core\hook\navigation\primary_extend $hook): void {
        if (\mod_seledascorm\local\report_navigation::visible()) {
            $hook->get_primaryview()->add(get_string('dashboardtitle', 'mod_seledascorm'),
                new \moodle_url('/mod/seledascorm/dashboard.php'), \navigation_node::TYPE_CUSTOM,
                null, 'seledascorm-dashboard');
        }
    }
}
