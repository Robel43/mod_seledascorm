<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Core Report class of basic reporting plugin
 * @package   exescormreport
 * @subpackage interactions
 * @author    Dan Marsden and Ankit Kumar Agarwal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace exescormreport_interactions;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/exescorm/report/interactions/responsessettings_form.php');
require_once($CFG->dirroot.'/mod/exescorm/report/timhirt_reporting.php');
require_once($CFG->dirroot.'/mod/exescorm/report/seleda_usage.php');
use mod_exescorm\local\timhirt_reporting;
use mod_exescorm\local\seleda_usage;

class report extends \mod_exescorm\report {
    /** Read only the authorised report attempts, in one query for a bounded chunk. */
    private function load_track_batch($activityid, array $attempts) {
        global $DB;
        $clauses = []; $params = ['activity' => $activityid];
        foreach ($attempts as $i => $attempt) {
            if (empty($attempt->attempt)) { continue; }
            $clauses[] = "(userid = :user$i AND attempt = :attempt$i)";
            $params["user$i"] = $attempt->userid;
            $params["attempt$i"] = $attempt->attempt;
        }
        if (!$clauses) { return []; }
        $sql = 'SELECT * FROM {exescorm_scoes_track} WHERE exescormid = :activity AND (' . implode(' OR ', $clauses) . ') ORDER BY element ASC';
        $records = $DB->get_recordset_sql($sql, $params);
        $batch = []; $raw = [];
        try {
            foreach ($records as $track) {
                $key = $track->userid . ':' . $track->attempt;
                if (!isset($batch[$key])) {
                    $batch[$key] = ['runtime' => (object)['start' => $track->timemodified, 'finish' => $track->timemodified], 'scoes' => []];
                }
                $runtime = $batch[$key]['runtime'];
                $runtime->start = min($runtime->start, $track->timemodified);
                $runtime->finish = max($runtime->finish, $track->timemodified);
                $raw[$key][$track->scoid][] = $track;
            }
        } finally { $records->close(); }
        foreach ($raw as $key => $scoes) {
            $usagetracks = [];
            foreach ($scoes as $scoid => $tracks) {
                $usagetracks = array_merge($usagetracks, $tracks);
                // Native formatter retains SCORM 1.2/2004 status and interaction semantics.
                $formatted = exescorm_format_interactions($tracks);
                $formatted->userid = $tracks[0]->userid;
                $formatted->scoid = $scoid;
                $batch[$key]['scoes'][$scoid] = $formatted;
            }
            $batch[$key]['usage'] = seleda_usage::summary($usagetracks);
        }
        return $batch;
    }

    /** Same grading methods as exescorm_grade_user_attempt, using the batch above. */
    private function batch_grade($activity, $scoes, $tracks) {
        $count = 0; $values = 0; $sum = 0; $max = 0;
        foreach ($scoes as $sco) {
            if (!$data = ($tracks[$sco->id] ?? false)) { continue; }
            if ($data->status === 'completed' || $data->status === 'passed') { $count++; }
            if (isset($data->score_raw) && $data->score_raw !== '') {
                $values++; $sum += $data->score_raw; $max = max($max, $data->score_raw);
            }
        }
        switch ($activity->grademethod) {
            case EXESCORM_GRADEAVERAGE: return $values ? $sum / $values : 0;
            case EXESCORM_GRADESUM: return $sum;
            case EXESCORM_GRADESCOES: return $count;
            default: return (float)$max;
        }
    }

