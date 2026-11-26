<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\output;
defined('MOODLE_INTERNAL') || die();

/** Accessible server-rendered reporting UI; charts always have textual equivalents. */
final class reporting_dashboard {
    private static function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function t(string $key): string {
        return self::h(get_string('dash' . $key, 'mod_seledascorm'));
    }

    private static function url(array $model, array $change = []): string {
        return self::h((new \moodle_url('/mod/seledascorm/dashboard.php', array_merge($model['filters'], $change)))->out(false));
    }

    private static function duration($ms): string {
        return \mod_seledascorm\local\seleda_usage::duration((int)$ms);
    }

    private static function fields(array $filters, array $exclude): string {
        $html = '';
        foreach ($filters as $key => $value) {
            if (!in_array($key, $exclude)) {
                $html .= '<input type="hidden" name="' . self::h($key) . '" value="' . self::h($value) . '">';
            }
        }
        return $html;
    }

    /** Render a complete dashboard inside Moodle's standard page chrome. */
    public static function render(array $m): string {
        $html='<div class="sd-dashboard sd-redesign"><header class="sd-heading"><h1>'.self::r('title').'</h1></header>';
        $html.='<nav class="sd-tabs" aria-label="'.self::r('views').'">';
        foreach (['teacher'=>'activityreport','analytics'=>'insights'] as $view=>$key) $html.='<a'.(($m['filters']['view']??'teacher')===$view?' aria-current="page"':'').' href="'.self::url($m,['view'=>$view,'learner'=>0,'group'=>0,'catalogpage'=>0]).'">'.self::r($key).'</a>';
        $html.='</nav><main class="sd-main">';
        if (!empty($m['activity'])) $html.='<section class="sd-context"><h2>'.self::h($m['activity']->name).'</h2><p>'.self::h($m['coursename']).'</p></section>';
        $html.=self::filter_bar($m);
        if (($m['filters']['view']??'teacher')==='analytics') return $html.self::analytics($m).'</main></div>';
        if (empty($m['activity'])) {
            $html.='<section class="sd-card"><h2>'.self::r('chooseactivity').'</h2><div class="sd-quick-picks">';
            foreach (array_slice($m['catalogue']['items'],0,6) as $item) $html.='<a href="'.self::url($m,['id'=>$item->id,'group'=>0]).'">'.self::h($item->name).'<small>'.self::h($item->coursename).'</small></a>';
            return $html.'</div></section></main></div>';
        }
        if (!empty($m['detail'])) return $html.self::detail($m).'</main></div>';
        $data=$m['metrics']['learning'];
        $html.='<nav class="sd-tabs" aria-label="'.self::r('sections').'">';
        foreach (['overview','questions'] as $panel) $html.='<a'.(($m['filters']['panel']??'overview')===$panel?' aria-current="page"':'').' href="'.self::url($m,['panel'=>$panel,'page'=>0]).'">'.self::r($panel==='overview'?'overview':'content').'</a>';
        $html.='</nav>';
        if (($m['filters']['panel']??'')==='questions') return $html.self::questions($data).'</main></div>';
        $html.=self::kpis($data,$m).'<p class="sd-note">'.self::r('scope',(object)['total'=>$data['roster'],'started'=>$data['started'],'notstarted'=>$data['statuses']['notstarted']]).'</p>';
        $html.='<div class="sd-visuals">'.self::page_progress($data).self::hardest($data['questions'],5).'</div><a href="'.self::url($m,['panel'=>'questions']).'">'.self::r('seequestions').'</a>';
        $html.=self::learner_table($m);
        return $html.'</main></div>';
    }
    private static function r(string $key,$a=null): string { return self::h(get_string('rpt'.$key,'mod_seledascorm',$a)); }
    private static function chip(string $state,bool $result=false): string {
        $icons=['completed'=>'✓','inprogress'=>'◐','notstarted'=>'○','unknown'=>'?','unavailable'=>'?','passed'=>'✓','failed'=>'!'];
        return '<span class="sd-status sd-'.self::h($state).'"><span aria-hidden="true">'.($icons[$state]??'').'</span> '.self::r($state).'</span>';
    }
    private static function number($value): string { return $value===null?'—':self::h(round($value,1)); }
    private static function time($value): string { return $value===null?'—':self::duration($value); }
    private static function score_value(array $p): string {
        if (($p['grading']??'')==='completion') return '<span class="sd-muted">'.self::r('completionbased').'</span>';
        return $p['score']===null?'—':self::number($p['score']).' / '.self::number($p['maximum']??100);
    }
    private static function filter_bar(array $m): string {
        $f=$m['filters'];
        $h='<form method="get" class="sd-card sd-filter-bar" aria-label="'.self::r('filters').'">'.self::fields($f,['course','id','group','state','role','from','to','search','page','learner','attempt','staff','catalogpage']);
        $select=function($key,$label,$options)use($f){$h='<label for="rpt-'.$key.'">'.self::r($label).'<select id="rpt-'.$key.'" name="'.$key.'"'.(in_array($key,['course','id'])?' data-rpt-submit="'.$key.'"':'').'>';foreach($options as $id=>$name)$h.='<option value="'.self::h($id).'"'.((string)($f[$key]??'')===(string)$id?' selected':'').'>'.self::h($name).'</option>';return $h.'</select></label>';};
        $h.=$select('course','course',[0=>get_string('rptallcourses','mod_seledascorm')]+($m['courses']??[]));
        $h.='<label for="rpt-id">'.self::r('activity').'<select id="rpt-id" name="id" data-rpt-submit="id"><option value="0">'.self::r('chooseactivityshort').'</option>';
        $lastcourse=null;
        foreach($m['catalogue']['items'] as $item) {
            if (empty($f['course']) && $lastcourse!==$item->course) { if($lastcourse!==null)$h.='</optgroup>'; $h.='<optgroup label="'.self::h($item->coursename).'">';$lastcourse=$item->course; }
            $h.='<option value="'.(int)$item->id.'"'.((int)$f['id']===(int)$item->id?' selected':'').'>'.self::h($item->name).'</option>';
        }
        if($lastcourse!==null)$h.='</optgroup>';
        $h.='</select></label>';
        if (!empty($m['metrics']['groups'])) { $groups=[0=>get_string('rptallgroups','mod_seledascorm')];foreach($m['metrics']['groups']as $group)$groups[$group->id]=$group->name;$h.=$select('group','group',$groups); }
        $states=[''=>'allstatuses','completed'=>'completed','inprogress'=>'inprogress','notstarted'=>'notstarted','unknown'=>'unknown','attention'=>'attention'];
        $h.=$select('state','status',array_map(function($k){return get_string('rpt'.$k,'mod_seledascorm');},$states));
        $h.=$select('role','role',['all'=>get_string('rptallroles','mod_seledascorm'),'learner'=>get_string('rptlearners','mod_seledascorm'),'staff'=>get_string('rptstaff','mod_seledascorm')]);
        foreach(['from'=>'visitedfrom','to'=>'visitedto','search'=>'searchlearner']as $key=>$label)$h.='<label for="rpt-'.$key.'">'.self::r($label).'<input id="rpt-'.$key.'" type="'.($key==='search'?'search':'date').'" name="'.$key.'" value="'.self::h($f[$key]??'').'"></label>';
        $h.='<div class="sd-filter-actions"><button type="submit">'.self::r('apply').'</button><a href="'.self::url($m,['from'=>'','to'=>'','state'=>'','role'=>'all','block'=>'','type'=>'','search'=>'','page'=>0,'learner'=>0,'attempt'=>0,'group'=>0]).'">'.self::r('clear').'</a><a class="sd-button sd-secondary" href="'.self::url($m,['download'=>'csv']).'">'.self::r('download').'</a></div></form>';
        if(!empty($m['catalogue']['more']))$h.='<p class="sd-note">'.self::r('narrowcourses').'</p>';
        foreach(['block','type']as $key)if(!empty($f[$key]))$h.='<a class="sd-filter-chip" href="'.self::url($m,[$key=>'','page'=>0]).'">'.self::r($key).': '.self::h($key==='type'?$f[$key]:($m['blocktitle']??get_string('rptselectedblock','mod_seledascorm'))).' ×</a>';
        if(!empty($f['from'])||!empty($f['to'])||!empty($f['block'])||!empty($f['type']))$h.='<p class="sd-note">'.self::r('recordedonly').'</p>';
        return $h;
    }
    private static function sample_hint(int $n,bool $insights=false): string {
        return self::r('sample',$n).($n<5?' <span class="sd-small-sample">'.self::r('smallsample').'</span>':'');
    }
    private static function kpis(array $d,array $m): string {
        $scope=self::r('ofpeople',(object)['done'=>$d['completed'],'total'=>$d['roster']]);
        $cards=[['completion',$d['roster']?round($d['completed']*100/$d['roster']).'%':'—',$scope],
            ['notstarted',(string)$d['statuses']['notstarted'],self::r('ofpeople',(object)['done'=>$d['statuses']['notstarted'],'total'=>$d['roster']])],
            ['attention','<a href="'.self::url($m,['state'=>'attention','page'=>0]).'#sd-learners">'.$d['needsattention'].'</a>',self::r('attentionhint')],
            ['median',self::time($d['median']),self::sample_hint($d['timed'])],
            [$d['grading']==='completion'?'notscored':'average',$d['grading']==='completion'?self::r('notscored'):($d['average']===null?'—':round($d['average'],1).'%'),$d['grading']==='completion'?self::r('completionhint'):self::sample_hint($d['scored'])]];
        $h='<div class="sd-kpis">';foreach($cards as[$label,$value,$hint])$h.='<section class="sd-card sd-kpi"><h3>'.self::r($label).'</h3><strong>'.$value.'</strong><small>'.$hint.'</small></section>';return $h.'</div>';
    }
    private static function progress(array $p): string {
        if($p['progress']===null)return '—';$done=$p['pagescompleted'];$total=$p['pagestotal'];
        return '<span class="sd-progress" role="img" aria-label="'.self::r('pageprogress',(object)['done'=>$done,'total'=>$total]).'"><span style="width:'.($total?round($done*100/$total):0).'%"></span></span><span>'.$done.' / '.$total.'</span>';
    }
    private static function page_progress(array $d): string {
        $h='<section class="sd-card"><h2>'.self::r('wherestop').'</h2>';
        foreach($d['pages']as $p){$n=$d['roster'];$h.='<div class="sd-page-bar"><strong>'.self::h($p['page']).'</strong><svg role="img" viewBox="0 0 500 12" width="100%" height="12"><title>'.self::h($p['page']).': '.$p['completed'].' / '.$n.'</title><rect width="500" height="12" fill="#d6deeb"/><rect width="'.($n?round($p['reached']*500/$n,2):0).'" height="12" fill="#4f7df4"/><rect width="'.($n?round($p['completed']*500/$n,2):0).'" height="12" fill="#129576"/></svg><small>'.self::r('pagecounts',(object)['done'=>$p['completed'],'progress'=>$p['reached']-$p['completed'],'none'=>$n-$p['reached'],'total'=>$n]).'</small></div>';}
        return $h.'</section>';
    }
    private static function hardest(array $questions,int $limit=50): string {
        $h='<section class="sd-card"><h2>'.self::r('hardest').'</h2><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach(['question','accuracy','answered','commonwrong']as $key)$h.='<th scope="col">'.self::r($key).'</th>';
        $h.='</tr></thead><tbody>';
        foreach(array_slice($questions,0,$limit)as $q){$wrong=[];foreach($q['wrong']as $answer=>$n)$wrong[]=self::h($answer).' ('.$n.')';$h.='<tr><th scope="row">'.self::h($q['question']).'<small>'.self::h($q['page']).'</small></th><td>'.($q['n']<5?self::r('notenough',$q['n']):round($q['accuracy']).'% · '.$q['correct'].' / '.$q['n']).'</td><td>'.$q['answered'].' / '.$q['n'].'</td><td>'.implode('<br>',$wrong).'</td></tr>';}
        if(!$questions)$h.='<tr><td colspan="4">'.self::r('noquestions').'</td></tr>';
        return $h.'</tbody></table></div></section>';
    }
    private static function learner_table(array $m): string {
        $d=$m['metrics']['learning'];$h='<section id="sd-learners" class="sd-card"><h2>'.self::r('learners').'</h2><p>'.self::r('showing',(object)['shown'=>count($m['rows']),'total'=>$m['filteredcount']]).'</p><div class="sd-table-scroll"><table class="sd-table sd-learner-table"><thead><tr>';
        foreach(['name','status','progress','result','score','time','attempts','last','details']as $key){$sort=in_array($key,['result','details'])?null:$key;$active=($m['filters']['sort']??'last')===$sort;$h.='<th scope="col"'.($active?' aria-sort="'.(($m['filters']['dir']??'desc')==='asc'?'ascending':'descending').'"':'').'>' . ($sort?'<a href="'.self::url($m,['sort'=>$sort,'dir'=>$active&&($m['filters']['dir']??'desc')==='asc'?'desc':'asc','page'=>0]).'">'.self::r($key).'</a>':self::r($key)).'</th>';}
        $h.='</tr></thead><tbody>';
        foreach($m['rows']as $p){$url=self::url($m,['learner'=>$p['id'],'attempt'=>$p['latest'],'tab'=>'answers','diagnostics'=>0]);$h.='<tr><th scope="row">'.($p['latest']?'<a class="sd-learner-name" href="'.$url.'">'.self::h($p['name']).'</a>':self::h($p['name'])).'<small>'.self::r($p['role']).'</small></th><td>'.self::chip($p['status']).'</td><td>'.self::progress($p).'</td><td>'.($p['result']?self::chip($p['result'],true):'—').'</td><td>'.self::score_value($p).'</td><td>'.self::time($p['ms']).'</td><td>'.($p['attemptcount']?:'—').'</td><td>'.($p['last']?self::h(userdate($p['last'])):'—').'</td><td>'.($p['latest']?'<a href="'.$url.'">'.self::r('details').'</a>':'—').'</td></tr>';}
        if(!$m['rows'])$h.='<tr><td colspan="9">'.self::r('nolearners').'</td></tr>';
        $h.='</tbody></table></div><nav class="sd-pagination">';
        if($m['filters']['page']>0)$h.='<a href="'.self::url($m,['page'=>$m['filters']['page']-1]).'">'.self::r('previous').'</a>';
        if(($m['filters']['page']+1)*25<$m['filteredcount'])$h.='<a href="'.self::url($m,['page'=>$m['filters']['page']+1]).'">'.self::r('next').'</a>';
        return $h.'</nav></section>';
    }
    /** SVG figures remain visible even if a browser has an old cached stylesheet. */
    private static function visuals(array $data, array $m): string {
        $total=$data['roster']; $radius=64; $circumference=2*M_PI*$radius; $offset=0;
        $colors=['completed'=>'#129576','inprogress'=>'#4f7df4','failed'=>'#df9148','unknown'=>'#94a3b8','notstarted'=>'#d6deeb'];
        $h='<div class="sd-visuals"><section class="sd-card sd-completion"><h3>'.self::t('completion').'</h3><p>'.self::simple('progresshint').'</p><div class="sd-ring-layout"><svg class="sd-completion-svg" role="img" aria-labelledby="sd-ring-title" viewBox="0 0 180 180" width="180" height="180"><title id="sd-ring-title">'.self::t('completion').': '.$data['completed'].' / '.$total.'</title><circle cx="90" cy="90" r="64" fill="none" stroke="#edf1f8" stroke-width="20"/>';
        foreach ($data['statuses'] as $state=>$n) {
            if (!$n || !$total) continue;
            $length=$n/$total*$circumference;
            $h.='<circle cx="90" cy="90" r="64" fill="none" stroke="'.$colors[$state].'" stroke-width="20" stroke-dasharray="'.round($length,3).' '.round($circumference,3).'" stroke-dashoffset="'.round(-$offset,3).'" transform="rotate(-90 90 90)"/>';
            $offset+=$length;
        }
        $h.='<text x="90" y="88" text-anchor="middle" fill="#192a46" font-size="28" font-weight="700">'.($total ? $data['completed'].' / '.$total : '—').'</text><text x="90" y="110" text-anchor="middle" fill="#63748b" font-size="11">'.self::simple('completed').'</text></svg><div class="sd-status-chart">';
        foreach ($data['statuses'] as $state=>$n) $h.='<a href="'.self::url($m,['state'=>$state,'page'=>0]).'#sd-learners"><span class="sd-legend-dot" style="background:'.$colors[$state].'"></span><span>'.self::status($state).'</span><strong>'.$n.' / '.$total.'</strong></a>';
        $h.='</div></div></section><section class="sd-card sd-time-chart"><h3>'.self::simple('timechart').'</h3><p>'.self::simple('timecharthint').'</p>';
        $pages=[];
        foreach ($m['metrics']['blocks'] as $block) {
            $id=$block['scoid'];
            if (!isset($pages[$id])) $pages[$id]=['label'=>$block['page'],'ms'=>0,'learners'=>[]];
            $pages[$id]['ms']+=$block['ms']; $pages[$id]['learners']+=$block['learners'];
        }
        $pages=array_filter($pages,function($p){return $p['ms']>0;});
        uasort($pages,function($a,$b){return $b['ms']<=>$a['ms'];});$pages=array_slice($pages,0,5);
        if (!$pages) return $h.'<p class="sd-empty">'.self::simple('notimechart').'</p></section></div>';
        $maximum=max(array_column($pages,'ms'));$height=count($pages)*66;
        $h.='<svg role="img" aria-labelledby="sd-time-title" viewBox="0 0 500 '.$height.'" width="100%" class="sd-page-time-svg"><title id="sd-time-title">'.self::simple('timechart').'</title>';
        foreach ($pages as $i=>$p) {
            $y=$i*66;$label=mb_strimwidth($p['label'],0,46,'…','UTF-8');
            $h.='<g><title>'.self::h($p['label']).': '.self::duration($p['ms']).' · '.count($p['learners']).' '.self::t('learners').'</title><text x="0" y="'.($y+16).'" fill="#324b70" font-size="13">'.self::h($label).'</text><text x="500" y="'.($y+16).'" text-anchor="end" fill="#192a46" font-size="13">'.self::duration($p['ms']).'</text><rect x="0" y="'.($y+27).'" width="500" height="9" rx="4" fill="#edf1f8"/><rect x="0" y="'.($y+27).'" width="'.round($p['ms']/$maximum*500,2).'" height="9" rx="4" fill="#4f7df4"/><text x="0" y="'.($y+52).'" fill="#63748b" font-size="11">'.count($p['learners']).' '.self::t('learners').'</text></g>';
        }
        return $h.'</svg></section></div>';
    }
    private static function simple(string $key): string { return self::h(get_string('simple'.$key,'mod_seledascorm')); }
    private static function status(string $key): string { return in_array($key,['unknown','notstarted']) ? self::simple($key) : self::t($key); }
    private static function score(array $row): string {
        if ($row['score']!==null) return self::h(round($row['score'],2)) . (isset($row['maximum']) ? ' / '.self::h($row['maximum']) : '');
        return self::simple(in_array($row['grading']??'', ['ungraded','completion']) ? 'ungraded' : 'missingscore');
    }
    private static function cards(array $cards): string {
        $h='<div class="sd-kpis">';
        foreach ($cards as [$key,$value,$hint]) $h.='<section class="sd-card sd-kpi"><h3>'.self::simple($key).'</h3><strong>'.$value.'</strong><small>'.$hint.'</small></section>';
        return $h.'</div>';
    }
    private static function tablehead(array $keys): string {
        return '<div class="sd-table-scroll"><table class="sd-table"><thead><tr>'.implode('',array_map(function($k){return '<th scope="col">'.self::t($k).'</th>';},$keys)).'</tr></thead><tbody>';
    }
    private static function filters(array $m): string {
        $h = '<details class="sd-advanced"><summary>' . self::simple('filters') . '</summary><form method="get" class="sd-card sd-filters">' . self::fields($m['filters'], ['from','to','staff','block','type','learner','attempt','page']);
        foreach (['from','to'] as $key) { $h .= '<label>' . self::t($key) . '<input type="date" name="' . $key . '" value="' . self::h($m['filters'][$key] ?? '') . '"></label>'; }
        $h .= '<label class="sd-checkbox"><input type="checkbox" name="staff" value="1"' . (!empty($m['filters']['staff']) ? ' checked' : '') . '>' . self::t('includestaff') . '</label>';
        foreach (['block','type'] as $key) if (!empty($m['filters'][$key])) $h.='<input type="hidden" name="'.$key.'" value="'.self::h($m['filters'][$key]).'">';
        if (!empty($m['filters']['block'])) { $h .= '<a href="' . self::url($m, ['block'=>'']) . '">' . self::t('clearblock') . '</a>'; }
        return $h . '<button type="submit">' . self::t('apply') . '</button><p class="sd-note">' . self::t('datenote') . '</p></form></details>';
    }

