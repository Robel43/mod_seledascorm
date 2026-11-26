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
            '<label class="sr-only" for="sd-course">' . self::t('course') . '</label><select id="sd-course" name="course"><option value="0">' . self::t('allcourses') . '</option>' . implode('', array_map(function($id, $name) use ($m) { return '<option value="' . (int)$id . '"' . ((int)($m['filters']['course'] ?? 0) === (int)$id ? ' selected' : '') . '>' . self::h($name) . '</option>'; }, array_keys($m['courses'] ?? []), array_values($m['courses'] ?? []))) . '</select>' . '<input id="sd-catalogue-search" type="search" name="q" placeholder="' . self::t('searchactivities') . '" value="' .
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
        if (!empty($m['metrics']['groups'])) {
            $html .= '<form method="get" class="sd-group">' . self::fields($m['filters'], ['group', 'learner', 'attempt', 'page']) .
                '<label for="sd-group">' . self::t('group') . '</label><select id="sd-group" name="group"><option value="0">' .
                self::t('allowedgroups') . '</option>';
            foreach ($m['metrics']['groups'] as $group) {
                $html .= '<option value="' . (int)$group->id . '"' . ((int)$group->id === (int)$m['filters']['group'] ? ' selected' : '') .
                    '>' . self::h($group->name) . '</option>';
            }
            $html .= '</select><button type="submit">' . self::t('apply') . '</button></form>';
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
        $html .= '<section class="sd-card sd-completion"><h3>' . self::t('completion') . '</h3><p>' . self::simple('completionnote') . '</p><div class="sd-status-chart">';
        foreach ($data['statuses'] as $status=>$n) {
            $html .= '<a href="' . self::url($m,['state'=>$status,'page'=>0]) . '#sd-learners"><span>' . self::status($status) . '</span><span class="sd-chart-track"><span class="sd-' . $status . '" style="width:' . ($data['roster'] ? round($n*100/$data['roster'],2) : 0) . '%"></span></span><strong>' . $n . ' / ' . $data['roster'] . '</strong></a>';
        }
        $html .= '</div></section><section id="sd-learners" class="sd-card sd-learners"><div class="sd-section-heading"><h3>' . self::t('learners') . '</h3><a class="sd-button sd-secondary" href="' . self::url($m,['download'=>'csv']) . '">' . self::t('download') . '</a></div><form method="get" class="sd-filters">' . self::fields($m['filters'],['search','state','page','learner','attempt']) . '<label class="sr-only" for="sd-learner-search">' . self::t('searchlearners') . '</label><input type="search" id="sd-learner-search" name="search" value="' . self::h($m['filters']['search']) . '" placeholder="' . self::t('searchlearners') . '"><label class="sr-only" for="sd-state">' . self::t('status') . '</label><select id="sd-state" name="state"><option value="">' . self::t('allstatuses') . '</option>';
        foreach (array_keys($data['statuses']) as $status) $html .= '<option value="'.$status.'"'.($m['filters']['state']===$status?' selected':'').'>' . self::status($status) . '</option>';
        $html .= '</select><button type="submit">' . self::t('apply') . '</button></form>' . self::tablehead(['learner','status','score','attempts','activetime','lastaccess','details']);
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
        $h .= '<label>' . self::t('audience') . '<select name="staff"><option value="0">' . self::t('learnersonly') . '</option><option value="1"' . (!empty($m['filters']['staff']) ? ' selected' : '') . '>' . self::t('includestaff') . '</option></select></label>';
        $h .= '<label>' . self::t('type') . '<select name="type"><option value="">' . self::t('alltypes') . '</option>' . implode('', array_map(function($type) use ($m) { return '<option value="' . self::h($type) . '"' . (($m['filters']['type'] ?? '') === $type ? ' selected' : '') . '>' . self::h($type) . '</option>'; }, array_unique(array_merge(['text','quick-questions','complete','mcq-feedback','speaking-practice','map','dragdrop'], array_column($m['metrics']['blocks'] ?? [], 'type'))))) . '</select></label>';
        if (!empty($m['filters']['block'])) { $h .= '<input type="hidden" name="block" value="' . self::h($m['filters']['block']) . '"><a href="' . self::url($m, ['block'=>'']) . '">' . self::t('clearblock') . '</a>'; }
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
        $h='<a href="'.self::url($m,['learner'=>0,'attempt'=>0,'diagnostics'=>0]).'">← '.self::t('alllearners').'</a><section class="sd-card"><div class="sd-section-heading"><h2>'.self::h($d['name']).'</h2><form method="get" class="sd-attempt">'.self::fields($m['filters'],['attempt','diagnostics']).'<label for="sd-attempt">'.self::t('attempt').'</label><select name="attempt" id="sd-attempt">';
        foreach ($d['attempts'] as $n) $h.='<option value="'.$n.'"'.($n===$m['filters']['attempt']?' selected':'').'>'.$n.'</option>';
        $h.='</select><button type="submit">'.self::t('apply').'</button></form></div><p>'.self::t('attemptscope').'</p><p>'.self::t('status').': <strong>'.self::status($d['status']).'</strong> · '.self::t('score').': <strong>'.self::score($d).'</strong> · '.self::t('activetime').': <strong>'.self::h($d['active']).'</strong></p></section><details class="sd-card sd-answers"><summary>'.self::simple('answers').'</summary><p>'.self::simple('answersnote').'</p>'.self::tablehead(['page','question','response','correctanswer','result']);
        foreach ($d['questions'] as $q) {
            $h.='<tr>';foreach (['page','question','response','correct','result'] as $key) $h.='<td>'.self::h($q[$key]).'</td>'; $h.='</tr>';
        }
        if (!$d['questions']) $h.='<tr><td colspan="5">'.self::simple('noquestions').'</td></tr>';
        $h.='</tbody></table></div></details><p class="sd-note"><a href="'.self::url($m,['diagnostics'=>empty($m['filters']['diagnostics'])?1:0]).'">'.self::simple(empty($m['filters']['diagnostics'])?'diagnostics':'hidediagnostics').'</a></p>';
        if (!empty($m['filters']['diagnostics'])) $h.='<section class="sd-card sd-person-usage"><h3>'.self::simple('diagnostics').'</h3>'.$d['usagehtml'].'</section>';
        return $h;
    }
}
