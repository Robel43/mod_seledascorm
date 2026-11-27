'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{spawnSync}=require('node:child_process');
test('activity entry redirects straight to content after login, while explicit overview and actions remain available',()=>{
    const source=path.resolve(__dirname,'../..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'seleda-launch-')),plugin=path.join(temp,'mod/seledascorm');
    fs.mkdirSync(plugin,{recursive:true});fs.mkdirSync(path.join(temp,'course'));
    fs.copyFileSync(path.join(source,'view.php'),path.join(plugin,'view.php'));
    fs.copyFileSync(path.join(source,'classes/local/direct_launch.php'),path.join(plugin,'direct_launch.php'));
    for(const name of ['lib.php','locallib.php'])fs.writeFileSync(path.join(plugin,name),'<?php');
    fs.writeFileSync(path.join(temp,'course/lib.php'),'<?php');
    fs.writeFileSync(path.join(temp,'config.php'),String.raw`<?php
define('MOODLE_INTERNAL',true);define('PARAM_INT',1);define('PARAM_ALPHA',2);
define('SELEDASCORM_UPDATE_EVERYTIME',1);define('SELEDASCORM_SCO_ONLY',1);define('SELEDASCORM_TOCFULLURL',1);
define('SELEDASCORM_FORCEATTEMPT_ALWAYS',2);define('SELEDASCORM_FORCEATTEMPT_ONCOMPLETE',1);
$case=json_decode(getenv('SELEDA_LAUNCH_CASE'),true);$log=['login'=>false,'viewed'=>0,'redirect'=>null];
register_shutdown_function(function()use(&$log){file_put_contents(getenv('SELEDA_LAUNCH_OUTPUT'),json_encode($log));});
$CFG=(object)['dirroot'=>__DIR__,'version'=>2024100700];$USER=(object)['id'=>5];$SESSION=(object)['seledascorm'=>'previous'];
$course=(object)['id'=>3,'shortname'=>'Course','fullname'=>'Course'];
$cm=(object)['id'=>4,'instance'=>9,'course'=>3];
$activity=(object)['id'=>9,'course'=>3,'name'=>'Topic','reference'=>!empty($case['elpx'])?'lesson.elpx':'lesson.zip','launch'=>7,'popup'=>!empty($case['popup']), 'skipview'=>0,'forcenewattempt'=>$case['force']??0,'updatefreq'=>0];
$DB=new class{function get_record($t,$w){global $course,$activity;return $t==='course'?$course:$activity;}function get_records_select(...$a){return[7=>(object)['id'=>7],8=>(object)['id'=>8]];}function sql_isnotempty(...$a){return 'launch IS NOT NULL';}};
class context_course{static function instance($id){return(object)['id'=>$id];}}class context_module{static function instance($id){return(object)['id'=>$id];}}
class moodle_url{public $path,$params;function __construct($p,$a=[]){$this->path=$p;$this->params=$a;}function param($k,$v){$this->params[$k]=$v;}}
$PAGE=new class{public $activityheader;function set_url($u){}function set_title($t){}function set_heading($h){}};
$PAGE->activityheader=new class{function set_description($s){}function disable(){}};
$OUTPUT=new class{function header(){echo 'OVERVIEW';exit;}};
function optional_param($k,$d,$t){global $case;return $k==='id'?4:($case[$k]??$d);}
function get_config(...$args){return false;}function get_coursemodule_from_id(...$args){global $cm;return $cm;}
function require_login(...$args){global $log,$case;$log['login']=true;if(!empty($case['denied'])){echo 'DENIED';exit;}}
function seledascorm_get_sco(...$args){return(object)['organization'=>'org','launch'=>'index.html','identifier'=>'item'];}
function seledascorm_get_toc(...$args){global $case;return(object)['sco'=>(object)['id'=>8],'incomplete'=>!empty($case['complete'])?false:true];}
function seledascorm_view(...$args){global $log;$log['viewed']++;}
function redirect($url){global $log,$SESSION;$log['redirect']=[$url->path,$url->params];$log['sessioncleared']=!isset($SESSION->seledascorm);echo 'REDIRECT';exit;}
function get_string(...$a){return $a[0];}function format_string($s,...$a){return $s;}function has_capability(...$a){return false;}
function seledascorm_simple_play(...$a){}function confirm_sesskey(){return true;}
require_once(__DIR__.'/mod/seledascorm/direct_launch.php');
`);
    try {
        const scenarios=[{}, {popup:true}, {force:2}, {force:1,complete:true}, {force:1}, {preventskip:1}, {action:'delete'}, {elpx:true}, {denied:true}];
        for(const fixture of scenarios){
            const output=path.join(temp,'output.json');
            const run=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(plugin,'view.php')],{cwd:plugin,encoding:'utf8',env:{...process.env,SELEDA_LAUNCH_CASE:JSON.stringify(fixture),SELEDA_LAUNCH_OUTPUT:output}});
            assert.equal(run.status,0,run.stderr+run.stdout);
            const log=JSON.parse(fs.readFileSync(output,'utf8'));assert.equal(log.login,true);
            if(fixture.denied){assert.equal(run.stdout,'DENIED');assert.equal(log.redirect,null);assert.equal(log.viewed,0);}
            else if(fixture.preventskip||fixture.action||fixture.elpx){assert.equal(run.stdout,'OVERVIEW');assert.equal(log.redirect,null);}
            else {assert.equal(run.stdout,'REDIRECT');assert.equal(log.viewed,1);assert.equal(log.sessioncleared,true);assert.equal(log.redirect[0],'/mod/seledascorm/player.php');const params=log.redirect[1];assert.equal(params.mode,'normal');assert.equal(params.cm,4);assert.equal(params.scoid,fixture.force===2?7:8);assert.equal(params.display,fixture.popup?'popup':undefined);assert.equal(params.newattempt,fixture.force===2||(fixture.force===1&&fixture.complete)?'on':undefined);}
        }
    } finally {fs.rmSync(temp,{recursive:true,force:true});}
});
