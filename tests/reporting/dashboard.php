<?php
// Execute actual dashboard aggregation, access boundaries and rendering with Moodle fixtures.
define('MOODLE_INTERNAL', true);
define('CONTEXT_MODULE', 70);
define('SEPARATEGROUPS', 1);
define('SQL_PARAMS_NAMED', 1);
define('MUST_EXIST', 1);
require_once(__DIR__ . '/../../classes/local/reporting_dashboard.php');
require_once(__DIR__ . '/../../classes/output/reporting_dashboard.php');
require_once(__DIR__ . '/../../report/seleda_usage.php');
require_once(__DIR__ . '/../../report/timhirt_reporting.php');
require_once(__DIR__ . '/../../classes/local/learning_insights.php');
use mod_seledascorm\local\reporting_dashboard as dashboard;
use mod_seledascorm\local\learning_insights;
function ensure($ok, $message) { if (!$ok) { throw new Exception($message); } }
function fullname($user) { return $user->firstname . ' ' . $user->lastname; }
function userdate($time) { return gmdate('Y-m-d H:i', $time); }
function get_string($key, $component = null) {
    $string = [];
    require(__DIR__ . '/../../lang/en/seledascorm.php');
    if (!isset($string[$key])) { throw new Exception('Missing language string ' . $key); }
    return $string[$key];
}
class moodle_url {
    private $url;
    function __construct($url, $params = []) { $this->url = $url . '?' . http_build_query($params); }
    function out($escaped = true) { return $this->url; }
}
class moodle_exception extends Exception {}
class required_capability_exception extends Exception {function __construct(...$args) { parent::__construct('Denied'); }}
eval('namespace core\access; class get_user_capability_course_helper {static function get_sql(...$a){return $GLOBALS["scope"];}}');
eval('namespace core_user; class fields {static function get_name_fields(){return ["firstname","lastname"];}}');
function isloggedin() { return $GLOBALS['loggedin']; }
function isguestuser() { return $GLOBALS['guest']; }
function is_siteadmin() { return $GLOBALS['admin']; }
function has_capability($capability, $context) {
    return $capability === 'moodle/site:accessallgroups' ? $GLOBALS['allgroups'] : $GLOBALS['permitted'] && $context->id !== 4;
}
function require_capability($capability, $context) { if (!has_capability($capability, $context)) { throw new moodle_exception('Denied'); } }
function require_login(...$args) { if (!isloggedin() || isguestuser()) { throw new moodle_exception('Login'); } }
function get_course($id) { return (object)['id' => $id, 'fullname' => 'Course ' . $id]; }
function can_access_course($course) { return $course->id !== 2; }
function get_coursemodule_from_id($name, $id, ...$args) { return (object)['id' => $id, 'course' => $id, 'instance' => 9]; }
function get_fast_modinfo($course) {
    return new class {function get_cm($id) { return (object)['id' => $id, 'instance' => 9, 'uservisible' => $id !== 3, 'context' => (object)['id' => $id]]; }};
}
function groups_get_activity_groupmode($cm) { return $GLOBALS['groupmode']; }
function groups_get_activity_allowed_groups($cm) { return $GLOBALS['groups']; }
class dashboard_recordset extends ArrayIterator {
    function close() { $GLOBALS['closed']++; }
}
class dashboard_db {
    public $lastsql = '', $lastparams = [], $detail = [], $tracks = [], $catalogue = [];
    function record_exists_sql($sql, $params) { return true; }
    function sql_like_escape($text) { return addcslashes($text, '%_'); }
    function sql_like($field, $placeholder, ...$args) { return $field . ' LIKE ' . $placeholder; }
    function get_in_or_equal($ids, $type, $prefix) {
        $params = []; foreach (array_values($ids) as $i => $id) { $params[$prefix . $i] = $id; }
        return ['IN (:' . implode(', :', array_keys($params)) . ')', $params];
    }
    function get_recordset_sql($sql, $params) {
        $this->lastsql = $sql; $this->lastparams = $params;
        return new dashboard_recordset(strpos($sql, 'FROM {seledascorm} s') !== false ? $this->catalogue : $this->tracks);
    }
    function get_records($table, $params, $sort) { return $GLOBALS['pages']; }
    function get_record($table, $params, ...$args) { return (object)['id' => 9, 'name' => 'English practice']; }
    function get_records_sql($sql, $params) { $this->lastsql = $sql; $this->lastparams = $params; return $this->detail; }
}
$USER = (object)['id' => 1];
$scope = ['x.path LIKE ?', ['/1/%']];
$loggedin = true; $guest = false; $admin = false; $permitted = true; $allgroups = false; $groupmode = 1;
$groups = [2 => (object)['id' => 2, 'name' => 'Class A']]; $closed = 0;
$DB = new dashboard_db();
$pages = [7 => (object)['title' => 'Introduction', 'launch' => 'index.html'], 8 => (object)['title' => 'Practice', 'launch' => 'practice.html'],
    9 => (object)['title' => 'Supporting asset', 'launch' => 'asset.html', 'seledascormtype' => 'asset']];
