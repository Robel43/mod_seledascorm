<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Unit tests for the bundled Seleda editor source resolver.
 *
 * @package    mod_seledascorm
 * @copyright  2025 eXeLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_seledascorm\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \mod_seledascorm\local\embedded_editor_source_resolver
 */
class embedded_editor_source_resolver_test extends \advanced_testcase {

    public function test_get_bundled_dir(): void {
        global $CFG;

        $dir = embedded_editor_source_resolver::get_bundled_dir();

        $this->assertEquals(
            $CFG->dirroot . '/mod/seledascorm/dist/static',
            $dir
        );
    }

    public function test_validate_editor_dir_requires_complete_bundle(): void {
        $tmpdir = make_temp_directory('mod_seledascorm_test/complete_editor');
        file_put_contents($tmpdir . '/index.html', '<!DOCTYPE html><html></html>');
        mkdir($tmpdir . '/app', 0777, true);
        mkdir($tmpdir . '/libs', 0777, true);
        mkdir($tmpdir . '/files', 0777, true);

        $this->assertTrue(embedded_editor_source_resolver::validate_editor_dir($tmpdir));

        remove_dir($tmpdir);
    }

    public function test_validate_editor_dir_rejects_partial_bundle(): void {
        $tmpdir = make_temp_directory('mod_seledascorm_test/partial_editor');
        file_put_contents($tmpdir . '/index.html', '<!DOCTYPE html><html></html>');
        mkdir($tmpdir . '/app', 0777, true);
        mkdir($tmpdir . '/files', 0777, true);

        $this->assertFalse(embedded_editor_source_resolver::validate_editor_dir($tmpdir));

        remove_dir($tmpdir);
    }

    public function test_validate_editor_dir_rejects_missing_index(): void {
        $tmpdir = make_temp_directory('mod_seledascorm_test/no_index');
        mkdir($tmpdir . '/app', 0777, true);
        mkdir($tmpdir . '/libs', 0777, true);
        mkdir($tmpdir . '/files', 0777, true);

        $this->assertFalse(embedded_editor_source_resolver::validate_editor_dir($tmpdir));

        remove_dir($tmpdir);
    }

    public function test_validate_editor_dir_rejects_nonexistent_directory(): void {
        $this->assertFalse(
            embedded_editor_source_resolver::validate_editor_dir('/nonexistent/seleda/editor')
        );
    }

    public function test_active_source_is_bundled_or_none_only(): void {
        $source = embedded_editor_source_resolver::get_active_source();

        $this->assertContains($source, [
            embedded_editor_source_resolver::SOURCE_BUNDLED,
            embedded_editor_source_resolver::SOURCE_NONE,
        ]);
    }

    public function test_status_exposes_only_bundled_state(): void {
        $status = embedded_editor_source_resolver::get_status();

        $this->assertObjectHasProperty('active_source', $status);
        $this->assertObjectHasProperty('bundled_dir', $status);
        $this->assertObjectHasProperty('bundled_available', $status);
        $this->assertIsBool($status->bundled_available);

        $this->assertObjectNotHasProperty('moodledata_dir', $status);
        $this->assertObjectNotHasProperty('moodledata_available', $status);
        $this->assertObjectNotHasProperty('moodledata_version', $status);
        $this->assertObjectNotHasProperty('moodledata_installed_at', $status);
    }

    public function test_index_source_is_bundled_path_or_null(): void {
        $source = embedded_editor_source_resolver::get_index_source();

        if ($source === null) {
            $this->assertEquals(
                embedded_editor_source_resolver::SOURCE_NONE,
                embedded_editor_source_resolver::get_active_source()
            );
            return;
        }

        $this->assertEquals(
            embedded_editor_source_resolver::get_bundled_dir() . '/index.html',
            $source
        );
    }
}
