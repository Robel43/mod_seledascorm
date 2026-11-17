# eXeLearning SCORM activities for Moodle

<a href="https://moodle-playground.com/?blueprint-url=https://raw.githubusercontent.com/exelearning/mod_exescorm/refs/heads/main/blueprint.json"><img src="https://raw.githubusercontent.com/ateeducacion/action-moodle-playground-pr-preview/refs/heads/main/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="224"></a>

> ℹ️ The eXeLearning editor is fetched from the shared release and unpacked into the plugin when the playground boots, so the first load may take a few extra seconds. ELPX upload, viewer and preview work normally.

Activity-type module to create and edit SCORM packages with eXeLearning inside Moodle.

The plugin can run in either of two editing modes, selected by the site administrator under _Site administration > Plugins > Activity modules > eXeLearning (SCORM) > Editor mode_:

* **eXeLearning Cloud (remote server, default)** — connects to an existing eXeLearning Cloud instance using its base URL and signing key. This is the default mode shipped by the plugin and the recommended choice when an organisation operates a shared eXeLearning Cloud deployment.
* **Embedded editor (integrated)** — a self-contained build of the eXeLearning editor ships with the plugin (or can be installed by an administrator from the plugin settings page). It runs directly inside Moodle, so **no external server is required**.

Both modes produce the same kind of activity; pick the one that best fits your infrastructure.

## Compatibility

This plugin works on every supported Moodle release from **Moodle 4.2** (the minimum required, see `version.php`: `$plugin->requires = 2023042400`) up to the latest Moodle 5.2.x stable. It is verified on the LTS branches commonly deployed in production:

| Moodle branch         | Status                                |
| --------------------- | ------------------------------------- |
| 4.2.x                 | Supported (minimum required version)  |
| 4.3.x                 | Supported                             |
| 4.4.x                 | Supported                             |
| 4.5.x (LTS)           | Supported                             |
| 5.0.x                 | Supported                             |
| 5.1.x                 | Supported                             |
| 5.2.x (latest stable) | Supported                             |

Older Moodle releases (3.x, 4.0 and 4.1) are **not** supported. The plugin is expected to keep working with newer Moodle releases as they appear; if you find an incompatibility please open an issue at <https://github.com/exelearning/exelearning/issues> (label `moodle`).

### Requirements

* **Moodle**: 4.2 or later (see table above).
* **PHP**: any version required by the Moodle release in use — the plugin does not add extra PHP requirements on top of Moodle's own.
* **Database**: any database supported by the Moodle release in use.
* **Browser**: any modern, evergreen browser with JavaScript enabled.
* **Required for _eXeLearning Cloud_ mode (default)**: an eXeLearning Cloud instance and access to its configuration files / signing key. Not needed when running the plugin in _Embedded editor_ mode.

## Installation

