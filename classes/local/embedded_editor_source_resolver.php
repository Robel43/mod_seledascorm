<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Bundled embedded Seleda editor resolver.
 *
 * Seleda V1 has exactly one supported embedded-editor source:
 * mod/seledascorm/dist/static inside the installed plugin.
 *
 * @package    mod_seledascorm
 * @copyright  2025 eXeLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_seledascorm\local;

class embedded_editor_source_resolver {
    /** @var string Active source is the bundled copy inside the plugin. */
    public const SOURCE_BUNDLED = 'bundled';

    /** @var string No usable bundled editor was found. */
    public const SOURCE_NONE = 'none';

    /** @var string[] Directories required in a complete static editor bundle. */
    private const EXPECTED_ASSET_DIRS = ['app', 'libs', 'files'];

    /**
     * Return the bundled editor directory inside the plugin.
     *
     * @return string
     */
    public static function get_bundled_dir(): string {
        global $CFG;
        return $CFG->dirroot . '/mod/seledascorm/dist/static';
    }

    /**
     * Validate a complete embedded Seleda bundle.
     *
     * @param string $dir Absolute editor directory.
     * @return bool
     */
    public static function validate_editor_dir(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $dir = rtrim($dir, '/');
        if (!is_file($dir . '/index.html') || !is_readable($dir . '/index.html')) {
            return false;
        }

        foreach (self::EXPECTED_ASSET_DIRS as $assetdir) {
            if (!is_dir($dir . '/' . $assetdir)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the active source.
     *
     * @return string
     */
    public static function get_active_source(): string {
        return self::validate_editor_dir(self::get_bundled_dir())
            ? self::SOURCE_BUNDLED
            : self::SOURCE_NONE;
    }

    /**
     * Return the active bundled editor directory.
     *
     * @return string|null
     */
    public static function get_active_dir(): ?string {
        return self::get_active_source() === self::SOURCE_BUNDLED
            ? self::get_bundled_dir()
            : null;
    }

    /**
     * Check whether the bundled editor is available.
     *
     * @return bool
     */
    public static function has_local_source(): bool {
        return self::get_active_dir() !== null;
    }

    /**
     * Return the bundled editor index.
     *
     * @return string|null
     */
    public static function get_index_source(): ?string {
        $dir = self::get_active_dir();
        return $dir === null ? null : $dir . '/index.html';
    }

    /**
     * Return status data for the read-only Moodle settings card.
     *
     * @return \stdClass
     */
    public static function get_status(): \stdClass {
        $status = new \stdClass();
        $status->active_source = self::get_active_source();
        $status->bundled_dir = self::get_bundled_dir();
        $status->bundled_available = self::validate_editor_dir($status->bundled_dir);
        return $status;
    }
}
