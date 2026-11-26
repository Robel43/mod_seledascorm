<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\output;
defined('MOODLE_INTERNAL') || die();

/** Accessible server-rendered reporting UI; charts always have textual equivalents. */
final class reporting_dashboard {
    private static function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        if (!empty($m['activity'])) $html.='<section class="sd-context"><h2>'.self::h($m['activity']->name).'</h2><p>'.self::h($m['coursename']).'</p>'.(!empty($m['updated'])?'<small>'.self::r('updated',userdate($m['updated'])).'</small>':'').'</section>';
        $html.=self::filter_bar($m);
        if (($m['filters']['view']??'teacher')==='analytics') return $html.self::analytics($m).'</main></div>';
        if (empty($m['activity'])) {
            $html.='<section class="sd-card"><h2>'.self::r('chooseactivity').'</h2><div class="sd-quick-picks">';
            foreach (array_slice($m['catalogue']['items'],0,6) as $item) $html.='<a href="'.self::url($m,['id'=>$item->id,'group'=>0]).'">'.self::h($item->name).'<small>'.self::h($item->coursename).'</small><small>'.self::r('accesspeople').': '.self::number($m['quickcounts'][$item->id]??null).'</small></a>';
            return $html.'</div></section></main></div>';
        }
        if (!empty($m['detail'])) return $html.self::detail($m).'</main></div>';
        $data=$m['metrics']['learning'];
        $html.='<nav class="sd-tabs" aria-label="'.self::r('sections').'">';
        foreach (['overview','questions'] as $panel) $html.='<a'.(($m['filters']['panel']??'overview')===$panel?' aria-current="page"':'').' href="'.self::url($m,['panel'=>$panel,'page'=>0]).'">'.self::r($panel==='overview'?'overview':'content').'</a>';
        $html.='</nav>';
        if (($m['filters']['panel']??'')==='questions') return $html.self::content($m).'</main></div>';
        $html.=self::kpis($data,$m).'<p class="sd-note">'.self::r('scope',(object)['total'=>$data['roster'],'started'=>$data['started'],'notstarted'=>$data['statuses']['notstarted']]).'</p>';
        $html.='<div class="sd-visuals">'.self::page_progress($data).self::hardest($data['questions'],5).'</div><a href="'.self::url($m,['panel'=>'questions']).'">'.self::r('seequestions').'</a>';
        $html.=self::learner_table($m).'<p class="sd-note">'.self::r('unknownnote').'</p>';
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
            ['attention','<a href="'.self::url($m,['state'=>'attention','page'=>0]).'#sd-learners">'.$d['needsattention'].'</a>',self::r('attentionbreakdown',(object)['failed'=>$d['attentionfailed'],'idle'=>$d['attentionidle']])],
            ['median',self::time($d['median']),self::sample_hint($d['timed'])],
            [$d['grading']==='completion'?'notscored':'average',$d['grading']==='completion'?self::r('completionbased'):($d['average']===null?'—':round($d['average'],1).'%'),$d['grading']==='completion'?self::r('completionhint'):self::sample_hint($d['scored'])]];
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
        foreach(array_slice($questions,0,$limit)as $q){$wrong=[];foreach($q['wrong']as $answer=>$n)$wrong[]=self::h($answer).' ('.$n.')';$h.='<tr><th scope="row">'.self::h($q['question']).'<small>'.self::h((isset($q['activity'])?$q['activity'].' · ':'').$q['page']).'</small></th><td>'.($q['n']<5?self::r('notenough',$q['n']):round($q['accuracy']).'% · '.$q['correct'].' / '.$q['n']).'</td><td>'.$q['answered'].' / '.$q['n'].'</td><td>'.implode('<br>',$wrong).'</td></tr>';}
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
    private static function content(array $m): string {
        $d=$m['metrics']['learning'];
        $h='<section class="sd-card"><h2>'.self::r('pages').'</h2>'.self::header_table(['page','reached','completed','inprogress','unknown','median']);
        foreach($d['pages']as $p)$h.='<tr><th scope="row">'.self::h($p['page']).'</th><td>'.$p['reached'].'</td><td>'.$p['completed'].'</td><td>'.$p['inprogress'].'</td><td>'.$p['unknown'].'</td><td>'.self::time($p['median']).'<small>'.self::sample_hint($p['timed']).'</small></td></tr>';
        $h.='</tbody></table></div></section>'.self::hardest($d['questions'],PHP_INT_MAX);
        $time=[];foreach($m['metrics']['blocks']as $b)if(!empty($b['timeknown']))$time[$b['scoid']]=['label'=>$b['page'],'value'=>($time[$b['scoid']]['value']??0)+$b['ms']];
        $h.=self::bar_chart('timebypage',array_values($time),true);
        $measure=$m['filters']['measure']??'time';$blocks=$m['metrics']['blocks'];
        usort($blocks,function($a,$b)use($measure){return $measure==='time'?$b['ms']<=>$a['ms']:$b['interactions']<=>$a['interactions'];});
        $h.='<section class="sd-card"><h2>'.self::r('mostused').'</h2><nav class="sd-tabs" aria-label="'.self::r('measure').'">';
        foreach(['time','interactions']as $key)$h.='<a'.($key===$measure?' aria-current="page"':'').' href="'.self::url($m,['measure'=>$key]).'">'.self::r($key).'</a>';
        $h.='</nav>'.self::header_table(['block','page',$measure,'people']);
        foreach(array_slice($blocks,0,20)as $b)$h.='<tr><th scope="row"><a href="'.self::url($m,['block'=>$b['key'],'page'=>0]).'">'.self::h($b['title']).'</a></th><td>'.self::h($b['page']).'</td><td>'.($measure==='time'?self::time(!empty($b['timeknown'])?$b['ms']:null):self::number($b['interactions'])).'</td><td>'.count($b['learners']).'</td></tr>';
        return $h.'</tbody></table></div></section>';
    }
    private static function header_table(array $keys): string {
        return '<div class="sd-table-scroll"><table class="sd-table"><thead><tr>'.implode('',array_map(function($k){return '<th scope="col">'.self::r($k).'</th>';},$keys)).'</tr></thead><tbody>';
    }
    private static function bar_chart(string $key,array $items,bool $time=false): string {
        $h='<section class="sd-card"><h2>'.self::r($key).'</h2>';
        if(!$items)return $h.'<p>'.self::r('nodata').'</p></section>';
        $max=max(1,max(array_column($items,'value')));$h.='<svg class="sd-rpt-chart" role="img" width="100%" viewBox="0 0 600 '.(count($items)*60).'"><title>'.self::r($key).'</title>';
        foreach($items as $i=>$item){$y=$i*60;$value=$time?self::time($item['value']):self::number($item['value']);$h.='<g><title>'.self::h($item['label']).': '.$value.'</title><text x="0" y="'.($y+16).'" fill="#324b70" font-size="13">'.self::h(mb_strimwidth($item['label'],0,52,'…','UTF-8')).'</text><text x="600" y="'.($y+16).'" text-anchor="end" fill="#192a46" font-size="13">'.$value.'</text><rect y="'.($y+28).'" width="600" height="10" rx="5" fill="#e3e9f2"/><rect y="'.($y+28).'" width="'.round($item['value']/$max*600,2).'" height="10" rx="5" fill="#4f7df4"/></g>';}
        $h.='</svg><ul class="sd-chart-values">';foreach($items as $item)$h.='<li>'.self::h($item['label']).': '.($time?self::time($item['value']):self::number($item['value'])).'</li>';
        return $h.'</ul></section>';
    }
    private static function insights_value($value,int $n,bool $time=false,string $suffix=''): string {
        return $n<5?self::r('notenough',$n):($value===null?'—':($time?self::time($value):self::number($value).$suffix)).'<small>'.self::r('sample',$n).'</small>';
    }
    private static function analytics(array $m): string {
        $items=$m['comparisons'];$summary=$m['insights']??\mod_seledascorm\local\report_view::comparison($items);
        $h='<section class="sd-card"><h2>'.self::r('participation').'</h2><p>'.self::r('insightsscope').'</p><dl class="sd-person-metrics"><div><dt>'.self::r('uniquepeople').'</dt><dd>'.$summary['unique'].'</dd></div><div><dt>'.self::r('participations').'</dt><dd>'.$summary['participations'].'</dd></div></dl></section>';
        $bars=[];foreach($items as $item)$bars[]=['label'=>$item['name'],'value'=>$item['data']['started']];
        $h.=self::bar_chart('participationactivity',$bars).'<section class="sd-card"><h2>'.self::r('completionactivity').'</h2>'.self::header_table(['activity','completion','completed']);
        foreach($items as $item){$d=$item['data'];$h.='<tr><th scope="row"><a href="'.self::url($m,['view'=>'teacher','id'=>$item['id'],'group'=>0,'learner'=>0]).'">'.self::h($item['name']).'</a><small>'.self::h($item['course']).'</small></th><td>'.self::insights_value($d['roster']?$d['completed']*100/$d['roster']:null,$d['roster'],false,'%').'</td><td>'.$d['completed'].' / '.$d['roster'].'</td></tr>';}
        $h.='</tbody></table></div></section><section class="sd-card"><h2>'.self::r('scoredistribution').'</h2>';
        foreach($items as $item){$d=$item['data'];$h.='<h3>'.self::h($item['name']).'</h3>';if($d['grading']==='completion'){$h.='<p>'.self::r('completionscorelimit').'</p>';continue;}
            if($d['scored']<5){$h.='<p>'.self::r('notenough',$d['scored']).'</p>';continue;}
            $bars=[];foreach($d['scoreBins']as $bin=>$n)$bars[]=['label'=>$bin,'value'=>$n];$h.='<p>'.self::r('sample',$d['scored']).'</p>'.self::bar_chart('scorebands',$bars);
        }
        $h.='</section><section class="sd-card"><h2>'.self::r('timeactivity').'</h2>'.self::header_table(['activity','median']);
        foreach($items as $item){$d=$item['data'];$h.='<tr><th scope="row">'.self::h($item['name']).'</th><td>'.self::insights_value($d['median'],$d['timed'],true).'</td></tr>';}
        $h.='</tbody></table></div></section><section class="sd-card"><h2>'.self::r('investigatepages').'</h2>'.self::header_table(['activity','page','unfinished']);
        foreach($summary['pages']as $p)$h.='<tr><td>'.self::h($p['activity']).'</td><th scope="row">'.self::h($p['page']).'</th><td>'.($p['reached']-$p['completed']).' / '.$p['reached'].'</td></tr>';
        $h.='</tbody></table></div></section>'.self::hardest($summary['questions'],5).'<section class="sd-card"><h2>'.self::r('retryoutcomes').'</h2>'.self::header_table(['activity','scorechange']);
        foreach($items as $item){$d=$item['data'];$h.='<tr><th scope="row">'.self::h($item['name']).'</th><td>'.($d['grading']==='completion'?self::r('completionscorelimit'):self::insights_value($d['improvement'],$d['paired'],false,' '.self::r('points'))).'</td></tr>';}
        $h.='</tbody></table></div></section><section class="sd-card"><h2>'.self::r('coverage').'</h2><p>'.self::r('coveragenote').'</p>'.self::header_table(['activity','recordedtime','recordedscore','readableanswers']);
        foreach($items as $item){$d=$item['data'];$h.='<tr><th scope="row">'.self::h($item['name']).'</th>';foreach(['timed','scored','readable']as $key)$h.='<td>'.self::insights_value($d['started']?$d[$key]*100/$d['started']:null,$d['started'],false,'%').' · '.$d[$key].' / '.$d['started'].'</td>';$h.='</tr>';}
        $h.='</tbody></table></div></section><nav class="sd-pagination">';
        if($m['filters']['catalogpage']>0)$h.='<a href="'.self::url($m,['catalogpage'=>$m['filters']['catalogpage']-1]).'">'.self::r('previous').'</a>';
        if($m['comparisonmore'])$h.='<a href="'.self::url($m,['catalogpage'=>$m['filters']['catalogpage']+1]).'">'.self::r('next').'</a>';
        return $h.'</nav>';
    }
    private static function detail(array $m): string {
        return self::learner_header($m).self::page_table($m).self::learner_tabs($m);
    }
    private static function learner_header(array $m): string {
        $d=$m['detail'];$p=$d['person'];$a=$d['selected'];
        $h='<a class="sd-back" href="'.self::url($m,['learner'=>0,'attempt'=>0,'diagnostics'=>0]).'">'.self::r('alllearners').'</a><section class="sd-card sd-learner-header"><div class="sd-section-heading"><h2>'.self::h($p['name']).'</h2><span>'.self::r($p['role']).'</span></div>';
        $h.='<p>'.self::chip($a['status']).' '.($a['result']?self::chip($a['result'],true):'—').'</p><form method="get" class="sd-attempt">'.self::fields($m['filters'],['attempt','diagnostics']).'<label for="rpt-attempt">'.self::r('moodleattempt').'</label><select name="attempt" id="rpt-attempt">';
        foreach ($p['attempts'] as $n=>$unused) $h.='<option value="'.$n.'"'.($n===$d['attempt']?' selected':'').'>'.$n.'</option>';
        $h.='</select><button type="submit">'.self::r('apply').'</button></form>';
        $selected=$p;foreach (['pagescompleted','pagestotal'] as $key)$selected[$key]=$a[$key];$selected['progress']=$a['status']==='unknown'?null:($a['pagestotal']?$a['pagescompleted']/$a['pagestotal']:null);
        $h.='<p class="sd-person-progress">'.self::r('progress').': '.self::progress($selected).'</p><p>'.($a['status']==='unknown'?self::r('unknownnote'):self::r('personpagecounts',(object)['progress'=>$a['pagesinprogress'],'none'=>$a['pagesnotstarted']])).'</p><dl class="sd-person-metrics"><div><dt>'.self::r('thisattempt').'</dt><dd>'.self::time($a['timeknown']?$a['ms']:null).'</dd></div>';
        if (count($p['attempts'])>1)$h.='<div><dt>'.self::r('allattemptstime').'</dt><dd>'.self::time($p['ms']).'</dd></div>';
        $h.='<div><dt>'.self::r('last').'</dt><dd>'.($a['last']?self::h(userdate($a['last'])):'—').'</dd></div><div><dt>'.self::r('score').'</dt><dd>'.self::score_value(['score'=>$a['score'],'grading'=>$p['grading'],'maximum'=>$p['maximum']]).'</dd></div>';
        if ($d['history']['best']!==null)$h.='<div><dt>'.self::r('bestscore').'</dt><dd>'.self::number($d['history']['best']).' / '.self::number($p['maximum']).'</dd></div>';
        return $h.'</dl></section>';
    }
    private static function page_table(array $m): string {
        $d=$m['detail'];$all=count($d['person']['attempts'])>1;
        $h='<section class="sd-card sd-learner-pages"><h2>'.self::r('pages').'</h2><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach(array_merge(['page','status','activetime'], $all?['allattemptstime']:[],['visits','score','last','blocks'])as $key)$h.='<th scope="col">'.self::r($key).'</th>';
        $h.='</tr></thead><tbody>';
        foreach($d['pages']as $p){$h.='<tr><th scope="row">'.self::h($p['title']).'</th><td>'.self::chip($p['state']).'</td><td>'.self::time($p['ms']).'</td>'.($all?'<td>'.self::time($p['allms']).'</td>':'').'<td>'.self::number($p['visits']).'</td><td>'.self::number($p['score']).'</td><td>'.($p['last']?self::h(userdate($p['last'])):'—').'</td><td><details><summary>'.self::r('blocks').' ('.count($p['blocks']).')</summary>'.self::block_table($p['blocks']).'</details></td></tr>';}
        return $h.'</tbody></table></div><p class="sd-note">'.self::r('activenote').'</p></section>';
    }
    private static function block_table(array $blocks): string {
        if(!$blocks)return '<p>'.self::r('noblocks').'</p>';
        $h='<ul class="sd-block-list">';
        foreach($blocks as $b)$h.='<li><strong>'.self::h($b['title']).'</strong><dl><dt>'.self::r('type').'</dt><dd>'.($b['type']!==''?self::h($b['type']):'—').'</dd><dt>'.self::r('tracking').'</dt><dd>'.self::r('mode'.$b['mode']).'</dd><dt>'.self::r('activetime').'</dt><dd>'.self::time($b['ms']).'</dd><dt>'.self::r('interactions').'</dt><dd>'.self::number($b['interactions']).'</dd><dt>'.self::r('score').'</dt><dd>'.($b['score']===null?'—':self::number($b['score']).'%').'</dd><dt>'.self::r('status').'</dt><dd>'.self::r($b['state']).'</dd></dl></li>';
        return $h.'</ul>';
    }
    private static function learner_tabs(array $m): string {
        $tab=$m['filters']['tab']??'answers';$h='<nav class="sd-tabs sd-learner-tabs" aria-label="'.self::r('learnersections').'">';
        foreach(['answers','attempts','technical']as $key)$h.='<a'.($key===$tab?' aria-current="page"':'').' href="'.self::url($m,['tab'=>$key,'diagnostics'=>0]).'">'.self::r($key).'</a>';
        $h.='</nav>';
        return $h.($tab==='attempts'?self::attempts_tab($m):($tab==='technical'?self::technical_tab($m):self::answers_tab($m)));
    }
    private static function answer_cells(array $q): string {
        $credit=$q['credit'];$result=$credit===null?self::r('ungraded'):($credit>=1?self::r('correct'):($credit>0?self::r('partial').' ('.round($credit*100).'%)':self::r('incorrect')));
        return '<td>'.self::h($q['page']).'</td><td>'.($q['question']!==null?self::h($q['question']):self::r('questionunavailable')).'</td><td>'.($q['response']===''?'—':self::h($q['response'])).(!empty($q['shortened'])?' '.self::r('shortened'):'').'</td><td>'.($q['expected']===''?'—':self::h($q['expected'])).'</td><td>'.$result.'</td><td>'.($credit===null?'—':round($credit*100).'%').(!empty($q['retry'])?' · '.self::r('retry',$q['retry']):'').'</td>';
    }
    private static function answers_tab(array $m): string {
        $h='<section class="sd-card sd-answers"><h2>'.self::r('answers').'</h2><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach(['page','question','response','expected','result','credit']as $key)$h.='<th scope="col">'.self::r($key).'</th>';
        $h.='</tr></thead><tbody>';
        foreach($m['detail']['questions']as $q){$h.='<tr>'.self::answer_cells($q).'</tr>';if($q['earlier']){$h.='<tr><td colspan="6"><details><summary>'.self::r('earliersubmissions',count($q['earlier'])).'</summary>'.self::header_table(['page','question','response','expected','result','credit']);foreach($q['earlier']as $earlier)$h.='<tr>'.self::answer_cells($earlier).'</tr>';$h.='</tbody></table></div></details></td></tr>';}}
        if(!$m['detail']['questions'])$h.='<tr><td colspan="6">'.self::r('noquestions').'</td></tr>';
        return $h.'</tbody></table></div><p class="sd-note">'.self::r('retrynote').'</p><p class="sd-note">'.self::r('answerlimit').'</p></section>';
    }
    private static function attempts_tab(array $m): string {
        $d=$m['detail'];$p=$d['person'];$h='<section class="sd-card"><h2>'.self::r('attempts').'</h2><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach(['moodleattempt','status','score','activetime','pagescompleted','last']as $key)$h.='<th scope="col">'.self::r($key).'</th>';
        $h.='</tr></thead><tbody>';
        foreach($p['attempts']as $n=>$a)$h.='<tr><th scope="row">'.$n.'</th><td>'.self::chip($a['status']).'</td><td>'.self::score_value(['grading'=>$p['grading'],'score'=>$a['score'],'maximum'=>$p['maximum']]).'</td><td>'.self::time($a['timeknown']?$a['ms']:null).'</td><td>'.$a['pagescompleted'].' / '.$a['pagestotal'].'</td><td>'.($a['last']?self::h(userdate($a['last'])):'—').'</td></tr>';
        $h.='</tbody></table></div>';
        foreach(['best'=>'bestscore','change'=>'scorechange','extratime'=>'extratime']as $key=>$label)if($d['history'][$key]!==null)$h.='<p>'.self::r($label).': '.($key==='extratime'?self::time($d['history'][$key]):self::number($d['history'][$key]).($key==='change'?' '.self::r('points'):'' )).'</p>';
        return $h.'</section>';
    }
    private static function technical_tab(array $m): string {
        return '<section class="sd-card sd-person-usage"><h2>'.self::r('technical').'</h2><details><summary>'.self::r('opentechnical').'</summary>'.$m['detail']['usagehtml'].'</details></section>';
    }
}
