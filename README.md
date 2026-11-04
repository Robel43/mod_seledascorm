# Seleda for Moodle

Seleda is the Moodle activity plugin used with **Seleda Studio** for publishing, launching and tracking SCORM learning packages. It is maintained as a compatibility-preserving customization of upstream `exelearning/mod_exescorm`.

## Compatibility contract

The repository and user-facing product are Seleda-branded, but the Moodle plugin keeps its upstream technical identity:

- plugin component: `mod_exescorm`
- installation folder: `mod/exescorm`
- database tables and capabilities: `exescorm_*` / `mod/exescorm:*`
- SCORM 1.2/2004 API and Moodle backup/restore identifiers: unchanged

This is intentional. Renaming those internal identifiers would break existing activities, attempts, grades, completion data, backups and upgrade compatibility.

## Seleda customizations

This fork layers Seleda-specific behavior on top of the upstream plugin:

- **Seleda** course/activity terminology
- **Seleda Studio** remote-authoring terminology
- Seleda blue/teal player styling aligned with the Studio visual identity
- readable interaction reporting for Seleda's shared LMS reporting contract
- learner-attempt interaction tables showing human-readable question/activity text, learner response, correct response, result and calculated weight
- activity-wide Interactions reports using the same readable decoder
- attempt-time label snapshots so later package edits do not silently rewrite historical report wording when metadata is available
- safe fallback to original SCORM identifiers when readable labels cannot be resolved

## Editor modes

Administrators choose the editor mode under:

`Site administration > Plugins > Activity modules > Seleda > Editor mode`

### Seleda Studio server

The recommended Seleda deployment connects Moodle to a Seleda Studio server using the existing eXeLearning-compatible remote-editor protocol. Existing configuration keys such as `exeonlinebaseuri` are retained for compatibility.

### Embedded eXeLearning-compatible editor

The plugin also retains the upstream embedded-editor path. This is an **upstream eXeLearning-compatible editor**, not the customized Seleda Studio Docker build. Keeping that distinction explicit avoids presenting upstream editor assets as Seleda-branded software.

## Moodle compatibility

This fork targets **Moodle 4.5**. Its minimum Moodle requirement is set to the Moodle 4.5 branch version (`2024100700`) so validation and deployment stay aligned with the production environment.

## Installation

Install the plugin in:

```text
{moodle-root}/mod/exescorm
```

Then complete the Moodle upgrade from the web interface or run:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

Do not install this repository in `mod/seledascorm`; the technical component remains `mod_exescorm`.

## Updating an existing mod_exescorm installation

Back up the existing plugin directory and Moodle database before replacing plugin code. Because the technical component remains `mod_exescorm`, the Seleda fork is intended to upgrade an existing eXeSCORM installation in place while preserving activities and attempts.

After replacing the plugin code:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

Verify at minimum:

1. existing Seleda activities open;
2. previous learner attempts and grades remain visible;
3. completion status is unchanged;
4. a fresh Seleda Studio SCORM 1.2 export reports score and status;
5. Reports > Interactions shows readable question/response labels when the package provides Seleda reporting metadata;
6. older packages still fall back safely to SCORM identifiers.

## Reporting architecture

The readable report implementation does not introduce a separate grade store. It reads Moodle's existing `exescorm_scoes_track` records and enriches them with bounded label metadata captured from the Seleda package.

The reporting decoder is shared across interaction types and is intended to support Seleda Content Blocks progressively. A Content Block still needs to emit meaningful SCORM interactions through Seleda's shared reporting contract; the Moodle plugin cannot reconstruct interaction data that a package never reported.

## Upstream relationship

Current upstream base:

- repository: `exelearning/mod_exescorm`
- internal component: `mod_exescorm`

Upstream compatibility fixes should be merged into this fork without renaming the technical plugin identity. Seleda-specific changes should remain isolated and documented so future upstream synchronization stays reviewable.

## Repository

```text
https://github.com/Robel43/mod_seledascorm
```

## License

This fork remains licensed under **GNU GPL v3 or later**, consistent with the upstream project. Upstream copyright and license notices in source files are retained.
