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
            self::t('private') . '</span></header><div class="sd-layout"><aside class="sd-catalogue"><details open><summary>' .
            self::t('chooseactivity') . '</summary><form method="get" action="' . self::url($m, []) . '">' .
            '<label for="sd-catalogue-search" class="sr-only">' . self::t('searchactivities') . '</label>' .
            '<label class="sr-only" for="sd-course">' . self::t('course') . '</label><select id="sd-course" name="course"><option value="0">' . self::t('allcourses') . '</option>' . implode('', array_map(function($id, $name) use ($m) { return '<option value="' . (int)$id . '"' . ((int)($m['filters']['course'] ?? 0) === (int)$id ? ' selected' : '') . '>' . self::h($name) . '</option>'; }, array_keys($m['courses'] ?? []), array_values($m['courses'] ?? []))) . '</select>' . '<input id="sd-catalogue-search" type="search" name="q" placeholder="' . self::t('searchactivities') . '" value="' .
            self::h($m['filters']['q']) . '"><button class="sd-search" type="submit">' . self::t('search') . '</button></form>' .
            '<nav aria-label="' . self::t('chooseactivity') . '" class="sd-activity-list">';
        foreach ($m['catalogue']['items'] as $item) {
            $active = (int)$m['filters']['id'] === (int)$item->id;
            $html .= '<a class="sd-activity' . ($active ? ' is-current' : '') . '"' . ($active ? ' aria-current="page"' : '') .
                ' href="' . self::url($m, ['id' => $item->id, 'group' => 0, 'learner' => 0, 'attempt' => 0, 'page' => 0,
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
        $data = $m['metrics'];
        $count = count($data['learners']);
        $values = [
            ['learners', number_format($count), 'recordedlearners'],
            ['attempts', number_format($data['attempts']), 'allattempts'],
            ['activetime', $data['tracked'] ? self::duration($data['ms']) : '—', 'allattempts'],
            ['interactions', $data['tracked'] ? number_format($data['interactions']) : '—', 'recordedactions'],
        ];
        $html .= '<div class="sd-kpis">';
        foreach ($values as [$label, $value, $hint]) {
            $html .= '<section class="sd-card sd-kpi"><h3>' . self::t($label) . '</h3><strong>' . $value . '</strong><small>' . self::t($hint) . '</small></section>';
        }
        $html .= '</div><p class="sd-note">' . self::t('scope') . ' ' . self::t('coverage') . ': ' . $data['tracked'] . ' / ' . $count . '</p>';
        $html .= self::charts($data, $m);
        $html .= '<section id="sd-learners" class="sd-card sd-learners"><div class="sd-section-heading"><div><h3>' . self::t('learners') . '</h3><p>' .
            self::t('learnerhint') . '</p></div><a class="sd-button sd-secondary" href="' . self::url($m, ['download' => 'csv']) . '">' .
            self::t('download') . '</a></div><form method="get" class="sd-filters">' .
            self::fields($m['filters'], ['search', 'state', 'page', 'learner', 'attempt']) .
            '<label class="sr-only" for="sd-learner-search">' . self::t('searchlearners') . '</label><input type="search" id="sd-learner-search" name="search" value="' .
            self::h($m['filters']['search']) . '" placeholder="' . self::t('searchlearners') . '"><label class="sr-only" for="sd-state">' .
            self::t('status') . '</label><select id="sd-state" name="state"><option value="">' . self::t('allstatuses') . '</option>';
        foreach (['completed', 'inprogress', 'failed'] as $state) {
            $html .= '<option value="' . $state . '"' . ($m['filters']['state'] === $state ? ' selected' : '') . '>' . self::t($state) . '</option>';
        }
        $html .= '</select><button type="submit">' . self::t('apply') . '</button></form><div class="sd-table-scroll"><table class="sd-table"><caption class="sr-only">' .
            self::t('learners') . '</caption><thead><tr>';
        foreach (['learner', 'status', 'attempts', 'activetime', 'interactions', 'lastaccess', 'details'] as $key) {
            $html .= '<th scope="col">' . self::t($key) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($m['rows'] as $learner) {
            $url = self::url($m, ['learner' => $learner['id'], 'attempt' => $learner['latest']]);
            $html .= '<tr><th scope="row"><a class="sd-learner-name" href="' . $url . '">' . self::h($learner['name']) . '</a>' . (!empty($learner['staff']) ? '<small class="sd-muted">' . self::t('stafflabel') . '</small>' : '') . '</th><td><span class="sd-status sd-' .
                $learner['status'] . '">' . self::t($learner['status']) . '</span></td><td>' . $learner['attemptcount'] . '</td><td>' .
                ($learner['tracked'] ? self::duration($learner['ms']) : '<span class="sd-muted">' . self::t('nodata') . '</span>') . '</td><td>' .
                ($learner['tracked'] ? number_format($learner['interactions']) : '—') . '</td><td>' . self::h(userdate($learner['last'])) .
                '</td><td><a class="sd-detail-link" aria-label="' . self::t('details') . ': ' . self::h($learner['name']) . '" href="' . $url . '">' . self::t('details') . ' →</a></td></tr>';
        }
        if (!$m['rows']) {
            $html .= '<tr><td colspan="7" class="sd-empty">' . self::t('nolearners') . '</td></tr>';
        }
        $html .= '</tbody></table></div><div class="sd-pagination"><span>' . (int)$m['filteredcount'] . ' ' . self::t('matching') . '</span>';
        if ($m['filters']['page'] > 0) {
            $html .= '<a href="' . self::url($m, ['page' => $m['filters']['page'] - 1]) . '">' . self::t('previous') . '</a>';
        }
        if (($m['filters']['page'] + 1) * 25 < $m['filteredcount']) {
            $html .= '<a href="' . self::url($m, ['page' => $m['filters']['page'] + 1]) . '">' . self::t('next') . '</a>';
        }
        return $html . '</div></section></main></div></div>';
    }

    private static function charts(array $data, array $m): string {
        $total = count($data['learners']);
        $complete = $total ? round($data['statuses']['completed'] * 100 / $total, 2) : 0;
        $progress = $total ? round($data['statuses']['inprogress'] * 100 / $total, 2) : 0;
        $html = '<div class="sd-charts"><section class="sd-card sd-completion"><h3>' . self::t('completion') . '</h3><p>' . self::t('latestattempt') .
            '</p><div class="sd-donut-wrap"><div class="sd-donut' . ($total ? '' : ' is-empty') . '" role="img" aria-label="' . self::t('completed') . ': ' . $complete . '%" style="--done:' .
            $complete . '%;--progress:' . ($complete + $progress) . '%"><span><strong>' . round($complete) . '%</strong><small>' . self::t('completed') . '</small></span></div><ul class="sd-legend">';
        foreach ($data['statuses'] as $key => $number) {
            $html .= '<li><a href="' . self::url($m, ['state' => $key, 'page' => 0]) . '#sd-learners"><i class="sd-dot sd-dot-' . $key . '" aria-hidden="true"></i><span>' . self::t($key) . '</span><strong>' . $number . '</strong></a></li>';
        }
        $html .= '</ul></div></section><section class="sd-card sd-blocks"><div class="sd-section-heading"><div><h3>' . self::t('topblocks') .
            '</h3><p>' . self::t('allattempts') . '</p></div><div class="sd-chart-switch" role="group" aria-label="' . self::t('chartmeasure') .
            '"><button type="button" data-sd-measure="ms" aria-pressed="true">' . self::t('time') . '</button><button type="button" data-sd-measure="interactions" aria-pressed="false">' . self::t('interactions') . '</button></div></div>' .
            '<ol class="sd-bars" id="sd-block-bars" aria-live="polite">';
        // Keep both measures available; changing the measure sorts the displayed top eight.
        $blocks = $data['blocks'];
        $max = max(array_merge([1], array_column($blocks, 'ms')));
        foreach ($blocks as $index => $block) {
            $html .= '<li data-ms="' . (int)$block['ms'] . '" data-interactions="' . (int)$block['interactions'] . '"' . ($index >= 8 ? ' hidden' : '') .
                '><div><span>' . '<a href="' . self::url($m, ['block' => $block['key'] ?? '', 'page' => 0]) . '">' . self::h($block['title']) . '</a><small>' . self::h($block['page']) . '</small></span><strong class="sd-bar-value">' .
                self::duration($block['ms']) . '</strong></div><div class="sd-bar-track"><span style="width:' . round($block['ms'] * 100 / $max, 2) . '%"></span></div></li>';
        }
        $html .= '</ol>' . (!$blocks ? '<p class="sd-empty">' . self::t('nousage') . '</p>' : '') . '</section></div><section class="sd-card sd-actions"><h3>' .
            self::t('actiontypes') . '</h3><p>' . self::t('actionhint') . '</p><div class="sd-action-grid">';
        $max = max(array_merge([1], array_values($data['actions'])));
        foreach ($data['actions'] as $action => $value) {
            $html .= '<div><div class="sd-action-label"><span>' . self::t('action' . $action) . '</span><strong>' . number_format($value) .
                '</strong></div><div class="sd-bar-track"><span style="width:' . round($value * 100 / $max, 2) . '%"></span></div></div>';
        }
        if (!$data['actions']) {
            $html .= '<p class="sd-muted">' . self::t('noactions') . '</p>';
        }
        return $html . '</div></section>';
    }

    private static function filters(array $m): string {
        $h = '<form method="get" class="sd-card sd-filters">' . self::fields($m['filters'], ['from','to','staff','block','type','learner','attempt','page']);
        foreach (['from','to'] as $key) { $h .= '<label>' . self::t($key) . '<input type="date" name="' . $key . '" value="' . self::h($m['filters'][$key] ?? '') . '"></label>'; }
        $h .= '<label>' . self::t('audience') . '<select name="staff"><option value="0">' . self::t('learnersonly') . '</option><option value="1"' . (!empty($m['filters']['staff']) ? ' selected' : '') . '>' . self::t('includestaff') . '</option></select></label>';
        $h .= '<label>' . self::t('type') . '<select name="type"><option value="">' . self::t('alltypes') . '</option>' . implode('', array_map(function($type) use ($m) { return '<option value="' . self::h($type) . '"' . (($m['filters']['type'] ?? '') === $type ? ' selected' : '') . '>' . self::h($type) . '</option>'; }, array_unique(array_merge(['text','quick-questions','complete','mcq-feedback','speaking-practice','map','dragdrop'], array_column($m['metrics']['blocks'] ?? [], 'type'))))) . '</select></label>';
        if (!empty($m['filters']['block'])) { $h .= '<input type="hidden" name="block" value="' . self::h($m['filters']['block']) . '"><a href="' . self::url($m, ['block'=>'']) . '">' . self::t('clearblock') . '</a>'; }
        return $h . '<button type="submit">' . self::t('apply') . '</button><p class="sd-note">' . self::t('datenote') . '</p></form>';
    }

    private static function detail(array $m): string {
        $detail = $m['detail'];
        $html = '<a class="sd-back" href="' . self::url($m, ['learner' => 0, 'attempt' => 0]) . '">← ' . self::t('alllearners') .
            '</a><section class="sd-card sd-person"><div class="sd-section-heading"><div><span class="sd-eyebrow">' . self::t('learnerdetail') .
            '</span><h2>' . self::h($detail['name']) . '</h2></div><form method="get" class="sd-attempt">' .
            self::fields($m['filters'], ['attempt']) . '<label for="sd-attempt">' . self::t('attempt') . '</label><select id="sd-attempt" name="attempt">';
        foreach ($detail['attempts'] as $attempt) {
            $html .= '<option value="' . (int)$attempt . '"' . ((int)$attempt === $m['filters']['attempt'] ? ' selected' : '') . '>' . (int)$attempt . '</option>';
        }
        $html .= '</select><button type="submit">' . self::t('apply') . '</button></form></div><p class="sd-note">' . self::t('attemptscope') . '</p><p>' .
            self::t('score') . ': <strong>' . ($detail['score'] === null ? self::t('noscore') : self::h($detail['score'])) . '</strong></p></section>';
        $html .= '<div class="sd-kpis"><section class="sd-card"><h3>' . self::t('activetime') . '</h3><strong>' . self::h($detail['active'] ?? '—') . '</strong></section><section class="sd-card"><h3>' . self::t('completion') . '</h3><strong>' . self::h($detail['completion'] ?? '—') . '</strong></section><section class="sd-card"><h3>' . self::t('pagesvisited') . '</h3><strong>' . (int)($detail['visited'] ?? 0) . '</strong></section><section class="sd-card"><h3>' . self::t('answered') . '</h3><strong>' . (int)($detail['questionsanswered'] ?? 0) . '</strong></section><section class="sd-card"><h3>' . self::t('accuracy') . '</h3><strong>' . (!empty($detail['graded']) ? round($detail['correct'] * 100 / $detail['graded']) . '%' : '—') . '</strong></section></div>';
        $html .= '<section class="sd-card sd-person-usage"><h3>' . self::t('blockusage') . '</h3>' . $detail['usagehtml'] . '</section>';
        $html .= '<section class="sd-card"><h3>' . self::t('questionresults') . '</h3><div class="sd-table-scroll"><table class="sd-table"><thead><tr>';
        foreach (['page', 'block', 'question', 'response', 'correctanswer', 'result'] as $key) {
            $html .= '<th scope="col">' . self::t($key) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($detail['questions'] as $row) {
            $html .= '<tr>';
            foreach (['page', 'block', 'question', 'response', 'correct', 'result'] as $key) {
                $html .= '<td>' . self::h($row[$key]) . '</td>';
            }
            $html .= '</tr>';
        }
        if (!$detail['questions']) {
            $html .= '<tr><td colspan="6" class="sd-empty">' . self::t('noquestions') . '</td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }
}
