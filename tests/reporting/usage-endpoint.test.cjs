'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os'),{spawnSync}=require('node:child_process');
test('the real Moodle usage endpoint enforces login, session key, capability and activity/SCO scope',()=>{
 const temp=fs.mkdtempSync(path.join(os.tmpdir(),'seleda-usage-endpoint-')),plugin=path.join(temp,'mod/seledascorm'),source=path.resolve(__dirname,'../..');
 fs.mkdirSync(path.join(plugin,'report'),{recursive:true});
 for(const file of ['datamodel.php','report/timhirt_reporting.php','report/seleda_usage.php'])fs.copyFileSync(path.join(source,file),path.join(plugin,file));
 fs.writeFileSync(path.join(plugin,'locallib.php'),'<?php');
 const config=String.raw`<?php
define('MOODLE_INTERNAL',true);define('PARAM_INT',1);define('PARAM_RAW',2);define('MUST_EXIST',1);
$fixture=json_decode(getenv('SELEDA_USAGE_CASE'),true);$log=['login'=>false,'records'=>[]];
register_shutdown_function(function()use(&$log){file_put_contents(getenv('SELEDA_USAGE_OUTPUT'),json_encode($log));});
$CFG=(object)['dirroot'=>__DIR__];$USER=(object)['id'=>4,'username'=>'1'];
class moodle_url{function __construct(...$a){}}class context_module{static function instance($id){return (object)['id'=>$id];}}
$PAGE=new class{function set_url(...$a){}};
function optional_param($name,$default,$type){global $fixture;if($name==='id')return 1;if($name==='seleda_usage')return json_encode($fixture['payload']);if($name==='seleda_student')return $fixture['student']??'1';return $default;}
function required_param($name,$type){global $fixture;return $name==='scoid'?($fixture['scoid']??7):1;}
function get_coursemodule_from_id(...$a){return (object)['id'=>1,'course'=>2,'instance'=>9];}
function require_login(...$a){global $fixture,$log;$log['login']=true;if(empty($fixture['login']))throw new Exception('Login required');}
function confirm_sesskey(){global $fixture;return !empty($fixture['sesskey']);}
function has_capability(...$a){global $fixture;return !empty($fixture['capability']);}
eval('namespace core\\lock; class lock_config{static function get_lock_factory($name){return new \\fixture_lock_factory;}}');
class fixture_lock_factory{function get_lock(...$a){return new class{function release(){}};}}
$DB=new class{
 function get_record($table,$where,...$args){if($table==='course')return(object)['id'=>2];if($table==='seledascorm')return(object)['id'=>9,'course'=>2];return false;}
 function record_exists($table,$where){return $where===['id'=>7,'seledascorm'=>9];}
 function start_delegated_transaction(){return new class{function allow_commit(){}function rollback($e){throw $e;}};}
 function insert_record($table,$record){global $log;$log['records'][]=$record;}
};`;
 fs.writeFileSync(path.join(temp,'config.php'),config);
 const payload={v:1,session:'aaaaaaaaaaaaaaaaaaaaaaaa',blocks:[{id:'text',title:'Reading',type:'text',ms:10000,visits:1,actions:{click:1},events:[{n:1,at:1000,action:'click',label:'Explore'}]}]};
 try{
  for(const name of ['valid','login','sesskey','capability','sco','payload','student']){
   const fixture={login:true,sesskey:true,capability:true,payload};if(['login','sesskey','capability'].includes(name))fixture[name]=false;if(name==='sco')fixture.scoid=99;if(name==='payload')fixture.payload={...payload,v:99};if(name==='student')fixture.student='previous-user';
   const output=path.join(temp,'output.json'),result=spawnSync(process.env.TIMHIRT_PHP||'php',[...JSON.parse(process.env.TIMHIRT_PHP_OPTIONS||'[]'),path.join(plugin,'datamodel.php')],{cwd:plugin,env:{...process.env,SELEDA_USAGE_CASE:JSON.stringify(fixture),SELEDA_USAGE_OUTPUT:output},encoding:'utf8'});
   const log=JSON.parse(fs.readFileSync(output,'utf8'));assert.equal(log.login,true);
   if(name==='valid'){assert.equal(result.status,0,result.stderr);assert.equal(result.stdout.trim(),'true\n0');assert.equal(log.records.length,1);const row=log.records[0];assert.equal(row.userid,4);assert.equal(row.seledascormid,9);assert.equal(row.scoid,7);assert.equal(row.attempt,1);assert.match(row.element,/^seleda\.usage\.v1\./);}
   else{assert.equal(log.records.length,0,name+' must not write usage');assert.notEqual(result.stdout.trim(),'true\n0',name+' must not acknowledge a rejected save');}
  }
 }finally{assert.ok(path.resolve(temp).startsWith(path.resolve(os.tmpdir())+path.sep+'seleda-usage-endpoint-'));fs.rmSync(temp,{recursive:true,force:true});}
});
