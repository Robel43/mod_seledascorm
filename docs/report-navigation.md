# Reports navigation roles

Configure this at Site administration → Plugins → Activity modules → Seleda →
Reports navigation → Roles shown the Seleda reports link.

The standard Moodle multi-select lists every current role, including custom roles,
using Moodle's display names. Site administrators are an additional explicit
choice because administrator status is not a normal Moodle role assignment.
Select one or several entries; clearing all entries hides the top navigation link.
All entries are selected by default, preserving existing behaviour. An installation
that has not saved the setting yet also retains its existing visibility.

A user must both match a selected entry and pass reporting_dashboard::can_open().
This is navigation visibility only. Dashboard, learner drawer, CSV, activity,
course, group and capability checks are unchanged. Selecting Student or Guest
never grants report access. Guests and logged-out visitors never see the link.

Global navigation matches a selected explicit role assignment anywhere on the
site (system, category, course or module), plus Moodle's default authenticated-user
and front-page roles. Selecting either default role therefore covers every signed-in
user who already has reporting access. A site administrator matches the explicit
administrator choice or any selected role they also hold. The empty selection has
no administrator bypass. Saving purges caches so the next request sees the change.

One bounded role-assignment existence query is used only when the existing access
gate passes and no selected default role already matches. No report catalogue,
tracking or per-course aggregation is loaded to make this decision. No capability,
schema or privacy-provider changes are introduced. Package suffix 23 refreshes the
navigation/settings caches during upgrade.

The sibling mod_exeweb has no corresponding Seleda reporting navigation component;
this configuration applies only to mod_seledascorm.
