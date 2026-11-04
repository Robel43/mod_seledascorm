<?php
// Seleda shared reporting for mod_seledascorm. GPL-3.0-or-later.
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Versioned, block-independent labels and attempt-scoped response decoding. */
final class timhirt_reporting {
    const SNAPSHOT = 'timhirt.reporting.catalog.v1';

    public static function hash(string $value): string {
        $utf16 = mb_convert_encoding($value, 'UTF-16LE', 'UTF-8');
        $hash = 2166136261;
        for ($i = 0; $i < strlen($utf16); $i += 2) {
            $unit = ord($utf16[$i]) | (ord($utf16[$i + 1]) << 8);
            $hash = (($hash ^ $unit) * 16777619) & 0xffffffff;
        }
        return str_pad(dechex($hash), 8, '0', STR_PAD_LEFT);
    }

    private static function safeid(string $value): string {
        return trim(preg_replace('/[^A-Za-z0-9_.:-]+/', '-', $value), '-');
    }

    public static function interactionid(string $block, string $item, string $revision): string {
        return substr(self::safeid($block), 0, 100) . '-r1-' . $revision . '-'
            . substr(self::safeid($item), 0, 100) . '-' . self::hash($block . "\0" . $item);
    }

    public static function text($html): string {
        if (!is_scalar($html)) { return ''; }
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string)$html);
        $html = preg_replace_callback('/<img\b[^>]*\balt\s*=\s*([\'"])(.*?)\1[^>]*>/is', function($m) {
            return ' ' . $m[2] . ' ';
        }, $html);
        $html = preg_replace('/<\/(p|div|li|h[1-6])>|<br\s*\/?\s*>/i', ' ', $html);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function options($items): array {
        $result = [];
        if (!is_array($items)) { return $result; }
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id']) && is_scalar($item['id'])) {
                $result[(string)$item['id']] = self::text($item['label'] ?? '');
            }
        }
        return $result;
    }

    public static function catalog(string $html): array {
        $catalog = [];
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//*[@data-timhirt-reporting]') as $element) {
                $raw = $element->getAttribute('data-timhirt-reporting');
                if (strlen($raw) > 524288) { continue; }
                $definition = json_decode($raw, true);
                if (!is_array($definition) || ($definition['schema'] ?? null) !== 1 || !is_array($definition['items'] ?? null)) { continue; }
                $node = $element;
                while ($node instanceof \DOMElement && !in_array('idevice_node', preg_split('/\s+/', $node->getAttribute('class')))) {
                    $node = $node->parentNode;
                }
                if (!($node instanceof \DOMElement) || !$node->getAttribute('id')) { continue; }
                $blockid = $node->getAttribute('id');
                $revision = self::hash($raw);
                $heading = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " box-title ")]', $node)->item(0);
                $blocktitle = $heading ? self::text($heading->textContent) : self::text($definition['title'] ?? $definition['blockType'] ?? 'Content Block');
                $seen = [];
                foreach (array_slice($definition['items'], 0, 1000) as $item) {
                    if (!is_array($item) || !isset($item['id']) || !is_scalar($item['id'])) { continue; }
                    $localid = (string)$item['id'];
                    $key = self::interactionid($blockid, $localid, $revision);
                    if (isset($seen[$localid])) { unset($catalog[$key]); continue; }
                    $seen[$localid] = true;
                    $catalog[$key] = [
                        'block' => $blocktitle, 'blockType' => self::text($definition['blockType'] ?? ''),
                        'prompt' => self::text($item['prompt'] ?? ''), 'type' => self::text($item['type'] ?? 'performance'),
                        'options' => self::options($item['options'] ?? []),
                        'sources' => self::options($item['sources'] ?? []), 'targets' => self::options($item['targets'] ?? []),
                    ];
                }
            }
            // Read older MCQ exports too. This adapter only supplies labels;
            // the renderer and transport remain block-independent.
            foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " mcqf-question ")]') as $question) {
                $node = $question;
                while ($node instanceof \DOMElement && !in_array('idevice_node', preg_split('/\s+/', $node->getAttribute('class')))) { $node = $node->parentNode; }
                if (!($node instanceof \DOMElement) || !$question->getAttribute('data-qid')) { continue; }
                $id = substr(self::safeid($node->getAttribute('id') . '-' . $question->getAttribute('data-qid')), 0, 250);
                $prompt = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " mcqf-question-text ")]', $question)->item(0);
                $heading = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " box-title ")]', $node)->item(0);
                $options = [];
                foreach ($xpath->query('.//*[@data-cid]', $question) as $choice) {
                    $label = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " mcqf-choice-text ")]', $choice)->item(0);
                    if ($label) { $options[$choice->getAttribute('data-cid')] = self::text($label->textContent); }
                }
                $catalog[$id] = ['block' => $heading ? self::text($heading->textContent) : 'Multiple Choice',
                    'prompt' => $prompt ? self::text($prompt->textContent) : $id, 'type' => 'choice',
                    'options' => $options, 'legacy' => true];
            }
        } finally {
            libxml_clear_errors(); libxml_use_internal_errors($previous);
        }
        return $catalog;
    }

    /** Read only Moodle-managed local package files, never fetch external URLs. */
    public static function packagecatalog($context, $seledascorm, int $scoid): array {
        global $DB;
        $sco = $DB->get_record('seledascorm_scoes', ['id' => $scoid, 'seledascorm' => $seledascorm->id]);
        if (!$sco || empty($sco->launch)) { return []; }
        $launch = rawurldecode(preg_split('/[?#]/', $sco->launch, 2)[0]);
        if (preg_match('#^[a-z][a-z0-9+.-]*:|^[/\\\\]|(^|/)\.\.(/|$)#i', $launch)) { return []; }
        $launch = preg_replace('#^\./#', '', $launch);
        $directory = dirname($launch);
        $filepath = $directory === '.' ? '/' : '/' . trim($directory, '/') . '/';
        $file = get_file_storage()->get_file($context->id, 'mod_seledascorm', 'content', 0, $filepath, basename($launch));
        if (!$file || $file->get_filesize() > 10485760) { return []; }
        return self::catalog($file->get_content());
    }

    /** Metadata uses the existing tracking table; no grade or schema changes. */
    public static function snapshot($context, $seledascorm, int $scoid, int $userid, int $attempt): void {
        global $DB;
        $where = ['userid' => $userid, 'seledascormid' => $seledascorm->id, 'scoid' => $scoid,
            'attempt' => $attempt, 'element' => self::SNAPSHOT];
        $existing = $DB->get_record('seledascorm_scoes_track', $where);
        $previous = $existing ? json_decode($existing->value, true) : [];
        if (!is_array($previous)) { $previous = []; }
        if (($previous['_package_revision'] ?? null) === (int)$seledascorm->revision) { return; }
        $catalog = self::packagecatalog($context, $seledascorm, $scoid);
        if (!$catalog) { return; }
        $merged = $previous + $catalog;
        $merged['_package_revision'] = (int)$seledascorm->revision;
        $value = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($value === false || strlen($value) > 1048576 || ($existing && $existing->value === $value)) { return; }
        $record = (object)($where + ['value' => $value, 'timemodified' => time()]);
        if ($existing) { $record->id = $existing->id; $DB->update_record('seledascorm_scoes_track', $record); }
        else { $DB->insert_record('seledascorm_scoes_track', $record); }
    }

    private static function label(string $id, array $labels): string {
        return array_key_exists($id, $labels) ? (string)$labels[$id] : $id;
    }

    public static function response(string $raw, array $definition): string {
        $type = $definition['type'] ?? 'performance';
        if ($type === 'true-false') {
            if ($raw === 't' || $raw === 'true') { return 'True'; }
            if ($raw === 'f' || $raw === 'false') { return 'False'; }
        }
        if (!in_array($type, ['choice', 'sequencing', 'matching', 'likert'])) { return $raw; }
        $parts = preg_split('/\[,]\s*|,\s*/', $raw);
        $labels = $definition['options'] ?? [];
        $decoded = [];
        foreach ($parts as $part) {
            if ($type === 'matching') {
                $pair = preg_split('/\[\.\]|\./', $part, 2);
                $decoded[] = count($pair) === 2 ? self::label($pair[0], $definition['sources'] ?? $labels) . ' → '
                    . self::label($pair[1], $definition['targets'] ?? $labels) : $part;
            } else { $decoded[] = self::label($part, $labels); }
        }
        return implode($type === 'sequencing' ? ' → ' : '; ', $decoded);
    }

    public static function rows(array $tracks, array $fallback = []): array {
        $groups = []; $catalogs = $fallback; $snapshots = [];
        foreach ($tracks as $track) {
            $scoid = (int)$track->scoid;
            if ($track->element === self::SNAPSHOT) {
                $snapshot = json_decode($track->value, true);
                if (is_array($snapshot)) { $snapshots[$scoid] = $snapshot; $catalogs[$scoid] = $snapshot + ($catalogs[$scoid] ?? []); }
            }
            if (preg_match('/^cmi\.interactions[._](\d+)\.(.+)$/', $track->element, $match)) {
                $groups[$scoid][(int)$match[1]][$match[2]] = (string)$track->value;
            }
        }
        $rows = []; ksort($groups);
        foreach ($groups as $scoid => $interactions) {
            ksort($interactions);
            foreach ($interactions as $index => $interaction) {
                $id = $interaction['id'] ?? '';
                if ($id === '') { continue; }
                $definition = $catalogs[$scoid][$id] ?? [];
                $type = $interaction['type'] ?? $definition['type'] ?? 'performance';
                $definition['type'] = $type;
                $response = $interaction['student_response'] ?? $interaction['learner_response'] ?? '';
                $correct = [];
                foreach ($interaction as $key => $value) {
                    if (preg_match('/^correct_responses[._](\d+)\.pattern$/', $key, $match)) { $correct[(int)$match[1]] = $value; }
                }
                ksort($correct);
                $result = $interaction['result'] ?? '';
                $credit = $result === 'correct' ? 1 : (is_numeric($result) ? max(0, min(1, (float)$result)) : 0);
                $rows[] = [
                    'scoid' => $scoid, 'index' => $index, 'id' => $id, 'resolved' => !empty($definition['prompt']),
                    'legacy' => !empty($definition['legacy']), 'labelSource' => isset($snapshots[$scoid][$id]) ? 'snapshot' : ($definition ? 'package' : 'unavailable'),
                    'block' => $definition['block'] ?? '', 'type' => $type,
                    'question' => $definition['prompt'] ?? $id,
                    'response' => self::response($response, $definition),
                    'correct' => implode(' OR ', array_map(function($value) use ($definition) { return self::response($value, $definition); }, $correct)),
                    'result' => ['correct' => 'Correct', 'wrong' => 'Incorrect', 'incorrect' => 'Incorrect', 'neutral' => 'Recorded',
                        'unanticipated' => 'Unexpected response'][$result] ?? (is_numeric($result) ? 'Partial credit (' . round($credit * 100) . '%)' : $result),
                    'weight' => $credit * (float)($interaction['weighting'] ?? 1),
                ];
            }
        }
        return $rows;
    }

    /** Decode formatted upstream tracks without querying another learner or SCO. */
    public static function overviewcells(int $scoid, $trackdata, array $catalog, int $count, array $options): array {
        $tracks = [];
        foreach ((array)$trackdata as $element => $value) {
            if ($element === self::SNAPSHOT || strpos($element, 'cmi.interactions') === 0) {
                $tracks[] = (object)['scoid' => $scoid, 'element' => $element, 'value' => (string)$value];
            }
        }
        $byindex = [];
        foreach (self::rows($tracks, [$scoid => $catalog]) as $row) { $byindex[$row['index']] = $row; }
        $cells = [];
        for ($index = 0; $index < $count; $index++) {
            $row = $byindex[$index] ?? [];
            foreach (['qtext' => 'question', 'resp' => 'response', 'right' => 'correct', 'result' => 'result'] as $option => $field) {
                if (empty($options[$option])) { continue; }
                $text = $row[$field] ?? '';
                if ($field === 'question' && !empty($row['block'])) { $text = $row['block'] . ': ' . $text; }
                $cells[] = $text;
            }
        }
        return $cells;
    }

    public static function exportcell(string $value): string {
        return preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value;
    }
}
