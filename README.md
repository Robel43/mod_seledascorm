# Seleda for Moodle

Seleda is the Moodle 4.5 activity plugin used with **Seleda Studio** to create, edit, publish, launch and track SCORM learning content directly from Moodle.

This repository is a deliberate Seleda architecture fork of upstream `exelearning/mod_exescorm`. V1 now uses its own Moodle technical identity, `mod_seledascorm`, instead of masquerading as the upstream module.

## Current V1 architecture

```text
Moodle 4.5
   |
   +-- Create with Seleda
   |
   +-- Edit with Seleda
            |
            v
Embedded Seleda Studio
            |
            +-- OPEN_FILE
            +-- author / edit
            +-- REQUEST_EXPORT
            |
            v
Moodle stores editable source + exported SCORM package
```

The same embedded Seleda bundle is used for both new activity creation and later editing. There is no separate edit architecture.

## Technical identity and migration boundary

The Seleda V1 technical identity is:

- plugin component: `mod_seledascorm`
- installation folder: `mod/seledascorm`
- database tables: `seledascorm_*`
- capabilities: `mod/seledascorm:*`
- SCORM 1.2 / 2004 APIs
- backup and restore identifiers
- completion and grading integration identifiers

`mod_seledascorm` is intentionally a different Moodle component from upstream `mod_exescorm`. An existing `mod_exescorm` installation is therefore **not** an automatic in-place upgrade target. Migrating historical activities, attempts, grades, completion records, files, capabilities and backups requires a separate migration procedure.

## Embedded Seleda Studio

The default V1 editor is a bundled **embedded Seleda Studio authoring workspace**.

The Moodle plugin does not ship a generic stock eXeLearning editor. It builds the Seleda embedded profile from:

```text
Robel43/Timhirt-Studio
branch: feature/seleda-embedded-editor
Dockerfile.embedded
```

The embedded profile is built from pinned **eXeLearning 4.0.3**, then Seleda customizations are applied before the static bundle is generated.

The final static editor is packaged under:

```text
dist/static/
```

## V1 included functionality

The embedded Seleda V1 includes:

- Seleda branding and terminology
- authoring shell
- Block Library
- Project Settings
- Content Block Manager authoring UI
- Page / Lesson Structure
- section editing controls
- Themes
- accessibility customizations
- learner page UI
- learner block reveal
- Seleda LMS runtime
- readable SCORM reporting metadata
- export branding
- rebuilt Website / SCORM resource bundles

### New Seleda Content Blocks

The embedded build explicitly includes:

- **MCQ Feedback** — `mcq-feedback`
- **Speaking Practice** — `speaking-practice`
- **Page Navigation** — `page-navigation`

### Modified existing editors / Content Blocks

Seleda also customizes:

- **Drag & Drop** — `dragdrop`
- **Before / After** — `beforeafter`
- **Map / hotspot** — `map`
- **Quick Test** — `quick-questions`
- **Complete** — `complete`

The Seleda Studio build and the Moodle plugin build both verify that these eight required editor directories exist in the final static bundle. The build fails if one is missing.

## V1 exclusions

The embedded Moodle profile intentionally excludes:

- Question Bank — reserved for V2
- Seleda account services
- standalone login/logout services
- standalone Administration pages
- standalone Appearance & Branding administration
- persistent server-side Content Block policy services
- Studio Services/backend routes
- standalone database-backed user services

Moodle remains responsible for authentication, authorization, course ownership and activity lifecycle.

## Installation-stage branding

On first installation, and when these settings are first introduced during an upgrade, Moodle displays its supported **New settings** step.

The Seleda branding fields appear first:

- **Display name** — required, defaults to `Seleda`
- **Logo** — optional; PNG, JPG/JPEG, WebP and SVG are supported

If no custom logo is uploaded, the bundled Seleda logo is used.

The configured display name is used in plugin-owned UI such as:

```text
Create with <name>
Edit with <name>
Embedded editor title / product title
```

The configured logo is injected into the embedded editor at runtime and is used for product surfaces such as:

- loading screen
- About/product logo
- error/product-logo surfaces

Changing the Moodle site's display name or logo does **not** require rebuilding Seleda Studio.

