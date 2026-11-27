<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\external;
defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use mod_seledascorm\local\reporting_dashboard as service;

/** Authenticated progressive enhancement for the existing learner report. */
final class learner_report extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'=>new external_value(PARAM_INT,'Course module ID'),
            'userid'=>new external_value(PARAM_INT,'Learner ID'),
            'attempt'=>new external_value(PARAM_INT,'Moodle attempt, zero for latest'),
            'groupid'=>new external_value(PARAM_INT,'Selected reporting group'),
            'tab'=>new external_value(PARAM_ALPHA,'Report section'),
            'filters'=>new external_value(PARAM_RAW,'JSON role, date and block filters'),
        ]);
    }

    public static function execute($cmid,$userid,$attempt,$groupid,$tab,$filters): array {
        global $CFG;
        $p=self::validate_parameters(self::execute_parameters(),compact('cmid','userid','attempt','groupid','tab','filters'));
        require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');
        require_once($CFG->dirroot.'/mod/seledascorm/report/timhirt_reporting.php');
        require_once($CFG->dirroot.'/mod/seledascorm/report/seleda_usage.php');
        if ($p['cmid']<1 || $p['userid']<1 || $p['attempt']<0 || $p['groupid']<0 ||
                !in_array($p['tab'],['answers','attempts','technical'],true)) throw new \invalid_parameter_exception('Invalid report selection');
        [$activity,$cm,$course]=service::activity($p['cmid']);
        self::validate_context($cm->context);
        $scope=json_decode($p['filters'],true);
        if (!is_array($scope) || array_diff(array_keys($scope),['role','from','to','block','type']))
            throw new \invalid_parameter_exception('Invalid reporting filters');
        $scope+=['role'=>'all','from'=>'','to'=>'','block'=>'','type'=>''];
        foreach($scope as $value) if(!is_string($value)) throw new \invalid_parameter_exception('Invalid filter value');
        if(!in_array($scope['role'],['all','learner','staff'],true) ||
                clean_param($scope['block'],PARAM_ALPHANUM)!==$scope['block'] ||
                clean_param($scope['type'],PARAM_ALPHANUMEXT)!==$scope['type'])
            throw new \invalid_parameter_exception('Invalid reporting filter');
        $options=$scope+['questions'=>false];
        foreach(['from','to'] as $key) if($scope[$key]!=='') {
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$scope[$key],\core_date::get_user_timezone_object());
            if(!$date || $date->format('Y-m-d')!==$scope[$key]) throw new \invalid_parameter_exception('Invalid date');
            $options[$key==='from'?'fromts':'tots']=($key==='to'?$date->modify('+1 day'):$date)->getTimestamp();
        }
        if(isset($options['fromts'],$options['tots']) && $options['fromts']>=$options['tots'])
            throw new \invalid_parameter_exception('Invalid date range');
        // Same streaming, role/date/block and separate-group scope as the full page.
        $metrics=service::overview($activity,$cm,$p['groupid'],$options);
        $person=$metrics['learning']['learners'][$p['userid']]??null;
        $selected=$p['attempt']?:($person['latest']??0);
        if(!$person || !isset($person['attempts'][$selected])) throw new \moodle_exception('nopermissiontoshow');
        $filters=$scope+['id'=>$p['cmid'],'learner'=>$p['userid'],'attempt'=>$selected,'group'=>$p['groupid'],
            'tab'=>$p['tab'],'view'=>'teacher','panel'=>'overview','diagnostics'=>0,'course'=>$course->id,'page'=>0];
        $detail=\mod_seledascorm\local\learner_report::build($activity,$cm,$person,$selected,$metrics['pages'],
            $p['groupid'],$p['tab'],false,(bool)($scope['from']||$scope['to']||$scope['block']||$scope['type']));
        return ['html'=>\mod_seledascorm\output\reporting_dashboard::detail([
            'filters'=>$filters,'activity'=>$activity,'coursename'=>$course->fullname,'detail'=>$detail])];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['html'=>new external_value(PARAM_RAW,'Escaped server-rendered learner report')]);
    }
}
