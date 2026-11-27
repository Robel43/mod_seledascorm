<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Export the same meaningful measures as the screen, including suppression and null values. */
final class report_export {
    private static function text(string $key): string { return get_string('rpt'.$key,'mod_seledascorm'); }
    private static function safe(array $row): array { return array_map(function($v){return timhirt_reporting::exportcell($v===null?'—':(string)$v);},$row); }
    public static function people(array $people,string $activity,string $course): array {
        $rows=[array_map([self::class,'text'],['activity','course','name','role','status','result','pagescompleted','pagestotal','score','activetime','attempts','last'])];
        foreach($people as $p)$rows[]=self::safe([$activity,$course,$p['name'],self::text($p['role']),self::text($p['status']),
            $p['result']?self::text($p['result']):null,$p['progress']===null?null:$p['pagescompleted'],$p['pagestotal'],
            $p['grading']==='completion'?self::text('completionbased'):$p['score'],$p['ms']===null?null:seleda_usage::duration($p['ms']),
            $p['attemptcount']?:null,$p['last']?userdate($p['last']):null]);
        return $rows;
    }
    private static function statistic($value,int $n,int $total,bool $time=false): string {
        if($n<report_view::MIN_SAMPLE)return self::text('insufficientdata').' — '.get_string('rptdatacoverage','mod_seledascorm',(object)['count'=>$n,'total'=>$total]);
        return $value===null?'—':($time?seleda_usage::duration($value):(string)round($value,1));
    }
    public static function insights(array $items,array $summary): array {
        $rows=[array_map([self::class,'text'],['activity','course','accesspeople','people','completed','average','scoredpeople','median','timedpeople','scorechange','pairedpeople','uniquepeople','participations'])];
        foreach($items as $item){$d=$item['data'];$completion=$d['grading']==='completion';
            $rows[]=self::safe([$item['name'],$item['course'],$d['started'],$d['roster'],$d['completed'],
                $completion?self::text('completionbased'):self::statistic($d['average'],$d['scored'],$d['roster']),$d['scored'],self::statistic($d['median'],$d['timed'],$d['roster'],true),$d['timed'],
                $completion?self::text('completionbased'):self::statistic($d['improvement'],$d['paired'],$d['roster']),$d['paired'],$summary['unique'],$summary['participations']]);}
        return $rows;
    }
}