Moodle's core activity chooser name remains statically **Seleda** because Moodle caches module language strings and icons.

## Create with Seleda

The creation workflow is:

```text
Teacher adds Seleda activity
        |
        v
Moodle opens embedded Seleda Studio
        |
        v
Teacher authors content
        |
        v
REQUEST_EXPORT
        |
        v
Moodle stores exported SCORM package
and keeps the editable Seleda source
```

## Edit with Seleda

The edit workflow uses the same editor bundle:

```text
Existing Seleda activity
        |
        v
Edit with Seleda
        |
        v
Moodle sends saved project through OPEN_FILE
        |
        v
Teacher edits existing project
        |
        v
REQUEST_EXPORT
        |
        v
Updated package is saved back to Moodle
```

The next edit reopens the updated source rather than starting a blank project.

## Building the embedded editor

The embedded editor is a **required part of every distributable Seleda plugin ZIP**. Moodle administrators do not download it separately after installation.

The supported Moodle-side build command is:

```bash
make build-editor
```

This always delegates to:

```bash
sh scripts/build-seleda-editor.sh
```

The build source is controlled by:

```text
.seleda-editor-ref
```

For V1 the default ref is:

```text
feature/seleda-embedded-editor
```

The generic stock eXeLearning build path has been removed from the active Makefile flow. `build-editor-no-update` remains only as a compatibility alias to the same Seleda build.

### Private Timhirt-Studio access

The embedded profile currently lives in the private `Robel43/Timhirt-Studio` repository.

### Required repository secret

Before GitHub can create a complete release ZIP, configure this repository secret in `Robel43/mod_seledascorm`:

```text
SELEDA_STUDIO_TOKEN
```

Use a fine-grained GitHub token with read-only **Contents** access to `Robel43/Timhirt-Studio`. The PR source-validation job can run without this secret, but the package sub-job will be skipped. The release workflow intentionally fails if the secret is absent because Seleda releases must never omit the embedded editor.

`make package RELEASE=X.Y.Z` now depends on `build-editor`; it refuses to produce a distributable ZIP unless the embedded bundle exists.

## Build integrity checks

The build system verifies:

- `dist/static/index.html`
- `dist/static/app/`
- `dist/static/files/`
- `dist/static/libs/`
- all required Seleda V1 Content Blocks/editors
- Seleda Studio branding in the generated bundle

The final Moodle ZIP also verifies that files present in `dist/static/` were not accidentally removed during packaging.

The protection chain is:

```text
Timhirt-Studio embedded build verification
        |
        v
Moodle build-seleda-editor.sh verification
        |
        v
Final ZIP inclusion verification
```

## Release workflow

The release workflow:

```text
.github/workflows/release.yml
```

performs this flow:

```text
read .seleda-editor-ref
        |
        v
clone Timhirt-Studio embedded branch/ref
        |
        v
build Dockerfile.embedded
        |
        v
extract /static
        |
        v
write dist/static/
        |
        v
package mod_seledascorm ZIP
```

The final Moodle plugin ZIP therefore contains the actual Seleda embedded editor rather than downloading a stock eXeLearning static release.

## Embedded compatibility workflow

The manual compatibility workflow is:

```text
.github/workflows/check-editor-releases.yml
```

It tests a Seleda Studio embedded ref, not the latest stock eXeLearning release.

It:

- resolves the Seleda ref
- builds the embedded Seleda editor
- verifies the static bundle
- creates a Moodle compatibility ZIP
- uploads the result as a CI artifact

## Editor status in Moodle

The Moodle settings page includes an embedded editor status widget implemented in:

```text
classes/admin/admin_setting_embeddededitor.php
```

For V1 it is deliberately read-only. The old install/update/remove editor service has been removed entirely. If this status shows the editor as missing, the installed plugin ZIP is incomplete and must be replaced with a complete Seleda release package.

## Editor modes

Administrators can configure the editor mode under:

```text
Site administration > Plugins > Activity modules > Seleda > Editor mode
```

### Embedded Seleda

This is the default and recommended V1 mode. The editor is bundled with the plugin.

### Seleda Studio server

The upstream-compatible remote editor protocol is retained for compatibility with server-based Seleda deployments. Existing configuration keys such as `exeonlinebaseuri` remain available where required.

