M.mod_seledascormform = {};
M.mod_seledascormform.init = function(Y) {
    var seledascormform = Y.one('#seledascormviewform');
    var cwidth = seledascormplayerdata.cwidth;
    var cheight = seledascormplayerdata.cheight;
    var poptions = seledascormplayerdata.popupoptions;
    var launch = seledascormplayerdata.launch;
    var currentorg = seledascormplayerdata.currentorg;
    var sco = seledascormplayerdata.sco;
    var seledascorm = seledascormplayerdata.seledascorm;
    var launch_url = M.cfg.wwwroot + "/mod/seledascorm/player.php?a=" + seledascorm + "&currentorg=" + currentorg + "&scoid=" + sco + "&sesskey=" + M.cfg.sesskey + "&display=popup";
    var course_url = seledascormplayerdata.courseurl;
    var winobj = null;

    poptions = poptions + ',resizable=yes'; // Added for IE (MDL-32506).

    if ((cwidth == 100) && (cheight == 100)) {
        poptions = poptions + ',width=' + screen.availWidth + ',height=' + screen.availHeight + ',left=0,top=0';
    } else {
        if (cwidth <= 100) {
            cwidth = Math.round(screen.availWidth * cwidth / 100);
        }
        if (cheight <= 100) {
            cheight = Math.round(screen.availHeight * cheight / 100);
        }
        poptions = poptions + ',width=' + cwidth + ',height=' + cheight;
    }

    // Hide the form and toc if it exists - we don't want to allow multiple submissions when a window is open.
    var seledascormload = function () {
        if (seledascormform) {
            seledascormform.hide();
        }

        var seledascormtoc = Y.one('#toc');
        if (seledascormtoc) {
            seledascormtoc.hide();
        }
        // Hide the intro and display a message to the user if the window is closed.
        var seledascormintro = Y.one('#intro');
        seledascormintro.setHTML('<a href="' + course_url + '">' + M.util.get_string('popuplaunched', 'mod_seledascorm') + '</a>');
    }

    // When pop-up is closed return to course homepage.
    var seledascormunload = function () {
        // Onunload is called multiple times in the SELEDASCORM window - we only want to handle when it is actually closed.
        setTimeout(function() {
            if (winobj.closed) {
                window.location = course_url;
            }
        }, 800)
    }

    var seledascormredirect = function (winobj) {
        Y.on('load', seledascormload, winobj);
        Y.on('unload', seledascormunload, winobj);
        // Check to make sure pop-up has been launched - if not display a warning,
        // this shouldn't happen as the pop-up here is launched on user action but good to make sure.
        setTimeout(function() {
            if (!winobj) {
                var seledascormintro = Y.one('#intro');
                seledascormintro.setHTML(M.util.get_string('popupsblocked', 'mod_seledascorm'));
            }}, 800);
    }

    // Set mode and newattempt correctly.
    var setlaunchoptions = function(mode) {
        if (mode) {
            launch_url += '&mode=' + (mode ? mode : 'normal');
        } else {
            launch_url += '&mode=normal';
        }

        var newattempt = Y.one('#seledascormviewform #a');
        launch_url += (newattempt && newattempt.get('checked') ? '&newattempt=on' : '');
    }

    if (launch == true) {
        setlaunchoptions();
        winobj = window.open(launch_url,'Popup', poptions);
        this.target = 'Popup';
        seledascormredirect(winobj);
        winobj.opener = null;
    }
    // Listen for view form submit and generate popup on user interaction.
    if (seledascormform) {
        seledascormform.delegate('click', function(e) {
            setlaunchoptions(e.currentTarget.getAttribute('value'));
            winobj = window.open(launch_url, 'Popup', poptions);
            this.target = 'Popup';
            seledascormredirect(winobj);
            winobj.opener = null;
            e.preventDefault();
        }, 'button[name=mode]');
    }
}
