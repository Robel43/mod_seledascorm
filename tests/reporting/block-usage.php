<?php
define('MOODLE_INTERNAL', true);
function reporting_string($key, $value = null) { $string = []; require(__DIR__ . '/../../lang/en/seledascorm.php'); return $string[$key] ?? ($key . ($value === null || is_object($value) ? '' : $value)); }
function get_string($key, $component = null, $value = null) { return reporting_string($key, $value); }

require_once(__DIR__ . '/../../report/timhirt_reporting.php');
require_once(__DIR__ . '/../../report/seleda_usage.php');
use mod_seledascorm\local\seleda_usage as usage;
use mod_seledascorm\local\timhirt_reporting as reporting;
function ensure($ok, $why) { if (!$ok) { throw new Exception($why); } }
eval('namespace core\lock; class lock_config {static function get_lock_factory($name){return new \\usage_lock_factory;}}');
class usage_lock_factory { function get_lock($key, $timeout) { $GLOBALS['lockkeys'][]=$key; return new class {function release(){$GLOBALS['released']++;}}; } }
class usage_db {
    public $records = []; public $writes=0;
    function record_exists($table, $where) { return $table==='seledascorm_scoes' && $where['seledascorm']===9 && in_array($where['id'],[7,8]); }
    function start_delegated_transaction() { return new class {function allow_commit(){} function rollback($e){throw $e;}}; }
    function get_record($table, $where) { foreach($this->records as $record){$match=true;foreach($where as $k=>$v){if($record->$k!==$v)$match=false;}if($match)return clone $record;}return false; }
    function insert_record($table, $record) { $record->id=count($this->records)+1;$this->records[$record->id]=clone $record;$this->writes++; }
    function update_record($table, $record) { $this->records[$record->id]=clone $record;$this->writes++; }
}
$DB=new usage_db();$released=0;$lockkeys=[];
$block=['id'=>'text-one','title'=>'Reading <b>lesson</b>','type'=>'text','ms'=>12000,'visits'=>1,'actions'=>['click'=>2,'change'=>1],
    'events'=>[['n'=>1,'at'=>1000,'action'=>'click','label'=>'Open details'],['n'=>2,'at'=>2000,'action'=>'change','label'=>'Answer','value'=>'=My answer']]];
