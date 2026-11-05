# Seleda Moodle Plugin Implementation Guide

## 1. Purpose

This document is the living technical record for the Seleda Moodle 4.5 activity plugin. It documents what was changed, why it was changed, how the embedded Seleda Studio editor is built and packaged, how Moodle creates and edits Seleda activities, how branding works during installation, how the customized Content Blocks are included, and how releases are validated.

The plugin is maintained in:

- Repository: `Robel43/mod_seledascorm`
- Technical Moodle component: `mod_seledascorm`
- Installation directory: `mod/seledascorm`
- V1 development branch: `feature/seleda-alignment`

The embedded authoring application is built from:

- Repository: `Robel43/Timhirt-Studio`
- Embedded branch: `feature/seleda-embedded-editor`
- Embedded build definition: `Dockerfile.embedded`

This guide must be updated whenever a material architectural, build, runtime, reporting, branding, or Content Block change is made.

---

## 2. Compatibility contract

Seleda V1 deliberately uses its own Moodle technical identity rather than retaining the upstream eXeSCORM component name.

Preserved identifiers include:

- component: `mod_seledascorm`
- install folder: `mod/seledascorm`
- database tables: `seledascorm_*`
- capabilities: `mod/seledascorm:*`
- SCORM API identifiers
- backup and restore identifiers
- completion and grade integration identifiers

This is a deliberate architecture break from upstream `mod_exescorm`. Historical `mod_exescorm` data is not automatically migrated; a separate migration procedure is required for existing sites.

The activity chooser name is `Seleda`, backed by component `mod_seledascorm`. Site-specific branding is applied to plugin-owned screens and actions.

---

## 3. V1 scope

### Included

The embedded Seleda V1 contains the authoring and learner/export functionality needed inside Moodle:

- Seleda branding and terminology
- authoring shell
- Block Library
- Project Settings
- Content Block Manager authoring UI
- Page / Lesson Structure panel
- section editing controls
- Themes interface
- accessibility customizations
- learner page UI
- learner block reveal
- Seleda LMS runtime
- readable SCORM reporting metadata
- export branding
- rebuilt Website / SCORM resource bundles

### New Seleda Content Blocks

The embedded build explicitly includes:

- MCQ Feedback: `mcq-feedback`
- Speaking Practice: `speaking-practice`
- Page Navigation: `page-navigation`

### Modified existing editors / Content Blocks

The embedded build also applies Seleda changes to:

- Drag & Drop: `dragdrop`
- Before / After: `beforeafter`
- Map / hotspot: `map`
- Quick Test: `quick-questions`
- Complete: `complete`

Both the Seleda Studio CI build and the Moodle plugin build verify that these eight directories exist in the final static bundle. A build fails if one is missing.

### Excluded from V1

The following are intentionally not part of the Moodle embedded profile:

- Question Bank
- Seleda account services
- standalone login/logout services
- standalone Administration pages
- standalone Appearance & Branding administration
- persistent server-side Content Block policy services
- Studio Services/backend routes
- standalone database-backed user services

Question Bank is reserved for V2.

---

## 4. Embedded editor source model

The Moodle plugin does not build a generic stock eXeLearning editor.

The source model is:

```text
eXeLearning 4.0.3
        ↓
Timhirt-Studio embedded profile
        ↓
Seleda customizations applied by Dockerfile.embedded
        ↓
static Seleda bundle under /static
        ↓
mod_seledascorm dist/static/
        ↓
Moodle plugin release ZIP
```

The upstream base is pinned in `Timhirt-Studio/Dockerfile.embedded` using:

```text
ARG UPSTREAM_REF=v4.0.3
```

This gives Seleda a known upstream foundation while keeping the customization layer reproducible.

---

## 5. Why there is a separate embedded profile

The standalone Seleda Studio Docker deployment and the Moodle embedded editor have different responsibilities.

The standalone Studio can contain server-oriented services. Moodle V1 only needs the authoring workspace and the resources required to produce learner packages.

For this reason:

- the normal standalone Docker profile remains unchanged;
- `Dockerfile.embedded` is dedicated to the Moodle-embedded build;
- the embedded output is serverless static content;
- Moodle remains responsible for authentication, authorization, course ownership, storage, and activity lifecycle.

This separation prevents Moodle packaging from accidentally pulling in standalone administration or account services.

---

## 6. Moodle editor build path

The canonical Moodle-side builder is:

```text
scripts/build-seleda-editor.sh
```

