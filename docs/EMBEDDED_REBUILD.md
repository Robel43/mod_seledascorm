# Embedded editor white-screen rebuild

The plugin's `feature/seleda-alignment` branch pins `.seleda-editor-ref` to editor
commit `4b6ada9455216fa719ab3cb3ef738643fa0de4ca` from
`feature/seleda-embedded-editor`. This integrates the latest Studio Develop changes
without changing either repository's main branch.

The static entry page references the Seleda shell in `timhirt-studio/`, which the
upstream static builder does not copy automatically. Dockerfile.embedded copies
that directory explicitly after building the static bundle. The refreshed profile
also includes native Test/Complete metadata, all-block usage and the current
preview lifecycle, matching the reporting endpoint on this plugin branch.

`scripts/verify-embedded-assets.py` checks the editor's local script/stylesheet/icon
dependencies, the required Seleda UI modules and the current reporting runtime.
The build runs it against both the extracted static directory and the final
Moodle ZIP. Regression tests deliberately omit an asset to verify failure.

Before uploading the complete plugin artifact, CI runs the actual static editor
in Chromium, rejecting missing resources, uncaught errors and a stuck loading
screen. This startup check supplements live Moodle testing; it does not replace
testing Save to Moodle, permissions, existing project loading and learner reports
on the target Moodle installation.

Install the inner `mod_seledascorm-pr-*.zip` from the successful workflow artifact
as `mod/seledascorm`, then purge Moodle caches. Source-only ZIPs lack the embedded
editor and must not be used as release packages.