$tracks = [];
function track($user, $attempt, $sco, $element, $value) {
    global $tracks;
    $tracks[] = (object)['id' => count($tracks) + 1, 'userid' => $user, 'attempt' => $attempt, 'scoid' => $sco,
        'element' => $element, 'value' => $value, 'timemodified' => 1700000000 + count($tracks) * 60,
        'firstname' => [4 => 'Aster', 5 => 'Dawit', 6 => '<img src=x onerror=alert(1)>'][$user], 'lastname' => 'Learner'];
}
function usage($user, $attempt, $sco, $id, $ms, $actions) {
    track($user, $attempt, $sco, 'seleda.usage.v1.' . $id, json_encode(['v' => 1, 'id' => $id,
        'title' => $id === 'reading' ? 'Reading & examples' : '<script>window.injected=true</script>',
        'ms' => $ms, 'visits' => 1, 'actions' => $actions]));
}
track(4, 1, 7, 'cmi.core.lesson_status', 'completed');
track(4, 1, 8, 'cmi.core.lesson_status', 'passed');
usage(4, 1, 7, 'reading', 12000, ['click' => 1]);
usage(4, 1, 8, 'quiz', 6000, ['change' => 3]);
track(4, 2, 7, 'cmi.core.lesson_status', 'completed');
usage(4, 2, 7, 'reading', 2000, ['click' => 1]);
track(4, 2, 7, 'cmi.core.score.raw', '0');
track(5, 1, 7, 'cmi.core.lesson_status', 'completed');
track(5, 1, 8, 'cmi.core.lesson_status', 'passed');
track(6, 1, 7, 'cmi.completion_status', 'incomplete');
track(6, 1, 8, 'cmi.success_status', 'failed');
$data = dashboard::summarise($tracks, $pages);
ensure(count($data['learners']) === 3 && $data['attempts'] === 4, 'Learners or attempts counted incorrectly');
ensure($data['ms'] === 20000 && $data['interactions'] === 5 && $data['tracked'] === 1, 'Usage totals or legacy coverage incorrect');
ensure($data['learners'][4]['status'] === 'inprogress', 'Completion from an older attempt leaked into latest status');
ensure($data['learners'][4]['scores'][7] === 0.0, 'A real zero score was discarded');
ensure($data['learners'][5]['status'] === 'completed' && !$data['learners'][5]['tracked'], 'Legacy completion needs no fabricated usage');
ensure($data['statuses'] === ['completed' => 1, 'inprogress' => 1, 'failed' => 1], 'Completion graph disagrees with learner rows');
ensure($data['blocks'][0]['ms'] === 14000 && $data['actions']['change'] === 3, 'Block totals or action-type chart incorrect');
$cm = (object)['id' => 1, 'context' => (object)['id' => 1]];
[, $sql, $params] = dashboard::group_scope($cm, 0);
ensure(strpos($sql, 'gm.userid = t.userid') !== false && array_values($params) === [2], 'Separate-group restriction not bound to learner');
try { dashboard::group_scope($cm, 99); throw new Exception('Forged group accepted'); } catch (moodle_exception $e) {}
$groups = []; [, $sql] = dashboard::group_scope($cm, 0); ensure(strpos($sql, '1 = 0') !== false, 'Teacher without groups can read all learners');
$allgroups = true; [, $sql] = dashboard::group_scope($cm, 0); ensure($sql === '', 'Access-all-groups capability ignored'); $allgroups = false;
$groupmode = 2; [, $sql] = dashboard::group_scope($cm, 0); ensure($sql === '', 'Visible groups should support all groups'); $groupmode = 1;
$groups = [2 => (object)['id' => 2, 'name' => 'Class A']];
$scope = [false, false]; ensure(!dashboard::can_open(), 'Student without reporting permission sees dashboard');
$admin = true; ensure(dashboard::can_open(), 'Admin should see empty dashboard'); $admin = false;
$scope = ['x.path LIKE ?', ['/1/%']]; $guest = true; ensure(!dashboard::can_open(), 'Guest sees private navigation'); $guest = false;
foreach ([1, 2, 3, 4] as $id) { $DB->catalogue[] = (object)['id' => $id, 'course' => $id, 'name' => 'Activity ' . $id, 'coursename' => 'Course ' . $id]; }
$catalogue = dashboard::catalogue();
ensure(count($catalogue['items']) === 1 && $catalogue['items'][0]->id === 1, 'Hidden or unauthorised activity names leaked');
ensure($closed > 0, 'Catalogue cursor was not closed');
foreach ([2, 3, 4] as $id) {
    try { dashboard::activity($id); throw new Exception('Forged activity ID accepted'); } catch (moodle_exception $e) {} catch (required_capability_exception $e) {}
}
$permitted = false;
try { dashboard::overview((object)['id' => 9], $cm); throw new Exception('Denied overview accepted'); } catch (moodle_exception $e) {}
$permitted = true;
$DB->detail = [];
try { dashboard::detail((object)['id' => 9], $cm, 99, 6); throw new Exception('Missing scoped detail accepted'); } catch (moodle_exception $e) {}
ensure($DB->lastparams['activity'] === 9 && $DB->lastparams['learner'] === 99 && $DB->lastparams['attempt'] === 6 &&
    isset($DB->lastparams['dashgroup0']), 'Detail query lost learner, group, activity or attempt scope');
