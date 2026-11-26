<?php
// Execute the real overview renderer against a small Moodle boundary fixture.
define('MOODLE_INTERNAL', true);
function reporting_string($key, $value = null) { $string = []; require(__DIR__ . '/../../lang/en/seledascorm.php'); return $string[$key] ?? ($key . ($value === null || is_object($value) ? '' : $value)); }

define('SELEDASCORM_GRADEHIGHEST', 1); define('SELEDASCORM_GRADEAVERAGE', 2); define('SELEDASCORM_GRADESUM', 3); define('SELEDASCORM_GRADESCOES', 0);
define('PARAM_ALPHA', 1); define('PARAM_RAW', 2); define('PARAM_INT', 3);
define('SELEDASCORM_REPORT_ATTEMPTS_ALL_STUDENTS', 0);
define('SELEDASCORM_REPORT_ATTEMPTS_STUDENTS_WITH', 1);
define('SELEDASCORM_REPORT_ATTEMPTS_STUDENTS_WITH_NO', 2);
define('SELEDASCORM_REPORT_DEFAULT_PAGE_SIZE', 20);
function verify($ok, $message) { if (!$ok) { throw new Exception($message); } }
eval('namespace mod_seledascorm; class report {}');
eval('namespace mod_seledascorm\\output; class actionbar {function __construct(...$args){}}');
eval('namespace core_user; class fields {static function get_identity_fields(...$a){return [];} static function for_identity(...$a){return new self;} function with_userpic(){return $this;} function including(...$a){return $this;} function get_sql(...$a){return (object)["selects"=>", u.id AS userid, u.firstname, u.lastname"];}}');
class context_module { static function instance($id) { return (object)['id' => $id]; } }
class context_course { static function instance($id) { return (object)['id' => $id]; } }
class moodle_url { function __construct(...$args) {} function out(...$args) { return '/report'; } }
class html_writer {
    static function __callStatic($name, $args) { return $name === 'link' ? '<a>' . $args[1] . '</a>' : ''; }
}
class flexible_table {
    public $columns; public $headers; public $rows = [];
    function __construct($name) { $GLOBALS['overviewtable'] = $this; }
    function define_columns($columns) { $this->columns = $columns; }
    function define_headers($headers) { $this->headers = $headers; }
    function add_data($row) { $this->rows[] = $row; }
    function get_sql_sort() { return ''; }
    function get_sql_where() { return ['', []]; }
    function get_page_start() { return 0; } function get_page_size() { return 20; }
    function __call($name, $args) {}
}
class csv_export_writer {
    public $rows = [];
    function __construct(...$args) {} function set_filename(...$args) {}
    function add_data($row) { $this->rows[] = $row; }
    function download_file() { checkoutput($this->rows[0], array_slice($this->rows, 1), true); echo "PASS: overview CSV renderer\n"; }
}
class overview_output {
    function report_actionbar(...$a) { return ''; } function notification(...$a) { return ''; }
    function pix_icon(...$a) { return ''; }
}
class overview_page {
    public $url;
    function __construct() { $this->url = new moodle_url(); }
    function set_url($url) { $this->url = $url; }
    function get_renderer(...$args) { return new overview_output(); }
}
class overview_recordset extends ArrayIterator { function close() { $GLOBALS['recordsetclosed'] = true; } }
class overview_db {
    function record_exists_sql($sql, $params) { verify($params === ['group' => 5], 'Group scope lost'); return true; }
    function get_records($table, $where, $sort = '') {
        verify($table === 'seledascorm_scoes' && $where['seledascorm'] === 9, 'Activity scope lost');
        return [(object)['id' => 7, 'launch' => 'index.html', 'title' => 'Page one'], (object)['id' => 8, 'launch' => 'two.html', 'title' => 'Page two']];
    }
    function get_record($table, $where) {
        verify($where['seledascorm'] === 9, 'Catalog scope lost');
        return (object)['launch' => $where['id'] === 7 ? 'index.html' : 'two.html'];
    }
    function sql_concat(...$args) { return implode('||', $args); }
    function get_record_sql(...$args) { return (object)['nbresults' => 2, 'nbattempts' => 1, 'nbusers' => 2]; }
    function get_recordset_sql($sql, $params) {
        verify($params === ['activity' => 9, 'user0' => 4, 'attempt0' => 1], 'Batched learner/activity/attempt scope lost');
        $GLOBALS['batchqueries'] = ($GLOBALS['batchqueries'] ?? 0) + 1;
        $data = fixture_track_data(7, 4, 1);
        $data->{'cmi.core.lesson_status'} = 'passed'; $data->{'cmi.core.score.raw'} = '100';
        $tracks = [];
        foreach (get_object_vars($data) as $element => $value) {
            if (!str_starts_with($element, 'cmi.') && !str_starts_with($element, 'timhirt.')) { continue; }
            $tracks[] = (object)['scoid' => 7, 'userid' => 4, 'attempt' => 1, 'element' => $element, 'value' => $value, 'timemodified' => 2];
        }
        $tracks[] = (object)['scoid'=>7,'userid'=>4,'attempt'=>1,'element'=>'seleda.usage.v1.fixture','value'=>json_encode(['v'=>1,'id'=>'reading','title'=>'Reading','type'=>'text','ms'=>12000,'visits'=>1,'actions'=>['click'=>2],'events'=>[]]),'timemodified'=>2];
        return new overview_recordset($tracks);
    }
    function get_records_sql($sql, $params, ...$limit) {
        verify(strpos($sql, 'u.id IN (allowed_group_users)') !== false && $params === ['group' => 5], 'Allowed learner filter lost');
        if (!$GLOBALS['csvmode']) { verify($limit === [0, 20], 'Pagination lost'); }
        return [(object)['userid' => 4, 'attempt' => 1, 'firstname' => 'Learner'], (object)['userid' => 5, 'attempt' => null, 'firstname' => 'No attempt']];
    }
}
function optional_param($name, $default, $type) { return $default; }
function optional_param_array($name, $default, $type) { return $default; }
function has_capability(...$args) { return false; }
function groups_get_activity_group(...$args) { return 5; }
function groups_get_activity_groupmode(...$args) { return 1; }
function groups_print_activity_menu(...$args) {}
function get_enrolled_sql(...$args) { return ['allowed_group_users', ['group' => 5]]; }
function get_user_preferences($name, $default) { return 1; }
function get_string($key, $component = null, $value = null) { return reporting_string($key, $value); }
function format_string($text, ...$args) { return $text; }
function clean_filename($text) { return $text; }
function s($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function fullname($user) { return $user->firstname; }
function userdate($date, ...$args) { return 'date'; }
function get_seledascorm_question_count($id) { return 2; }
function seledascorm_get_sco_runtime(...$args) { throw new Exception('N+1 runtime lookup'); }
function seledascorm_grade_user_attempt(...$args) { throw new Exception('N+1 grade lookup'); }
function fixture_track_data($scoid, $userid, $attempt) {
    verify($userid === 4 && $attempt === 1, 'Wrong learner attempt requested');
    if ($scoid === 8) { return false; }
    $catalog = ['old-q' => ['block' => 'Quiz', 'prompt' => '<Original question>', 'type' => 'choice', 'options' => ['a' => '=Chosen answer', 'b' => 'Right answer']]];
    return (object)['status' => 'passed', 'score_raw' => 100,
        'cmi.interactions_0.id' => 'old-q', 'cmi.interactions_0.type' => 'choice',
        'cmi.interactions_0.student_response' => 'a', 'cmi.interactions_0.correct_responses_0.pattern' => 'b',
        'cmi.interactions_0.result' => 'wrong', 'timhirt.reporting.catalog.v1' => json_encode($catalog)];
}
class overview_storage { function get_file(...$args) { return false; } }
function seledascorm_get_tracks(...$args) { throw new Exception('N+1 track lookup'); }
function seledascorm_format_interactions($tracks) {
    $data = (object)['status' => '', 'score_raw' => '', 'timemodified' => 0];
    foreach ($tracks as $t) {
        $data->{$t->element} = $t->value;
        if ($t->element === 'cmi.core.lesson_status') { $data->status = $t->value; }
        if ($t->element === 'cmi.core.score.raw') { $data->score_raw = (float)$t->value; }
        $data->timemodified = max($data->timemodified, $t->timemodified);
    }
    return $data;
}
function get_file_storage() { return new overview_storage(); }
function checkoutput($headers, $rows, $csv) {
    verify(($GLOBALS['batchqueries'] ?? 0) === 1 && !empty($GLOBALS['recordsetclosed']), 'Tracks must use one closed, scoped batch query');
    verify(count($headers) === 26 && count($rows) === 2, 'Wrong overview dimensions');
    foreach ($rows as $row) { verify(count($row) === count($headers), 'Multi-SCO headers and cells misaligned'); }
    verify($headers[9] === 'questionx1' && $headers[18] === 'questionx1', 'Question numbering must restart at one per SCO');
    verify($rows[0][9] === ($csv ? 'Quiz: <Original question>' : 'Quiz: &lt;Original question&gt;'), 'Snapshot prompt not displayed safely');
    verify($rows[0][10] === ($csv ? "'=Chosen answer" : '=Chosen answer'), 'Response decoding/export safety failed');
    verify($rows[0][11] === 'Right answer' && $rows[0][12] === 'Incorrect', 'Readable answer/result lost');
    verify($rows[0][18] === ($csv ? '' : '&nbsp;'), 'Unattempted SCO should have empty responses');
    verify($rows[0][5] === '00:00:12' && (int)$rows[0][6] === 2, 'Attempt usage totals missing');
    verify(strpos($rows[0][7], $csv ? 'Reading: 00:00:12' : 'View block usage') !== false, 'Independent block usage missing from overview');
}
$csvmode = in_array('--csv', $argv);
$fixture = sys_get_temp_dir() . '/timhirt-overview-' . bin2hex(random_bytes(5));
$dir = $fixture . '/mod/seledascorm/report'; mkdir($dir . '/interactions', 0700, true);
file_put_contents($dir . '/interactions/responsessettings_form.php', '<?php class mod_seledascorm_report_interactions_settings { function __construct(...$a){} function get_data(){return false;} function set_data(...$a){} function display(){} }');
file_put_contents($dir . '/timhirt_reporting.php', '<?php require_once ' . var_export(realpath(__DIR__ . '/../../report/timhirt_reporting.php'), true) . ';');
file_put_contents($dir . '/seleda_usage.php', '<?php require_once ' . var_export(realpath(__DIR__ . '/../../report/seleda_usage.php'), true) . ';');
register_shutdown_function(function() use ($dir, $fixture) { unlink($dir . '/interactions/responsessettings_form.php'); unlink($dir . '/timhirt_reporting.php'); unlink($dir . '/seleda_usage.php'); rmdir($dir . '/interactions'); rmdir($dir); rmdir($fixture . '/mod/seledascorm'); rmdir($fixture . '/mod'); rmdir($fixture); });
$CFG = (object)['dirroot' => $fixture, 'grade_report_showuserimage' => false];
$DB = new overview_db(); $OUTPUT = new overview_output(); $PAGE = new overview_page();
require(__DIR__ . '/../../report/interactions/classes/report.php');
ob_start();
(new seledascormreport_interactions\report())->display((object)['id' => 9, 'name' => 'Test', 'grademethod' => SELEDASCORM_GRADEHIGHEST], (object)['id' => 489], (object)['id' => 2, 'shortname' => 'Course'], $csvmode ? 'CSV' : '');
ob_end_clean();
checkoutput($overviewtable->headers, $overviewtable->rows, false);
echo "PASS: overview HTML renderer, multi-SCO alignment, snapshots, learner/group scope and pagination\n";

require_once(__DIR__.'/redesign-data-cases.php');
redesign_data_cases();
