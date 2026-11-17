<?php
// Universal attempt-scoped content-block usage. GPL-3.0-or-later.
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

final class seleda_usage {
    const PREFIX = 'seleda.usage.v1.';
    const ACTIONS = ['click', 'change', 'dragstart', 'drop', 'hover', 'play', 'pause', 'ended', 'seeked', 'embed'];

    private static function text($value, int $limit): string {
        if (!is_string($value)) { throw new \InvalidArgumentException('Invalid usage text'); }
        // DOM labels and learner values are already plain text. Preserve code
        // answers such as <div>; escape at the HTML/CSV output boundary.
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value)), 0, $limit, 'UTF-8');
    }
    private static function integer($value, int $max): int {
        if (!is_int($value) || $value < 0 || $value > $max) { throw new \InvalidArgumentException('Invalid usage number'); }
        return $value;
    }
    private static function validate(string $raw): array {
        if (strlen($raw) > 60000) { throw new \InvalidArgumentException('Usage request too large'); }
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (($data['v'] ?? null) !== 1 || !is_string($data['session'] ?? null) || !preg_match('/^[a-f0-9]{24}$/D', $data['session']) ||
                !is_array($data['blocks'] ?? null) || count($data['blocks']) > 32 || !$data['blocks']) {
            throw new \InvalidArgumentException('Invalid usage envelope');
        }
        $blocks = []; $seen = [];
        foreach ($data['blocks'] as $block) {
            $id = self::text($block['id'] ?? null, 200);
            if ($id === '' || isset($seen[$id])) { throw new \InvalidArgumentException('Invalid block identity'); }
            $seen[$id] = true;
            $clean = ['v'=>1, 'id'=>$id, 'title'=>self::text($block['title'] ?? null, 200), 'type'=>self::text($block['type'] ?? null, 80),
                'ms'=>self::integer($block['ms'] ?? null, 31536000000), 'visits'=>self::integer($block['visits'] ?? null, 1),
                'actions'=>[], 'dropped'=>self::integer($block['dropped'] ?? 0, 10000000), 'events'=>[]];
            if (!is_array($block['actions'] ?? null) || !is_array($block['events'] ?? null) || count($block['events']) > 40) {
                throw new \InvalidArgumentException('Invalid usage actions');
            }
            foreach ($block['actions'] as $name => $count) {
                if (!in_array($name, self::ACTIONS, true)) { throw new \InvalidArgumentException('Unknown usage action'); }
                $clean['actions'][$name] = self::integer($count, 10000000);
            }
            $previous = 0;
            foreach ($block['events'] as $event) {
                $n = self::integer($event['n'] ?? null, 10000000);
                if ($n <= $previous || !in_array($event['action'] ?? null, self::ACTIONS, true)) { throw new \InvalidArgumentException('Invalid event sequence'); }
                $previous = $n;
                $clean['events'][] = ['n'=>$n, 'at'=>self::integer($event['at'] ?? null, 31536000000), 'action'=>$event['action'],
                    'label'=>self::text($event['label'] ?? null, 160), 'value'=>self::text($event['value'] ?? '', 160)];
            }
            $blocks[] = $clean;
        }
        return [$data['session'], $blocks];
    }

    /** Request authentication, capability and sesskey are checked by datamodel.php. */
    public static function save(int $userid, int $activity, int $scoid, int $attempt, string $raw): void {
        global $DB;
        if ($attempt < 1 || !$DB->record_exists('seledascorm_scoes', ['id'=>$scoid, 'seledascorm'=>$activity])) {
            throw new \InvalidArgumentException('SCO does not belong to this activity');
        }
        [$session, $blocks] = self::validate($raw);
        // Keep late keepalive snapshots and retries from overwriting a newer save.
        $factory = \core\lock\lock_config::get_lock_factory('mod_seledascorm_seleda_usage');
        $lock = $factory->get_lock($activity . ':' . $userid . ':' . $attempt, 5);
        if (!$lock) { throw new \RuntimeException('Usage save is busy'); }
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($blocks as $block) {
                $where = ['userid'=>$userid, 'seledascormid'=>$activity, 'scoid'=>$scoid, 'attempt'=>$attempt,
                    'element'=>self::PREFIX . hash('sha256', $block['id']) . '.' . $session];
                $record = $DB->get_record('seledascorm_scoes_track', $where);
                $old = $record ? json_decode($record->value, true) : [];
                if (!is_array($old)) { $old = []; }
                $block['ms'] = max($old['ms'] ?? 0, $block['ms']);
                $block['visits'] = max($old['visits'] ?? 0, $block['visits']);
                $block['dropped'] = max($old['dropped'] ?? 0, $block['dropped']);
                foreach (($old['actions'] ?? []) as $name => $count) { $block['actions'][$name] = max($block['actions'][$name] ?? 0, $count); }
                $events = $old['events'] ?? []; $through = $old['through'] ?? 0;
                foreach ($block['events'] as $event) { if ($event['n'] > $through) { $events[] = $event; } }
                $block['through'] = max($through, $block['events'] ? end($block['events'])['n'] : 0);
                $block['omitted'] = ($old['omitted'] ?? 0) + max(0, count($events) - 2000);
                $block['events'] = array_slice($events, -2000);
                $block['started'] = $old['started'] ?? time();
                $value = json_encode($block, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if ($record) { $record->value = $value; $record->timemodified = time(); $DB->update_record('seledascorm_scoes_track', $record); }
                else { $DB->insert_record('seledascorm_scoes_track', (object)($where + ['value'=>$value, 'timemodified'=>time()])); }
            }
            $transaction->allow_commit();
        } catch (\Throwable $error) {
            if (isset($transaction)) { $transaction->rollback($error); }
            throw $error;
        } finally { $lock->release(); }
    }

    /** Input must already be scoped to one authorised learner and Moodle attempt. */
    public static function summary(array $tracks): array {
        $blocks = []; $seen = [];
        foreach ($tracks as $track) {
            if (strpos($track->element, self::PREFIX) !== 0) { continue; }
            $key = $track->scoid . ':' . $track->element;
            if (isset($seen[$key])) { continue; } $seen[$key] = true;
            $data = json_decode($track->value, true);
            if (!is_array($data) || ($data['v'] ?? null) !== 1 || !isset($data['id'])) { continue; }
            $key = $track->scoid . ':' . $data['id'];
            if (!isset($blocks[$key])) { $blocks[$key] = ['scoid'=>(int)$track->scoid, 'id'=>$data['id'], 'title'=>$data['title'], 'type'=>$data['type'], 'ms'=>0, 'visits'=>0, 'actions'=>[], 'interactions'=>0, 'events'=>[], 'omitted'=>0]; }
            $b = &$blocks[$key]; $b['ms'] += max(0, (int)($data['ms'] ?? 0)); $b['visits'] += (int)($data['visits'] ?? 0);
            foreach (($data['actions'] ?? []) as $action => $count) { $b['actions'][$action] = ($b['actions'][$action] ?? 0) + (int)$count; $b['interactions'] += (int)$count; }
            $session = substr($track->element, -24);
            $b['sessions'][$session] = $data['started'] ?? 0;
            foreach (($data['events'] ?? []) as $event) { $b['events'][] = $event + ['started'=>$data['started'] ?? 0, 'session'=>$session]; }
            $b['omitted'] += (int)($data['omitted'] ?? 0) + (int)($data['dropped'] ?? 0);
            unset($b);
        }
        foreach ($blocks as &$block) {
            asort($block['sessions']); $visits = array_flip(array_keys($block['sessions']));
            foreach ($block['events'] as &$event) { $event['visit'] = $visits[$event['session']] + 1; } unset($event);
            usort($block['events'], function($a,$b) { return [$a['started'],$a['visit'],$a['at'],$a['n']] <=> [$b['started'],$b['visit'],$b['at'],$b['n']]; });
            unset($block['sessions']);
        } unset($block);
        return ['blocks'=>array_values($blocks), 'ms'=>array_sum(array_column($blocks, 'ms')), 'interactions'=>array_sum(array_column($blocks, 'interactions')),
            'visits'=>array_sum(array_column($blocks, 'visits')), 'available'=>(bool)$blocks];
    }
    public static function duration(int $ms): string {
        $seconds = (int)floor($ms / 1000);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
    public static function compact(array $usage, array $pages = []): string {
        return implode('; ', array_map(function($b) use ($pages) {
            return ($pages[$b['scoid']] ?? (get_string('usagepage', 'mod_seledascorm') . ' ' . $b['scoid'])) . ' / ' . $b['title'] . ': ' . self::duration($b['ms']) . ', ' . $b['interactions'] . ' ' . get_string('usageinteractions', 'mod_seledascorm');
        }, $usage['blocks']));
    }
    public static function table(array $usage, array $pages = [], bool $events = false): string {
        $s = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
        $label = function($key) use ($s) { return $s(get_string($key, 'mod_seledascorm')); };
        if (!$usage['available']) { return '<p>' . $label('usagenone') . '</p>'; }
        $html = '<div class="seleda-usage"><p><strong>' . $label('usageactivetime') . ': ' . self::duration($usage['ms']) . '</strong> · ' . $usage['interactions'] . ' ' . $label('usageinteractions') . ' · ' . $usage['visits'] . ' ' . $label('usageblockvisits') . '</p>';
        $html .= '<div class="seleda-usage-scroll"><table class="generaltable"><thead><tr>';
        foreach (['usagepage', 'usageblock', 'usagetype', 'usageactivetime', 'usagevisits', 'usageinteractions'] as $key) {
            $html .= '<th scope="col">' . $label($key) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($usage['blocks'] as $b) {
            $html .= '<tr><td>' . $s($pages[$b['scoid']] ?? (get_string('usagepage', 'mod_seledascorm') . ' ' . $b['scoid'])) . '</td><td>' . $s($b['title']) . '</td><td>' . $s($b['type']) . '</td><td>' . self::duration($b['ms']) . '</td><td>' . $b['visits'] . '</td><td>' . $b['interactions'] . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
        if ($events) {
            foreach ($usage['blocks'] as $b) {
                $html .= '<details><summary>' . $s($b['title']) . ': ' . $label('usagelog') . ' (' . $b['interactions'] . ')</summary><ol>';
                foreach ($b['events'] as $e) { $html .= '<li>' . $label('usagevisit') . ' ' . $e['visit'] . ' · +' . self::duration((int)$e['at']) . ' · ' . $s($e['action']) . ': ' . $s($e['label']) . ($e['value'] !== '' ? ' · ' . $s($e['value']) : '') . '</li>'; }
                $html .= '</ol>' . ($b['omitted'] ? '<p>' . $b['omitted'] . ' ' . $label('usageomitted') . '</p>' : '') . '</details>';
            }
        }
        return $html . '<p class="seleda-usage-note">' . $label('usagenote') . '</p></div>';
    }
}
