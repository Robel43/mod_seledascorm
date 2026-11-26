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
        $html = '<div class="sd-dashboard"><header class="sd-heading"><div><span class="sd-eyebrow">' . self::t('workspace') .
            '</span><h1>' . self::t('title') . '</h1><p>' . self::t('intro') . '</p></div><span class="sd-private">' .
            self::t('private') . '</span></header><nav class="sd-tabs" aria-label="' . self::simple('views') . '"><a href="' . self::url($m,['view'=>'teacher','learner'=>0,'diagnostics'=>0]) . '">' . self::simple('teacher') . '</a><a href="' . self::url($m,['view'=>'analytics','learner'=>0,'diagnostics'=>0,'group'=>0,'block'=>'','type'=>'','catalogpage'=>0]) . '">' . self::simple('analytics') . '</a></nav><div class="sd-layout"><aside class="sd-catalogue"><details open><summary>' .
            self::t('chooseactivity') . '</summary><form method="get" action="' . self::url($m, []) . '">' .
            '<input type="hidden" name="view" value="' . self::h($m['filters']['view'] ?? 'teacher') . '"><label for="sd-catalogue-search" class="sr-only">' . self::t('searchactivities') . '</label>' .
            '<details class="sd-course-filter"><summary>' . self::t('course') . '</summary><label class="sr-only" for="sd-course">' . self::t('course') . '</label><select id="sd-course" name="course"><option value="0">' . self::t('allcourses') . '</option>' . implode('', array_map(function($id, $name) use ($m) { return '<option value="' . (int)$id . '"' . ((int)($m['filters']['course'] ?? 0) === (int)$id ? ' selected' : '') . '>' . self::h($name) . '</option>'; }, array_keys($m['courses'] ?? []), array_values($m['courses'] ?? []))) . '</select></details>' . '<input id="sd-catalogue-search" type="search" name="q" placeholder="' . self::t('searchactivities') . '" value="' .
            self::h($m['filters']['q']) . '"><button class="sd-search" type="submit">' . self::t('search') . '</button></form>' .
            '<nav aria-label="' . self::t('chooseactivity') . '" class="sd-activity-list">';
        foreach ($m['catalogue']['items'] as $item) {
            $active = (int)$m['filters']['id'] === (int)$item->id;
            $html .= '<a class="sd-activity' . ($active ? ' is-current' : '') . '"' . ($active ? ' aria-current="page"' : '') .
                ' href="' . self::url($m, ['view'=>'teacher', 'panel'=>'overview', 'diagnostics'=>0, 'id' => $item->id, 'group' => 0, 'learner' => 0, 'attempt' => 0, 'page' => 0,
                    'search' => '', 'state' => '']) . '"><span class="sd-activity-icon" aria-hidden="true">▤</span><span><strong>' .
                self::h($item->name) . '</strong><small>' . self::h($item->coursename) . '</small></span></a>';
        }
        if (!$m['catalogue']['items']) {
            $html .= '<p class="sd-muted">' . self::t('noactivities') . '</p>';
        }
        $html .= '</nav><div class="sd-pagination">';
        if ($m['filters']['catalogpage'] > 0) {
            $html .= '<a href="' . self::url($m, ['catalogpage' => $m['filters']['catalogpage'] - 1]) . '">' . self::t('previous') . '</a>';
        }
        if ($m['catalogue']['more']) {
            $html .= '<a href="' . self::url($m, ['catalogpage' => $m['filters']['catalogpage'] + 1]) . '">' . self::t('next') . '</a>';
        }
        $html .= '</div></details></aside><main class="sd-main">';
        if (($m['filters']['view'] ?? 'teacher') === 'analytics') { return $html . self::filters($m) . self::analytics($m) . '</main></div></div>'; }
        if (empty($m['activity'])) {
            return $html . '<section class="sd-card sd-welcome"><span class="sd-welcome-symbol" aria-hidden="true">▥</span><h2>' .
                self::t('welcome') . '</h2><p>' . self::t('selectprompt') . '</p></section></main></div></div>';
        }
        $html .= self::filters($m);
        $html .= '<section class="sd-context"><div><span class="sd-eyebrow">' . self::h($m['coursename']) . '</span><h2>' .
            self::h($m['activity']->name) . '</h2></div></section>';
        if (count($m['metrics']['groups'] ?? []) > 1 || !empty($m['filters']['group'])) {
            $html .= '<details class="sd-advanced"><summary>' . self::t('group') . '</summary><form method="get" class="sd-group">' . self::fields($m['filters'], ['group', 'learner', 'attempt', 'page']) .
                '<label for="sd-group">' . self::t('group') . '</label><select id="sd-group" name="group"><option value="0">' .
                self::t('allowedgroups') . '</option>';
            foreach ($m['metrics']['groups'] as $group) {
                $html .= '<option value="' . (int)$group->id . '"' . ((int)$group->id === (int)$m['filters']['group'] ? ' selected' : '') .
                    '>' . self::h($group->name) . '</option>';
            }
            $html .= '</select><button type="submit">' . self::t('apply') . '</button></form></details>';
        }
        if (!empty($m['detail'])) {
            return $html . self::detail($m) . '</main></div></div>';
        }
        $data = $m['metrics']['learning'];
        $html .= '<nav class="sd-tabs" aria-label="' . self::simple('sections') . '">';
        foreach (['overview','questions'] as $panel) $html .= '<a' . (($m['filters']['panel'] ?? 'overview') === $panel ? ' aria-current="page"' : '') . ' href="' . self::url($m,['panel'=>$panel,'page'=>0]) . '">' . self::simple($panel) . '</a>';
        $html .= '</nav>';
        if (($m['filters']['panel'] ?? '') === 'questions') return $html . self::questions($data) . '</main></div></div>';
        $cards = [
            ['started',(string)$data['started'],self::simple('startedhint')],
            ['completion',$data['started'] ? round($data['completed']*100/$data['started']).'%' : '—',$data['completed'].' / '.$data['started'].' '.self::simple('started')],
            ['average',$data['average'] === null ? '—' : round($data['average'],1).'%', $data['scored'].' '.self::simple('scoredhint')],
            ['typical',$data['median'] === null ? '—' : self::duration($data['median']),$data['timed'].' '.self::simple('timedhint')],
        ];
        $html .= self::cards($cards) . '<p class="sd-note">' . self::simple('measuresnote') . '</p>';
        if (!empty($data['recordedonly'])) $html .= '<p class="sd-note">' . self::simple('recordedonly') . '</p>';
        $html .= self::visuals($data, $m);
        $html .= '<section id="sd-learners" class="sd-card sd-learners"><div class="sd-section-heading"><h3>' . self::t('learners') . '</h3><a class="sd-button sd-secondary" href="' . self::url($m,['download'=>'csv']) . '">' . self::t('download') . '</a></div><form method="get" class="sd-filters">' . self::fields($m['filters'],['search','state','page','learner','attempt']) . '<label class="sr-only" for="sd-learner-search">' . self::t('searchlearners') . '</label><input type="search" id="sd-learner-search" name="search" value="' . self::h($m['filters']['search']) . '" placeholder="' . self::t('searchlearners') . '"><button type="submit">' . self::t('search') . '</button></form><nav class="sd-status-filters" aria-label="' . self::t('status') . '"><a' . ($m['filters']['state']===''?' aria-current="page"':'') . ' href="' . self::url($m,['state'=>'','page'=>0]) . '#sd-learners">' . self::t('allstatuses') . '</a>';
        foreach ($data['statuses'] as $state=>$count) $html.='<a' . ($m['filters']['state']===$state?' aria-current="page"':'') . ' href="' . self::url($m,['state'=>$state,'page'=>0]) . '#sd-learners">' . self::status($state) . ' <strong>'.$count.'</strong></a>';
        $html.='</nav>' . self::tablehead(['learner','status','score','attempts','activetime','lastaccess','details']);
        foreach ($m['rows'] as $learner) {
            $url=self::url($m,['learner'=>$learner['id'],'attempt'=>$learner['latest'],'diagnostics'=>0]);
            $html.='<tr><th scope="row">' . ($learner['latest'] ? '<a class="sd-learner-name" href="'.$url.'">'.self::h($learner['name']).'</a>' : self::h($learner['name'])) . (!empty($learner['staff']) ? '<small>'.self::t('stafflabel').'</small>' : '') . '</th><td><span class="sd-status sd-'.$learner['status'].'">'.self::status($learner['status']).'</span></td><td>'.self::score($learner).'</td><td>'.$learner['attemptcount'].'</td><td>'.($learner['ms']===null?self::simple('missingtime'):self::duration($learner['ms'])).'</td><td>'.($learner['last']?self::h(userdate($learner['last'])):'—').'</td><td>'.($learner['latest']?'<a href="'.$url.'">'.self::t('details').' →</a>':'—').'</td></tr>';
        }
        if (!$m['rows']) $html.='<tr><td colspan="7" class="sd-empty">'.self::t('nolearners').'</td></tr>';
        $html.='</tbody></table></div><div class="sd-pagination"><span>'.(int)$m['filteredcount'].' '.self::t('matching').'</span>';
        if ($m['filters']['page']>0) $html.='<a href="'.self::url($m,['page'=>$m['filters']['page']-1]).'">'.self::t('previous').'</a>';
        if (($m['filters']['page']+1)*25<$m['filteredcount']) $html.='<a href="'.self::url($m,['page'=>$m['filters']['page']+1]).'">'.self::t('next').'</a>';
        return $html.'</div></section></main></div></div>';
    }
    /** SVG figures remain visible even if a browser has an old cached stylesheet. */
    private static function visuals(array $data, array $m): string {
        $total=$data['roster']; $radius=64; $circumference=2*M_PI*$radius; $offset=0;
        $colors=['completed'=>'#129576','inprogress'=>'#4f7df4','failed'=>'#df9148','unknown'=>'#94a3b8','notstarted'=>'#d6deeb'];
        $h='<div class="sd-visuals"><section class="sd-card sd-completion"><h3>'.self::t('completion').'</h3><p>'.self::simple('progresshint').'</p><div class="sd-ring-layout"><svg class="sd-completion-svg" role="img" aria-labelledby="sd-ring-title" viewBox="0 0 180 180" width="180" height="180"><title id="sd-ring-title">'.self::t('completion').': '.$data['completed'].' / '.$data['started'].' '.self::simple('started').'</title><circle cx="90" cy="90" r="64" fill="none" stroke="#edf1f8" stroke-width="20"/>';
        foreach ($data['statuses'] as $state=>$n) {
            if (!$n || !$total) continue;
            $length=$n/$total*$circumference;
            $h.='<circle cx="90" cy="90" r="64" fill="none" stroke="'.$colors[$state].'" stroke-width="20" stroke-dasharray="'.round($length,3).' '.round($circumference,3).'" stroke-dashoffset="'.round(-$offset,3).'" transform="rotate(-90 90 90)"/>';
            $offset+=$length;
        }
        $h.='<text x="90" y="88" text-anchor="middle" fill="#192a46" font-size="28" font-weight="700">'.($data['started']?round($data['completed']*100/$data['started']).'%':'—').'</text><text x="90" y="110" text-anchor="middle" fill="#63748b" font-size="11">'.self::simple('completed').'</text></svg><div class="sd-status-chart">';
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
    private static function analytics(array $m): string {
        $items=$m['comparisons'];$started=0;$completed=0;
        foreach ($items as $item) {$started+=$item['data']['started'];$completed+=$item['data']['completed'];}
        $h='<section class="sd-context"><h2>'.self::simple('analytics').'</h2><a href="'.self::url($m,['download'=>'csv']).'">'.self::t('download').'</a></section><p class="sd-note">'.self::simple('analyticsscope').'</p><p><strong>'.$started.'</strong> '.self::simple('participations').' · <strong>'.$completed.' / '.$started.'</strong> '.self::simple('completed').'</p><section class="sd-card"><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
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
