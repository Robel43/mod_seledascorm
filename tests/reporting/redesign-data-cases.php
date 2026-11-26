<?php
// Pure behavioural checks shared by the standalone overview suite.
function redesign_data_cases(): void {
    require_once(__DIR__.'/../../classes/local/learning_insights.php');
    $pages=[7=>(object)['title'=>'One','launch'=>'a.html'],8=>(object)['title'=>'Two','launch'=>'b.html'],9=>(object)['title'=>'Three','launch'=>'c.html']];
    $make=function($u,$a,$s,$e,$v,$role='learner'){return (object)['userid'=>$u,'attempt'=>$a,'scoid'=>$s,'element'=>$e,'value'=>$v,'timemodified'=>100,'firstname'=>'Person','lastname'=>(string)$u,'dashboardrole'=>$role];};
    $session=str_repeat('a',24);$other=str_repeat('b',24);
    $usage=function($id,$ms,$visits){return json_encode(['v'=>1,'id'=>$id,'title'=>$id,'type'=>'text','ms'=>$ms,'visits'=>$visits,'actions'=>[],'events'=>[]]);};
    $records=[$make(1,1,7,'cmi.core.lesson_status','failed','admin'),$make(1,1,7,'cmi.core.score.raw','0','admin'),
        $make(1,1,7,'seleda.usage.v1.a.'.$session,$usage('a',10,1),'admin'),$make(1,1,7,'seleda.usage.v1.b.'.$session,$usage('b',20,1),'admin'),
        $make(1,1,7,'seleda.usage.v1.a.'.$other,$usage('a',0,0),'admin')];
    $calc=new \mod_seledascorm\local\learning_insights($pages,(object)['grademethod'=>'1','maxgrade'=>100],null,false,900000);
    iterator_to_array($calc->observe($records));$d=$calc->report([2=>(object)['firstname'=>'No','lastname'=>'Access','dashboardrole'=>'staff']]);$p=$d['learners'][1];$a=$p['attempts'][1];
    verify($p['role']==='admin'&&$d['learners'][2]['role']==='staff','Roles missing');
    verify($p['status']==='inprogress'&&$p['result']==='failed'&&$p['needsattention'],'Failure must be a result, not completion');
    verify($a['pagestate'][7]==='failed'&&$a['pagestate'][8]==='notstarted'&&$a['pagesnotstarted']===2,'Missing pages omitted');
    verify($a['pagetimems'][7]===30&&$a['pagevisits'][7]===1,'Block visits summed or time lost');
    verify($p['score']===0.0&&$d['learners'][2]['score']===null&&$d['roster']===2,'Null and zero or roster denominator confused');
    verify(\mod_seledascorm\local\report_view::sample(20,3,true)['value']===null,'Small insights sample exposed');
    verify(\mod_seledascorm\local\report_view::sample(20,3)['value']===20.0,'Small teacher sample hidden');
    foreach (['1'=>40.0,'2'=>30.0,'3'=>60.0,'0'=>null] as $method=>$expected) {
        $c=new \mod_seledascorm\local\learning_insights($pages,(object)['grademethod'=>(string)$method,'maxgrade'=>100]);
        iterator_to_array($c->observe([$make(1,1,7,'cmi.core.score.raw','20'),$make(1,1,8,'cmi.core.score.raw','40')]));
        $x=$c->report();verify($x['learners'][1]['score']===$expected,'Grading method changed: '.$method);
        if ((string)$method==='0') verify(array_sum($x['scoreBins'])===0&&$x['improvement']===null,'Completion mode entered assessment statistics');
    }
    $cross=\mod_seledascorm\local\report_view::comparison([['name'=>'A','data'=>$d],['name'=>'B','data'=>$d]]);
    verify($cross['unique']===2&&$cross['participations']===4,'People confused with participations');
    $history=\mod_seledascorm\local\report_view::attempts([1=>['score'=>0,'percent'=>0,'ms'=>100,'timeknown'=>true],2=>['score'=>50,'percent'=>50,'ms'=>20,'timeknown'=>true]]);
    verify($history['best']===50&&$history['change']===50&&$history['extratime']===20,'Attempt summary missing real zero');
    verify(\mod_seledascorm\local\report_view::attempts([1=>$a])['change']===null,'Single attempt invented improvement');
}
