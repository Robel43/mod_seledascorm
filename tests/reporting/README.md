# Standalone reporting regression checks

Run with PHP 8.1+ (mbstring enabled) and Node.js 22+ from the plugin root:

```sh
php tests/reporting/readable-reporting.php
php tests/reporting/block-usage.php
php tests/reporting/overview-reporting.php
php tests/reporting/overview-reporting.php --csv
node --test tests/reporting/*.test.cjs
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
