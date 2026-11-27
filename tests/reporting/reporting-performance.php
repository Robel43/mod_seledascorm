<?php
// Reproducible in-memory stream benchmark. Includes no database or network timings.
ob_start();require(__DIR__.'/dashboard.php');ob_end_clean();
use mod_seledascorm\local\learning_insights;
function performance_records(): iterable {
    for($u=1;$u<=50;$u++)for($s=1;$s<=5;$s++) {
        $base=['userid'=>$u,'firstname'=>'Person','lastname'=>(string)$u,'attempt'=>1,'scoid'=>$s,'timemodified'=>1700000000];
        yield (object)($base+['element'=>'cmi.core.lesson_status','value'=>'completed']);
        yield (object)($base+['element'=>'cmi.core.score.raw','value'=>'80']);
        yield (object)($base+['element'=>'seleda.usage.v1.block.'.str_repeat('a',24),'value'=>json_encode(['v'=>1,'id'=>'block','title'=>'Block','type'=>'text','ms'=>1000,'visits'=>1,'actions'=>[],'events'=>[]])]);
        for($q=0;$q<10;$q++)foreach(['id'=>'q'.$q,'type'=>'choice','student_response'=>'a','correct_responses_0.pattern'=>'a','result'=>'correct'] as $field=>$value)
            yield (object)($base+['element'=>'cmi.interactions_'.$q.'.'.$field,'value'=>$value]);
    }
}
$pages=[];$catalog=[];
for($s=1;$s<=5;$s++)$pages[$s]=(object)['title'=>'Page '.$s,'launch'=>'page'.$s.'.html'];
for($q=0;$q<10;$q++)$catalog['q'.$q]=['prompt'=>'Question '.$q,'type'=>'choice','blockid'=>'block','block'=>'Block','options'=>['a'=>'Answer'],'tracking'=>['mode'=>'graded']];
$run=function($questions,$activities)use($pages,$catalog){
    $start=hrtime(true);$items=[];
    for($i=0;$i<$activities;$i++) {
        $calc=new learning_insights($pages,(object)['grademethod'=>'1','maxgrade'=>100],function()use($catalog){return $catalog;},$questions,1700000100);
        foreach($calc->observe(performance_records()) as $ignored){};
        $items[]=['name'=>'Activity '.$i,'data'=>$calc->report()];
    }
    if($activities>1)\mod_seledascorm\local\report_view::comparison($items);
    return (hrtime(true)-$start)/1e6;
};
$out=[];
foreach(['overview_without_questions'=>[false,1],'overview_with_questions'=>[true,1],'insights_10_activities'=>[true,10]]as $name=>$args){
    $values=[];for($i=0;$i<5;$i++)$values[]=$run(...$args);
    $out[$name.'_median_ms']=round(learning_insights::median($values),2);
}
$out['fixture']='50 people, 5 pages, 10 questions per page per activity';
echo json_encode($out,JSON_PRETTY_PRINT)."\n";
