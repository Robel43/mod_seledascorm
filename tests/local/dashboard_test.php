<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Real Moodle database and access-control integration for the standalone dashboard.
 * @covers \mod_seledascorm\local\reporting_dashboard
 */
final class dashboard_test extends \advanced_testcase {
    public function test_catalogue_and_group_scoped_usage(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $activity = $generator->create_module('seledascorm', ['course' => $course->id, 'groupmode' => SEPARATEGROUPS]);
        $other = $generator->create_module('seledascorm', ['course' => $othercourse->id]);
        $teacher = $generator->create_user();
        $one = $generator->create_user();
        $two = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($one->id, $course->id, 'student');
        $generator->enrol_user($two->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $one->id]);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $teacherrole->id, \context_course::instance($course->id)->id);
        $sco = $DB->get_record('seledascorm_scoes', ['seledascorm' => $activity->id, 'scormtype' => 'sco'], '*', IGNORE_MULTIPLE);
        $this->assertNotFalse($sco);
        foreach ([$one, $two] as $learner) {
            $DB->insert_record('seledascorm_scoes_track', (object)['userid' => $learner->id, 'seledascormid' => $activity->id,
                'scoid' => $sco->id, 'attempt' => 1, 'element' => 'cmi.core.lesson_status', 'value' => 'completed', 'timemodified' => time()]);
        }
        $this->setUser($teacher);
        $this->assertTrue(reporting_dashboard::can_open());
        $catalogue = reporting_dashboard::catalogue();
        $this->assertSame([$activity->cmid], array_map(function($row) { return (int)$row->id; }, $catalogue['items']));
        [$allowed, $cm] = reporting_dashboard::activity($activity->cmid);
        $metrics = reporting_dashboard::overview($allowed, $cm);
        $this->assertSame([$one->id], array_keys($metrics['learners']));
        $this->assertSame(0, $metrics['tracked']);
        $this->assertEmpty($metrics['blocks']);
        $this->expectException(\moodle_exception::class);
        reporting_dashboard::detail($allowed, $cm, $two->id, 1);
    }

    public function test_student_cannot_select_an_activity_directly(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('seledascorm', ['course' => $course->id]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $this->assertFalse(reporting_dashboard::can_open());
        $this->assertEmpty(reporting_dashboard::catalogue()['items']);
        $this->expectException(\required_capability_exception::class);
        reporting_dashboard::activity($activity->cmid);
    }
}