    private static function questions(array $data): string {
        $h='<section class="sd-card"><h3>'.self::simple('questions').'</h3><p>'.self::simple('questionnote').'</p><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach (['question','firstaccuracy','commonwrong'] as $k) $h.='<th scope="col">'.self::simple($k).'</th>';
        $h.='</tr></thead><tbody>';
        foreach (array_slice($data['questions'],0,50) as $q) {
            $wrong=[];foreach ($q['wrong'] as $answer=>$n) $wrong[]=self::h($answer).' ('.$n.')';
            $h.='<tr><th scope="row">'.self::h($q['question']).'<small class="sd-muted">'.self::h($q['page']).'</small></th><td>'.round($q['accuracy']).'% · '.$q['correct'].' / '.$q['n'].'<small class="sd-muted">'.$q['answered'].' / '.$q['n'].' '.self::simple('answered').'</small></td><td>'.($wrong?implode('<br>',$wrong):'—').'</td></tr>';
        }
        if (!$data['questions']) $h.='<tr><td colspan="3">'.self::simple('noquestions').'</td></tr>';
        $h.='</tbody></table></div></section><section class="sd-card"><h3>'.self::simple('unfinished').'</h3><p>'.self::simple('unfinishednote').'</p><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach (['page','reached','unfinished','missingprogress'] as $k) $h.='<th>'.self::simple($k).'</th>';
        $h.='</tr></thead><tbody>';
        foreach (array_slice($data['pages'],0,5) as $p) $h.='<tr><th scope="row">'.self::h($p['page']).'</th><td>'.$p['reached'].'</td><td>'.$p['unfinished'].' / '.$p['reached'].'</td><td>'.$p['unknown'].' / '.$p['reached'].'</td></tr>';
        return $h.'</tbody></table></div></section>';
    }
    private static function participation_chart(array $items): string {
        if (!$items) return '';
        usort($items,function($a,$b){return $b['data']['started'] <=> $a['data']['started'];});
        $items=array_slice($items,0,5);$maximum=max(1,max(array_map(function($item){return $item['data']['started'];},$items)));
        $h='<section class="sd-card"><h3>'.self::simple('participationchart').'</h3><p>'.self::simple('participationhint').'</p><svg class="sd-participation-svg" role="img" aria-labelledby="sd-participation-title" width="100%" viewBox="0 0 700 '.(count($items)*60).'"><title id="sd-participation-title">'.self::simple('participationchart').'</title>';
        foreach ($items as $i=>$item) {
            $n=$item['data']['started'];$y=$i*60;$label=mb_strimwidth($item['name'],0,65,'…','UTF-8');
            $h.='<g><title>'.self::h($item['course'].' / '.$item['name']).': '.$n.' '.self::simple('started').'</title><text x="0" y="'.($y+16).'" fill="#324b70" font-size="13">'.self::h($label).'</text><text x="700" y="'.($y+16).'" text-anchor="end" fill="#192a46" font-size="13">'.$n.'</text><rect x="0" y="'.($y+28).'" width="700" height="10" rx="5" fill="#edf1f8"/><rect x="0" y="'.($y+28).'" width="'.round($n/$maximum*700,2).'" height="10" rx="5" fill="#129576"/></g>';
        }
        return $h.'</svg></section>';
    }
    private static function analytics(array $m): string {
        $items=$m['comparisons'];$started=0;$completed=0;
        foreach ($items as $item) {$started+=$item['data']['started'];$completed+=$item['data']['completed'];}
        $h='<section class="sd-context"><h2>'.self::simple('analytics').'</h2><a href="'.self::url($m,['download'=>'csv']).'">'.self::t('download').'</a></section><p class="sd-note">'.self::simple('analyticsscope').'</p><p><strong>'.$started.'</strong> '.self::simple('participations').' · <strong>'.$completed.' / '.$started.'</strong> '.self::simple('completed').'</p>' . self::participation_chart($items) . '<section class="sd-card"><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach (['activity','started','completion','average','typical','improvement'] as $k) $h.='<th scope="col">'.self::simple($k).'</th>';
        $h.='</tr></thead><tbody>';
        foreach ($items as $item) {
            $d=$item['data'];$h.='<tr><th scope="row"><a href="'.self::url($m,['view'=>'teacher','id'=>$item['id'],'learner'=>0,'group'=>0,'panel'=>'overview']).'">'.self::h($item['name']).'</a><small class="sd-muted">'.self::h($item['course']).'</small></th><td>'.$d['started'].'</td><td>'.($d['started']?round($d['completed']*100/$d['started']).'% · ':'').$d['completed'].' / '.$d['started'].'</td><td>'.($d['average']===null?'—':round($d['average'],1).'%').'<small class="sd-muted">'.$d['scored'].' '.self::simple('scored').'</small></td><td>'.($d['median']===null?'—':self::duration($d['median'])).'<small class="sd-muted">'.$d['timed'].' '.self::simple('timed').'</small></td><td>'.($d['improvement']===null?'—':sprintf('%+.1f',$d['improvement']).' '.self::simple('points')).'<small class="sd-muted">'.$d['paired'].' '.self::simple('paired').'</small></td></tr>';
        }
        if (!$items) $h.='<tr><td colspan="6">'.self::t('noactivities').'</td></tr>';
        $h.='</tbody></table></div><p class="sd-note">'.self::simple('improvementnote').'</p><div class="sd-pagination">';
        if ($m['filters']['catalogpage']>0) $h.='<a href="'.self::url($m,['catalogpage'=>$m['filters']['catalogpage']-1]).'">'.self::t('previous').'</a>';
        if ($m['comparisonmore']) $h.='<a href="'.self::url($m,['catalogpage'=>$m['filters']['catalogpage']+1]).'">'.self::t('next').'</a>';
        return $h.'</div></section>';
    }
    private static function detail(array $m): string {
        $d=$m['detail'];
        $h='<a href="'.self::url($m,['learner'=>0,'attempt'=>0,'diagnostics'=>0]).'">← '.self::t('alllearners').'</a><section class="sd-card"><div class="sd-section-heading"><h2>'.self::h($d['name']).'</h2>';
        if (count($d['attempts']) > 1) {
            $h.='<form method="get" class="sd-attempt">'.self::fields($m['filters'],['attempt','diagnostics']).'<label for="sd-attempt">'.self::t('attempt').'</label><select name="attempt" id="sd-attempt">';
        foreach ($d['attempts'] as $n) $h.='<option value="'.$n.'"'.($n===$m['filters']['attempt']?' selected':'').'>'.$n.'</option>';
        $h.='</select><button type="submit">'.self::t('apply').'</button></form>';
        } else { $h.='<span class="sd-muted">'.self::t('attempt').' '.(int)$m['filters']['attempt'].'</span>'; }
        $h.='</div><p>'.self::t('attemptscope').'</p><p>'.self::t('status').': <strong>'.self::status($d['status']).'</strong> · '.self::t('score').': <strong>'.self::score($d).'</strong> · '.self::t('activetime').': <strong>'.self::h($d['active']).'</strong></p></section><section class="sd-card sd-answers"><h3>'.self::simple('answers').'</h3><p>'.self::simple('answersnote').'</p>'.self::tablehead(['page','question','response','correctanswer','result']);
        foreach ($d['questions'] as $q) {
            $h.='<tr>';foreach (['page','question','response','correct','result'] as $key) $h.='<td>'.self::h($q[$key]).'</td>'; $h.='</tr>';
        }
        if (!$d['questions']) $h.='<tr><td colspan="5">'.self::simple('noquestions').'</td></tr>';
        $h.='</tbody></table></div></section><p class="sd-note"><a href="'.self::url($m,['diagnostics'=>empty($m['filters']['diagnostics'])?1:0]).'">'.self::simple(empty($m['filters']['diagnostics'])?'diagnostics':'hidediagnostics').'</a></p>';
        if (!empty($m['filters']['diagnostics'])) $h.='<section class="sd-card sd-person-usage"><h3>'.self::simple('diagnostics').'</h3>'.$d['usagehtml'].'</section>';
        return $h;
    }
}
