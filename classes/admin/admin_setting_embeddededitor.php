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
 * Admin status widget for the bundled Seleda editor.
 *
 * Renders a read-only status card. Seleda V1 ships the embedded editor
 * with the plugin and does not download/replace it independently.
 *
 * @package    mod_seledascorm
 * @copyright  2025 eXeLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_seledascorm\admin;

/**
 * Custom admin setting that renders the embedded editor management widget.
 *
 * This setting stores no value itself (get_setting returns '' and
 * write_setting always succeeds). Its sole purpose is to output the
 * interactive status/action card into the admin settings page.
 */
class admin_setting_embeddededitor extends \admin_setting {

    /**
     * Constructor.
     *
     * @param string $visiblename Visible name shown as the setting label.
     * @param string $description Short description shown below the label.
     */
    public function __construct(string $visiblename, string $description) {
        parent::__construct('seledascorm/embeddededitorwidget', $visiblename, $description, '');
    }

    /**
     * Returns the current value of this setting.
     *
     * This widget stores no persistent value; return empty string so
     * admin_setting machinery treats it as "has a value" (not null).
     *
     * @return string Always empty string.
     */
    public function get_setting(): string {
        return '';
    }

    /**
     * Persists a new value for this setting (no-op).
     *
     * All real actions are performed via AJAX; the form submit path is unused.
     *
     * @param mixed $data Submitted form data (ignored).
     * @return string Empty string signals success to the settings framework.
     */
    public function write_setting($data): string {
        return '';
    }

    /**
     * Render the embedded editor status widget as HTML.
     *
     * Reads locally-cached state only (no GitHub API call). The AMD module
     * mod_seledascorm/admin_embedded_editor is initialised with a JS context
     * object so it can wire up action buttons and the "latest version" area.
     *
     * @param mixed  $data  Current setting value (unused).
     * @param string $query Admin search query string (unused).
     * @return string Rendered HTML for the widget.
     */
    public function output_html($data, $query = ''): string {
        global $OUTPUT;

        $status = \mod_seledascorm\local\embedded_editor_source_resolver::get_status();

        // Determine source flags for the template.
        $sourcemoodledata = ($status->active_source ===
            \mod_seledascorm\local\embedded_editor_source_resolver::SOURCE_MOODLEDATA);
        $sourcebundled = ($status->active_source ===
            \mod_seledascorm\local\embedded_editor_source_resolver::SOURCE_BUNDLED);
        $sourcenone = ($status->active_source ===
            \mod_seledascorm\local\embedded_editor_source_resolver::SOURCE_NONE);

        // V1 ships Seleda with the plugin. Independent install/update/remove
        // actions are disabled so an upstream editor cannot overwrite Seleda.
        $caninstall = false;
        $canupdate = false;
        $canuninstall = false;

        // Build template context.
        $context = [
            'sesskey'                  => sesskey(),
            'active_source'            => $status->active_source,
            'active_source_moodledata' => $sourcemoodledata,
            'active_source_bundled'    => $sourcebundled,
            'active_source_none'       => $sourcenone,
            'moodledata_available'     => (bool) $status->moodledata_available,
            'moodledata_version'       => $status->moodledata_version ?? '',
            'moodledata_installed_at'  => $status->moodledata_installed_at ?? '',
            'bundled_available'        => (bool) $status->bundled_available,
            'can_install'              => $caninstall,
            'can_update'               => $canupdate,
            'can_uninstall'            => $canuninstall,
        ];

        $widgethtml = $OUTPUT->render_from_template('mod_seledascorm/admin_embedded_editor', $context);
        $labelhtml = \html_writer::tag('label', s($this->visiblename));
        $labelcolumn = \html_writer::div($labelhtml, 'form-label col-md-3 text-md-right');
        $contentcolumn = \html_writer::div($widgethtml, 'form-setting col-md-9');
        $rowhtml = \html_writer::div($labelcolumn . $contentcolumn, 'form-item row');

        return \html_writer::div($rowhtml, 'mod_seledascorm-admin-embedded-editor-setting');
    }
}
