<?php
// This file is part of Moodle - https://moodle.org/
// SPDX-License-Identifier: GPL-3.0-or-later

namespace mod_seledascorm\local;

defined('MOODLE_INTERNAL') || die();

/** Standalone reporting with module permissions and activity-group scoping. */
final class reporting_dashboard {
    /** Discover permitted module contexts without requiring a course page. */
    private static function capability_scope(): array {
        global $USER;
        if (is_siteadmin()) {
            return ['1 = 1', []];
        }
        return \core\access\get_user_capability_course_helper::get_sql($USER->id, 'mod/seledascorm:viewreport');
    }

    /** Whether the main navigation should offer the dashboard. */
    public static function can_open(): bool {
        global $DB;
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (is_siteadmin()) {
            return true;
        }
        [$scope, $params] = self::capability_scope();
        if ($scope === false) {
            return false;
        }
        return $DB->record_exists_sql('SELECT cm.id FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {context} x ON x.instanceid = cm.id AND x.contextlevel = ' . CONTEXT_MODULE . '
                WHERE m.name = ? AND cm.deletioninprogress = 0 AND (' . $scope . ')',
            array_merge(['seledascorm'], $params));
    }

    /** Searchable, permission-filtered catalogue; no hidden activity names leak. */
    public static function catalogue(string $search = '', int $page = 0, int $size = 30): array {
        global $DB;
        [$scope, $params] = self::capability_scope();
        if ($scope === false) {
            return ['items' => [], 'more' => false];
        }
        $where = '';
        $params = array_merge(['seledascorm'], $params);
        if ($search !== '') {
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $where = ' AND (' . $DB->sql_like('s.name', '?', false) . ' OR ' . $DB->sql_like('c.fullname', '?', false) . ')';
            $params = array_merge($params, [$like, $like]);
        }
        $records = $DB->get_recordset_sql('SELECT cm.id, s.name, s.course, c.fullname AS coursename
                FROM {seledascorm} s JOIN {course_modules} cm ON cm.instance = s.id
                JOIN {modules} m ON m.id = cm.module JOIN {course} c ON c.id = s.course
                JOIN {context} x ON x.instanceid = cm.id AND x.contextlevel = ' . CONTEXT_MODULE . '
                WHERE m.name = ? AND cm.deletioninprogress = 0 AND (' . $scope . ')' . $where . '
                ORDER BY c.fullname, s.name, cm.id', $params);
        $items = [];
        $skip = max(0, $page) * $size;
        $more = false;
        try {
            foreach ($records as $record) {
                $course = get_course($record->course);
                if (!can_access_course($course)) {
                    continue;
                }
                $cm = get_fast_modinfo($course)->get_cm($record->id);
                if (!$cm->uservisible || !has_capability('mod/seledascorm:viewreport', $cm->context)) {
                    continue;
                }
                if ($skip-- > 0) {
                    continue;
                }
                if (count($items) === $size) {
                    $more = true;
                    break;
                }
                $items[] = $record;
            }
        } finally {
            $records->close();
        }
        return ['items' => $items, 'more' => $more];
    }

    /** Authorise a direct activity URL, not just catalogue selection. */
    public static function activity(int $cmid): array {
        global $DB;
        $record = get_coursemodule_from_id('seledascorm', $cmid, 0, false, MUST_EXIST);
        $course = get_course($record->course);
        require_login();
        if (!can_access_course($course)) {
            throw new \moodle_exception('nopermissiontoshow');
        }
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        if (!$cm->uservisible) {
            throw new \required_capability_exception($cm->context, 'mod/seledascorm:view', 'nopermissions', '');
        }
        require_capability('mod/seledascorm:viewreport', $cm->context);
        $activity = $DB->get_record('seledascorm', ['id' => $cm->instance], '*', MUST_EXIST);
        return [$activity, $cm, $course];
    }

    /** Return allowed groups plus a SQL filter that cannot be bypassed by URL parameters. */
    public static function group_scope($cm, int $group): array {
        global $DB;
        $mode = groups_get_activity_groupmode($cm);
        $groups = $mode ? (groups_get_activity_allowed_groups($cm) ?: []) : [];
        if ($group && !isset($groups[$group])) {
            throw new \moodle_exception('nopermissiontoshow');
        }
        $restricted = $mode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $cm->context);
        $ids = $group ? [$group] : ($restricted ? array_keys($groups) : []);
        if ($restricted && !$ids) {
            return [$groups, ' AND 1 = 0', []];
        }
        if (!$ids) {
            return [$groups, '', []];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'dashgroup');
        return [$groups, ' AND EXISTS (SELECT 1 FROM {groups_members} gm WHERE gm.userid = t.userid AND gm.groupid ' .
            $in . ')', $params];
    }

