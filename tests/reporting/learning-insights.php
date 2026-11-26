<?php
// Behavioural checks for teacher and organisation measures using the production stream aggregator.
ob_start(); require(__DIR__.'/dashboard.php'); ob_end_clean();
use mod_seledascorm\local\learning_insights;
use mod_seledascorm\local\timhirt_reporting;
$pages=[7=>(object)['title'=>'Lesson','launch'=>'index.html']];
$records=[];
$add=function($u,$a,$element,$value,$time=100) use (&$records) {
    $records[]=(object)['userid'=>$u,'firstname'=>'Learner','lastname'=>(string)$u,'attempt'=>$a,'scoid'=>7,'element'=>$element,'value'=>$value,'timemodified'=>$time];
};
$add(1,1,'cmi.core.lesson_status','incomplete');$add(1,1,'cmi.core.score.raw','0');
$add(1,1,'seleda.usage.v1.a',json_encode(['v'=>1,'id'=>'a','title'=>'Text','type'=>'text','ms'=>10000,'visits'=>1]));
$add(1,2,'cmi.core.lesson_status','completed');$add(1,2,'cmi.core.score.raw','80');
$add(1,2,'seleda.usage.v1.b',json_encode(['v'=>1,'id'=>'b','title'=>'Text','type'=>'text','ms'=>20000,'visits'=>1]));
$add(2,1,'x.start.time','100'); // Access exists, progress and time are unknown.
$add(3,1,'cmi.core.lesson_status','incomplete');$add(3,1,'cmi.core.score.raw','0');
$add(3,1,'seleda.usage.v1.c',json_encode(['v'=>1,'id'=>'c','title'=>'Text','type'=>'text','ms'=>10000,'visits'=>1]));
$calc=new learning_insights($pages,(object)['grademethod'=>'1','maxgrade'=>100]);iterator_to_array($calc->observe($records));
$d=$calc->report([4=>(object)['firstname'=>'Enrolled','lastname'=>'No access']]);
ensure($d['started']===3 && $d['roster']===4 && $d['statuses']['notstarted']===1 && $d['statuses']['inprogress']===2,'Access, enrolment and missing progress conflated');
ensure($d['scored']===2 && $d['average']===40.0 && $d['learners'][3]['score']===0.0 && $d['learners'][2]['score']===null,'Missing score treated as zero or true zero excluded');
ensure($d['median']===20000.0 && $d['timed']===2 && $d['learners'][2]['ms']===null,'Typical time must be median of known learner totals');
$rosterModel=$model;$rosterModel['metrics']['learning']=$d;
$rosterHtml=\mod_seledascorm\output\reporting_dashboard::render($rosterModel);
ensure(strpos($rosterHtml,'>1 / 4</text>')!==false,'Completion ring must show the same all-learner denominator as its segments');
ensure($d['paired']===1 && $d['improvement']===80.0,'Retry improvement lost first score of zero');
ensure($d['pages'][0]['reached']===3 && $d['pages'][0]['completed']===1 && $d['pages'][0]['unfinished']===2 && $d['pages'][0]['unknown']===0,'Unknown completion incorrectly treated as unfinished');
ensure(learning_insights::median([])===null && learning_insights::median([100,10,20])===20.0,'Median edge cases');
$ungraded=new learning_insights($pages,(object)['grademethod'=>'0']);iterator_to_array($ungraded->observe($records));
ensure($ungraded->report()['average']===null && $ungraded->report()['grading']==='completion','Completion-count grade advertised as assessment');
$twoPages=$pages+[8=>(object)['title'=>'Unknown page','launch'=>'unknown.html']];
$partialCatalog=new learning_insights($twoPages,(object)['grademethod'=>'1'],function($sco){return $sco===7?['q'=>['tracking'=>['mode'=>'completion']]]:[];});
ensure($partialCatalog->report()['grading']==='unknown','A missing page catalogue must not declare the entire activity ungraded');
$records=[];
// Same revision across native retries and Moodle attempts: only first assessed submission counts.
$catalog=['question-r1'=>['prompt'=>'What is 2 + 2?','type'=>'choice','block'=>'Quiz','blockid'=>'quiz','options'=>['a'=>'4','b'=>'3'],'tracking'=>['mode'=>'graded']]];
$native=function($u,$attempt,$index,$id,$response,$result) use ($add) {
    foreach (['id'=>$id,'type'=>'choice','student_response'=>$response,'result'=>$result,'correct_responses_0.pattern'=>'a'] as $name=>$value) $add($u,$attempt,'cmi.interactions_'.$index.'.'.$name,$value);
};
$add(1,1,timhirt_reporting::SNAPSHOT,json_encode($catalog));
$native(1,1,0,'question-r1','b','wrong');$native(1,1,1,'question-r1-a2','a','correct');
$add(1,2,timhirt_reporting::SNAPSHOT,json_encode($catalog));$native(1,2,0,'question-r1','a','correct');
$add(2,1,timhirt_reporting::SNAPSHOT,json_encode($catalog));$native(2,1,0,'question-r1','','wrong');
$add(3,1,timhirt_reporting::SNAPSHOT,json_encode($catalog));$native(3,1,0,'question-r1','a','0.5');
$add(4,1,timhirt_reporting::SNAPSHOT,json_encode($catalog));$native(4,1,0,'question-r1','a','correct');
$calc=new learning_insights($pages,(object)['grademethod'=>'1'],null,true);iterator_to_array($calc->observe($records));$d=$calc->report();$q=$d['questions'][0];
ensure(count($d['questions'])===1 && $q['n']===4 && $q['answered']===3 && $q['correct']===1 && $q['accuracy']===25,'First-submission denominator, blank or partial credit incorrect');
ensure(isset($q['wrong']['3']) && isset($q['wrong']['No answer submitted']),'Readable common wrong answers missing');
$records=[];
$add(1,1,'seleda.usage.v1.answers',json_encode(['v'=>1,'id'=>'custom','title'=>'Writing','type'=>'text','ms'=>0,'visits'=>0,'started'=>100,'events'=>[
    ['action'=>'click','label'=>'Check','n'=>1,'at'=>1],
    ['action'=>'answer','label'=>'Write a sentence','expected'=>'Sample','value'=>'Hello','result'=>'neutral','n'=>2,'at'=>2],
    ['action'=>'answer','label'=>'Reading question','expected'=>'A prize','value'=>'A book','result'=>'incorrect','n'=>3,'at'=>3],
    ['action'=>'answer','label'=>'Reading question','expected'=>'A prize','value'=>'A prize','result'=>'correct','n'=>4,'at'=>4],
]]));
$calc=new learning_insights($pages,(object)[],null,true);iterator_to_array($calc->observe($records));$d=$calc->report();
ensure(count($d['questions'])===1 && $d['questions'][0]['correct']===0 && $d['timed']===0,'Clicks, ungraded writing or assessment-only records invented a grade/time');
// Both lazy-load and display boundaries: no raw logs on default page, escaped assessed text on analysis view.
ensure(strpos($html,'How learners interact')===false && strpos($html,'sd-event-log')===false,'Default report still dumps raw interactions');
$model['metrics']['learning']=$d;$model['filters']['panel']='questions';
$questionhtml=\mod_seledascorm\output\reporting_dashboard::render($model);
ensure(strpos($questionhtml,'Reading question')!==false && strpos($questionhtml,'A book')!==false,'Question analysis not rendered');
$model['filters']['view']='analytics';$model['comparisons']=[['id'=>1,'name'=>'<script>bad</script>','course'=>'Course','data'=>$d]];$model['comparisonmore']=false;
$orghtml=\mod_seledascorm\output\reporting_dashboard::render($model);
ensure(strpos($orghtml,'sd-participation-svg')!==false && strpos($orghtml,'learner–activity')!==false && strpos($orghtml,'<script>bad')===false,'Organisation scope or escaping lost');
$model['filters']['view']='teacher';$model['filters']['attempt']=1;
$model['detail']=['name'=>'Learner','attempts'=>[1],'status'=>'inprogress','score'=>null,'grading'=>'unknown','active'=>'00:01:00','questions'=>[],'usagehtml'=>''];
$detailhtml=\mod_seledascorm\output\reporting_dashboard::render($model);
ensure(strpos($detailhtml,'name="attempt" id="sd-attempt"')===false && strpos($detailhtml,'<section class="sd-card sd-answers">')!==false,'Single-attempt detail should not require a dropdown or expanding submitted answers');
echo "PASS: meaningful scores, missing data, roster, median time, first submissions, retry comparisons, page progress and scoped views\n";
