# Custom HTML assessment reporting

The Moodle player adapts the supplied `matching-activity`, `readingActivity`, and
`writingActivity` HTML contracts. Existing uploaded packages work without a new
export. Matching reads the literal `correctMap` from the authored script, tracks
pair selections, and reports only a complete Check. Reading reads `data-answer`
and `data-correct-text`. Fixed writing reads `data-answers`. Comparisons use the
same normalization as these authored checkers. Reset and solution buttons never
submit an assessment. Empty answers remain ungraded.

Open writing responses are recorded with the sample, without a correctness claim.
Other arbitrary HTML is still recorded as ungraded interactions until an adapter
for its actual answer/checking contract exists. Native activity reporting remains
unchanged. This feature records detail; it does not change Moodle gradebook scores.

Assessment events use the authenticated, attempt-bound usage transport, a separate
session and zero time/visits, merged with their owning content block. They cannot
double active time or visits. Retries are sequence-idempotent. Same-origin nested
frames inherit their owning content block. Cross-origin frames are not inspected.
Review/browse modes do not receive a sender. Answer details are bounded and escaped.
Historical click logs cannot acquire missing responses or results retroactively.