    /** Stream all allowed learners without retaining their full action histories in memory. */
    public static function overview($activity, $cm, int $group = 0): array {
        global $DB;
        require_capability('mod/seledascorm:viewreport', $cm->context);
        [$groups, $where, $params] = self::group_scope($cm, $group);
        $params['activity'] = $activity->id;
        $namefields = \core_user\fields::get_name_fields();
        $fields = implode(', ', array_map(function($name) { return 'u.' . $name; }, $namefields));
        $tracks = $DB->get_recordset_sql('SELECT t.*, ' . $fields . ' FROM {seledascorm_scoes_track} t
                JOIN {user} u ON u.id = t.userid WHERE t.seledascormid = :activity AND u.deleted = 0' . $where . '
                ORDER BY t.userid, t.attempt, t.scoid, t.id', $params);
        $pages = $DB->get_records('seledascorm_scoes', ['seledascorm' => $activity->id], 'sortorder, id');
        try {
            $result = self::summarise($tracks, $pages);
        } finally {
            $tracks->close();
        }
        $result['groups'] = $groups;
        return $result;
    }

    /** Pure aggregation: cumulative usage across attempts, completion from the latest attempt. */
    public static function summarise(iterable $tracks, array $pages): array {
        $learners = [];
        $blocks = [];
        $actions = [];
        $required = array_keys(array_filter($pages, function($p) {
            return !empty($p->launch) && ($p->scormtype ?? 'sco') === 'sco';
        }));
        foreach ($tracks as $track) {
            $userid = (int)$track->userid;
            if (!isset($learners[$userid])) {
                $learners[$userid] = ['id' => $userid, 'name' => fullname($track), 'attempts' => [], 'latest' => 0,
                    'first' => (int)$track->timemodified, 'last' => 0, 'ms' => 0, 'interactions' => 0,
                    'visits' => 0, 'tracked' => false, 'statuses' => [], 'scores' => []];
            }
            $learner = &$learners[$userid];
            $attempt = (int)$track->attempt;
            $learner['attempts'][$attempt] = true;
            $learner['first'] = min($learner['first'], (int)$track->timemodified);
            $learner['last'] = max($learner['last'], (int)$track->timemodified);
            if ($attempt > $learner['latest']) {
                $learner['latest'] = $attempt;
                $learner['statuses'] = [];
                $learner['scores'] = [];
            }
            if ($attempt === $learner['latest']) {
                if (in_array($track->element, ['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status'])) {
                    $learner['statuses'][$track->scoid][$track->element] = $track->value;
                }
                if (in_array($track->element, ['cmi.core.score.raw', 'cmi.score.raw']) && is_numeric($track->value)) {
                    $learner['scores'][$track->scoid] = (float)$track->value;
                }
            }
            if (strpos($track->element, 'seleda.usage.v1.') !== 0) {
                unset($learner);
                continue;
            }
            $data = json_decode($track->value, true);
            if (!is_array($data) || ($data['v'] ?? null) !== 1 || !isset($data['id'])) {
                unset($learner);
                continue;
            }
            $learner['tracked'] = true;
            $ms = max(0, (int)($data['ms'] ?? 0));
            $count = array_sum(array_map('intval', $data['actions'] ?? []));
            $learner['ms'] += $ms;
            $learner['interactions'] += $count;
            $learner['visits'] += (int)($data['visits'] ?? 0);
            $key = $track->scoid . ':' . $data['id'];
            if (!isset($blocks[$key])) {
                $blocks[$key] = ['title' => $data['title'], 'page' => $pages[$track->scoid]->title ?? '',
                    'ms' => 0, 'interactions' => 0, 'learners' => []];
            }
            $blocks[$key]['ms'] += $ms;
            $blocks[$key]['interactions'] += $count;
            $blocks[$key]['learners'][$userid] = true;
            foreach (($data['actions'] ?? []) as $action => $number) {
                $actions[$action] = ($actions[$action] ?? 0) + (int)$number;
            }
            unset($learner);
        }
        $statuses = ['completed' => 0, 'inprogress' => 0, 'failed' => 0];
        foreach ($learners as &$learner) {
            $complete = 0;
            $failed = false;
            foreach ($required as $scoid) {
                $values = $learner['statuses'][$scoid] ?? [];
                if (array_intersect($values, ['passed', 'completed'])) {
                    $complete++;
                }
                $failed = $failed || in_array('failed', $values);
            }
            $learner['status'] = $required && $complete === count($required) ? 'completed' : ($failed ? 'failed' : 'inprogress');
            $statuses[$learner['status']]++;
            $learner['attemptcount'] = count($learner['attempts']);
            unset($learner['statuses']);
        }
        unset($learner);
        uasort($learners, function($a, $b) { return [$b['last'], $a['id']] <=> [$a['last'], $b['id']]; });
        uasort($blocks, function($a, $b) { return $b['ms'] <=> $a['ms']; });
        arsort($actions);
        return ['learners' => $learners, 'blocks' => array_values($blocks), 'actions' => $actions, 'statuses' => $statuses,
            'ms' => array_sum(array_column($learners, 'ms')), 'interactions' => array_sum(array_column($learners, 'interactions')),
            'attempts' => array_sum(array_column($learners, 'attemptcount')), 'tracked' => count(array_filter($learners,
                function($l) { return $l['tracked']; })), 'pages' => $pages];
    }

    /** Detailed records remain scoped by activity, learner, attempt and allowed groups. */
    public static function detail($activity, $cm, int $userid, int $attempt, int $group = 0): array {
        global $DB;
        require_capability('mod/seledascorm:viewreport', $cm->context);
        [, $where, $params] = self::group_scope($cm, $group);
        $params += ['activity' => $activity->id, 'learner' => $userid, 'attempt' => $attempt];
        $records = $DB->get_records_sql('SELECT t.* FROM {seledascorm_scoes_track} t
                WHERE t.seledascormid = :activity AND t.userid = :learner AND t.attempt = :attempt' . $where, $params);
        if (!$records) {
            throw new \moodle_exception('nopermissiontoshow');
        }
        return $records;
    }
}