The script:

1. resolves the Seleda Studio repository;
2. resolves the embedded editor ref;
3. shallow-fetches that ref;
4. checks that `Dockerfile.embedded` exists;
5. builds the embedded Docker image;
6. extracts `/static`;
7. replaces `dist/static/`;
8. verifies the core static layout;
9. verifies all required Seleda V1 Content Blocks/editors;
10. verifies that Seleda branding survived the build.

The default repository is:

```text
https://github.com/Robel43/Timhirt-Studio.git
```

The default ref is controlled by:

```text
.seleda-editor-ref
```

For V1 it points to:

```text
feature/seleda-embedded-editor
```

The ref can be overridden for controlled testing through `SELEDA_STUDIO_REF`.

---

## 7. Makefile and mandatory editor packaging

The previous Makefile contained a generic upstream eXeLearning build path. That path was removed from the active editor build flow because it could generate a stock editor that did not contain Seleda customizations.

The supported command is now:

```bash
make build-editor
```

which delegates to:

```bash
sh scripts/build-seleda-editor.sh
```

`build-editor-no-update` is retained only as a compatibility alias and resolves to the same Seleda build.

The legacy `fetch-editor-source` target no longer downloads stock eXeLearning. It explains that source selection is controlled by the Seleda builder and `.seleda-editor-ref`.

This creates one authoritative editor build path. `make package RELEASE=X.Y.Z` depends on `build-editor` and must not produce a distributable ZIP unless the bundled editor exists under `dist/static/`.

---

## 8. Installation-stage branding form

The installation branding step is implemented using Moodle's supported plugin settings mechanism rather than a separate unsupported installer page.

On first installation, or when the settings are first introduced during upgrade, Moodle displays its standard **New settings** stage. The Seleda branding fields are intentionally placed first in `settings.php`.

### Fields

**Display name**

- setting: `mod_seledascorm/brandname`
- required
- default: `Seleda`
- type: text

**Logo**

- setting: `mod_seledascorm/brandlogo`
- optional
- maximum files: 1
- accepted types:
  - PNG
  - JPG/JPEG
  - WebP
  - SVG

If no logo is uploaded, the bundled Seleda logo is used.

### Runtime use

The configured display name is used for plugin-owned authoring UI, including:

```text
Create with <Display name>
Edit with <Display name>
embedded product/browser title
```

The configured logo is served through Moodle file storage and the plugin file callback and is injected into the embedded editor at runtime.

It is used on Seleda-owned product surfaces such as:

- editor loading screen
- About/product logo
- error/product-logo surfaces

Changing a site's branding therefore does not require rebuilding the static Seleda editor.

### Activity chooser limitation

Moodle's activity-type name and icon are cached as part of the plugin/module system. V1 therefore keeps the activity chooser identity statically as Seleda and applies configurable branding only to plugin-owned surfaces.

---

## 9. Embedded editor status setting

The Moodle settings page also includes an embedded editor status widget implemented by:

```text
classes/admin/admin_setting_embeddededitor.php
```

For V1 this widget is intentionally read-only.

Independent install/update/remove actions are disabled because the Seleda editor is shipped with the plugin. This prevents an administrator from replacing the customized Seleda build with an arbitrary upstream editor.

---

## 10. Create with Seleda flow

The embedded editor is the default authoring path.

Conceptually:

```text
Teacher adds Seleda activity
        ↓
Moodle creates activity/editable context
        ↓
Create with Seleda
        ↓
embedded Seleda Studio opens
        ↓
teacher authors
        ↓
REQUEST_EXPORT
        ↓
Moodle stores exported package and editable source
```

The same static Seleda bundle is used for creation and editing.

---

## 11. Edit with Seleda flow

Existing activities do not use a separate editor architecture.

The edit cycle is:

```text
Existing Seleda activity
        ↓
Edit with Seleda
        ↓
Moodle sends saved editable package
        ↓
OPEN_FILE
        ↓
Seleda reopens the existing project
        ↓
teacher edits
        ↓
REQUEST_EXPORT
        ↓
updated package saved back to Moodle
```

The next edit therefore reopens the updated source rather than starting a blank project.

This continuous create → save → reopen → edit → resave lifecycle is a core V1 requirement.

---

## 12. Runtime branding injection

Site-specific Moodle branding is injected into the static editor at runtime rather than compiled into every build.

Relevant Moodle-side files include:

