<?php
// Consume a real JSON export and captured learner tracks from the browser test.
define('MOODLE_INTERNAL', true);
function get_string($key, $component = null) { $string=[];require(__DIR__.'/../../lang/en/seledascorm.php');return $string[$key] ?? $key; }
require_once(__DIR__.'/../../report/timhirt_reporting.php');
require_once(__DIR__.'/../../report/seleda_usage.php');
require_once(__DIR__.'/../../classes/output/dashboard_detail.php');
use mod_seledascorm\local\timhirt_reporting as reporting;
$data=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$catalog=reporting::catalog($data['html']);
$tracks=array_map(fn($t)=>(object)$t,$data['tracks']);
$blocks=reporting::blockstates($tracks,[7=>$catalog]);
echo json_encode(['catalog'=>$catalog,'rows'=>reporting::rows($tracks,[7=>$catalog]),'blocks'=>$blocks,
    'html'=>\mod_seledascorm\output\dashboard_detail::tracking($blocks,[7=>'Quiz 1'])]);
