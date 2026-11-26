<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Pure presentation calculations over already-authorised aggregates. */
final class report_view {
    const MIN_SAMPLE = 5;
    const STALLED_DAYS = 7;

    public static function page_state(array $statuses, bool $reached, bool $hasrows, bool $coverage): string {
        if (array_intersect($statuses, ['passed', 'completed'])) return 'completed';
        if (in_array('failed', $statuses, true)) return 'failed';
        if ($statuses || $reached) return 'inprogress';
        return !$hasrows && !$coverage ? 'unavailable' : 'notstarted';
    }
    public static function attention(array $person, int $now): bool {
        return $person['result'] === 'failed' || (in_array($person['status'], ['inprogress','unknown'], true) &&
            !empty($person['last']) && $person['last'] < $now - self::STALLED_DAYS * 86400);
    }
    public static function sample(?float $value, int $n, bool $insights = false): array {
        return ['value'=>$insights && $n < self::MIN_SAMPLE ? null : $value, 'n'=>$n, 'small'=>$n < self::MIN_SAMPLE];
    }
    public static function score_bins(array $people): array {
        $bins = array_fill_keys(['<50','50–59','60–69','70–79','80–89','90–100'], 0);
        foreach ($people as $person) {
            if ($person['percent'] === null || ($person['grading'] ?? '') === 'completion') continue;
            $v=$person['percent']; $key=$v<50?'<50':($v<60?'50–59':($v<70?'60–69':($v<80?'70–79':($v<90?'80–89':'90–100'))));
            $bins[$key]++;
        }
        return $bins;
    }
    public static function table(array $people, array $filters): array {
        $rows=array_values(array_filter($people,function($p) use($filters) {
            $state=$filters['state'] ?? '';
            return (!$state || ($state==='attention' ? $p['needsattention'] : $p['status']===$state)) &&
                (empty($filters['search']) || mb_stripos($p['name'],$filters['search'],0,'UTF-8')!==false);
        }));
        $key=['name'=>'name','status'=>'status','progress'=>'progress','score'=>'score','time'=>'ms','attempts'=>'attemptcount','last'=>'last'][$filters['sort'] ?? 'last'];
        $dir=($filters['dir'] ?? 'desc')==='asc'?1:-1;
        usort($rows,function($a,$b) use($key,$dir) {
            $left=$a[$key]; $right=$b[$key];
            if ($left===null || $right===null) return $left===null ? ($right===null ? $a['id']<=>$b['id'] : 1) : -1;
            $cmp=is_string($left)?strnatcasecmp($left,$right):($left<=>$right);
            return $cmp ? $dir*$cmp : $a['id']<=>$b['id'];
        });
        return $rows;
    }
    public static function attempts(array $attempts): array {
        ksort($attempts);
        $scores=array_values(array_filter(array_column($attempts,'score'),function($s){return $s!==null;}));
        $percents=array_values(array_filter(array_column($attempts,'percent'),function($s){return $s!==null;}));
        $timed=array_values(array_filter($attempts,function($a){return $a['timeknown'];}));
        return ['best'=>$scores?max($scores):null, 'change'=>count($percents)>1?end($percents)-$percents[0]:null,
            'extratime'=>count($timed)>1?array_sum(array_column(array_slice($timed,1),'ms')):null];
    }
    public static function comparison(array $items): array {
        $ids=[];$participations=0;$questions=[];$pages=[];$bins=array_fill_keys(['<50','50–59','60–69','70–79','80–89','90–100'],0);
        foreach ($items as $item) {
            $d=$item['data'];$participations+=$d['roster'];
            foreach ($d['learners'] as $id=>$person) $ids[$id]=true;
            foreach ($d['questions'] as $q) if ($q['n']>=self::MIN_SAMPLE) $questions[]=$q+['activity'=>$item['name']];
            foreach ($d['pages'] as $p) $pages[]=$p+['activity'=>$item['name']];
            foreach ($d['scoreBins'] as $bin=>$n) $bins[$bin]+=$n;
        }
        usort($questions,function($a,$b){return $a['accuracy']<=>$b['accuracy'];});
        usort($pages,function($a,$b){return ($b['reached']-$b['completed'])<=>($a['reached']-$a['completed']);});
        return ['unique'=>count($ids),'participations'=>$participations,'questions'=>array_slice($questions,0,5),'pages'=>array_slice($pages,0,5),'bins'=>$bins];
    }
}
