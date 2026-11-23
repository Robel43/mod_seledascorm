# Native quiz reporting: JSON exports

Moodle can now read the saved `data-idevice-json-data` MCQ definition directly.
The ordered definition serialization matches the actual browser runtime exactly,
including Unicode and HTML entities, so revisioned question and choice IDs decode
into text without running authored JavaScript. Old responses are never matched to
a different question revision. Existing packages do not need a new export.

The learner detail report shows each quiz's tracking mode separately from click
logs. Saved Seleda attempt state supplies the actual block score and historical
mode, including a score of zero. When no score was saved, the report says so;
configured Graded mode is not presented as evidence of a submitted score. Ordinary
clicks say they have no assessment result, instead of claiming a quiz is ungraded.
Partial-credit results now count as assessed answers in the accuracy denominator.
Native grade calculation and Moodle gradebook values are not rewritten.

Catalog version 2 backfills previously unsupported metadata on the next commit
without changing saved labels. Exact-revision fallback also works immediately
when viewing existing attempts against the same uploaded package.

Validation runs an unchanged existing MCQ export and the real shared LMS runtime
and upstream weighted aggregator in a browser, then decodes emitted SCORM tracks
through PHP. Coverage includes Unicode, single/multiple choices, partial credit,
75% and 0% scores, retries, missing scores, and completion mode. Existing snapshot,
HTML/CSV safety, API permission and learner/group scope checks remain in place.

Sibling check: the inherited instructions requested a mod_exeweb issue search
and report-directory audit. Both GitHub reads were unavailable, so sibling status
could not be verified. This patch changes the Seleda-specific catalog/dashboard
service only; no sibling or main branch changes are included.
