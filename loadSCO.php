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

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/seledascorm/locallib.php');

$id = optional_param('id', '', PARAM_INT);    // Course Module ID, or...
$a = optional_param('a', '', PARAM_INT);     // Exescorm ID.
$scoid = required_param('scoid', PARAM_INT);     // Sco ID.

$delayseconds = 2;  // Delay time before sco launch, used to give time to browser to define API.

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('seledascorm', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $seledascorm = $DB->get_record('seledascorm', array('id' => $cm->instance))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else if (!empty($a)) {
    if (! $seledascorm = $DB->get_record('seledascorm', array('id' => $a))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $course = $DB->get_record('course', array('id' => $seledascorm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('seledascorm', $seledascorm->id, $course->id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else {
    throw new \moodle_exception('missingparameter');
}

$PAGE->set_url('/mod/seledascorm/loadSCO.php', array('scoid' => $scoid, 'id' => $cm->id));

if (!isloggedin()) { // Prevent login page from being shown in iframe.
    // Using simple html instead of exceptions here as shown inside iframe/object.
    echo html_writer::start_tag('html');
    echo html_writer::tag('head', '');
    echo html_writer::tag('body', get_string('loggedinnot'));
    echo html_writer::end_tag('html');
    exit;
}

require_login($course, false, $cm, false); // Call require_login anyway to set up globals correctly.

// Check if SELEDASCORM is available.
seledascorm_require_available($seledascorm);

$context = context_module::instance($cm->id);

// Forge SCO URL.
list($sco, $scolaunchurl) = seledascorm_get_sco_and_launch_url($seledascorm, $scoid, $context);

if ($sco->seledascormtype == 'asset') {
    $attempt = seledascorm_get_last_attempt($seledascorm->id, $USER->id);
    $element = (seledascorm_version_check($seledascorm->version, SELEDASCORM_SCORM_13)) ? 'cmi.completion_status' : 'cmi.core.lesson_status';
    $value = 'completed';
    seledascorm_insert_track($USER->id, $seledascorm->id, $sco->id, $attempt, $element, $value);
}

// Trigger the SCO launched event.
seledascorm_launch_sco($seledascorm, $sco, $cm, $context, $scolaunchurl);

header('Content-Type: text/html; charset=UTF-8');

if ($sco->seledascormtype == 'asset') {
    // HTTP 302 Found => Moved Temporarily.
    header('Location: ' . $scolaunchurl);
    // Provide a short feedback in case of slow network connection.
    echo html_writer::start_tag('html');
    echo html_writer::tag('body', html_writer::tag('p', get_string('activitypleasewait', 'mod_seledascorm')));
    echo html_writer::end_tag('html');
    exit;
}

// We expect a SCO: select which API are we looking for.
$lmsapi = (seledascorm_version_check($seledascorm->version, SELEDASCORM_SCORM_12) || empty($seledascorm->version)) ? 'API' : 'API_1484_11';

echo html_writer::start_tag('html');
echo html_writer::start_tag('head');
echo html_writer::tag('title', 'LoadSCO');
?>
    <script type="text/javascript">
    //<![CDATA[
    var myApiHandle = null;
    var myFindAPITries = 0;

    function myGetAPIHandle() {
       myFindAPITries = 0;
       if (myApiHandle == null) {
          myApiHandle = myGetAPI();
       }
       return myApiHandle;
    }

    function myFindAPI(win) {
       while ((win.<?php echo $lmsapi; ?> == null) && (win.parent != null) && (win.parent != win)) {
          myFindAPITries++;
          // Note: 7 is an arbitrary number, but should be more than sufficient
          if (myFindAPITries > 7) {
             return null;
          }
          win = win.parent;
       }
       return win.<?php echo $lmsapi; ?>;
    }

    // hun for the API - needs to be loaded before we can launch the package
    function myGetAPI() {
       var theAPI = myFindAPI(window);
       if ((theAPI == null) && (window.opener != null) && (typeof(window.opener) != "undefined")) {
          theAPI = myFindAPI(window.opener);
       }
       if (theAPI == null) {
          return null;
       }
       return theAPI;
    }

   function doredirect() {
        if (myGetAPIHandle() != null) {
            location = "<?php echo $scolaunchurl ?>";
        }
        else {
            document.body.innerHTML = "<p><?php echo get_string('activityloading', 'mod_seledascorm');?>" +
                                        "<span id='countdown'><?php echo $delayseconds ?></span> " +
                                        "<?php echo get_string('numseconds', 'moodle', '');?>. &nbsp; " +
                                        "<?php echo addslashes($OUTPUT->pix_icon('wait', '', 'seledascorm')); ?></p>";
            var e = document.getElementById("countdown");
            var cSeconds = parseInt(e.innerHTML);
            var timer = setInterval(function() {
                                            if( cSeconds && myGetAPIHandle() == null ) {
                                                e.innerHTML = --cSeconds;
                                            } else {
                                                clearInterval(timer);
                                                document.body.innerHTML =
                                                    "<p><?php echo get_string('activitypleasewait', 'mod_seledascorm');?></p>";
                                                location = "<?php echo $scolaunchurl ?>";
                                            }
                                        }, 1000);
        }
    }
    //]]>
    </script>
    <noscript>
        <meta http-equiv="refresh" content="0;url=<?php echo $scolaunchurl ?>" />
    </noscript>
<?php
echo html_writer::end_tag('head');
echo html_writer::tag('body',
                        html_writer::tag('p', get_string('activitypleasewait', 'mod_seledascorm')),
                        ['onload' => "doredirect();"]
                    );
echo html_writer::end_tag('html');
