# Direct activity entry

Course activity links now open the learner player directly, in normal mode,
without the Preview/Enter landing form. This applies to existing activities and
all roles, independent of the older skipview setting or reporting capability.
Login/course-module restrictions still run before the redirect. The player keeps
its existing availability, hidden-activity, attempt-limit and sequencing checks.

Launch selection uses the native table of contents: unfinished content is resumed,
forced-new-attempt policies retain their original semantics, and opening an
activity does not itself create/reset an attempt or change scores. Legacy popup
activities open actual content in the current tab without requiring an automatic
popup. Unpublished .elpx projects and empty packages retain their existing page.
Explicit `preventskip=1` overview requests and response-management actions also
keep their normal handling. Exit returns to site home for single-activity courses
to avoid an immediate relaunch loop under the new default.

Regression checks execute the actual view.php against a fixture Moodle boundary
and verify login precedes launch, normal-mode redirects, resume, force policies,
popup handling, management actions and unpublished projects. Moodle/MariaDB tests
also exercise the resolver against a real generated package as student, teacher
and admin, including persisted incomplete/completed attempts.

The inherited sibling-plugin audit could not access mod_exeweb in this session.
No main or sibling branch changes are included.