- `lib.php`
- `editor/index.php`
- `editor/branding.js`
- `settings.php`

The editor receives a Moodle-provided branding object and updates plugin-owned product surfaces.

This approach keeps one reproducible embedded editor artifact while still allowing each Moodle installation to use its chosen name and logo.

---

## 13. Content Block packaging controls

A central requirement is that a successful build must not silently omit a customized Content Block.

### Seleda Studio CI

`Timhirt-Studio/.github/workflows/build-embedded-editor.yml` verifies the built static package contains all required V1 editor directories.

### Moodle build script

`mod_seledascorm/scripts/build-seleda-editor.sh` performs the same verification after extracting the Docker artifact into `dist/static/`.

### Package integrity

The Moodle `Makefile package` target checks that files present under `dist/static/` are not accidentally dropped by `.distignore`.

This provides three layers of protection:

```text
Seleda source build verification
        ↓
Moodle embedded extraction verification
        ↓
final ZIP inclusion verification
```

---

## 14. Release workflow

The canonical release workflow is:

```text
.github/workflows/release.yml
```

For a release it:

1. checks out the Moodle plugin;
2. resolves the release version;
3. reads the Seleda editor ref from `.seleda-editor-ref`;
4. runs `scripts/build-seleda-editor.sh`;
5. creates the Moodle package;
6. uploads the ZIP as a workflow artifact;
7. for GitHub releases, attaches the ZIP to the release.

The final Moodle ZIP therefore contains the actual Seleda embedded editor under:

```text
dist/static/
```

It does not download a stock eXeLearning release during packaging.

---

## 15. Compatibility test workflow

The manual embedded editor compatibility workflow is:

```text
.github/workflows/check-editor-releases.yml
```

It was changed from an upstream-stock-eXeLearning checker into a Seleda embedded-profile checker.

It can test a supplied Seleda ref or default to `.seleda-editor-ref`, then:

- builds the Seleda embedded editor;
- verifies the static bundle;
- packages a Moodle compatibility ZIP;
- uploads the result as a CI artifact.

This ensures compatibility testing exercises the product that Moodle will actually ship.

---

## 16. Moodle validation

The plugin validation workflow is:

```text
.github/workflows/seleda-validation.yml
```

It checks, among other things:

- PHP syntax on modified files
- JavaScript syntax for branding logic
- preserved `mod_seledascorm` identity
- Seleda activity terminology
- branding settings
- Create/Edit strings
- reporting integration
- Moodle 4.5 minimum version
- Seleda player alignment

A separate Playground workflow exercises the Moodle plugin in a Moodle 4.5 environment.

Both should pass before the feature branch is merged.

---

## 17. Reporting architecture

Seleda readable reporting does not create a second grade or attempt database.

The plugin continues to use Moodle's existing SCORM tracking storage and adds readable interpretation where metadata is available.

Key behavior includes:

- readable interaction labels
- learner response
- correct response
- result
- calculated weight where applicable
- attempt-time label snapshots
- safe fallback to original SCORM identifiers

The Content Block is responsible for emitting meaningful interaction data. Moodle cannot reconstruct an interaction that the exported package never reported.

---

## 18. Styles and embedded editor configuration

The plugin retains the required embedded editor and style-management settings needed by the upstream-compatible architecture.

V1 defaults to the bundled embedded editor.

The read-only editor status approach is intentional: the release artifact is the approved editor source. The legacy editor installer, AJAX management service, and install/update/uninstall controls have been removed from V1. A missing bundled editor is an invalid/incomplete plugin package.

---

## 19. Source-control structure

Current V1 work is deliberately isolated from production branches.

### Seleda Studio

```text
Robel43/Timhirt-Studio
feature/seleda-embedded-editor
Draft PR #6
```

### Moodle plugin

```text
Robel43/mod_seledascorm
feature/seleda-alignment
Draft PR #1
```

The branches should remain separate until Moodle 4.5, editor packaging, create/edit, SCORM tracking, and upgrade regression checks are complete.

---

## 20. Change documentation rule

From this point forward, every material change to this plugin should be recorded in this document.

For each significant change, record:

1. what changed;
2. why it changed;
3. files/components affected;
4. implementation approach;
5. compatibility impact;
6. validation performed;
7. whether it changes V1 scope;
8. whether it requires changes in Timhirt-Studio.

This guide is intended to become the final technical handover document for the Seleda Moodle plugin.

---

## 21. Current implementation log

### 2026-11-04 — Embedded Seleda profile