function payload($block,$session='aaaaaaaaaaaaaaaaaaaaaaaa'){return json_encode(['v'=>1,'session'=>$session,'blocks'=>[$block]]);}
usage::save(4,9,7,1,payload($block));usage::save(4,9,7,1,payload($block));
ensure(count($DB->records)===1,'Retries must update one attempt/session/block record');
$summary=usage::summary(array_values($DB->records));ensure($summary['ms']===12000 && $summary['interactions']===3 && $summary['visits']===1,'Retries doubled totals');
ensure(count($summary['blocks'][0]['events'])===2,'Retries duplicated events');
$new=$block;$new['ms']=18000;$new['actions']['click']=3;$new['events']=[['n'=>3,'at'=>17000,'action'=>'click','label'=>'Next']];
usage::save(4,9,7,1,payload($new));usage::save(4,9,7,1,payload($block));
$summary=usage::summary(array_values($DB->records));ensure($summary['ms']===18000 && $summary['interactions']===4 && count($summary['blocks'][0]['events'])===3,'Late keepalive overwrote newer data');
usage::save(4,9,7,1,payload($block,'bbbbbbbbbbbbbbbbbbbbbbbb'));usage::save(4,9,8,1,payload($block));
$summary=usage::summary(array_values($DB->records));ensure($summary['ms']===42000 && $summary['interactions']===10 && $summary['visits']===3,'Whole SCORM attempt did not aggregate pages and visits');
ensure(count($summary['blocks'])===2,'Same block IDs on different SCOs were merged');
ensure($summary['blocks'][0]['title']==='Reading <b>lesson</b>','Plain-text labels must retain literal angle brackets');
usage::save(4,9,7,2,payload($block));usage::save(5,9,7,1,payload($block));
$scoped=array_values(array_filter($DB->records,fn($r)=>$r->userid===4&&$r->attempt===1));
ensure(usage::summary($scoped)['ms']===42000,'Learners or Moodle attempts mixed');
ensure(usage::summary(array_merge($scoped,$scoped))['ms']===42000,'Duplicate DB rows doubled totals');
foreach($DB->records as $record){ensure(strpos($record->element,usage::PREFIX)===0,'Usage wrote a grade/status field');}
$html=usage::table($summary,[7=>'First page',8=>'Second page'],true);
ensure(strpos($html,'00:00:42')!==false && strpos($html,'Second page')!==false && strpos($html,'interaction log')!==false,'Independent and aggregate HTML report missing');
ensure(strpos($html,'&lt;b&gt;lesson&lt;/b&gt;')!==false && strpos($html,'<b>lesson</b>')===false,'Plain-text titles must be escaped, not executed as HTML');
ensure(reporting::exportcell($summary['blocks'][0]['events'][1]['value'])==="'=My answer",'Usage exports allow spreadsheet formulas');
ensure(usage::duration(3661000)==='01:01:01','Duration formatting broken');
ensure(strpos(usage::table(usage::summary([])),'No content-block usage')!==false,'Old packages need an explicit unavailable state');
$code=$block;$code['id']='code-answer';$code['events']=[['n'=>1,'at'=>1000,'action'=>'change','label'=>'Code answer','value'=>'<div>literal answer</div>']];
usage::save(4,9,7,4,payload($code));$coded=usage::summary(array_values(array_filter($DB->records,fn($r)=>$r->attempt===4)));
ensure($coded['blocks'][0]['events'][0]['value']==='<div>literal answer</div>','Code answers must not be stripped as markup');
ensure(strpos(usage::table($coded,[],true),'&lt;div&gt;literal answer&lt;/div&gt;')!==false,'Code answers must render safely');
$retained=$block;$retained['id']='long-log';$retained['actions']=['click'=>2001];
for($offset=0;$offset<2001;$offset+=40){
    $retained['events']=[];
    for($i=$offset+1;$i<=min(2001,$offset+40);$i++)$retained['events'][]=['n'=>$i,'at'=>$i,'action'=>'click','label'=>'Action '.$i];
    usage::save(4,9,7,3,payload($retained));
}
$long=usage::summary(array_values(array_filter($DB->records,fn($r)=>$r->attempt===3)));
ensure($long['interactions']===2001&&count($long['blocks'][0]['events'])===2000&&$long['blocks'][0]['omitted']===1,'Event retention must keep complete counters and show omitted details');
$assessment=$block; $assessment['id']='assessed'; $assessment['ms']=0; $assessment['visits']=0;
$assessment['actions']=['answer'=>1];
$assessment['events']=[['n'=>1,'at'=>1000,'action'=>'answer','label'=>'Question <script>','value'=>'<b>answer</b>','expected'=>'<b>expected</b>','result'=>'incorrect']];
usage::save(4,9,7,5,payload($assessment)); usage::save(4,9,7,5,payload($assessment));
$assessed=usage::summary(array_values(array_filter($DB->records,fn($r)=>$r->attempt===5)));
ensure($assessed['ms']===0 && $assessed['visits']===0 && $assessed['interactions']===1,'Assessment streams must not duplicate active time or visits');
ensure($assessed['blocks'][0]['events'][0]['result']==='incorrect' && $assessed['blocks'][0]['events'][0]['expected']==='<b>expected</b>','Assessment details lost');
require_once(__DIR__.'/../../classes/output/dashboard_detail.php');
$detail=\mod_seledascorm\output\dashboard_detail::usage($assessed,[7=>'Page']);
ensure(strpos($detail,'Incorrect')!==false && strpos($detail,'&lt;b&gt;expected&lt;/b&gt;')!==false && strpos($detail,'<script>')===false,'Assessment report unsafe or missing results');
$questions=[
 ['scoid'=>7,'blockid'=>'assessed','block'=>'Same title','question'=>'Question one','response'=>'First response','correct'=>'First key','result'=>'Correct'],
 ['scoid'=>8,'blockid'=>'assessed','block'=>'Same title','question'=>'Question two','response'=>'Second response','correct'=>'Second key','result'=>'Incorrect'],
];
$grouped=\mod_seledascorm\output\dashboard_detail::usage($assessed,[7=>'First page',8=>'Second page'],$questions);
$dom=new DOMDocument();@$dom->loadHTML($grouped);$xpath=new DOMXPath($dom);
$panels=$xpath->query('//details[@class="sd-block-detail"]');
ensure($panels->length===2,'Same block IDs on different SCOs must have independent dropdowns');
ensure(strpos($panels->item(0)->textContent,'Question one')!==false && strpos($panels->item(0)->textContent,'Question two')===false,'Another SCO question leaked into the first dropdown');
ensure(strpos($panels->item(1)->textContent,'Question two')!==false && strpos($panels->item(1)->textContent,'Question one')===false,'Another SCO question leaked into the second dropdown');
$assessment['events'][0]['result']='invented';$failed=false;try { usage::save(4,9,7,5,payload($assessment)); } catch(Throwable $e) { $failed=true; }
ensure($failed,'Unknown assessment result accepted');
$writes=$DB->writes;
foreach(['wrong-sco','negative','too-many','unknown-action','bad-session','bad-json','oversize'] as $case){
    $bad=$block;$sco=7;$raw=payload($bad);
    if($case==='wrong-sco')$sco=99;
    if($case==='negative'){$bad['ms']=-1;$raw=payload($bad);}
    if($case==='too-many'){$bad['events']=array_fill(0,41,$block['events'][0]);$raw=payload($bad);}
    if($case==='unknown-action'){$bad['actions']=['admin'=>1];$raw=payload($bad);}
    if($case==='bad-session')$raw=payload($bad,'not-valid');
    if($case==='bad-json')$raw='no json';
    if($case==='oversize')$raw=str_repeat('x',60001);
    $failed=false;try{usage::save(4,9,$sco,1,$raw);}catch(Throwable $e){$failed=true;}
    ensure($failed,'Invalid usage accepted: '.$case);ensure($DB->writes===$writes,'Invalid request partially wrote records');
}
ensure($released===count($lockkeys),'Usage lock leaked');
echo "PASS: scoped usage storage, idempotent and late snapshots, cross-page totals, session visits, event logs, validation and safe output\n";
