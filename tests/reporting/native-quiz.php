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
$rows=reporting::rows($tracks,[7=>$catalog]);
$usage=['available'=>true,'ms'=>1000,'visits'=>1,'interactions'=>1,'blocks'=>[
    ['scoid'=>7,'id'=>'idevice-1795417967460-z505cd28','title'=>'Quiz 1','type'=>'mcq-feedback','ms'=>1000,'visits'=>1,'interactions'=>1,'omitted'=>0,
     'events'=>[['visit'=>1,'at'=>1000,'action'=>'change','label'=>'Selected option','value'=>'on']]],
]];

echo json_encode(['catalog'=>$catalog,'rows'=>reporting::rows($tracks,[7=>$catalog]),'blocks'=>$blocks,
    'usagehtml'=>\mod_seledascorm\output\dashboard_detail::usage($usage,[7=>'Quiz 1'],$rows),
    'html'=>\mod_seledascorm\output\dashboard_detail::tracking($blocks,[7=>'Quiz 1'])]);