## Moodle compatibility

This fork targets **Moodle 4.5**.

Minimum Moodle version:

```text
2024100700
```

The plugin is developed and validated against Moodle 4.5.

## Installation

Install the plugin in:

```text
{moodle-root}/mod/seledascorm
```

Then complete the Moodle upgrade from the web interface or run:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

During the New settings stage, configure the Seleda display name and optional logo.

## Migrating from an existing mod_exescorm installation

Do not replace an existing `mod_exescorm` directory with this plugin as though it were the same Moodle component. Back up the Moodle database and files first. A dedicated migration path is required to move historical `mod_exescorm` data into `mod_seledascorm` storage.

For a clean Seleda installation:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

Verify a clean Seleda installation at minimum:

1. Moodle identifies the module as `mod_seledascorm`;
2. the installed plugin folder is `mod/seledascorm`;
3. the embedded editor status reports the bundled editor as available;
4. Create with Seleda opens the customized embedded editor without a separate download step;
5. all required custom/new Content Blocks are available;
6. a fresh Seleda SCORM 1.2 export reports score and status;
7. Reports > Interactions shows readable question/response labels when Seleda metadata is present;
8. Edit with Seleda reopens the existing editable source and resaves successfully.

Legacy `mod_exescorm` migration must be validated separately once a dedicated migration procedure is implemented.

## Reporting architecture

The readable reporting implementation does not introduce a second grade store.

It reads Moodle's existing `seledascorm_scoes_track` records and enriches them with readable Seleda interaction metadata where available.

Supported behavior includes:

- human-readable activity/question labels
- learner response
- correct response
- result
- calculated weight where applicable
- attempt-time label snapshots
- safe fallback to original SCORM identifiers

A Content Block must emit meaningful SCORM interaction data. Moodle cannot reconstruct an interaction that the exported package never reported.

## Validation

The main validation workflow is:

```text
.github/workflows/seleda-validation.yml
```

It checks, among other things:

- PHP syntax
- branding JavaScript syntax
- preserved `mod_seledascorm` identity
- Seleda language strings
- installation branding settings
- Create/Edit strings
- reporting integration
- Moodle 4.5 minimum version
- Seleda player alignment

A separate Moodle Playground workflow is used for Moodle 4.5 integration testing.

## Development branches

Current V1 work is isolated from the production branches.

### Moodle plugin

```text
Repository: Robel43/mod_seledascorm
Branch: feature/seleda-alignment
Draft PR: #1
```

### Embedded Seleda Studio

```text
Repository: Robel43/Timhirt-Studio
Branch: feature/seleda-embedded-editor
Draft PR: #6
```

These branches should remain separate until Moodle 4.5, SCORM, create/edit, upgrade and packaging regression checks are complete.

## Technical implementation documentation

The complete living implementation and handover record is maintained in:

```text
docs/SELEDA_MOODLE_PLUGIN_IMPLEMENTATION_GUIDE.md
```

That guide documents:

- architecture
- design decisions
- installation-stage branding
- embedded editor build
- Create/Edit bridge
- Content Block packaging
- reporting
- release process
- validation
- compatibility rules
- implementation history
- pre-merge checklist

### Documentation rule

Every material plugin or embedded-editor change must update the implementation guide with:

1. what changed;
2. why it changed;
3. files/components affected;
4. implementation approach;
5. compatibility impact;
6. validation performed;
7. V1 scope impact;
8. Timhirt-Studio impact.

The implementation guide is intended to become the final technical handover document for the Seleda Moodle plugin.

## Upstream relationship

Current upstream base:

- repository: `exelearning/mod_exescorm`
- upstream component: `mod_exescorm`
- Seleda component: `mod_seledascorm`

Upstream fixes may still be reviewed and ported, but component-specific identifiers must be adapted to `mod_seledascorm`. Seleda-specific changes should remain isolated, documented and reviewable so future upstream synchronization stays manageable.

## Repository

```text
https://github.com/Robel43/mod_seledascorm
```

## License

This fork remains licensed under **GNU GPL v3 or later**, consistent with the upstream project. Upstream copyright and license notices in source files are retained.
