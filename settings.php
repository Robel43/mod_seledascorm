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

defined('MOODLE_INTERNAL') || die;

// Register the styles management admin page so it is reachable from
// `/admin/settings.php` and from a dedicated link below.
if ($hassiteconfig) {
    $ADMIN->add(
        'modsettings',
        new admin_externalpage(
            'mod_seledascorm_styles',
            get_string('stylesmanager', 'mod_seledascorm'),
            new moodle_url('/mod/seledascorm/admin/styles.php'),
            ['moodle/site:config', 'mod/seledascorm:manageembeddededitor']
        )
    );
}

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/mod/seledascorm/locallib.php');
    $yesno = [0 => get_string('no'),
                   1 => get_string('yes')];

    // Site branding. Moodle presents new plugin settings during the web
    // installation/upgrade flow, so these fields form the first-run Seleda
    // branding step and remain editable later from Site administration.
    $settings->add(new admin_setting_heading(
        'mod_seledascorm/brandingsettings',
        get_string('brandingsettings', 'mod_seledascorm'),
        get_string('brandingsettings_desc', 'mod_seledascorm')
    ));

    $settings->add(new admin_setting_requiredtext(
        'mod_seledascorm/brandname',
        get_string('brandname', 'mod_seledascorm'),
        get_string('brandname_desc', 'mod_seledascorm'),
        'Seleda',
        PARAM_TEXT
    ));

    $brandlogosetting = new admin_setting_configstoredfile(
        'mod_seledascorm/brandlogo',
        get_string('brandlogo', 'mod_seledascorm'),
        get_string('brandlogo_desc', 'mod_seledascorm'),
        'brandinglogo',
        0,
        [
            'maxfiles' => 1,
            'subdirs' => 0,
            'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp', '.svg'],
        ]
    );
    $brandlogosetting->set_updatedcallback('purge_all_caches');
    $settings->add($brandlogosetting);

    // Embedded editor settings.
    $settings->add(new admin_setting_heading('seledascorm/embeddededitorsettings',
        get_string('embeddededitorsettings', 'mod_seledascorm'), ''));

    $editormodedesc = get_string('editormodedesc', 'mod_seledascorm');

    $editormodes = [
        'online' => get_string('editormodeonline', 'mod_seledascorm'),
        'embedded' => get_string('editormodeembedded', 'mod_seledascorm'),
    ];
    $settings->add(new admin_setting_configselect('seledascorm/editormode',
        get_string('editormode', 'mod_seledascorm'), $editormodedesc,
        'embedded', $editormodes));

    $settings->add(new \mod_seledascorm\admin\admin_setting_embeddededitor(
        get_string('embeddededitorstatus', 'mod_seledascorm'),
        ''
    ));

    // Inline style ZIP upload (native filemanager). Each dropped .zip is
    // validated + extracted + registered on save; the file is then
    // removed from the filearea so the next render starts clean.
    $settings->add(new \mod_seledascorm\admin\admin_setting_stylesupload(
        'seledascorm/styles_drops',
        get_string('stylesupload_label', 'mod_seledascorm'),
        get_string('stylesupload_hint', 'mod_seledascorm',
            display_size(\mod_seledascorm\local\styles_service::get_max_zip_size())),
        'styles_drops',
        0,
        [
            'accepted_types' => ['.zip'],
            'maxbytes' => \mod_seledascorm\local\styles_service::get_max_zip_size(),
            'maxfiles' => -1,
            'subdirs' => 0,
        ]
    ));

    // Uploaded styles list (checkbox per style + per-row delete link).
    $settings->add(new \mod_seledascorm\admin\admin_setting_stylesuploaded(
        'seledascorm/styles_uploaded'
    ));

    // Built-in styles list (checkbox per style).
    $settings->add(new \mod_seledascorm\admin\admin_setting_stylesbuiltins(
        'seledascorm/styles_builtins'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'seledascorm/stylesblockimport',
        get_string('stylesblockimport', 'mod_seledascorm'),
        get_string('stylesblockimport_desc', 'mod_seledascorm'),
        0
    ));

    // JavaScript to toggle connection settings visibility based on editor mode.
    $connectionsettingsdesc = '<script>
