<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Read-only status setting for the bundled Seleda editor.
 *
 * @package    mod_seledascorm
 * @copyright  2025 eXeLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_seledascorm\admin;

class admin_setting_embeddededitor extends \admin_setting {
    public function __construct(string $visiblename, string $description) {
        parent::__construct('seledascorm/embeddededitorwidget', $visiblename, $description, '');
    }

    public function get_setting(): string {
        return '';
    }

    public function write_setting($data): string {
        return '';
    }

    public function output_html($data, $query = ''): string {
        global $OUTPUT;

        $status = \mod_seledascorm\local\embedded_editor_source_resolver::get_status();
        $context = [
            'active_source_bundled' => $status->active_source ===
                \mod_seledascorm\local\embedded_editor_source_resolver::SOURCE_BUNDLED,
            'active_source_none' => $status->active_source ===
                \mod_seledascorm\local\embedded_editor_source_resolver::SOURCE_NONE,
            'bundled_available' => (bool) $status->bundled_available,
        ];

        $widgethtml = $OUTPUT->render_from_template('mod_seledascorm/admin_embedded_editor', $context);
        $labelhtml = \html_writer::tag('label', s($this->visiblename));
        $labelcolumn = \html_writer::div($labelhtml, 'form-label col-md-3 text-md-right');
        $contentcolumn = \html_writer::div($widgethtml, 'form-setting col-md-9');
        return \html_writer::div(
            \html_writer::div($labelcolumn . $contentcolumn, 'form-item row'),
            'mod_seledascorm-admin-embedded-editor-setting'
        );
    }
}
