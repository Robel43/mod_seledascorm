<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../report/timhirt_reporting.php');
require_once(__DIR__ . '/../../report/seleda_usage.php');

/** Compact learning measures from already-authorised records. Never writes grades. */
final class learning_insights {
    private $pages;
    private $activity;
    private $loader;
    private $questionsenabled;
    private $catalogs = [];
    private $people = [];
    private $firstanswers = [];

    public function __construct(array $pages, $activity, ?callable $loader = null, bool $questions = false) {
        $this->pages = array_filter($pages, function($p) { return !empty($p->launch) && ($p->seledascormtype ?? 'sco') === 'sco'; });
        $this->activity = $activity; $this->loader = $loader; $this->questionsenabled = $questions;
    }
    private function catalog(int $sco): array {
        if (!isset($this->catalogs[$sco])) { $this->catalogs[$sco] = $this->loader ? ($this->loader)($sco) : []; }
        return $this->catalogs[$sco];
    }
    /** Input is ordered by learner, attempt, SCO and record ID, as in overview(). */
    public function observe(iterable $records): iterable {
        $batch = []; $key = null;
        foreach ($records as $record) {
            $next = $record->userid . ':' . $record->attempt;
            if ($key !== null && $key !== $next) { $this->attempt($batch); yield from $batch; $batch = []; }
            $key = $next; $batch[] = $record;
        }
        if ($batch) { $this->attempt($batch); yield from $batch; }
    }
    private function attempt(array $records): void {
        $first = $records[0]; $id = (int)$first->userid; $number = (int)$first->attempt;
        if (!isset($this->people[$id])) {
            $this->people[$id] = ['id'=>$id, 'name'=>fullname($first), 'staff'=>!empty($first->dashboardstaff), 'attempts'=>[]];
        }
        $attempt = ['statuses'=>[], 'scores'=>[], 'reached'=>[], 'ms'=>0, 'timeknown'=>false, 'last'=>0, 'first'=>PHP_INT_MAX];
        $custom = []; $scos = [];
        foreach ($records as $track) {
            $sco = (int)$track->scoid; $scos[$sco] = true;
            if ($track->element !== timhirt_reporting::SNAPSHOT) {
                $attempt['last'] = max($attempt['last'], (int)$track->timemodified);
                $attempt['first'] = min($attempt['first'], (int)$track->timemodified);
            }
            if (in_array($track->element, ['cmi.core.lesson_status','cmi.completion_status','cmi.success_status'], true) && $track->value !== '') {
                $attempt['statuses'][$sco][$track->element] = $track->value; $attempt['reached'][$sco] = true;
            }
            if (in_array($track->element, ['cmi.core.score.raw','cmi.score.raw'], true) && is_numeric($track->value) && isset($this->pages[$sco])) { $attempt['scores'][$sco] = (float)$track->value; }
            if ($track->element === 'x.start.time') { $attempt['reached'][$sco] = true; }
            if (strpos($track->element, seleda_usage::PREFIX) !== 0) { continue; }
            $data = json_decode($track->value, true);
            if (!is_array($data) || ($data['v'] ?? null) !== 1 || !isset($data['id'])) { continue; }
            $ms = max(0, (int)($data['ms'] ?? 0));
            $attempt['ms'] += $ms;
            if ($ms || !empty($data['visits'])) { $attempt['timeknown'] = true; $attempt['reached'][$sco] = true; }
            if ($this->questionsenabled) {
                foreach (($data['events'] ?? []) as $event) {
                    if (($event['action'] ?? '') !== 'answer' || !in_array($event['result'] ?? '', ['correct','incorrect'], true)) { continue; }
                    $key = $sco . ':custom:' . hash('sha256', json_encode([$data['id'], $event['label'], $event['expected'] ?? '']));
                    $custom[] = ['key'=>$key, 'sco'=>$sco, 'prompt'=>$event['label'], 'response'=>$event['value'] ?? '',
                        'credit'=>$event['result'] === 'correct' ? 1 : 0, 'order'=>[(int)($data['started'] ?? $track->timemodified), (int)($event['at'] ?? 0), (int)($event['n'] ?? 0)]];
                }
            }
        }
        $complete = 0; $failed = false; $known = false;
        foreach ($this->pages as $sco => $page) {
            $status = $attempt['statuses'][$sco] ?? [];
            $complete += (bool)array_intersect($status, ['passed','completed']);
            $failed = $failed || in_array('failed', $status, true);
            $known = $known || (bool)$status;
        }
        $attempt['status'] = $this->pages && $complete === count($this->pages) ? 'completed' : ($failed ? 'failed' : ($known ? 'inprogress' : 'unknown'));
        $attempt['completedpages'] = $complete;
        if ($attempt['first'] === PHP_INT_MAX) $attempt['first'] = 0;
        $method = (string)($this->activity->grademethod ?? '1');
        $values = array_values($attempt['scores']);
        $attempt['score'] = $method !== '0' && $values ? ($method === '2' ? array_sum($values)/count($values) : ($method === '3' ? array_sum($values) : max($values))) : null;
        $maximum = (float)($this->activity->maxgrade ?? 100);
        $attempt['percent'] = $attempt['score'] !== null && $maximum > 0 ? max(0, min(100, $attempt['score']*100/$maximum)) : null;
        $this->people[$id]['attempts'][$number] = $attempt;
        if ($this->questionsenabled) {
            $catalogs = [];
            foreach (array_keys($scos) as $sco) { $catalogs[$sco] = $this->catalog($sco); }
            foreach (timhirt_reporting::rows($records, $catalogs) as $row) {
                if (empty($row['graded']) || empty($row['resolved'])) { continue; }
                $key = $row['scoid'] . ':native:' . preg_replace('/-a[1-9][0-9]*$/', '', $row['id']);
                $this->answer($id, $key, (int)$row['scoid'], $row['question'], $row['response'], $row['credit']);
            }
            usort($custom, function($a,$b) { return $a['order'] <=> $b['order']; });
            foreach ($custom as $row) { $this->answer($id,$row['key'],$row['sco'],$row['prompt'],$row['response'],$row['credit']); }
        }
    }
    private function answer(int $userid, string $key, int $sco, string $prompt, string $response, float $credit): void {
        if (isset($this->firstanswers[$key]['users'][$userid])) { return; }
        if (!isset($this->firstanswers[$key])) { $this->firstanswers[$key] = ['question'=>$prompt, 'page'=>$this->pages[$sco]->title ?? '', 'users'=>[], 'n'=>0, 'answered'=>0, 'correct'=>0, 'wrong'=>[]]; }
        $q = &$this->firstanswers[$key]; $q['users'][$userid] = true; $q['n']++;
        $q['answered'] += trim($response) !== '';
        $q['correct'] += $credit >= 1;
        if ($credit < 1) { $value = trim($response) === '' ? get_string('simpleblank','mod_seledascorm') : $response; $q['wrong'][$value] = ($q['wrong'][$value] ?? 0)+1; }
    }
    public static function median(array $values): ?float {
        if (!$values) { return null; } sort($values,SORT_NUMERIC); $n=count($values); return $n%2 ? (float)$values[intdiv($n,2)] : ($values[intdiv($n,2)-1]+$values[intdiv($n,2)])/2;
    }
    /** Roster is already permission/group scoped. Missing access is not a zero score. */
    public function report(array $roster = []): array {
        $mode = (string)($this->activity->grademethod ?? '1') === '0' ? 'completion' : 'unknown';
        foreach ($this->people as $person) { foreach ($person['attempts'] as $a) { if ($a['score'] !== null) { $mode = 'graded'; break 2; } } }
        if ($mode === 'unknown' && $this->loader) {
            $known = 0; $unknown = 0;
            foreach (array_keys($this->pages) as $sco) {
                $catalog = $this->catalog($sco);
                if (!$catalog) $unknown++;
                foreach ($catalog as $def) {
                if (!is_array($def)) { continue; } $m = $def['tracking']['mode'] ?? null;
                if ($m === 'graded') { $mode='graded'; break 2; }
                if (in_array($m,['off','completion'],true)) $known++; else $unknown++;
            } }
            if ($mode !== 'graded' && $known && !$unknown) $mode='ungraded';
        }
        $people = []; $times = []; $percents = []; $improvements = []; $statuses = array_fill_keys(['completed','inprogress','failed','unknown','notstarted'],0);
        $pagecounts = [];
        foreach ($this->pages as $id=>$page) { $pagecounts[$id]=['page'=>$page->title,'reached'=>0,'completed'=>0,'unfinished'=>0,'unknown'=>0]; }
        foreach ($roster as $id=>$person) { if (!isset($this->people[$id])) $people[$id]=['id'=>(int)$id,'name'=>fullname($person),'staff'=>!empty($person->dashboardstaff),'status'=>'notstarted','attemptcount'=>0,'latest'=>0,'score'=>null,'percent'=>null,'grading'=>$mode,'ms'=>null,'last'=>0,'first'=>0,'attempts'=>[]]; }
        foreach ($this->people as $id=>$person) {
            ksort($person['attempts']); $latest = max(array_keys($person['attempts'])); $a=$person['attempts'][$latest];
            $timed=array_filter($person['attempts'],function($v){return $v['timeknown'];});
            $ms=$timed ? array_sum(array_column($timed,'ms')) : null;
            if ($ms!==null) $times[]=$ms;
            if ($a['percent']!==null) $percents[]=$a['percent'];
            $scored=array_values(array_filter($person['attempts'],function($v){return $v['percent']!==null;}));
            if (count($scored)>1) $improvements[]=end($scored)['percent']-$scored[0]['percent'];
            foreach ($pagecounts as $sco=>&$p) {
                if (empty($a['reached'][$sco])) continue;
                $p['reached']++; $status=$a['statuses'][$sco]??[];
                if (array_intersect($status,['passed','completed'])) $p['completed']++;
                else if ($status) $p['unfinished']++; else $p['unknown']++;
            } unset($p);
            $people[$id]=$person+['maximum'=>(float)($this->activity->maxgrade??100),'status'=>$a['status'],'latest'=>$latest,'attemptcount'=>count($person['attempts']),'score'=>$a['score'],'percent'=>$a['percent'],'grading'=>$mode,'ms'=>$ms,
                'last'=>max(array_column($person['attempts'],'last')),'first'=>min(array_column($person['attempts'],'first'))];
        }
        foreach ($people as $person) $statuses[$person['status']]++;
        uasort($people,function($a,$b){return [$b['last'],$a['id']]<=>[$a['last'],$b['id']];});
        $questions=[];
        foreach ($this->firstanswers as $q) { unset($q['users']); arsort($q['wrong']); $q['wrong']=array_slice($q['wrong'],0,3,true); $q['accuracy']=$q['n']?$q['correct']*100/$q['n']:null; $questions[]=$q; }
        usort($questions,function($a,$b){return [$a['accuracy'],-$a['n'],$a['question']]<=>[$b['accuracy'],-$b['n'],$b['question']];});
        uasort($pagecounts,function($a,$b){return $b['unfinished']<=>$a['unfinished'];});
        return ['learners'=>$people,'statuses'=>$statuses,'started'=>count($this->people),'roster'=>count($people),'completed'=>$statuses['completed'],
            'average'=>$percents?array_sum($percents)/count($percents):null,'scored'=>count($percents),'median'=>self::median($times),'timed'=>count($times),
            'improvement'=>$improvements?array_sum($improvements)/count($improvements):null,'paired'=>count($improvements),'grading'=>$mode,
            'maximum'=>(float)($this->activity->maxgrade??100),'questions'=>$questions,'pages'=>array_values($pagecounts)];
    }
}