document.addEventListener("DOMContentLoaded", function() {
    var modeSelect = document.getElementById("id_s_seledascorm_editormode");
    if (!modeSelect) return;
    var connectionIds = [
        "admin-connectionsettings", "admin-exeonlinebaseuri", "admin-providername",
        "admin-providerversion", "admin-hmackey1", "admin-tokenexpiration"
    ];
    var embeddedWidget = document.querySelector(".mod_seledascorm-admin-embedded-editor-setting");
    function toggleConnectionSettings() {
        var show = (modeSelect.value === "online");
        connectionIds.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = show ? "" : "none";
        });
        if (embeddedWidget) embeddedWidget.style.display = (modeSelect.value === "embedded") ? "" : "none";
        ["admin-styles_drops", "admin-styles_uploaded", "admin-styles_builtins",
            "admin-stylesblockimport"].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = (modeSelect.value === "embedded") ? "" : "none";
        });
    }
    modeSelect.addEventListener("change", toggleConnectionSettings);
    toggleConnectionSettings();
});
</script>';

    // Connection settings.
    $settings->add(new admin_setting_heading('seledascorm/connectionsettings',
        get_string('exeonline:connectionsettings', 'mod_seledascorm'), $connectionsettingsdesc));

    $settings->add(new admin_setting_configtext('seledascorm/exeonlinebaseuri',
        get_string('exeonline:baseuri', 'mod_seledascorm'),
        get_string('exeonline:baseuri_desc', 'mod_seledascorm'), '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configpasswordunmask('seledascorm/hmackey1',
        get_string('exeonline:hmackey1', 'mod_seledascorm'),
        get_string('exeonline:hmackey1_desc', 'mod_seledascorm'), ''));

    $settings->add(new admin_setting_configtext('seledascorm/providername',
        get_string('exeonline:provider_name', 'mod_seledascorm'),
        get_string('exeonline:provider_name_desc', 'mod_seledascorm'), 'Moodle'));

    $moodleversion = substr($CFG->release, 0, 3);
    $settings->add(new admin_setting_configtext('seledascorm/providerversion',
        get_string('exeonline:provider_version', 'mod_seledascorm'),
        get_string('exeonline:provider_version_desc', 'mod_seledascorm')
            . "<br><code>Current Moodle Version: "
            . $CFG->release . "</code>", $moodleversion));

    $settings->add(new admin_setting_configduration('seledascorm/tokenexpiration',
        get_string('exeonline:tokenexpiration', 'mod_seledascorm'),
        get_string('exeonline:tokenexpiration_desc', 'mod_seledascorm'), 86400, 1));

    // Exescorm default template.
    $filemanageroptions = [
        'accepted_types' => ['.zip'],
        'maxbytes' => 0,
        'maxfiles' => 1,
        'subdirs' => 0,
    ];

    $settings->add(new \mod_seledascorm\admin\admin_setting_optional_configstoredfile('seledascorm/template',
        get_string('seledascorm:template', 'mod_seledascorm'),
        get_string('seledascorm:template_desc', 'mod_seledascorm'),
        'config', 0, $filemanageroptions
    ));

    $settings->add(new admin_setting_configcheckbox('seledascorm/sendtemplate',
        get_string('seledascorm:sendtemplate', 'mod_seledascorm'), get_string('seledascorm:sendtemplate_desc', 'mod_seledascorm'), 0));

    // The eXescorm package validation rules.
    //
    // No file is mandatory by default. SCORM validity is enforced separately by
    // seledascorm_validate_package(), which requires a root imsmanifest.xml (or an
    // AICC .cst), so this list is purely a site policy knob: add
    // '/^content(v\d+)?\.xml$/' to accept eXeLearning-authored packages only.
    // It used to default to that, which rejected every plain SCORM package --
    // including one eXeLearning itself produces when the author turns the
    // "Editable export" property off, see
    // https://github.com/exelearning/exelearning/issues/2415. Such a package
    // plays fine here; it simply cannot be edited, and the "Edit in eXeLearning"
    // button is not offered for uploaded packages anyway.
    $mandatoryfilesre = '';
    $forbiddenfilesre = implode("\n", [
        '/.*\.php$/',
    ]);
    $settings->add(new admin_setting_configtextarea('seledascorm/mandatoryfileslist',
        new lang_string('seledascorm:mandatoryfileslist', 'mod_seledascorm'),
        new lang_string('seledascorm:mandatoryfileslist_desc', 'mod_seledascorm'), $mandatoryfilesre, PARAM_RAW, '50', '10'));
    $settings->add(new admin_setting_configtextarea('seledascorm/forbiddenfileslist',
        new lang_string('seledascorm:forbiddenfileslist', 'mod_seledascorm'),
        new lang_string('seledascorm:forbiddenfileslist_desc', 'mod_seledascorm'), $forbiddenfilesre, PARAM_RAW, '50', '10'));

    // Default display settings.
    $settings->add(new admin_setting_heading('seledascorm/displaysettings',
        get_string('defaultdisplaysettings', 'mod_seledascorm'), ''));

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/displaycoursestructure',
        get_string('displaycoursestructure', 'mod_seledascorm'), get_string('displaycoursestructuredesc', 'mod_seledascorm'),
        ['value' => 0, 'adv' => false], $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/popup',
        get_string('display', 'mod_seledascorm'), get_string('displaydesc', 'mod_seledascorm'),
        ['value' => 0, 'adv' => false], seledascorm_get_popup_display_array()));

    $settings->add(new admin_setting_configtext_with_advanced('seledascorm/framewidth',
        get_string('width', 'mod_seledascorm'), get_string('framewidth', 'mod_seledascorm'),
        ['value' => '100', 'adv' => true]));

    $settings->add(new admin_setting_configtext_with_advanced('seledascorm/frameheight',
        get_string('height', 'mod_seledascorm'), get_string('frameheight', 'mod_seledascorm'),
        ['value' => '500', 'adv' => true]));

    $settings->add(new admin_setting_configcheckbox('seledascorm/winoptgrp_adv',
         get_string('optionsadv', 'mod_seledascorm'), get_string('optionsadv_desc', 'mod_seledascorm'), 1));

    foreach (seledascorm_get_popup_options_array() as $key => $value) {
        $settings->add(new admin_setting_configcheckbox('seledascorm/'.$key,
            get_string($key, 'mod_seledascorm'), '', $value));
    }

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/skipview',
        get_string('skipview', 'mod_seledascorm'), get_string('skipviewdesc', 'mod_seledascorm'),
        ['value' => 2, 'adv' => true], seledascorm_get_skip_view_array()));

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/hidebrowse',
        get_string('hidebrowse', 'mod_seledascorm'), get_string('hidebrowsedesc', 'mod_seledascorm'),
        ['value' => 0, 'adv' => true], $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/hidetoc',
        get_string('hidetoc', 'mod_seledascorm'), get_string('hidetocdesc', 'mod_seledascorm'),
        ['value' => 0, 'adv' => true], seledascorm_get_hidetoc_array()));

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/nav',
        get_string('nav', 'mod_seledascorm'), get_string('navdesc', 'mod_seledascorm'),
        ['value' => SELEDASCORM_NAV_UNDER_CONTENT, 'adv' => true], seledascorm_get_navigation_display_array()));

    $settings->add(new admin_setting_configtext_with_advanced('seledascorm/navpositionleft',
        get_string('fromleft', 'mod_seledascorm'), get_string('navpositionleft', 'mod_seledascorm'),
        ['value' => -100, 'adv' => true]));

    $settings->add(new admin_setting_configtext_with_advanced('seledascorm/navpositiontop',
        get_string('fromtop', 'mod_seledascorm'), get_string('navpositiontop', 'mod_seledascorm'),
        ['value' => -100, 'adv' => true]));

    $settings->add(new admin_setting_configtext_with_advanced('seledascorm/collapsetocwinsize',
        get_string('collapsetocwinsize', 'mod_seledascorm'), get_string('collapsetocwinsizedesc', 'mod_seledascorm'),
        ['value' => 767, 'adv' => true]));

    $settings->add(new admin_setting_configselect_with_advanced('seledascorm/displayattemptstatus',
        get_string('displayattemptstatus', 'mod_seledascorm'), get_string('displayattemptstatusdesc', 'mod_seledascorm'),
        ['value' => 1, 'adv' => false], seledascorm_get_attemptstatus_array()));

    // Default grade settings.
    $settings->add(new admin_setting_heading('seledascorm/gradesettings', get_string('defaultgradesettings', 'mod_seledascorm'), ''));
    $settings->add(new admin_setting_configselect('seledascorm/grademethod',
        get_string('grademethod', 'mod_seledascorm'), get_string('grademethoddesc', 'mod_seledascorm'),
        SELEDASCORM_GRADEHIGHEST, seledascorm_get_grade_method_array()));

    for ($i = 0; $i <= 100; $i++) {
        $grades[$i] = "$i";
    }

    $settings->add(new admin_setting_configselect('seledascorm/maxgrade',
        get_string('maximumgrade'), get_string('maximumgradedesc', 'mod_seledascorm'), 100, $grades));

    $settings->add(new admin_setting_heading('seledascorm/othersettings', get_string('defaultothersettings', 'mod_seledascorm'), ''));

    // Default attempts settings.
    $settings->add(new admin_setting_configselect('seledascorm/maxattempt',
        get_string('maximumattempts', 'mod_seledascorm'), '', '0', seledascorm_get_attempts_array()));

    $settings->add(new admin_setting_configselect('seledascorm/whatgrade',
        get_string('whatgrade', 'mod_seledascorm'), get_string('whatgradedesc', 'mod_seledascorm'),
        SELEDASCORM_HIGHESTATTEMPT, seledascorm_get_what_grade_array()));

    $settings->add(new admin_setting_configselect('seledascorm/forcecompleted',
        get_string('forcecompleted', 'mod_seledascorm'), get_string('forcecompleteddesc', 'mod_seledascorm'), 0, $yesno));

    $forceattempts = seledascorm_get_forceattempt_array();
    $settings->add(new admin_setting_configselect('seledascorm/forcenewattempt',
        get_string('forcenewattempts', 'mod_seledascorm'), get_string('forcenewattempts_help', 'mod_seledascorm'), 0, $forceattempts));

    $settings->add(new admin_setting_configselect('seledascorm/autocommit',
    get_string('autocommit', 'mod_seledascorm'), get_string('autocommitdesc', 'mod_seledascorm'), 0, $yesno));

    $settings->add(new admin_setting_configselect('seledascorm/masteryoverride',
        get_string('masteryoverride', 'mod_seledascorm'), get_string('masteryoverridedesc', 'mod_seledascorm'), 1, $yesno));

    $settings->add(new admin_setting_configselect('seledascorm/lastattemptlock',
        get_string('lastattemptlock', 'mod_seledascorm'), get_string('lastattemptlockdesc', 'mod_seledascorm'), 0, $yesno));

    $settings->add(new admin_setting_configselect('seledascorm/auto',
        get_string('autocontinue', 'mod_seledascorm'), get_string('autocontinuedesc', 'mod_seledascorm'), 0, $yesno));

    $settings->add(new admin_setting_configselect('seledascorm/updatefreq',
                                                    get_string('updatefreq', 'mod_seledascorm'),
                                                    get_string('updatefreqdesc', 'mod_seledascorm'),
                                                    0, seledascorm_get_updatefreq_array()));

    // Admin level settings.
    $settings->add(new admin_setting_heading('seledascorm/adminsettings', get_string('adminsettings', 'mod_seledascorm'), ''));

    $settings->add(new admin_setting_configcheckbox('seledascorm/seledascormstandard',
                                                    get_string('seledascormstandard', 'mod_seledascorm'),
                                                    get_string('seledascormstandarddesc', 'mod_seledascorm'), 0));

    $settings->add(new admin_setting_configcheckbox('seledascorm/allowtypeexternal',
                                                    get_string('allowtypeexternal', 'mod_seledascorm'), '', 0));

    $settings->add(new admin_setting_configcheckbox('seledascorm/allowtypelocalsync',
                                                    get_string('allowtypelocalsync', 'mod_seledascorm'), '', 0));

    $settings->add(new admin_setting_configcheckbox('seledascorm/allowtypeexternalaicc',
        get_string('allowtypeexternalaicc', 'mod_seledascorm'), get_string('allowtypeexternalaicc_desc', 'mod_seledascorm'), 0));

    $settings->add(new admin_setting_configcheckbox('seledascorm/allowaicchacp', get_string('allowtypeaicchacp', 'mod_seledascorm'),
                                                    get_string('allowtypeaicchacp_desc', 'mod_seledascorm'), 0));

    $settings->add(new admin_setting_configtext('seledascorm/aicchacptimeout',
        get_string('aicchacptimeout', 'mod_seledascorm'), get_string('aicchacptimeout_desc', 'mod_seledascorm'),
        30, PARAM_INT));

    $settings->add(new admin_setting_configtext('seledascorm/aicchacpkeepsessiondata',
        get_string('aicchacpkeepsessiondata', 'mod_seledascorm'), get_string('aicchacpkeepsessiondata_desc', 'mod_seledascorm'),
        1, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('seledascorm/aiccuserid', get_string('aiccuserid', 'mod_seledascorm'),
                                                    get_string('aiccuserid_desc', 'mod_seledascorm'), 1));

    $settings->add(new admin_setting_configcheckbox('seledascorm/forcejavascript', get_string('forcejavascript', 'mod_seledascorm'),
                                                    get_string('forcejavascript_desc', 'mod_seledascorm'), 1));

    $settings->add(new admin_setting_configcheckbox('seledascorm/allowapidebug',
                                                    get_string('allowapidebug', 'mod_seledascorm'), '', 0));

    $settings->add(new admin_setting_configtext('seledascorm/apidebugmask', get_string('apidebugmask', 'mod_seledascorm'), '', '.*'));

    $settings->add(new admin_setting_configcheckbox('seledascorm/protectpackagedownloads',
                                                    get_string('protectpackagedownloads', 'mod_seledascorm'),
                                                    get_string('protectpackagedownloads_desc', 'mod_seledascorm'), 0));

}