> **Important:** It is recommended to install from a [release ZIP](https://github.com/exelearning/mod_exescorm/releases), which includes the embedded editor pre-built for optimal performance. If the release ZIP does not include the editor, or if you want to install a newer version, administrators can download it from GitHub Releases via the embedded editor controls in the plugin settings.

### Installing via uploaded ZIP file

1. Download the latest ZIP from [Releases](https://github.com/exelearning/mod_exescorm/releases).
2. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
3. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
4. Check the plugin validation report and finish the installation.

### Installing manually

1. Download and extract the latest ZIP from [Releases](https://github.com/exelearning/mod_exescorm/releases).
2. Place the extracted contents in `{your/moodle/dirroot}/mod/exescorm`.
3. Log in to your Moodle site as an admin and go to _Site administration >
   Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Configuration

Go to the URL:

    {your/moodle/dirroot}/admin/settings.php?section=modsettingexescorm

  * Remote URI: *exescorm  | exeonlinebaseuri*
    * eXeLearning Cloud base URI

  * Signing Key: *exescorm | hmackey1*
    * Key used to sign data sent to the eXeLearning server, to check the data origin. Use up to 32 characters.

  * Token expiration: *exescorm | tokenexpiration*
    * Max time (in seconds) to edit the package in eXeLearning and get back to Moodle.

  * New package template: *exescorm | template*
    * Package uploaded there will be used as the default package for new activities.

  * Send template: *exescorm | sendtemplate*
    * Sends uploaded (or default) template to eXeLearning when creating a new activity.

  * Mandatory files RE list: *exescorm | mandatoryfileslist*
    * A mandatory files list can be configurad here. Enter each mandatory file as a PHP regular expression (RE) on a new line.

  * Forbidden files RE list: *exescorm | forbiddenfileslist*
    * A forbidden files list can be configurad here. Enter each forbidden file as a PHP regular expression (RE) on a new line.

## Embedded Editor Management

The plugin supports two local editor sources with the following precedence:

1. **Admin-installed** (moodledata): Downloaded from GitHub Releases via the plugin settings.
2. **Bundled** (plugin): Included in the plugin release ZIP at `dist/static/`.

The admin-installed version takes priority over the bundled one. If neither source is available, the embedded editor mode cannot be used.

## Development

For development setup, build instructions, and contributing guidelines, see [DEVELOPMENT.md](DEVELOPMENT.md).

## Support

Issue tracking for this plugin is centralized in the main
[`exelearning/exelearning`](https://github.com/exelearning/exelearning) repository.
Please [open new issues there](https://github.com/exelearning/exelearning/issues/new),
and browse [existing `moodle`-labeled issues](https://github.com/exelearning/exelearning/issues?q=is%3Aissue+label%3Amoodle)
before reporting a bug or requesting a feature.

## About

Copyright 2023-2026:
Centro Nacional de Desarrollo Curricular en Sistemas no Propietarios (CeDeC) /
INTEF (Instituto Nacional de Tecnologías Educativas y de Formación del Profesorado)

### License

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should receive a copy of the GNU General Public License
along with this program.

## Seleda Studio block reporting

This fork includes the Seleda reporting integration directly. Do not apply the
separate Studio Moodle overlay after updating this plugin.

With a fresh SCORM 1.2 export from the updated Seleda Studio, the **Interactions**
report shows each learner's active viewing time, action count and individual
content-block breakdown, plus totals across the whole SCORM attempt. Expand
**View block usage**, then **View interaction log** for the detailed attempt page.
The detailed page has expandable block logs and a downloadable table containing
totals, blocks, actions and readable question/answer records. Question labels are
snapshotted per learner/SCO/attempt to preserve wording when packages change.

All block types can supply usage, including ungraded text and image blocks.
Usage is separate from grades and completion. Time is estimated for one visible
block at a time; hidden/unfocused tabs and more than 60 seconds of inactivity are
excluded. Recorded actions include clicks, committed answers, drag/drop, hotspot
hover and HTML audio/video actions. Cross-origin embeds may only expose launch
or focus, not their internal actions. No microphone recordings are collected.

The plugin accepts bounded, authenticated batches without blocking navigation.
Retries do not double-count, and late saves remain tied to their original SCO,
learner and attempt. Up to 2,000 action details per block visit session are retained;
omitted detail counts are shown while cumulative totals remain available. A hard
browser crash or an offline exit may lose the unsaved tail. SCORM 2004 retains
its native behavior; this additional usage bridge currently targets SCORM 1.2.
Historical packages cannot retrospectively provide usage they never recorded.

### Updating an existing installation

1. Back up Moodle's plugin directory and database.
2. Update this repository's code in **mod/exescorm**. The repository name is
   mod_seledascorm, but the installed component remains **mod_exescorm**. Keep this
   directory and component name so existing activities and records remain intact.
3. Run Moodle's normal upgrade and purge its caches. Git checkouts intentionally
   retain the upstream dev version values; release packaging supplies a dated
   version. No new tables or data migration are required for usage reporting.
4. Re-export a SCORM 1.2 package from the updated Seleda Studio and update the
   activity package. Verify two learners and separate attempts before rollout.

Usage and label snapshots use the existing exescorm_scoes_track table. Existing
Moodle privacy exports/deletions, attempt deletion and course reset cover these
records. Learner-entered text can appear in action details; report access retains
the plugin's existing capability and group restrictions. Display values are
escaped for HTML and spreadsheet formula safety.

Source ZIPs and the reporting-checks CI artifact include the complete plugin for
**remote editor** mode. They do not bundle the embedded editor. Keep the existing
embedded build during an in-place source update, or use make build-editor before
packaging if an embedded-editor distribution is required.

### Development checks

See [tests/reporting/README.md](tests/reporting/README.md) for executable regression
checks. The Seleda reporting checks workflow runs them on pushes and pull requests
and produces an installable remote-editor plugin artifact. These tests use Moodle
boundary fixtures; full site acceptance remains necessary.

Sibling review: mod_exeweb does not contain the SCORM datamodel endpoint or CMI
interaction report used here. Its native web-package delivery does not implement
this SCORM bridge, so this change does not apply to that sibling plugin.
