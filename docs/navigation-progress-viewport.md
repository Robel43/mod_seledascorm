# Learner navigation, visible accessibility dock and completion refresh

The course tree keeps Moodle's existing nodes and sequencing. Folder decorations
are hidden; chapter arrows remain native clickable indicators and move to the
right. Chapter cards, reduced indentation, full-width leaf labels and modern
empty/in-progress/completed/failed badges follow the supplied reference. Text
wraps and the tree's old extra right margin is removed, avoiding horizontal
scrollbars. The menu launcher uses a chevron in both open and closed states.

A same-origin viewport bridge keeps the existing accessibility toolbar in its
original lesson document, anchored to the visible part of the iframe. It updates
on outer scrolling, iframe reloads, content/tool changes, resize and fullscreen.
Tool handlers and learner document context remain intact; panel height is bounded
to the visible area. Normal document-height playback and fullscreen playback are
both supported without reserving extra content width.

SCORM 1.2 now refreshes the tree through request-specific success callbacks rather
than accumulating global io:complete listeners. Only the newest successful tree
response can render. Failed saves do not refresh completion, and failed requests
restore pending datamodel changes so retries resend them. Successful per-SCO state
is retained for revisits within the same API/attempt, including suspend data and
scores. The native tree refresh preserves selection, updates aria-current and
uses its actual YUI context; non-tree responses cannot erase navigation. Nothing
infers completion from clicks or scrolling or changes configured grading rules.

Regression coverage uses the real SCORM API and native tree callback, including
out-of-order responses, completion after returning to a SCO, failed-save retries,
visual badge refresh, long headings, narrow-screen scrolling, disclosure clicks,
normal-view dock scrolling/open tools and real browser fullscreen. The inherited
mod_exeweb sibling reference remains inaccessible; no sibling or main branch is
changed. The rebuilt plugin includes all earlier fixes.
