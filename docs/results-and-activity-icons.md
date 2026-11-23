# Consistent dropdown results and full-colour Moodle icons

Dashboard block dropdowns now read the same decoded, saved SCORM question rows as
the Question results table. Grouping uses the SCO ID and content-block ID together,
never labels or selected option text. Submitted results appear first; raw clicks
and input changes appear in a separate nested Interaction history section without
assessment columns. Custom HTML checked-answer events retain their actual answers
and outcomes. A block without usage events can still display submitted results;
no time, visits or interaction totals are fabricated. Exact-ID metadata fallback
keeps older label snapshots usable for block grouping without changing their text.

Seleda now implements Moodle's is_branded activity callback, as supported by the
Moodle 4.5 activity icon system. This prevents purpose-based monochrome filters
from flattening the supplied opaque full-colour logo into a solid square. Scoped
CSS also supports chooser, course-list, icon/monologo and query-string image URL
variants while leaving other activity icons unchanged. The supplied logo pixels
and icon assets are unchanged. Purge Moodle caches once after upgrading if an old
icon style remains cached.

Browser checks compare each dropdown's submitted cells against the authoritative
question rows, including partial credit, and verify history stays separate. PHP
checks protect identical block IDs on different SCOs. Icon tests load the real
SVG logo under Moodle's purpose-filter selectors; visual inspection confirms the
chalkboard in chooser/list slots and an unchanged filter on another activity.
Moodle integration checks exercise the branded callback through component_callback.
The sibling-plugin audit remains unavailable; no main or sibling changes are made.
