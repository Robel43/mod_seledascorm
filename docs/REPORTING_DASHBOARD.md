# Standalone Seleda reporting dashboard

## Dashboard usability update

Generic block labels resolve to a heading or paragraph in the current packaged
HTML, with a page/type/identity fallback. Historical named labels are preserved.
Logs use a structured offset/action/label/value/result table. Content clicks are
labelled as recorded, ungraded interactions; only native question results supply
accuracy. Learner details distinguish pages visited, SCORM pages completed and
assessment answers, and explain absent scores.

The compact heading and collapsible activity catalogue reduce scrolling. The
catalogue has a permission-filtered course selector. Visit-date, block-type and
audience filters apply to the overview, matching learner list and CSV. Staff
(administrators or users with the activity's report capability) are excluded by
default and can be included explicitly. Staff records then carry a visible label.
Completion legend links filter learners; block chart links filter that block's
visits and learners. Clear-block links return to the wider view.

Date filters use persisted usage visit-start timestamps in the viewer's Moodle
timezone, with an inclusive end date. Old records without a visit-start timestamp
are excluded from date-filtered results. Active time is assigned to the visit's
start date; it is not a reconstruction of daily activity from overwritten save
timestamps. Native completion/score refer to the full qualifying attempt, and
learner details deliberately show the full selected attempt. No historical dates
or grades are fabricated. Group and module permission checks remain mandatory.

The **Seleda reports** item in Moodle's main navigation opens
`/mod/seledascorm/dashboard.php`. It is independent of a course page and the
activity's existing Reports tab. Administrators and users with
`mod/seledascorm:viewreport` in an activity context can use it. The Moodle 4.5
primary navigation hook adds the link without changing the site's theme.
After installing the updated plugin, run the normal Moodle upgrade and purge
caches so Moodle discovers the new hook registration and AMD module.

The searchable activity catalogue displays Seleda SCORM activity names with their
course names, including activities with no learners yet. It is not a list of
arbitrary uploaded ZIP files or the separate core `mod_scorm` activity type.
Course access, module capability and activity visibility are checked again when
an activity ID is selected directly. Catalogue pages contain up to 30 accessible
activities. A teacher cannot discover names from another course through search.

The selected activity shows:

- People with recorded access, distinct Moodle attempts, total active viewing
  time and content-action count, with block-usage coverage shown explicitly.
- A completion doughnut for each learner's latest attempt against the current
  package's launchable SCORM pages. Earlier completed attempts do not make an
  unfinished latest attempt appear complete. Completion is not a grade.
- Most-used content blocks, switchable between active time and interactions.
- An action-type breakdown for clicks, committed input, drag/drop, hotspot
  reveals and native media actions.
- A searchable, status-filtered learner table, 25 learners per page, with a CSV
  export of all matching learners, not only the displayed page.
- A learner detail view with Moodle attempt selection, the native attempt score,
  per-block usage, action logs and readable question/answer records, all within
  the standalone dashboard.

All time/actions in the overview include every recorded attempt. Detailed views
show only the selected attempt. A missing raw score stays unavailable rather
than displaying an invented zero. A zero actually reported by SCORM is retained.
Legacy packages can still show learners/completion but missing block analytics
are labelled **No usage data**. Untracked learners are not fabricated from course
enrolment. The dashboard cannot recover access or actions the package never saved.

Separate-groups restrictions apply before computing charts, totals, learner rows,
CSV or details. Teachers without an allowed group get no learner data. Forged
group IDs are rejected. Visible-groups mode and the access-all-groups capability
follow Moodle's existing semantics. Suspended/deleted-user treatment follows the
underlying recorded attempts, excluding deleted users. No analytics are copied
into a new table, browser storage or an external service.

The activity overview streams the permitted activity's records and discards
individual event details during aggregation. It retains learner/block summaries;
only an explicitly opened learner attempt loads its full logs. This avoids
rendering every learner's event history on the landing page. Charts have textual
equivalents, controls support keyboards and the page works without JavaScript;
only chart measure switching requires the small AMD enhancement. Local assets
are used, with no chart CDN or external analytics dependency.

Tests include standalone aggregation/access fixtures, actual rendered PHP/AMD
browser checks at desktop and mobile sizes, and a Moodle 4.5 + MariaDB integration
job for the real capability helper, module lookup and separate-group SQL scope.
The plugin data generator is aligned to its `mod_seledascorm` identity to allow
those native tests. Test data and screenshots are synthetic, not live learner data.
