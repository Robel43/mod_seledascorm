<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Content-addressed URLs for static presentation files outside Moodle's theme CSS cache. */
final class presentation_assets {
    public static function css(string $file): \moodle_url {
        if (!in_array($file, ['dashboard.css', 'learner-layout.css'], true)) {
            throw new \coding_exception('Unknown Seleda presentation asset');
        }
        $revision = substr(hash_file('sha256', __DIR__ . '/../../' . $file), 0, 16);
        return new \moodle_url('/mod/seledascorm/' . $file, ['v' => $revision]);
    }
}
