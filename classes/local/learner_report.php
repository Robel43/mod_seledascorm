<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;
defined('MOODLE_INTERNAL') || die();

/** Builds one learner's already-authorised detail; tracks are rechecked against group scope. */
final class learner_report {
    public static function build($activity,$cm,array $learner,int $attempt,array $pagesource,int $group,string $tab,bool $diagnostics=false): array {
        $tracks = reporting_dashboard::detail($activity, $cm, $learner['id'], $attempt, $group);
        $pages = array_map(function($p) { return $p->title; }, $pagesource);
        $catalogues = [];
        foreach ($tracks as $track) {
            if (!array_key_exists($track->scoid, $catalogues)) {
                $catalogues[$track->scoid] = timhirt_reporting::packagecatalog($cm->context, $activity, (int)$track->scoid);
            }
        }
        $usage=seleda_usage::summary($tracks);
        $blockmeta=\mod_seledascorm\output\dashboard_detail::titles($cm->context,$pagesource);
        $questions=report_view::answers($tracks,$catalogues,$pages);
        $blockstates=timhirt_reporting::blockstates($tracks,$catalogues);
        $pageitems=report_view::learner_pages($learner,$attempt,$pagesource,$blockmeta,$usage,$blockstates);
        $event=\mod_seledascorm\event\user_report_viewed::create(['context'=>$cm->context,'relateduserid'=>$learner['id'],
            'other'=>['attemptid'=>$attempt,'instanceid'=>$activity->id]]);
        $event->trigger();
        return ['person'=>$learner,'selected'=>$learner['attempts'][$attempt], 'attempt'=>$attempt,
            'pages'=>$pageitems,'questions'=>$questions,'history'=>report_view::attempts($learner['attempts']),
            'usagehtml'=>$tab==='technical' || $diagnostics ?
                \mod_seledascorm\output\dashboard_detail::tracking($blockstates,$pages) .
                \mod_seledascorm\output\dashboard_detail::usage($usage,$pages,timhirt_reporting::rows($tracks,$catalogues)) : ''];

    }
}