$data['groups'] = $groups;
$insights=new learning_insights($pages,(object)['grademethod'=>'1','maxgrade'=>100]);
iterator_to_array($insights->observe($tracks));
$data['learning']=$insights->report();
$model = ['filters' => ['id' => 1, 'q' => '', 'catalogpage' => 0, 'group' => 0, 'search' => '', 'state' => '', 'page' => 0,
    'learner' => 0, 'attempt' => 0], 'catalogue' => ['items' => [(object)['id' => 1, 'name' => 'English practice', 'coursename' => 'English 101']], 'more' => false],
    'activity' => (object)['name' => 'English practice'], 'coursename' => 'English 101', 'metrics' => $data,
    'rows' => array_values($data['learning']['learners']), 'filteredcount' => 3];
$html = \mod_seledascorm\output\reporting_dashboard::render($model);
// Date filters use immutable visit starts, never a row's overwritten save timestamp.
$dated = array_map(function($row) {
    $row = clone $row;
    if (strpos($row->element, 'seleda.usage.v1.') === 0) {
        $value = json_decode($row->value, true); $value['started'] = $row->attempt === 1 ? 1000 : 3000;
        $value['type'] = 'text'; $row->value = json_encode($value);
    }
    return $row;
}, $tracks);
$window = dashboard::summarise(dashboard::filter_records($dated, $cm, ['fromts'=>900,'tots'=>2000,'staff'=>1]), $pages);
ensure($window['ms'] === 18000 && $window['learners'][4]['latest'] === 1, 'Date range mixed visits or native attempt status');
$none = iterator_to_array(dashboard::filter_records($dated, $cm, ['staff'=>0]));
ensure(!$none, 'Staff/test attempts must be excluded when requested');
$oneblock = dashboard::summarise(dashboard::filter_records($dated, $cm, ['block'=>hash('sha256','8:quiz'),'staff'=>1]), $pages);
ensure($oneblock['ms'] === 6000 && count($oneblock['learners']) === 1, 'Chart drill-down did not restrict its block/learners');
$unknown = dashboard::summarise(dashboard::filter_records($tracks, $cm, ['fromts'=>900,'staff'=>1]), $pages);
ensure(!$unknown['tracked'], 'Undated history was assigned an invented date');
ensure(strpos($html, '<script>window.injected') === false && strpos($html, '&lt;img') !== false, 'Stored labels execute HTML');
ensure(strpos($html, '00:00:20') !== false && strpos($html, 'No time recorded') !== false, 'Totals or missing-data state lost in UI');
$empty = $model;
$empty['metrics'] = dashboard::summarise([], $pages) + ['groups' => []];
$empty['metrics']['learning']=(new learning_insights($pages,(object)[]))->report();
$empty['rows'] = []; $empty['filteredcount'] = 0;
$emptyhtml = \mod_seledascorm\output\reporting_dashboard::render($empty);
ensure(strpos($emptyhtml, '0 / 0') !== false && strpos($emptyhtml, 'No learners match') !== false,
    'An empty activity should show explicit empty states, not crash or invent failed learners');
if (in_array('--html', $argv)) {
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width, initial-scale=1"><meta charset="utf-8"><title>Seleda reports</title><style>body{font:16px system-ui;margin:0;padding:24px;background:#f7f9fd}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}' .
        file_get_contents(__DIR__ . '/../../dashboard.css') . '</style>' . $html . '<script>window.define=(name,deps,factory)=>factory().init();</script><script>' .
        file_get_contents(__DIR__ . '/../../amd/build/reporting_dashboard.min.js') . '</script></html>';
} else {
    echo "PASS: dashboard totals, latest-attempt status, legacy coverage, module/course/group access, ID tampering and safe rendering\n";
}
