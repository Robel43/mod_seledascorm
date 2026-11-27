<?php
define('MOODLE_INTERNAL', true);
function get_string($key, $component = null) { $string = []; require(__DIR__ . '/../../lang/en/seledascorm.php'); return $string[$key] ?? $key; }
require_once(__DIR__ . '/../../report/timhirt_reporting.php');
use mod_seledascorm\local\timhirt_reporting as reporting;
function check($condition, $message) { if (!$condition) { throw new Exception($message); } }
$vectors = json_decode(file_get_contents((getenv('TIMHIRT_REPORT_VECTORS') ?: __DIR__ . '/vectors.json')), true, 512, JSON_THROW_ON_ERROR);
$tracks = []; $html = ''; $expected = [];
foreach ($vectors as $index => $vector) {
    $scoid = $index + 1;
    check(reporting::hash($vector['raw']) === $vector['revision'], 'JS/PHP Unicode hash mismatch');
    check(reporting::interactionid($vector['block'], $vector['definition']['items'][0]['id'], $vector['revision']) === $vector['id'], 'Cross-language interaction ID mismatch');
    $page = '<article class="idevice_node" id="' . $vector['block'] . '"><h2 class="box-title">' . $vector['definition']['title']
        . '</h2><div data-timhirt-reporting="' . htmlspecialchars($vector['raw'], ENT_QUOTES, 'UTF-8') . '"></div></article>';
    $catalog = reporting::catalog($page);
    check(isset($catalog[$vector['id']]), 'Definition not indexed');
    $fields = ['id' => $vector['id'], 'type' => $vector['definition']['items'][0]['type'], 'student_response' => $vector['wireResponse'],
        'result' => $vector['result'], 'weighting' => '1'];
    if ($vector['wireCorrect'] !== null) { $fields['correct_responses_0.pattern'] = $vector['wireCorrect']; }
    $sample = [];
    foreach ($fields as $name => $value) { $sample[] = (object)['scoid' => $scoid, 'element' => 'cmi.interactions_0.' . $name, 'value' => $value]; }
    $sample[] = (object)['scoid' => $scoid, 'element' => reporting::SNAPSHOT, 'value' => json_encode($catalog)];
    $rows = reporting::rows($sample);
    check(count($rows) === 1, 'Expected one interaction');
    // Retries retain the same static labels but remain separate activity attempts.
    $retry = $sample;
    $retry[0] = clone $retry[0];
    $retry[0]->value .= '-a2';
    $retryrows = reporting::rows($retry);
    check($retryrows[0]['activityAttempt'] === 2, 'Activity attempt not decoded');
    check(strpos($retryrows[0]['question'], '(Activity attempt 2)') !== false, 'Retry label missing');
    check($retryrows[0]['response'] === $vector['expectedResponse'], 'Retry lost readable response');
    check($rows[0]['response'] === $vector['expectedResponse'], 'Unreadable response: ' . $vector['block']);
    check($rows[0]['correct'] === $vector['expectedCorrect'], 'Unreadable correct response: ' . $vector['block']);
    check($rows[0]['resolved'], 'Question text unresolved');
    $formatted = new stdClass();
    foreach ($sample as $track) { $formatted->{$track->element} = $track->value; }
    $overview = reporting::overviewcells($scoid, $formatted, [], 2, ['qtext' => 1, 'resp' => 1, 'right' => 1, 'result' => 1]);
    check(count($overview) === 8 && $overview[1] === $vector['expectedResponse'] && $overview[2] === $vector['expectedCorrect'], 'Overview contract decode mismatch');
    check(array_slice($overview, 4) === ['', '', '', ''], 'Missing overview interactions must keep empty columns');
    $tracks = array_merge($tracks, $sample);
}
check(count(reporting::rows($tracks)) === count($vectors), 'Interactions on separate SCOs were merged');
check(reporting::text('<script>alert(1)</script><b>Hello</b> &amp; <img src="x" alt="diagram">') === 'Hello & diagram', 'HTML must become safe readable text');
check(reporting::exportcell('=HYPERLINK("bad")')[0] === "'", 'Spreadsheet formula must be neutralised');
$unknown = [(object)['scoid' => 1, 'element' => 'cmi.interactions_0.id', 'value' => 'legacy-id']];
check(reporting::rows($unknown)[0]['question'] === 'legacy-id', 'Unresolved legacy ID must survive');
$legacy = '<article class="idevice_node" id="legacy"><h2 class="box-title">Old quiz</h2><section class="mcqf-question" data-qid="q1"><div class="mcqf-question-text">Old question</div><li data-cid="c1"><span class="mcqf-choice-text">Old answer</span></li></section></article>';
check(reporting::catalog($legacy)['legacy-q1']['options']['c1'] === 'Old answer', 'Old MCQ exports need readable labels');
// Snapshots override current-package labels, preserving an attempt's wording.
$old = ['legacy-id' => ['prompt' => 'Original question', 'type' => 'choice', 'options' => ['a' => 'Original answer']]];
$unknown[] = (object)['scoid' => 1, 'element' => reporting::SNAPSHOT, 'value' => json_encode($old)];
check(reporting::rows($unknown, [1 => ['legacy-id' => ['prompt' => 'Edited question']]])[0]['question'] === 'Original question', 'Snapshot must win over later package edits');
// Exercise snapshot persistence through the Moodle storage/DB boundary.
class reporting_fake_db {
    public $snapshot; public $writes = 0;
    function get_record($table, $where) {
        if ($table === 'seledascorm_scoes') { return ($where['id'] === 7 && $where['seledascorm'] === 9) ? (object)['launch' => 'index.html'] : false; }
        return $this->snapshot ?? false;
    }
    function insert_record($table, $record) { $record->id = 1; $this->snapshot = clone $record; $this->writes++; }
    function update_record($table, $record) { $this->snapshot = clone $record; $this->writes++; }
}
class reporting_fake_file {
    public $html;
    function get_filesize() { return strlen($this->html); }
    function get_content() { return $this->html; }
}
class reporting_fake_storage {
    public $file; public $reads = 0;
    function get_file(...$args) { $this->reads++; check($args === [3, 'mod_seledascorm', 'content', 0, '/', 'index.html'], 'Wrong content file scope'); return $this->file; }
}
function get_file_storage() { global $reporting_storage; return $reporting_storage; }
$DB = new reporting_fake_db(); $reporting_storage = new reporting_fake_storage();
$reporting_storage->file = new reporting_fake_file(); $reporting_storage->file->html = $legacy;
$activity = (object)['id' => 9, 'revision' => 1];
reporting::snapshot((object)['id' => 3], $activity, 7, 4, 1);
check($DB->snapshot->userid === 4 && $DB->snapshot->scoid === 7 && $DB->snapshot->attempt === 1, 'Metadata must belong to this learner/SCO/attempt');
reporting::snapshot((object)['id' => 3], $activity, 7, 4, 1);
check($DB->writes === 1 && $reporting_storage->reads === 1, 'Repeated commits must not reparse unchanged packages');
$activity->revision = 2; $reporting_storage->file->html = str_replace('Old question', 'Edited question', $legacy);
reporting::snapshot((object)['id' => 3], $activity, 7, 4, 1);
$saved = json_decode($DB->snapshot->value, true);
check($saved['legacy-q1']['prompt'] === 'Old question', 'An existing snapshot label must remain immutable');
// Optional preview fixture: the same decoded rows, escaped as the Moodle view does.
if (getenv('TIMHIRT_REPORT_PREVIEW')) {
    $html = '<!doctype html><meta charset="utf-8"><title>Readable learner attempt</title><style>body{font:16px system-ui;margin:40px;color:#17243b}table{border-collapse:collapse;width:100%}td,th{padding:16px;text-align:left;border-bottom:1px solid #dce2ea}th{background:#eef3f8}h1{font-size:28px}tr:nth-child(even){background:#f9fbfd}</style><h1>Interactions — learner attempt</h1><p>Shared reporting across Content Block types</p><table><thead><tr><th>Content Block</th><th>Question / activity</th><th>Learner response</th><th>Correct response</th><th>Result</th></tr></thead><tbody>';
    foreach (reporting::rows($tracks) as $row) {
        $html .= '<tr>';
        foreach (['block', 'question', 'response', 'correct', 'result'] as $column) { $html .= '<td>' . htmlspecialchars($row[$column], ENT_QUOTES, 'UTF-8') . '</td>'; }
        $html .= '</tr>';
    }
    file_put_contents(getenv('TIMHIRT_REPORT_PREVIEW'), $html . '</tbody></table>');
}
echo 'PASS: ' . count($vectors) . " block contracts, snapshots, multiple SCOs, legacy exports, HTML safety and CSV safety\n";
