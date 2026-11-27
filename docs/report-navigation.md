# Reports access roles

Configure this at Site administration → Plugins → Activity modules → Seleda →
Reports navigation → Roles allowed to view Seleda reports.

The single multi-select lists all Moodle roles and custom roles, plus Site
administrators. Saving grants only `mod/seledascorm:viewreport` to selected roles
at system context. Moodle applies that role permission only where a user holds
the role: course assignments cover that course; category/system assignments can
cover broader scopes. Existing course access, module visibility and group checks
continue to run. No other Moodle capability is granted and no role is assigned to
a user by this setting. Only a user with `moodle/role:manage` at system context may
make capability changes. Core capability-assigned/unassigned events audit changes.

The upgrade uses a new `reportaccessroles` setting with safe defaults: site admins
and roles already allowed this capability at system level. It does not convert
the old visibility selector's “all roles” values into grants to students or
Authenticated user. The legacy navigation selection is read only until the new
setting is saved. All roles remain available as choices; deliberately select only
the staff roles intended to report. Selecting Authenticated user would cover all
signed-in users, so it is not newly enabled by default.

Deselecting restores only system-level capability values this setting changed.
A pre-existing Allow is never owned or removed. If a different administrator has
subsequently changed a managed capability away from Allow, deselection preserves
that change. Lower-context overrides remain intact, and a system Prohibit blocks
saving that role rather than being silently overwritten. Existing independent
permissions may therefore still permit direct report URLs after deselection;
the top link follows the selected roles. No roles selected hides the top link.

Prior values are stored in `reportrolegrants` in standard plugin configuration.
Grant/restore/selection writes are transactional; an exclusive Moodle lock
serialises simultaneous saves. The core APIs invalidate role caches, and the
setting's updated callback purges navigation caches. No new tables, services or
privacy-provider changes are required. Package suffix 24 refreshes the settings.

The sibling mod_exeweb has no corresponding Seleda reports component.
