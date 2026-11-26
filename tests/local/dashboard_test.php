<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Real Moodle database and access-control integration for the standalone dashboard.
 * @covers \mod_seledascorm\local\reporting_dashboard
 */
final class dashboard_test extends \advanced_testcase {
    public function test_activity_is_branded_for_moodle_icon_rendering(): void {
        $this->assertTrue(component_callback('mod_seledascorm', 'is_branded', [], false));
    }
    public function test_direct_entry_preserves_native_attempt_selection(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/seledascorm/locallib.php');
        require_once($CFG->dirroot . '/mod/seledascorm/datamodels/scorm_12lib.php');
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('seledascorm', [
            'course'=>$course->id, 'skipview'=>0, 'forcenewattempt'=>0, 'popup'=>0,
        ]);
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        foreach ([$student, $teacher, get_admin()] as $user) {
            $this->setUser($user);
            $url = direct_launch::url($activity, $user, (int)$activity->cmid);
            $this->assertNotNull($url);
            $this->assertSame('normal', $url->get_param('mode'));
            $this->assertEquals($activity->cmid, $url->get_param('cm'));
            $this->assertTrue($DB->record_exists('seledascorm_scoes', [
                'id'=>$url->get_param('scoid'), 'seledascorm'=>$activity->id,
            ]));
            $this->assertNull($url->get_param('newattempt'));
        }
        $this->assertEquals(0, $DB->count_records('seledascorm_scoes_track', ['seledascormid'=>$activity->id]));
        $this->setUser($student);
        $scoid = direct_launch::url($activity, $student, (int)$activity->cmid)->get_param('scoid');
        $DB->insert_record('seledascorm_scoes_track', (object)[
            'userid'=>$student->id, 'seledascormid'=>$activity->id, 'scoid'=>$scoid,
            'attempt'=>1, 'element'=>'cmi.core.lesson_status', 'value'=>'incomplete', 'timemodified'=>time(),
        ]);
        $activity->forcenewattempt = SELEDASCORM_FORCEATTEMPT_ONCOMPLETE;
        $this->assertNull(direct_launch::url($activity, $student, (int)$activity->cmid)->get_param('newattempt'));
        $DB->set_field('seledascorm_scoes_track', 'value', 'completed', [
            'seledascormid'=>$activity->id, 'userid'=>$student->id, 'element'=>'cmi.core.lesson_status',
        ]);
        $this->assertSame('on', direct_launch::url($activity, $student, (int)$activity->cmid)->get_param('newattempt'));
        $activity->forcenewattempt = SELEDASCORM_FORCEATTEMPT_ALWAYS;
        $activity->popup = 1;
        $url = direct_launch::url($activity, $student, (int)$activity->cmid);
        $this->assertSame('on', $url->get_param('newattempt'));
        $this->assertSame('popup', $url->get_param('display'));
        $activity->reference = 'unpublished.elpx';
        $this->assertNull(direct_launch::url($activity, $student, (int)$activity->cmid));
    }
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
        $notstarted = $generator->create_user();
        $suspended = $generator->create_user(['suspended'=>1]);
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($one->id, $course->id, 'student');
        $generator->enrol_user($two->id, $course->id, 'student');
        $generator->enrol_user($notstarted->id, $course->id, 'student');
        $generator->enrol_user($suspended->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $one->id]);
        $generator->create_group_member(['groupid'=>$group->id,'userid'=>$notstarted->id]);
        $generator->create_group_member(['groupid'=>$group->id,'userid'=>$suspended->id]);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $teacherrole->id, \context_course::instance($course->id)->id);
        $sco = $DB->get_record('seledascorm_scoes', ['seledascorm' => $activity->id, 'seledascormtype' => 'sco'], '*', IGNORE_MULTIPLE);
        $this->assertNotFalse($sco);
        foreach ([$one, $two] as $learner) {
            $DB->insert_record('seledascorm_scoes_track', (object)['userid' => $learner->id, 'seledascormid' => $activity->id,
                'scoid' => $sco->id, 'attempt' => 1, 'element' => 'cmi.core.lesson_status', 'value' => 'completed', 'timemodified' => time()]);
        }
        $this->setUser($teacher);
        $this->assertTrue(reporting_dashboard::can_open());
        $catalogue = reporting_dashboard::catalogue();
        $this->assertSame([(int)$activity->cmid], array_map(function($row) { return (int)$row->id; }, $catalogue['items']));
        [$allowed, $cm] = reporting_dashboard::activity($activity->cmid);
        $metrics = reporting_dashboard::overview($allowed, $cm);
        $this->assertSame([(int)$one->id], array_keys($metrics['learners']));
        $this->assertSame(0, $metrics['tracked']);
        $this->assertEmpty(reporting_dashboard::catalogue('', 0, 30, $othercourse->id)['items']);
        $studentmetrics = reporting_dashboard::overview($allowed, $cm, 0, ['staff'=>0]);
        $this->assertSame([(int)$one->id], array_keys($studentmetrics['learners']));
        $this->assertEmpty($metrics['blocks']);
        $this->assertSame('notstarted', $studentmetrics['learning']['learners'][$notstarted->id]['status']);
        $this->assertSame(2, $studentmetrics['learning']['roster']);
        $this->assertSame(1, $studentmetrics['learning']['started']);
        $this->assertArrayNotHasKey($two->id, $studentmetrics['learning']['learners']);
        $this->assertArrayNotHasKey($suspended->id, $studentmetrics['learning']['learners']);
        $this->assertArrayNotHasKey($teacher->id, $studentmetrics['learning']['learners']);
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
