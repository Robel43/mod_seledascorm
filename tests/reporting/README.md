# Standalone reporting regression checks

Run with PHP 8.1+ (mbstring enabled) and Node.js 22+ from the plugin root:

```sh
php tests/reporting/readable-reporting.php
php tests/reporting/block-usage.php
php tests/reporting/dashboard.php
php tests/reporting/learning-insights.php
php tests/reporting/report-navigation.php
php tests/reporting/overview-reporting.php
php tests/reporting/overview-reporting.php --csv
node --test tests/reporting/*.test.cjs
php tests/reporting/reporting-performance.php
```

These execute the plugin's actual PHP report renderer, persistence helper, HTTP
endpoint and JavaScript SCORM API with small Moodle boundary fixtures. They cover
authentication, sesskey, capability and SCO scope, retries and late snapshots,
learner/attempt isolation, multi-SCO aggregation, historical labels, Unicode,
CSV/HTML safety, report pagination and group scope. `vectors.json` contains the
Studio schema-1 response contract vectors, including native Test and Complete.
Set `TIMHIRT_PHP` and JSON-array `TIMHIRT_PHP_OPTIONS` for a non-default PHP runtime.

These checks complement Moodle's native PHPUnit/privacy tests; they do not replace
an end-to-end run in the site's Moodle version and database. Verify a fresh Studio
SCORM 1.2 export with two learners, multiple blocks/pages and separate attempts;
check both the Interactions overview and the individual attempt report.

## Redesign checks

`overview-reporting.php` also executes `redesign-data-cases.php`: roles, separate
completion/result fields, missing values, visits, completion-based grading,
score bins, sample suppression, attempt comparisons and safe CSV columns.
`redesign-ui.test.cjs` exercises summary-before-pages-before-tabs, unvisited
pages, keyboard expanders, all Content/Insights panels, and JavaScript-disabled
filters and layout. `presentation-cache.test.cjs` tests actual HTTP stylesheet
caching with a previously cached old stylesheet.

The performance command reports five-run medians over an in-memory iterable:
50 people × 5 pages × 10 questions per activity. It deliberately excludes database,
network and managed-file I/O. The language fixture now caches strings once, as
Moodle does, rather than repeatedly reading the language file inside the benchmark.

For native Moodle 4.5/MariaDB PHPUnit:

```sh
vendor/bin/phpunit mod/seledascorm/tests/local/dashboard_test.php
```

The existing `Seleda reporting checks` workflow runs the native integration test.

## Phase 6 and course catalogue

`drawer-ui.test.cjs` tests the real compiled drawer against the PHP-rendered
fragment: tabs, attempt switching, keyboard containment, Escape/focus return,
error/retry/full-page fallback, late responses, no-JS course/activity links,
missing-progress coverage and small viewports. Native dashboard_test also tests
the actual external function with separate groups, invalid learner/attempt/filter
selections, denied capability, audit event and admin access.

## Interactive charts

`charts-ui.test.cjs` covers a forty-page Overview with six initially visible rows,
keyboard and no-JavaScript expansion, searching hidden pages, sorting, empty
search results, native doughnut links, bar hover/click/keyboard selection, no
duplicate bottom lists and readable labels/no overflow at 320/375/768/1024px.

## Reports navigation roles

`report-navigation.php` executes the actual role-visibility helper and navigation
hook with bounded role-assignment fixtures. Native dashboard_test additionally
checks real Moodle role choices (including a newly created custom role), core
multi-select saving/defaults/empty values, assignments across courses, implicit
default roles, administrators, guests, the primary navigation hook, and unchanged
reporting capability checks. The Seleda reporting workflow runs both checks.
