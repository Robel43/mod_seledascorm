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
        $this->assertSame(3, $studentmetrics['learning']['roster']);
        $this->assertSame(1, $studentmetrics['learning']['started']);
        $this->assertArrayNotHasKey($two->id, $studentmetrics['learning']['learners']);
        $this->assertArrayNotHasKey($suspended->id, $studentmetrics['learning']['learners']);
        $this->assertSame('staff', $studentmetrics['learning']['learners'][$teacher->id]['role']);
        $onlylearners=reporting_dashboard::overview($allowed,$cm,0,['role'=>'learner']);
        $this->assertArrayNotHasKey($teacher->id,$onlylearners['learning']['learners']);
        $this->assertSame($metrics['learning']['roster'],$studentmetrics['learning']['roster']);
        $nogroup=$generator->create_user();$generator->enrol_user($nogroup->id,$course->id,'editingteacher');
        $this->setUser($nogroup);
        $this->assertEmpty(reporting_dashboard::overview($allowed,$cm)['learning']['learners']);
        $this->setAdminUser();
        $all=reporting_dashboard::overview($allowed,$cm);
        $this->assertArrayHasKey($two->id,$all['learning']['learners']);
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        reporting_dashboard::detail($allowed, $cm, $two->id, 1);
    }

    public function test_roles_audit_event_and_real_zero_are_preserved(): void {
        global $DB;
        $this->resetAfterTest(true);$this->setAdminUser();$g=$this->getDataGenerator();
        $course=$g->create_course();$activity=$g->create_module('seledascorm',['course'=>$course->id,'grademethod'=>1]);
        $student=$g->create_user();$teacher=$g->create_user();
        $g->enrol_user($student->id,$course->id,'student');$g->enrol_user($teacher->id,$course->id,'editingteacher');
        $sco=$DB->get_record('seledascorm_scoes',['seledascorm'=>$activity->id,'seledascormtype'=>'sco'],'*',IGNORE_MULTIPLE);
        foreach([$student,get_admin()]as $user)foreach(['cmi.core.lesson_status'=>'failed','cmi.core.score.raw'=>'0']as $element=>$value)
            $DB->insert_record('seledascorm_scoes_track',(object)['userid'=>$user->id,'seledascormid'=>$activity->id,'scoid'=>$sco->id,'attempt'=>1,'element'=>$element,'value'=>$value,'timemodified'=>time()]);
        foreach([100=>1000,200=>2000]as $started=>$ms) {
            $session=str_repeat($started===100?'a':'b',24);
            $DB->insert_record('seledascorm_scoes_track',(object)['userid'=>$student->id,'seledascormid'=>$activity->id,'scoid'=>$sco->id,'attempt'=>1,
                'element'=>'seleda.usage.v1.reading.'.$session,'value'=>json_encode(['v'=>1,'id'=>'reading','title'=>'Reading','type'=>'text','ms'=>$ms,'visits'=>1,'actions'=>[],'events'=>[],'started'=>$started]),'timemodified'=>time()]);
        }
        [$allowed,$cm]=reporting_dashboard::activity($activity->cmid);$m=reporting_dashboard::overview($allowed,$cm,0,['staff'=>0]);
        $people=$m['learning']['learners'];
        $this->assertSame('admin',$people[get_admin()->id]['role']);
        $this->assertSame('staff',$people[$teacher->id]['role']);
        $this->assertSame('learner',$people[$student->id]['role']);
        $this->assertSame('inprogress',$people[$student->id]['status']);
        $this->assertSame('failed',$people[$student->id]['result']);
        $this->assertSame(0.0,$people[$student->id]['score']);
        $filtered=reporting_dashboard::overview($allowed,$cm,0,['fromts'=>150,'tots'=>250]);
        $this->assertSame(2000,$filtered['learning']['learners'][$student->id]['ms']);
        $sink=$this->redirectEvents();
        $detail=learner_report::build($allowed,$cm,$filtered['learning']['learners'][$student->id],1,$m['pages'],0,'answers',false,true);
        $this->assertSame(3000,$detail['selected']['ms']);
        $this->assertSame(3000,$detail['pages'][0]['ms']);
        $this->assertSame(2,$detail['pages'][0]['visits']);
        $this->assertSame(0.0,$detail['selected']['score']);
        $events=$sink->get_events();$this->assertCount(1,$events);
        $this->assertInstanceOf(\mod_seledascorm\event\user_report_viewed::class,$events[0]);
        $this->assertEquals($student->id,$events[0]->relateduserid);
        $this->assertCount(count(array_filter($m['pages'],function($p){return !empty($p->launch)&&$p->seledascormtype==='sco';})),$detail['pages']);
        $this->setUser($student);$this->expectException(\required_capability_exception::class);
        learner_report::build($allowed,$cm,$people[$student->id],1,$m['pages'],0,'answers');
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
    public function test_drawer_rechecks_capability_groups_learner_and_attempt(): void {
        global $DB;
        $this->resetAfterTest(true);$this->setAdminUser();$g=$this->getDataGenerator();
        $course=$g->create_course();
        $activity=$g->create_module('seledascorm',['course'=>$course->id,'groupmode'=>SEPARATEGROUPS]);
        $teacher=$g->create_user();$one=$g->create_user();$two=$g->create_user();
        $g->enrol_user($teacher->id,$course->id,'editingteacher');
        foreach([$one,$two] as $user)$g->enrol_user($user->id,$course->id,'student');
        $a=$g->create_group(['courseid'=>$course->id]);$b=$g->create_group(['courseid'=>$course->id]);
        groups_add_member($a,$teacher);groups_add_member($a,$one);groups_add_member($b,$two);
        $role=$DB->get_field('role','id',['shortname'=>'editingteacher']);
        assign_capability('moodle/site:accessallgroups',CAP_PREVENT,$role,\context_course::instance($course->id));
        $sco=$DB->get_record('seledascorm_scoes',['seledascorm'=>$activity->id,'seledascormtype'=>'sco'],'*',IGNORE_MULTIPLE);
        foreach([$one,$two] as $user)$DB->insert_record('seledascorm_scoes_track',(object)[
            'userid'=>$user->id,'seledascormid'=>$activity->id,'scoid'=>$sco->id,'attempt'=>1,
            'element'=>'cmi.core.lesson_status','value'=>'completed','timemodified'=>time()]);
        $this->setUser($teacher);$sink=$this->redirectEvents();
        $external=\mod_seledascorm\external\learner_report::class;
        $result=$external::execute($activity->cmid,$one->id,1,0,'answers','{}');
        $clean=\core_external\external_api::clean_returnvalue($external::execute_returns(),$result);
        $this->assertStringContainsString('sd-learner-header',$clean['html']);
        $this->assertStringContainsString('sd-learner-pages',$clean['html']);
        $this->assertCount(1,$sink->get_events());
        foreach([[$two->id,1,0,'answers','{}'],[$one->id,99,0,'answers','{}'],
            [$one->id,1,$b->id,'answers','{}'],[$one->id,1,0,'bogus','{}'],
            [$one->id,1,0,'answers','{"role":"learner","from":"invalid"}'],
            [$one->id,1,0,'answers','{"staff":1}']] as $args) {
            try{$external::execute($activity->cmid,...$args);$this->fail('Forged drawer selection accepted');}
            catch(\moodle_exception $e){$this->assertNotEmpty($e->getMessage());}
        }
        $this->setAdminUser();
        $this->assertStringContainsString('sd-learner-header',$external::execute($activity->cmid,$two->id,1,0,'attempts','{}')['html']);
        $this->setUser($one);
        $this->expectException(\required_capability_exception::class);
        $external::execute($activity->cmid,$one->id,1,0,'answers','{}');
    }

    public function test_reports_navigation_role_setting_and_permissions(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->libdir.'/adminlib.php');
        $this->resetAfterTest(true);$this->setAdminUser();$g=$this->getDataGenerator();
        unset_config('reportaccessroles','mod_seledascorm');
        $course=$g->create_course();$other=$g->create_course();
        $g->create_module('seledascorm',['course'=>$course->id]);
        $teacher=$g->create_user();$student=$g->create_user();$analyst=$g->create_user();
        $g->enrol_user($teacher->id,$course->id,'editingteacher');
        $g->enrol_user($student->id,$course->id,'student');
        $teacherrole=(int)$DB->get_field('role','id',['shortname'=>'editingteacher']);
        $studentrole=(int)$DB->get_field('role','id',['shortname'=>'student']);
        $custom=create_role('Course analyst','courseanalyst','Reports analyst');
        assign_capability('mod/seledascorm:viewreport',CAP_ALLOW,$custom,\context_course::instance($course->id));
        $g->enrol_user($analyst->id,$course->id,$custom);
        $choices=report_navigation::choices();
        $this->assertCount($DB->count_records('role')+1,$choices);
        $this->assertSame('Course analyst',$choices[$custom]);
        $this->assertArrayHasKey(report_navigation::SITE_ADMIN,$choices);
        $setting=new \admin_setting_configmultiselect('mod_seledascorm/reportlinkroles','Roles','',array_keys($choices),$choices);
        $this->assertSame(array_keys($choices),$setting->get_defaultsetting());
        $this->assertSame('',$setting->write_setting([$teacherrole,$custom]));
        $this->assertSame([$teacherrole,$custom],array_map('intval',$setting->get_setting()));
        $this->setUser($teacher);$this->assertTrue(reporting_dashboard::can_open());
        $this->assertTrue(report_navigation::visible());
        set_config('reportlinkroles',(string)$studentrole,'mod_seledascorm');
        $this->assertFalse(report_navigation::visible());
        // A global link recognises any selected assigned role, across courses.
        $g->enrol_user($teacher->id,$other->id,'student');
        $this->assertTrue(report_navigation::visible());
        $this->setUser($student);$this->assertFalse(report_navigation::visible());
        $this->assertFalse(reporting_dashboard::can_open());
        $this->setUser($analyst);set_config('reportlinkroles',(string)$custom,'mod_seledascorm');
        $this->assertTrue(report_navigation::visible());
        set_config('reportlinkroles',(string)$CFG->defaultuserroleid,'mod_seledascorm');
        $this->assertTrue(report_navigation::visible());
        set_config('defaultfrontpageroleid',$studentrole);
        set_config('reportlinkroles',(string)$studentrole,'mod_seledascorm');
        $this->assertTrue(report_navigation::visible());
        $this->assertSame('',$setting->write_setting([]));
        $this->assertSame([],$setting->get_setting());
        $this->assertFalse(report_navigation::visible());
        $this->setAdminUser();$this->assertFalse(report_navigation::visible());
        set_config('reportlinkroles',(string)report_navigation::SITE_ADMIN,'mod_seledascorm');
        $this->assertTrue(report_navigation::visible());
        $PAGE->set_context(\context_system::instance());
        $view=new \core\navigation\views\primary($PAGE);
        $hook=new \core\hook\navigation\primary_extend($view);
        \mod_seledascorm\hook_callbacks::extend_primary($hook);
        $this->assertNotFalse($view->find('seledascorm-dashboard',\navigation_node::TYPE_CUSTOM));
        set_config('reportlinkroles','','mod_seledascorm');
        $hiddenview=new \core\navigation\views\primary($PAGE);
        \mod_seledascorm\hook_callbacks::extend_primary(new \core\hook\navigation\primary_extend($hiddenview));
        $this->assertFalse($hiddenview->find('seledascorm-dashboard',\navigation_node::TYPE_CUSTOM));
        unset_config('reportlinkroles','mod_seledascorm');
        $this->assertTrue(report_navigation::visible());
        $this->setGuestUser();$this->assertFalse(report_navigation::visible());
        $this->setUser(0);$this->assertFalse(report_navigation::visible());
    }

}