Created a dedicated Seleda embedded editor profile in Timhirt-Studio using `Dockerfile.embedded`. It builds from pinned eXeLearning 4.0.3, applies Seleda authoring/runtime customizations, and produces a serverless static bundle.

### 2026-11-04 — Moodle create/edit alignment

Aligned the Moodle plugin so the same embedded Seleda bundle supports both creation and later editing through the existing `OPEN_FILE` / `REQUEST_EXPORT` bridge.

### 2026-11-04 — Installation branding

Added installation-stage branding settings using Moodle's standard New settings flow: required Display name and optional Logo. Branding is injected into the embedded editor dynamically.

### 2026-11-04 — Release packaging

Changed the release process so the plugin ZIP builds and packages the Seleda embedded editor under `dist/static/` rather than downloading a stock eXeLearning static bundle.

### 2026-11-04 — Compatibility workflow alignment

Changed the manual compatibility workflow so it tests a Seleda Studio embedded ref instead of building the latest stock eXeLearning release.

### 2026-11-04 — Makefile hardening

Removed the active generic upstream editor build path. `make build-editor` now always delegates to `scripts/build-seleda-editor.sh`. Legacy aliases cannot independently build stock eXeLearning.

### 2026-11-04 — Deliberate component rename

Changed the Moodle technical identity from `mod_exescorm` to `mod_seledascorm`, including install folder, callbacks, namespaces, capabilities, database/storage identifiers, report subplugin component names, language component names, backup/restore class names, editor URLs, build/package names, and validation rules. This is intentionally not an automatic in-place upgrade of an existing `mod_exescorm` installation.

### 2026-11-04 — Bundled editor enforcement

Changed packaging so a distributable `mod_seledascorm` ZIP builds the embedded Seleda editor first and fails if `dist/static/index.html`, `app/`, `files/`, or `libs/` are absent. Added optional `SELEDA_STUDIO_TOKEN` support for authenticated reads from the private Timhirt-Studio repository.

### 2026-11-04 — Content Block completeness checks

Added explicit build-time verification for all V1 Seleda Content Blocks and modified editors:

- mcq-feedback
- speaking-practice
- page-navigation
- dragdrop
- beforeafter
- map
- quick-questions
- complete

The Seleda Studio build and Moodle extraction build both fail if a required editor is absent.

---

### 2026-11-05 — Bundled-only editor enforcement

Removed the legacy Moodledata editor installation path and all runtime download/update/uninstall services. V1 now resolves only `mod/seledascorm/dist/static`. The resolver requires `index.html`, `app/`, `libs/`, and `files/` to be present before the editor is considered valid.

Removed:
- `classes/local/embedded_editor_installer.php`
- `classes/external/manage_embedded_editor.php`
- `amd/src/admin_embedded_editor.js`
- built admin embedded-editor AMD action module
- embedded editor install/update/uninstall web-service definitions
- installer/action tests

The Moodle settings card is now status-only.

### 2026-11-05 — Release credential requirement

The embedded source repository `Robel43/Timhirt-Studio` is private. `mod_seledascorm` therefore requires a repository secret named `SELEDA_STUDIO_TOKEN` for automated package/release builds. The token requires read-only Contents access to Timhirt-Studio.

PR source validation remains runnable without the secret and emits a warning while skipping the package sub-job. The release workflow fails early when the secret is absent because publishing a Moodle ZIP without the embedded editor is prohibited.

This credential is repository configuration, not application source code, and must be configured by a repository administrator in GitHub.

### 2026-11-05 — Embedded artifact verification

Verified the successful Timhirt-Studio CI artifact from `feature/seleda-embedded-editor`. The artifact contains:
- `index.html`
- `app/`
- `files/`
- `libs/`
- `mcq-feedback`
- `speaking-practice`
- `page-navigation`
- modified `dragdrop`
- modified `beforeafter`
- modified `map`
- modified `quick-questions`
- modified `complete`
- Seleda Studio branding

The Moodle PR validation workflow now includes a package job that builds `mod_seledascorm` with this embedded editor and verifies those core paths inside the final ZIP.

---

### 2026-11-05 — Player layout and Edit with Seleda regression fix

Moodle acceptance testing exposed two rename regressions:

1. `styles.css` still used legacy `mod_exescorm` page IDs/classes, pix component names, and `exescorm-editor-*` modal classes. After the component rename, Moodle rendered `mod_seledascorm` page IDs, so most player CSS no longer matched. The result was a narrow/collapsed player layout.
2. The embedded editor control depended entirely on the AMD modal handler. Without the corresponding renamed modal CSS, a click could appear to do nothing.

Fixes:
- converted all plugin stylesheet selectors and pix references to `mod_seledascorm` / `seledascorm`;
- aligned editor overlay, header, iframe, loading modal and animation class names;
- changed **Edit with Seleda** from a JavaScript-only button to a real link to `editor/index.php` while retaining modal interception;
- added CI regression checks for the Seleda player page selector, editor overlay class, pix component reference, and direct editor fallback URL;
- advanced the package version suffix so Moodle treats the corrected ZIP as an upgrade and refreshes cached plugin assets.

---

### 2026-11-05 — Second player layout fix

A second Moodle acceptance test showed that renaming the legacy selectors was insufficient. The player still depended on the old YUI 20% / 4% / 75% grid classes for its main geometry, and the rendered SCO remained confined to a narrow column.

The player now uses an explicit flex layout:
- TOC: bounded 20% column;
- toggle: fixed 34px column;
- content: flexible remaining width;
- SCO iframe/object: forced to 100% of content width;
- mobile breakpoint: stacked 100% layout.

The **Edit with Seleda** link no longer depends on the AMD modal at all. It navigates directly to `/mod/seledascorm/editor/index.php`. This removes the failure mode where the click is intercepted and then appears to do nothing.

Package version suffix advanced again so Moodle refreshes the corrected CSS/JS.

---

### 2026-11-05 — Embedded static shell aligned to current Develop

Moodle testing showed that the embedded editor had the correct Seleda customization files but still rendered the older static eXeLearning shell.

Root cause:
- standalone Timhirt-Studio `Develop` injects the latest visual identity through `views/workarea/workarea.njk`;
- the Moodle embedded profile is built from `scripts/static-bundle/static-index.html`;
- the custom CSS/JS files were copied into the static bundle but that separate static entry template did not load them.

Correction:
- the embedded profile now patches the static entry template before the static bundle is generated;
- it explicitly loads the current Develop visual-identity assets for the authoring shell, Block Library drawer, Project Settings, Content Block Manager and Page Structure panel;
- the embedded loading screen is intentionally blank, with no R43 artwork and no eXeLearning technical/version line;
- CI and the Moodle packaging script verify these exact conditions.

The visual-identity files used by the embedded branch were checked against current `Develop` and are byte-for-byte identical.

---

### 2026-11-05 — Direct static editor asset delivery

Editor startup profiling showed that all embedded application assets were being served through `editor/static.php/{cmid}/...`. That endpoint bootstraps Moodle and performs module/course/authentication checks for every asset request. It also embeds the course-module ID in the asset URL, reducing cache reuse between Seleda activities.

The editor bootstrap now points its asset base directly to:

```text
/mod/seledascorm/dist/static/
```

These files are application assets only; they do not contain learner, course or authentication data.

Protected Moodle operations remain unchanged:
- authenticated editor bootstrap;
- editable package loading;
- OPEN_FILE;
- REQUEST_EXPORT;
- Save to Moodle;
- package persistence.

The legacy `editor/static.php` endpoint is retained only for compatibility/fallback and is no longer the normal asset path.

Expected effect:
- fewer PHP requests during editor startup;
- no repeated Moodle bootstrap per JS/CSS/font/image request;
- shared browser cache across activities;
- faster repeat opens, especially on slower school networks and lower-powered devices.

---

## 22. Pre-merge checklist

Before V1 merge/release, verify at minimum:

- Seleda Studio embedded CI passes
- Moodle Seleda Validation passes
- Moodle 4.5 Playground passes
- installation New settings screen shows Display name first
- installation New settings screen shows optional Logo
- bundled logo fallback works
- configured name appears in Create/Edit actions
- configured logo reaches embedded editor surfaces
- Create with Seleda opens the customized editor
- all eight required custom/new Content Blocks are visible and functional
- saved activity exports successfully
- Edit with Seleda reopens the existing editable project
- resave replaces the Moodle package correctly
- SCORM 1.2 tracking remains functional
- readable interaction reporting remains functional
- clean `mod_seledascorm` installation succeeds; migration from legacy `mod_exescorm` is tested separately if required
- backup/restore remains functional
- final release ZIP contains complete `dist/static/`
- Question Bank and standalone administration services are absent from embedded V1

