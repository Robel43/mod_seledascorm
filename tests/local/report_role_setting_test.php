<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Real Moodle role grants, rollback and report boundaries. */
final class report_role_setting_test extends \advanced_testcase {
    private function prepare(): array {
        global $CFG, $DB;
        require_once($CFG->libdir.'/adminlib.php');
        $this->resetAfterTest(true);$this->setAdminUser();
        unset_config('reportrolegrants','mod_seledascorm');
        $g=$this->getDataGenerator();$course=$g->create_course();
        $activity=$g->create_module('seledascorm',['course'=>$course->id,'groupmode'=>SEPARATEGROUPS]);
        $role=create_role('Course reviewer','coursereviewer','');
        assign_capability('moodle/course:view',CAP_ALLOW,$role,\context_system::instance());
        $user=$g->create_user();$g->enrol_user($user->id,$course->id,$role);
        return [$g,$course,$activity,$role,$user,new report_role_setting()];
    }
    private function permission(int $role) {
        global $DB;
        $r=$DB->get_record('role_capabilities',['contextid'=>\context_system::instance()->id,
            'roleid'=>$role,'capability'=>report_role_setting::CAPABILITY]);
        return $r?(int)$r->permission:null;
    }
    public function test_defaults_grants_audit_and_reversible_ownership(): void {
        global $CFG,$DB;
        [$g,$course,$activity,$role,$user,$setting]=$this->prepare();
        $student=(int)$DB->get_field('role','id',['shortname'=>'student']);
        $teacher=(int)$DB->get_field('role','id',['shortname'=>'editingteacher']);
        $before=$this->permission($student);
        $this->assertNotContains($student,$setting->get_defaultsetting());
        $this->assertNotContains((int)$CFG->defaultuserroleid,$setting->get_defaultsetting());
        // The previous visibility-only all-role selection cannot become automatic grants.
        set_config('reportlinkroles',implode(',',array_keys(report_navigation::choices())),'mod_seledascorm');
        $this->assertSame('',$setting->write_setting($setting->get_defaultsetting()));
        $this->assertSame($before,$this->permission($student));
        $this->assertSame(null,$this->permission($role));
        $other=create_role('Another reviewer','anotherreviewer','');
        assign_capability(report_role_setting::CAPABILITY,CAP_PREVENT,$other,\context_system::instance());
        $sink=$this->redirectEvents();
        $this->assertSame('',$setting->write_setting([$role,$other,$teacher,report_navigation::SITE_ADMIN]));
        $this->assertSame(CAP_ALLOW,$this->permission($role));
        $this->assertSame(CAP_ALLOW,$this->permission($other));
        $this->assertContains((string)$other,$setting->get_setting());
        $managed=json_decode(get_config('mod_seledascorm','reportrolegrants'),true);
        $this->assertArrayHasKey($role,$managed);$this->assertNull($managed[$role]);
        $this->assertSame(CAP_PREVENT,$managed[$other]);
        $this->assertArrayNotHasKey($teacher,$managed);
        $events=array_filter($sink->get_events(),function($event){return $event instanceof \core\event\capability_assigned;});
        $this->assertCount(2,$events);
        $this->assertSame('',$setting->write_setting([$teacher,report_navigation::SITE_ADMIN]));
        $this->assertNull($this->permission($role));
        $this->assertSame(CAP_PREVENT,$this->permission($other));
        $this->assertSame(CAP_ALLOW,$this->permission($teacher));
        $this->assertSame($before,$this->permission($student));
        $this->assertTrue(has_capability('moodle/course:view',\context_course::instance($course->id),$user));
        // A subsequent administrator override is not erased by deselection.
        $setting->write_setting([$role]);
        assign_capability(report_role_setting::CAPABILITY,CAP_PREVENT,$role,\context_system::instance(),true);
        $setting->write_setting([]);
        $this->assertSame(CAP_PREVENT,$this->permission($role));
        $this->assertSame([],json_decode(get_config('mod_seledascorm','reportrolegrants'),true));
    }
    public function test_role_assignment_courses_groups_and_module_overrides_stay_enforced(): void {
        global $DB;
        [$g,$course,$activity,$role,$user,$setting]=$this->prepare();
        $one=$g->create_user();$two=$g->create_user();
        foreach([$one,$two] as $student)$g->enrol_user($student->id,$course->id,'student');
        $a=$g->create_group(['courseid'=>$course->id]);$b=$g->create_group(['courseid'=>$course->id]);
        groups_add_member($a,$user);groups_add_member($a,$one);groups_add_member($b,$two);
        $sco=$DB->get_record('seledascorm_scoes',['seledascorm'=>$activity->id,'seledascormtype'=>'sco'],'*',IGNORE_MULTIPLE);
        foreach([$one,$two] as $student)$DB->insert_record('seledascorm_scoes_track',(object)[
            'userid'=>$student->id,'seledascormid'=>$activity->id,'scoid'=>$sco->id,'attempt'=>1,
            'element'=>'cmi.core.lesson_status','value'=>'completed','timemodified'=>time()]);
        $other=$g->create_course();$outside=$g->create_module('seledascorm',['course'=>$other->id]);
        $this->assertSame('',$setting->write_setting([$role]));
        $this->setUser($user);$this->assertTrue(report_navigation::visible());
        [$allowed,$cm]=reporting_dashboard::activity($activity->cmid);
        $data=reporting_dashboard::overview($allowed,$cm);
        $this->assertArrayHasKey($one->id,$data['learning']['learners']);
        $this->assertArrayNotHasKey($two->id,$data['learning']['learners']);
        $ids=array_column(reporting_dashboard::catalogue()['items'],'id');
        $this->assertContains((int)$activity->cmid,array_map('intval',$ids));
        $this->assertNotContains((int)$outside->cmid,array_map('intval',$ids));
        try{reporting_dashboard::activity($outside->cmid);$this->fail('Unassigned course became reportable');}
        catch(\moodle_exception $e){$this->assertNotEmpty($e->getMessage());}
        $this->setAdminUser();assign_capability(report_role_setting::CAPABILITY,CAP_PREVENT,$role,$cm->context,true);
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        reporting_dashboard::activity($activity->cmid);
    }
    public function test_prohibitions_and_role_management_permission_cannot_be_bypassed(): void {
        [$g,$course,$activity,$role,$user,$setting]=$this->prepare();
        assign_capability(report_role_setting::CAPABILITY,CAP_PROHIBIT,$role,\context_system::instance(),true);
        $this->assertNotSame('',$setting->write_setting([$role]));
        $this->assertSame(CAP_PROHIBIT,$this->permission($role));
        unassign_capability(report_role_setting::CAPABILITY,$role,\context_system::instance());
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        $setting->write_setting([$role]);
    }
}
