# Frozen export regression fixtures

These are the actual MCQ and LMS runtimes shipped by the pinned Seleda editor
(4b6ada9455216fa719ab3cb3ef738643fa0de4ca). They reproduce an existing uploaded
SCORM package, including browser-generated reporting metadata. The test passes
its original serialized HTML and real emitted SCORM tracks to the PHP reporter.
The reporter must not require a package re-export or execute authored scripts.
Both runtimes retain their original GPL licensing and are not deployment assets.

common.js is the eXeLearning v4.0.3 gamification aggregator; jquery.min.js is
its upstream jQuery dependency. Original license headers remain in those files.
