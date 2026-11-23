<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\output;
defined('MOODLE_INTERNAL') || die();

/** Human-readable usage details without treating content clicks as graded answers. */
final class dashboard_detail {
    public static function completion(array $tracks, array $pages): string {
        $done = [];
        foreach ($tracks as $track) {
            if (in_array($track->element, ['cmi.core.lesson_status','cmi.completion_status','cmi.success_status']) && in_array($track->value,['passed','completed'])) { $done[$track->scoid] = true; }
        }
        $required = array_filter($pages, function($p) { return !empty($p->launch) && ($p->seledascormtype ?? 'sco') === 'sco'; });
        return count(array_intersect_key($done, $required)) . ' / ' . count($required);
    }
    public static function titles($context, array $pages): array {
        $titles = [];
        foreach ($pages as $scoid => $page) {
            $launch = rawurldecode(preg_split('/[?#]/', $page->launch, 2)[0]);
            if (!$launch || preg_match('#^[a-z][a-z0-9+.-]*:|^[/\\\\]|(^|/)\.\.(/|$)#i', $launch)) { continue; }
            $dir = dirname($launch);
            $file = get_file_storage()->get_file($context->id, 'mod_seledascorm', 'content', 0,
                $dir === '.' ? '/' : '/' . trim($dir, '/') . '/', basename($launch));
            if (!$file || $file->get_filesize() > 10485760) { continue; }
            $dom = new \DOMDocument(); $before = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML('<?xml encoding="UTF-8">' . $file->get_content(), LIBXML_NONET);
                $xpath = new \DOMXPath($dom);
                foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " idevice_node ")]') as $node) {
                    foreach ($xpath->query('.//h1|.//h2|.//h3|.//h4|.//p', $node) as $text) {
                        $label = trim(preg_replace('/\s+/u', ' ', $text->textContent));
                        if ($label !== '' && !in_array(strtolower($label), ['text', 'untitled'])) {
                            $titles[$scoid . ':' . $node->getAttribute('id')] = mb_substr($label, 0, 120); break;
                        }
                    }
                }
            } finally { libxml_clear_errors(); libxml_use_internal_errors($before); }
        }
        return $titles;
    }

    public static function usage(array $usage, array $pages): string {
        $html = \mod_seledascorm\local\seleda_usage::table($usage, $pages, false);
        $h = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
        foreach ($usage['blocks'] as $block) {
            if (!$block['events']) { continue; }
            $html .= '<details><summary>' . $h(($pages[$block['scoid']] ?? '') . ' · ' . $block['title']) . '</summary><div class="sd-event-log"><table class="sd-table"><thead><tr>';
            foreach (['eventtime','eventaction','question','eventanswer','eventresult'] as $key) {
                $html .= '<th scope="col">' . $h(get_string('dash' . $key, 'mod_seledascorm')) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($block['events'] as $event) {
                $label = preg_replace('/^(\d+)(?=[A-Za-z])/', '$1 · ', $event['label']);
                $html .= '<tr><td>' . (int)$event['visit'] . ' · +' . \mod_seledascorm\local\seleda_usage::duration((int)$event['at']) .
                    '</td><td>' . $h(get_string('dashaction' . $event['action'], 'mod_seledascorm')) . '</td><td>' . $h($label) .
                    '</td><td>' . $h($event['value']) . '</td><td>' . $h(get_string('dashrecorded', 'mod_seledascorm')) . '</td></tr>';
            }
            $html .= '</tbody></table></div>' . (!empty($block['omitted']) ? '<p>' . (int)$block['omitted'] . ' ' . $h(get_string('usageomitted','mod_seledascorm')) . '</p>' : '') . '</details>';
        }
        return $html;
    }
}
