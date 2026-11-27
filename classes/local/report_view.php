<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Pure presentation calculations over already-authorised aggregates. */
final class report_view {
    const MIN_SAMPLE = 5;
    const STALLED_DAYS = 7;

    /** Equal-weight learner/activity progress; missing progress never becomes zero. */
    public static function course_progress(array $people): array {
        $sum=0; $known=0;
        foreach ($people as $person) {
            if ($person['progress'] !== null) { $sum += $person['progress']; $known++; }
        }
        return ['sum'=>$sum,'known'=>$known,'total'=>count($people)];
    }

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
            return (!$state || ($state==='attention' ? $p['needsattention'] : ($state==='failed' ? $p['result']==='failed' : $p['status']===$state))) &&
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
    /** Latest answer plus its earlier submissions; no ordinary clicks are graded. */
    public static function answers(array $tracks, array $catalogues, array $pages): array {
        $groups=[];
        foreach (timhirt_reporting::rows($tracks,$catalogues) as $row) {
            $key=$row['scoid'].':native:'.preg_replace('/-a[1-9][0-9]*$/','',$row['id']);
            $groups[$key][]=['page'=>$pages[$row['scoid']]??'', 'question'=>$row['prompt'] === preg_replace('/-a[1-9][0-9]*$/','',$row['id']) ? null : $row['prompt'], 'response'=>$row['response'],
                'expected'=>$row['correct'], 'credit'=>$row['graded']?$row['credit']:null,'retry'=>$row['activityAttempt'],
                'shortened'=>!empty($row['shortened'])];
        }
        foreach (seleda_usage::summary($tracks)['blocks'] as $block) foreach ($block['events'] as $event) {
            if (($event['action']??'')!=='answer') continue;
            $key=$block['scoid'].':'.$block['id'].':'.hash('sha256',json_encode([$event['label'],$event['expected']??'']));
            $groups[$key][]=['page'=>$pages[$block['scoid']]??'', 'question'=>$event['label'],'response'=>$event['value']??'',
                'expected'=>$event['expected']??'', 'credit'=>['correct'=>1,'incorrect'=>0][$event['result']??'']??null,
                'retry'=>0,'shortened'=>!empty($event['shortened'])];
        }
        $rows=[];
        foreach ($groups as $history) { $latest=array_pop($history);$latest['earlier']=$history;$rows[]=$latest; }
        return $rows;
    }
    public static function learner_pages(array $person,int $attempt,array $pages,array $metadata,array $usage,array $states): array {
        $a=$person['attempts'][$attempt];$blocks=[];$saved=[];
        foreach ($usage['blocks'] as $block) $blocks[$block['scoid'].':'.$block['id']]=$block;
        foreach ($states as $state) $saved[$state['scoid'].':'.$state['id']]=$state;
        $out=[];
        foreach ($pages as $id=>$page) {
            if (empty($page->launch) || ($page->seledascormtype??'sco')!=='sco') continue;
            $universe=[];
            foreach ($metadata as $key=>$meta) if (strpos($key,$id.':')===0) $universe[$key]=$meta;
            foreach ($blocks as $key=>$block) if ($block['scoid']==$id && !isset($universe[$key])) $universe[$key]=['id'=>$block['id'],'title'=>$block['title'],'type'=>$block['type']];
            $rows=[];
            foreach ($universe as $key=>$meta) {
                $b=$blocks[$key]??null;$savedstate=$saved[$key]??[];
                $active=$b && ($b['visits']>0 || $b['ms']>0 || $b['interactions']>0);
                $rows[]=$meta+['mode'=>$savedstate['mode']??'unknown','ms'=>$b && ($b['visits']>0 || $b['ms']>0)?$b['ms']:null,
                    'interactions'=>$active?$b['interactions']:null,'score'=>$person['grading']==='completion'?null:($savedstate['score']??null),
                    'state'=>!$active?'noactivity':(($savedstate['completed']??null)===true?'completed':'inprogress')];
            }
            $times=[];foreach ($person['attempts'] as $other) if(array_key_exists($id,$other['pagetimems']))$times[]=$other['pagetimems'][$id];
            $out[]=['id'=>$id,'title'=>$page->title,'state'=>$a['pagestate'][$id],'ms'=>$a['pagetimems'][$id]??null,
                'allms'=>$times?array_sum($times):null,'visits'=>$a['pagevisits'][$id]??null,
                'score'=>$person['grading']==='completion'?null:($a['pagescore'][$id]??null),
                'last'=>$a['pagelast'][$id]??null,'blocks'=>$rows];
        }
        return $out;
    }
    public static function comparison(array $items): array {
        $ids=[];$participations=0;$questions=[];$pages=[];$bins=array_fill_keys(['<50','50–59','60–69','70–79','80–89','90–100'],0);
        foreach ($items as $item) {
            $d=$item['data'];$participations+=$d['roster'];
            foreach ($d['learners'] as $id=>$person) $ids[$id]=true;
            foreach ($d['questions'] as $q) if ($q['n']>=self::MIN_SAMPLE) $questions[]=$q+['activity'=>$item['name'],'scopeparticipants'=>$d['roster']];
            foreach ($d['pages'] as $p) $pages[]=$p+['activity'=>$item['name']];
            foreach ($d['scoreBins'] as $bin=>$n) $bins[$bin]+=$n;
        }
        usort($questions,function($a,$b){return $a['accuracy']<=>$b['accuracy'];});
        usort($pages,function($a,$b){return ($b['reached']-$b['completed'])<=>($a['reached']-$a['completed']);});
        return ['unique'=>count($ids),'participations'=>$participations,'questions'=>array_slice($questions,0,5),'pages'=>array_slice($pages,0,5),'bins'=>$bins];
    }
}
