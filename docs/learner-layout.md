# Learner sidebar and fullscreen viewport

The narrow Moodle learner player uses an off-canvas left drawer instead of
stacking its table of contents above the lesson. The existing YUI tree retains
its sequencing, expansion, progress icons and selected-page state. Styling adds
spacing, rounded surfaces, readable wrapping and an active-page accent. The
drawer includes a close button, backdrop, Escape handling, focus restoration,
keyboard containment and an inert lesson while open. Desktop navigation stays
beside the content. Reduced-motion preferences are respected.

Fullscreen constrains the player and iframe to the visible screen. The lesson
scrolls inside its iframe instead of expanding that iframe to the full document
height. Existing fixed accessibility controls therefore remain fixed in the
visible viewport, without moving controls to another document or changing their
handlers. Exiting fullscreen restores normal content-height sizing. Fullscreen
state is read from the actual browser state, rather than toggled on every vendor
event, so duplicate fullscreen events cannot invert the layout.

Browser checks exercise 320/390/768px drawers, unchanged content width, backdrop,
Escape, lesson selection, focus and resize-to-desktop. Real browser fullscreen is
tested at 390/1440px with long scrolling content and the shipped accessibility
CSS, including duplicate events and exiting fullscreen. Sidebar icons are still
supplied by Moodle's tree; no progress or SCORM tracking behavior is replaced.

The inherited sibling-plugin audit could not access mod_exeweb on GitHub in this
session. These enhancements are scoped to Seleda player IDs and module loading.
No main or sibling branch changes are included.