    /**
     * displays the full report
     * @param \stdClass $exescorm full EXESCORM …3346 tokens truncated…                   $colnum++;
                }
                $rownum = 1;
            } else if ($download == 'Excel') {
                require_once("$CFG->libdir/excellib.class.php");

                $filename .= ".xls";
                // Creating a workbook.
                $workbook = new \MoodleExcelWorkbook("-");
                // Sending HTTP headers.
                $workbook->send($filename);
                // Creating the first worksheet.
                $sheettitle = get_string('report', 'mod_exescorm');
                $myxls = $workbook->add_worksheet($sheettitle);
                // Format types.
                $format = $workbook->add_format();
                $format->set_bold(0);
                $formatbc = $workbook->add_format();
                $formatbc->set_bold(1);
                $formatbc->set_align('center');
                $formatb = $workbook->add_format();
                $formatb->set_bold(1);
                $formaty = $workbook->add_format();
                $formaty->set_bg_color('yellow');
                $formatc = $workbook->add_format();
                $formatc->set_align('center');
                $formatr = $workbook->add_format();
                $formatr->set_bold(1);
                $formatr->set_color('red');
                $formatr->set_align('center');
                $formatg = $workbook->add_format();
                $formatg->set_bold(1);
                $formatg->set_color('green');
                $formatg->set_align('center');

                $colnum = 0;
                foreach ($headers as $item) {
                    $myxls->write(0, $colnum, $item, $formatbc);
                    $colnum++;
                }
                $rownum = 1;
            } else if ($download == 'CSV') {
                $csvexport = new \csv_export_writer("tab");
                $csvexport->set_filename($filename, ".txt");
                $csvexport->add_data($headers);
            }

            if (!$download) {
                $sort = $table->get_sql_sort();
            } else {
                $sort = '';
            }
            // Fix some wired sorting.
            if (empty($sort)) {
                $sort = ' ORDER BY uniqueid';
            } else {
                $sort = ' ORDER BY '.$sort;
            }

            if (!$download) {
                // Add extra limits due to initials bar.
                list($twhere, $tparams) = $table->get_sql_where();
                if ($twhere) {
                    $where .= ' AND '.$twhere; // Initial bar.
                    $params = array_merge($params, $tparams);
                }

                if (!empty($countsql)) {
                    $count = $DB->get_record_sql($countsql, $params);
                    $totalinitials = $count->nbresults;
                    if ($twhere) {
                        $countsql .= ' AND '.$twhere;
                    }
                    $count = $DB->get_record_sql($countsql, $params);
                    $total = $count->nbresults;
                }

                $table->pagesize($pagesize, $total);

                echo \html_writer::start_div('exescormattemptcounts');
                if ( $count->nbresults == $count->nbattempts ) {
                    echo get_string('reportcountattempts', 'mod_exescorm', $count);
                } else if ( $count->nbattempts > 0 ) {
                    echo get_string('reportcountallattempts', 'mod_exescorm', $count);
                } else {
                    echo $count->nbusers.' '.get_string('users');
                }
                echo \html_writer::end_div();
            }

            // Fetch the attempts.
            if (!$download) {
                $attempts = $DB->get_records_sql($select.$from.$where.$sort, $params,
                $table->get_page_start(), $table->get_page_size());
                echo \html_writer::start_div('', array('id' => 'exescormtablecontainer'));
                if ($candelete) {
                    // Start form.
                    $strreallydel = addslashes_js(get_string('deleteattemptcheck', 'mod_exescorm'));
                    echo \html_writer::start_tag('form', array('id' => 'attemptsform', 'method' => 'post',
                                                                'action' => $PAGE->url->out(false),
                                                                'onsubmit' => 'return confirm("'.$strreallydel.'");'));
                    echo \html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'delete'));
                    echo \html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                    echo \html_writer::start_div('', array('style' => 'display: none;'));
                    echo \html_writer::input_hidden_params($PAGE->url);
                    echo \html_writer::end_div();
                    echo \html_writer::start_div();
                }
                $table->initialbars($totalinitials > 20); // Build table rows.
            } else {
                $attempts = $DB->get_records_sql($select.$from.$where.$sort, $params);
            }
            if ($attempts) {
                $pagedattempts = array_values($attempts);
                foreach ($pagedattempts as $rowindex => $scouser) {
                    // Limit raw interaction memory during downloads as well as on paged reports.
                    if ($rowindex % 25 === 0) {
                        $trackbatch = $this->load_track_batch($exescorm->id, array_slice($pagedattempts, $rowindex, 25));
                    }
                    $attemptkey = $scouser->userid . ':' . $scouser->attempt;
                    $attempttracks = $trackbatch[$attemptkey] ?? ['runtime' => (object)['start' => false, 'finish' => false], 'scoes' => []];
                    $row = array();
                    if (!empty($scouser->attempt)) {
                        $timetracks = $attempttracks['runtime'];
                    } else {
                        $timetracks = '';
                    }
                    if (in_array('checkbox', $columns)) {
                        if ($candelete && !empty($timetracks->start)) {
                            $row[] = $this->generate_row_checkbox('attemptid[]', "{$scouser->userid}:{$scouser->attempt}");
                        } else if ($candelete) {
                            $row[] = '';
                        }
                    }
                    if (in_array('picture', $columns)) {
                        $user = new \stdClass();
                        $additionalfields = explode(',', implode(',', \core_user\fields::get_picture_fields()));
                        $user = username_load_fields_from_object($user, $scouser, null, $additionalfields);
                        $user->id = $scouser->userid;
                        $row[] = $OUTPUT->user_picture($user, array('courseid' => $course->id));
                    }
                    if (!$download) {
                        $url = new \moodle_url('/user/view.php', array('id' => $scouser->userid, 'course' => $course->id));
                        $row[] = \html_writer::link($url, fullname($scouser));
                    } else {
                        $row[] = fullname($scouser);
                    }
                    foreach ($extrafields as $field) {
                        $row[] = s($scouser->{$field});
                    }
                    if (empty($timetracks->start)) {
                        $row[] = '-';
                        $row[] = '-';
                        $row[] = '-';
                        $row[] = '-';
                    } else {
                        if (!$download) {
                            $url = new \moodle_url('/mod/exescorm/report/userreport.php',
                                array('id' => $cm->id,
                                    'user' => $scouser->userid,
                                    'attempt' => $scouser->attempt,
                                    'mode' => 'interactions'));
                            $row[] = \html_writer::link($url, $scouser->attempt);
                        } else {
                            $row[] = $scouser->attempt;
                        }
                        if ($download == 'ODS' || $download == 'Excel' ) {
                            $row[] = userdate($timetracks->start, get_string("strftimedatetime", "langconfig"));
                        } else {
                            $row[] = userdate($timetracks->start);
                        }
                        if ($download == 'ODS' || $download == 'Excel' ) {
                            $row[] = userdate($timetracks->finish, get_string('strftimedatetime', 'langconfig'));
                        } else {
                            $row[] = userdate($timetracks->finish);
                        }
                        $row[] = $this->batch_grade($exescorm, $scoes, $attempttracks['scoes']);
                    }
                    $usage = $attempttracks['usage'] ?? seleda_usage::summary([]);
                    $pages = []; foreach ($scoes as $page) { $pages[$page->id] = $page->title; }
                    $row[] = $usage['available'] ? seleda_usage::duration($usage['ms']) : '-';
                    $row[] = $usage['available'] ? $usage['interactions'] : '-';
                    if ($download) { $row[] = timhirt_reporting::exportcell(seleda_usage::compact($usage, $pages)); }
                    else if ($usage['available']) {
                        $detailurl = new \moodle_url('/mod/exescorm/report/userreportinteractions.php', ['id'=>$cm->id, 'user'=>$scouser->userid, 'attempt'=>$scouser->attempt]);
                        $row[] = '<details><summary>' . s(get_string('usageviewblocks', 'mod_exescorm')) . '</summary>' . seleda_usage::table($usage, $pages) . \html_writer::link($detailurl, get_string('usageviewlog', 'mod_exescorm')) . '</details>';
                    } else { $row[] = '-'; }
                    // Print out all scores of attempt.
                    $emptyrow = $download ? '' : '&nbsp;';
                    foreach ($scoes as $sco) {
                        if ($sco->launch != '') {
                            if (!empty($scouser->attempt) && ($trackdata = ($attempttracks['scoes'][$sco->id] ?? false))) {
                                if ($trackdata->status == '') {
                                    $trackdata->status = 'notattempted';
                                }
                                $strstatus = get_string($trackdata->status, 'mod_exescorm');
                                // If raw score exists, print it.
                                if ($trackdata->score_raw != '') {
                                    $score = $trackdata->score_raw;
                                    // Add max score if it exists.
                                    if (isset($trackdata->score_max)) {
                                        $score .= '/'.$trackdata->score_max;
                                    }
                                } else { // Else print out status.
                                    $score = $strstatus;
                                }
                                if (!$download) {
                                    $url = new \moodle_url('/mod/exescorm/report/userreporttracks.php', array('id' => $cm->id,
                                        'scoid' => $sco->id, 'user' => $scouser->userid, 'attempt' => $scouser->attempt,
                                        'mode' => 'interactions'));
                                    $row[] = $OUTPUT->pix_icon($trackdata->status, $strstatus, 'exescorm') . '<br>' .
                                        \html_writer::link($url, $score, array('title' => get_string('details', 'mod_exescorm')));
                                } else {
                                    $row[] = $score;
                                }
                                // Use the same decoder and attempt snapshots as the learner detail report.
                                $cells = timhirt_reporting::overviewcells((int)$sco->id, $trackdata,
                                    $catalogs[$sco->id] ?? [], $questioncount, $displayoptions);
                                foreach ($cells as $cell) {
                                    $row[] = $download ? timhirt_reporting::exportcell($cell) : s($cell);
                                }
                                // End of interaction data.
                            } else {
                                // If we don't have track data, we haven't attempted yet.
                                $strstatus = get_string('notattempted', 'mod_exescorm');
                                if (!$download) {
                                    $row[] = $OUTPUT->pix_icon('notattempted', $strstatus, 'exescorm') . '<br>' . $strstatus;
                                } else {
                                    $row[] = $strstatus;
                                }
                                // Complete the empty cells.
                                for ($i = 0; $i < $questioncount * count(array_filter(array_intersect_key($displayoptions, array_flip(['qtext', 'resp', 'right', 'result'])))); $i++) {
                                    $row[] = $emptyrow;
                                }
                            }
                        }
                    }

                    if ($download) {
                        $row = array_map([timhirt_reporting::class, 'exportcell'], array_map('strval', $row));
                    }
                    if (!$download) {
                        $table->add_data($row);
                    } else if ($download == 'Excel' || $download == 'ODS') {
                        $colnum = 0;
                        foreach ($row as $item) {
                            $myxls->write($rownum, $colnum, $item, $format);
                            $colnum++;
                        }
                        $rownum++;
                    } else if ($download == 'CSV') {
                        $csvexport->add_data($row);
                    }
                }
                if (!$download) {
                    $table->finish_output();
                    if ($candelete) {
                        echo \html_writer::start_tag('table', array('id' => 'commands'));
                        echo \html_writer::start_tag('tr').\html_writer::start_tag('td');
                        echo $this->generate_delete_selected_button();
                        echo \html_writer::end_tag('td').\html_writer::end_tag('tr').\html_writer::end_tag('table');
                        // Close form.
                        echo \html_writer::end_tag('div');
                        echo \html_writer::end_tag('form');
                    }
                }
            } else {
                if ($candelete && !$download) {
                    echo \html_writer::end_div();
                    echo \html_writer::end_tag('form');
                    $table->finish_output();
                }
                echo \html_writer::end_div();
            }
            // Show preferences form irrespective of attempts are there to report or not.
            if (!$download) {
                $mform->set_data(compact('pagesize', 'attemptsmode'));
                $mform->display();
            }
            if ($download == 'Excel' || $download == 'ODS') {
                $workbook->close();
                exit;
            } else if ($download == 'CSV') {
                $csvexport->download_file();
                exit;
            }
        } else {
            echo $OUTPUT->notification(get_string('noactivity', 'mod_exescorm'));
        }
    }// Function ends.
}
