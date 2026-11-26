# Seleda reporting dashboard

Implemented phases 1–5 of the corrected redesign brief. The optional drawer and
external service are not included. Full-page links and all filters work without
JavaScript. No tables, external dependencies, browser storage, permission changes
or privacy-provider changes were introduced. Source `version.php` is unchanged;
the existing packaging date suffix advances to 17 so a rebuilt ZIP can upgrade the
previous suffix-16 ZIP and refresh Moodle's AMD cache.

## Screens and scope

Activity Overview answers who needs attention; Content covers pages/questions/
blocks; learner detail starts with a summary and every learning page before
Answers, Attempts and Technical details. Insights aggregates at most ten permitted
activities per page. Each activity rechecks course access, visibility, reporting
capability and group restrictions. The activity picker is capped at 200. The home
screen computes access counts for at most six quick picks using those same checks.

The obsolete `staff` parameter is accepted but ignored. All roles are included
unless `role=learner|staff` is selected; the latter includes admins. Role filtering
changes KPIs, charts and the learner table. Status and name search change only the
table and CSV. Date/block/type filters retain the existing recorded-attempt scope
and hide roster-only people. A date qualifies a visit by its immutable saved start;
completion and scores describe the complete qualifying attempt. Learner detail
shows the selected complete attempt, matching the existing behaviour.

Legacy `state=failed` links filter Result Failed. `diagnostics=1` selects Technical
details. Other old parameters remain accepted. Unknown enum values are rejected.

## Metric dictionary: source and formula

All track sources below are `seledascorm_scoes_track` rows scoped by activity and
existing group SQL. Learning pages are launched `sco` rows; assets are excluded.

- Scope people: distinct tracked users plus active, non-suspended enrolled users
  with `savetrack` and no tracks; staff are not removed.
- Access people: distinct user IDs with scoped track rows (deleted users excluded).
- Role: site admin, otherwise module `viewreport` holder, otherwise learner; cached
  once per user/context during aggregation and reused for the roster.
- Completion: people whose latest Moodle attempt completes all learning pages / scope N.
- Not started: scoped enrolled users with no track rows / scope N.
- Needs attention: Result Failed, or In progress/Unknown with last saved activity
  older than 7 days. The clock is injectable in the aggregator.
- Overall progress: completed pages / learning pages in the selected/latest attempt;
  Unknown progress is null, roster-only progress is a real 0 / total.
- Page state: completion/passed wins; failure next; status/reach next; absent page
  without usage coverage is unavailable; otherwise unvisited is Not started.
- Result: any saved failure yields Failed; otherwise any passed status yields Passed;
  otherwise no result. This is separate from completion and never writes grades.
- Active time: sum of Seleda block usage `ms`, excluding native SCORM timing fields.
  A visited block with actual zero time remains zero; no timing coverage is null.
- Page time: sum of block `ms` on that page/attempt. All-attempts time sums known
  page times across attempts and is shown only when there are multiple attempts.
- Median active time: median known per-person cumulative active times, with n.
- Page median: median known per-person page times from latest attempts, with n.
- Page visits: distinct final 24-hex session IDs per page/attempt among usage rows
  with `visits > 0`; assessment-only sessions are ignored. Never sum block visits.
- Latest activity: maximum track save timestamp excluding question-label snapshots.
  It is a save timestamp, not a guaranteed exact exit or completion timestamp.
- Assessment score: native raw page scores combined by methods 1=max, 2=mean, 3=sum;
  displayed against configured maxgrade. Method 0 remains Completion-based.
- Average score: mean of latest-attempt assessment score/maxgrade percentages, with n.
- Page/block score: saved page raw score / saved graded block score; null stays missing.
  Method 0 raw assessment values are withheld in reporting, including score bins/retries.
- Best score: maximum known assessment score across Moodle attempts.
- Retry outcome: mean of each paired person's latest minus first scored-attempt
  percentages; paired n is separate from participant count.
- Extra attempt time: sum of known timed attempts after the first timed attempt;
  omitted unless at least two attempts have time data.
- Question accuracy: first retained assessed submission per person/question revision;
  full credit counts correct, partial credit is answered but not fully correct.
