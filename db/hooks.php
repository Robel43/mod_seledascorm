<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$callbacks = [
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\mod_seledascorm\hook_callbacks::class, 'extend_primary'],
    ],
];
