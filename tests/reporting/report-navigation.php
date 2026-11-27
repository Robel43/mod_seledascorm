<?php
// Execute the actual global navigation policy without loading tracking data.
define('MOODLE_INTERNAL', true);
define('ROLENAME_ORIGINAL', 1);
define('SQL_PARAMS_NAMED', 1);
function ensure($ok, $message) { if (!$ok) throw new Exception($message); }
function get_string($key, $component=null) { $string=[];require(__DIR__.'/../../lang/en/seledascorm.php');return $string[$key]??$key; }
function get_config($component,$name) { ensure($component==='mod_seledascorm' && in_array($name,['reportaccessroles','reportlinkroles']),'Wrong config key');return $name==='reportaccessroles'?$GLOBALS['navaccessconfig']:$GLOBALS['navconfig']; }
function role_get_names(...$args) { return $GLOBALS['navchoices']; }
function is_siteadmin() { return $GLOBALS['navadmin']; }
eval('namespace mod_seledascorm\\local; class reporting_dashboard {public static $permitted=true;public static function can_open(){return self::$permitted && $GLOBALS["navloggedin"] && !$GLOBALS["navguest"];}}');
eval('namespace core\\hook\\navigation; class primary_extend {public $view;public function __construct($view){$this->view=$view;}public function get_primaryview(){return $this->view;}}');
class moodle_url {public $path;public function __construct($path){$this->path=$path;}}
class navigation_node {const TYPE_CUSTOM=1;}
class nav_database {
    public $queries=0;
    public function get_in_or_equal($ids,$type,$prefix) {
        $params=[];foreach(array_values($ids)as $i=>$id)$params[$prefix.$i]=$id;
        return ['IN (:'.implode(', :',array_keys($params)).')',$params];
    }
    public function record_exists_select($table,$where,$params) {
        $this->queries++;
        ensure($table==='role_assignments' && $params['reportnavuser']===$GLOBALS['USER']->id,'Role lookup lost user/table scope');
        ensure(strpos($where,'userid = :reportnavuser AND roleid IN')===0,'Unbounded role lookup');
        unset($params['reportnavuser']);return (bool)array_intersect($params,$GLOBALS['navassigned']);
    }
}
require_once(__DIR__.'/../../classes/local/report_navigation.php');
require_once(__DIR__.'/../../classes/hook_callbacks.php');
use mod_seledascorm\local\report_navigation as policy;
use mod_seledascorm\local\reporting_dashboard as gate;
$CFG=(object)['defaultuserroleid'=>7,'defaultfrontpageroleid'=>8];$USER=(object)['id'=>12];$DB=new nav_database();
$navaccessconfig=false;$navconfig=false;$navadmin=false;$navloggedin=true;$navguest=false;$navassigned=[3];
$navchoices=[1=>'Manager',3=>'Teacher',4=>'Non-editing teacher',5=>'Student',6=>'Guest',7=>'Authenticated user',8=>'Front page'];
ensure(policy::visible(),'Unsaved setting broke existing link visibility');
$navconfig='';ensure(!policy::visible(),'Empty selection must hide the link');
$navaccessconfig='3';ensure(policy::visible(),'New access selection did not override legacy navigation');
$navaccessconfig='';$navconfig='3';ensure(!policy::visible(),'Empty new access selection fell back to legacy roles');$navaccessconfig=false;
$navconfig='4,3';ensure(policy::visible(),'Multiple selection did not match assigned role');
$navconfig='4,5';ensure(!policy::visible(),'Unselected role showed the link');
$navconfig='7';ensure(policy::visible(),'Default authenticated role needs no assignment row');
$navconfig='8';ensure(policy::visible(),'Default front-page role needs no assignment row');
$navconfig='-1';ensure(!policy::visible(),'Ordinary user matched administrator choice');
$navadmin=true;ensure(policy::visible(),'Administrator choice failed');
$navconfig='';ensure(!policy::visible(),'Empty selection must hide from administrators too');$navadmin=false;
$navchoices[44]='Custom analyst';$choices=policy::choices();
ensure(isset($choices[44],$choices[6],$choices[7],$choices[-1]) && count($choices)===count($navchoices)+1,'Not every Moodle/custom role is selectable');
$navassigned=[44,3];$navconfig='44';ensure(policy::visible(),'Custom role selection ignored');
$navconfig='44,3';gate::$permitted=false;$before=$DB->queries;
ensure(!policy::visible() && $DB->queries===$before,'Role selection bypassed reporting access or queried roles before the gate');
$view=new class {public $links=[];public function add(...$args){$this->links[]=$args;}};
$hook=new \core\hook\navigation\primary_extend($view);
\mod_seledascorm\hook_callbacks::extend_primary($hook);ensure(!$view->links,'Hook ignored access gate');
gate::$permitted=true;\mod_seledascorm\hook_callbacks::extend_primary($hook);
ensure(count($view->links)===1 && $view->links[0][1]->path==='/mod/seledascorm/dashboard.php','Hook lost navigation destination');
$navguest=true;ensure(!policy::visible(),'Guest saw reports link');$navguest=false;$navloggedin=false;
ensure(!policy::visible(),'Logged-out visitor saw reports link');
echo "PASS: dynamic role choices, multiple roles, default roles, administrators, empty selection, permission gate and actual navigation hook\n";
