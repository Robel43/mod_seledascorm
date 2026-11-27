<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_seledascorm\local;

defined('MOODLE_INTERNAL') || die();

/** Resolve the same default Enter action without an intermediate launch form. */
final class direct_launch {
    public static function url($activity, $user, int $cmid): ?\moodle_url {
        global $DB;
        // Unpublished authoring projects must retain their conversion/editor page.
        if (strtolower(pathinfo($activity->reference ?? '', PATHINFO_EXTENSION)) === 'elpx') {
            return null;
        }
        if ($activity->updatefreq == SELEDASCORM_UPDATE_EVERYTIME) {
            seledascorm_parse($activity, false);
        }
        $scoes = $DB->get_records_select('seledascorm_scoes', 'seledascorm = ? AND ' .
            $DB->sql_isnotempty('seledascorm_scoes', 'launch', false, true), [$activity->id], 'sortorder, id', 'id');
        if (!$scoes) { return null; }
        $organization = '';
        $root = seledascorm_get_sco($activity->launch, SELEDASCORM_SCO_ONLY);
        if ($root) {
            $organization = $root->organization === '' && $root->launch === '' ? $root->identifier : $root->organization;
        }
        $toc = seledascorm_get_toc($user, $activity, $cmid, SELEDASCORM_TOCFULLURL, $organization);
        $resume = $toc->sco->id ?? 0;
        $first = reset($scoes);
        $scoid = $activity->forcenewattempt != SELEDASCORM_FORCEATTEMPT_ALWAYS && isset($scoes[$resume]) ? $resume : $first->id;
        $url = new \moodle_url('/mod/seledascorm/player.php', [
            'cm'=>$cmid, 'scoid'=>$scoid, 'currentorg'=>$organization, 'mode'=>'normal',
        ]);
        if ($activity->forcenewattempt == SELEDASCORM_FORCEATTEMPT_ALWAYS ||
                ($activity->forcenewattempt == SELEDASCORM_FORCEATTEMPT_ONCOMPLETE && $toc->incomplete === false)) {
            $url->param('newattempt', 'on');
        }
        // Direct navigation uses the current tab even for legacy popup activities.
        // This selects actual content in player.php, rather than its popup launcher.
        if (!empty($activity->popup)) { $url->param('display', 'popup'); }
        // player.php remains responsible for availability, attempts and sequencing.
        return $url;
    }
}