- Answered: nonblank first submissions / assessed first submissions; ungraded writing
  never contributes to accuracy. Latest answers retain earlier recorded submissions.
- Common wrong answers: readable response frequencies among non-full-credit submissions.
- Page progress chart: completed, reached-but-not-completed and scope N minus reached,
  in package order; unavailable coverage stays explicit in the Content page table.
- Most-used blocks: recorded time or action totals, with distinct contributing people.
- Unique people: union of scoped user IDs across the activities displayed on Insights.
- Participations: sum of scope N across those activities; a person can count in each activity.
  The participation bars specifically show people with recorded access.
- Score bins: known latest assessment percentages in <50, 50–59, 60–69, 70–79,
  80–89 and 90–100. Counts across activities are scored participations, not unique people.
- Tracking coverage: people with known time / assessment score / readable answers,
  each divided by people with access. Time/answers can be from any qualifying attempt;
  score coverage follows latest-attempt scoring.

Teacher averages/medians always show n and a Small sample tag below five. Question
accuracy and Insights statistical values are suppressed below five; counts remain
visible to explain scope. Completion-based activities never enter assessment-score
averages, bins or retry changes; Moodle's Learning objects grade is not recomputed.
CSV uses the same missing/suppression rules and `timhirt_reporting::exportcell`.

## Runtime verification

Read-only audit of the user's exact `mod_seledascorm-pr-f4818c4.zip`: extracted
`seledascorm/dist/static/bundles/libs.zip` → `common.js` into a temporary workspace
folder, without modifying `dist/` or editor sources. SHA-256 of common.js:
`33d624aa5b002579138eb45a4ccdf7171778018a28394cff6568da17ada918e3`.

- Lines 4165–4168: singleton guard and one `crypto.getRandomValues(new Uint8Array(12))`
  session per document load. The same session is sent for all blocks on that page.
- Lines 4187–4196: `tick()` increments only `current.ms`; `current` is one block.
  Multiple visible blocks are not timed concurrently within one page.
- Line 4198: visits flips to 1 for the active block. Server page visits therefore
  deduplicate session IDs; a second block in the same session adds no page visit.
- Custom assessment-only sessions have zero visits/time and are excluded from visits.

## Performance verification

`php tests/reporting/reporting-performance.php` runs five repeats on PHP 8.3,
50 people × 5 pages × 10 questions per activity. Latest local medians:

| Case | Median |
|---|---:|
| One Overview, questions off | 28.01 ms |
| One Overview, questions on | 55.27 ms |
| Ten activities, questions on + cross-activity models | 537.34 ms |

The roughly 27 ms fixture increment justifies Overview questions being enabled.
These are in-memory CPU measurements, excluding DB, managed-file reads and network.
Real site latency remains dependent on database size and package content. Tracks
are streamed one learner/attempt batch at a time; compact per-person/attempt/page
aggregates are retained. Sorting is in-memory over those aggregates with 25 visible
rows. Large enrolments or many attempts need production profiling; no pagination
claim implies bounded total aggregation memory. Parsed package metadata is cached
by content hash per request, and overview events are never queried separately per learner.

## Unsupported or incomplete historical measures

No reliable live-online status, exact exit/completion timestamps, reason for stopping,
external-tool internals or video-watched percentage can be derived. Native SCORM time
is not added to active time. Concurrent devices/tabs cannot be globally deduplicated
from duration-only historical counters. Event retention is capped and custom answers
are limited to 1000 characters; complete earlier submission history and exact first
submissions cannot be recovered after eviction. Old storage has no reliable truncation
flag, so the UI explains possible shortening rather than falsely marking every
1000-character answer as definitely shortened. Explicit shortening metadata is rendered
when present. Missing readable question text keeps the raw answer with a clear label.

## Verification commands

See `tests/reporting/README.md`. CI runs standalone PHP and Playwright plus native
Moodle 4.5/MariaDB `vendor/bin/phpunit mod/seledascorm/tests/local/dashboard_test.php`.
The native suite covers report permissions, groups, roles, real zeroes and the report
view audit event. No optional drawer, web service, DB schema or privacy change is made.
