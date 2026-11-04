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
 * Javascript helper function for SELEDASCORM module.
 *
 * @package   mod-seledascorm
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

mod_seledascorm_launch_next_sco = null;
mod_seledascorm_launch_prev_sco = null;
mod_seledascorm_activate_item = null;
mod_seledascorm_parse_toc_tree = null;
seledascorm_layout_widget = null;

window.seledascorm_current_node = null;

function underscore(str) {
    str = String(str).replace(/.N/g,".");
    return str.replace(/\./g,"__");
}

M.mod_seledascorm = {};

M.mod_seledascorm.init = function(Y, nav_display, navposition_left, navposition_top, hide_toc, collapsetocwinsize, toc_title, window_name, launch_sco, scoes_nav) {
    var seledascorm_disable_toc = false;
    var seledascorm_hide_nav = true;
    var seledascorm_hide_toc = true;
    var launch_sco_fix = launch_sco;
    if (hide_toc == 0) {
        if (nav_display !== 0) {
            seledascorm_hide_nav = false;
        }
        seledascorm_hide_toc = false;
    } else if (hide_toc == 3) {
        seledascorm_disable_toc = true;
    }

    scoes_nav = Y.JSON.parse(scoes_nav);

    var seledascorm_update_siblings = function (scoesnav) {
        // Group every entry by its parentscoid, then link the members of each group as
        // siblings. Top-level sections have no parentscoid at all (they are the roots of
        // the organization being displayed; the organization node itself has no launchable
        // URL and is absent from scoesnav), so we bucket them together under a sentinel key.
        // The previous implementation only matched a parentscoid that was itself a key in
        // scoesnav, so those top-level sections never received prevsibling/nextsibling and
        // the "Previous/Next within this level" buttons did nothing (issue #63).
        var groups = {};
        var rootkey = '__seledascorm_root__';
        for (var mk in scoesnav) {
            var val = scoesnav[mk];
            if (typeof val !== "undefined") {
                var pscoid = (typeof val.parentscoid !== 'undefined') ? val.parentscoid : rootkey;
                if (typeof groups[pscoid] === "undefined") {
                    groups[pscoid] = [];
                }
                groups[pscoid].push(mk);
            }
        }
        for (var parentscoid in groups) {
            if (groups[parentscoid].length > 1) {
                scoesnav = seledascorm_get_siblings(scoesnav, groups[parentscoid]);
            }
        }
        return scoesnav;
    };

    var seledascorm_get_siblings = function (scoesnav, siblings) {
        siblings.forEach(function (key, index) {
            if (index > 0 && typeof scoesnav[key] !== "undefined" && typeof scoesnav[key].prevsibling === "undefined") {
                scoesnav[key].prevsibling = siblings[index - 1];
            }
            if (index < siblings.length - 1 && typeof scoesnav[key] !== "undefined" &&
               typeof scoesnav[key].nextsibling === "undefined") {
                scoesnav[key].nextsibling = siblings[index + 1];
            }
        });
        return scoesnav;
    };

    scoes_nav = seledascorm_update_siblings(scoes_nav);
    var seledascorm_buttons = [];
    var seledascorm_bloody_labelclick = false;
    var seledascorm_nav_panel;
    // Re-entry guard for `tree.after('select', ...)`. `seledascorm_activate_item`
    // collapses + re-expands the whole TreeView via `closeAll()`/`openAll()`,
    // and YUI fires extra `select` events during that dance. Without this
    // flag we re-entered the navigation handler with a sibling node and ended
    // up loading the wrong page in the iframe (issue #63).
    var seledascorm_navigating = false;

    Y.use('button', 'dd-plugin', 'panel', 'resize', 'gallery-sm-treeview', function(Y) {

        Y.TreeView.prototype.getNodeByAttribute = function(attribute, value) {
            var node = null,
                domnode = Y.one('a[' + attribute + '="' + value + '"]');
            if (domnode !== null) {
                node = seledascorm_tree_node.getNodeById(domnode.ancestor('li').get('id'));
            }
            return node;
        };

        Y.TreeView.prototype.openAll = function () {
            this.get('container').all('.yui3-treeview-can-have-children').each(function(target) {
                this.getNodeById(target.get('id')).open();
            }, this);
        };

        Y.TreeView.prototype.closeAll = function () {
            this.get('container').all('.yui3-treeview-can-have-children').each(function(target) {
                this.getNodeById(target.get('id')).close();
            }, this);
        }

        var seledascorm_parse_toc_tree = function(srcNode) {
            var SELECTORS = {
                    child: '> li',
                    label: '> li, > a',
                    textlabel : '> li, > span',
                    subtree: '> ul, > li'
                },
                children = [];

            srcNode.all(SELECTORS.child).each(function(childNode) {
                var child = {},
                    labelNode = childNode.one(SELECTORS.label),
                    textNode = childNode.one(SELECTORS.textlabel),
                    subTreeNode = childNode.one(SELECTORS.subtree);

                if (labelNode) {
                    var title = labelNode.getAttribute('title');
                    var scoid = labelNode.getData('scoid');
                    child.label = labelNode.get('outerHTML');
                    // Will be good to change to url instead of title.
                    if (title && title !== '#') {
                        child.title = title;
                    }
                    if (typeof scoid !== 'undefined') {
                        child.scoid = scoid;
                    }
                } else if (textNode) {
                    // The selector did not find a label node with anchor.
                    child.label = textNode.get('outerHTML');
                }

                if (subTreeNode) {
                    child.children = seledascorm_parse_toc_tree(subTreeNode);
                }

                children.push(child);
            });

            return children;
        };

        mod_seledascorm_parse_toc_tree = seledascorm_parse_toc_tree;

        var seledascorm_activate_item = function(node) {
            if (!node) {
                return;
            }
            // Check if the item is already active, avoid recursive calls.
            var content = Y.one('#seledascorm_content');
            var old = Y.one('#seledascorm_object');
            if (old) {
                var seledascorm_active_url = Y.one('#seledascorm_object').getAttribute('src');
                var node_full_url = M.cfg.wwwroot + '/mod/seledascorm/loadSCO.php?' + node.title;
                if (node_full_url === seledascorm_active_url) {
                    return;
                }
                // Start to unload iframe here
                if(!window_name){
                    content.removeChild(old);
                    old = null;
                }
            }
            // End of - Avoid recursive calls.

            seledascorm_current_node = node;
            // Suppress the 'select' re-entry the next .select()/closeAll()/openAll()
            // calls would otherwise generate (issue #63). Cleared after openAll()
            // below.
            seledascorm_navigating = true;
            if (!seledascorm_current_node.state.selected) {
                seledascorm_current_node.select();
            }

            // Mark the active TOC link so screen readers can announce the current item.
            Y.one('#seledascorm_tree').all('a[aria-current]').each(function(a) {
                a.removeAttribute('aria-current');
                a.setAttribute('aria-selected', 'false');
            });
            if (node.id) {
                var activeEl = Y.one('#' + node.id);
                if (activeEl) {
                    var activeLink = activeEl.one('a');
                    if (activeLink) {
                        activeLink.setAttribute('aria-current', 'page');
                        activeLink.setAttribute('aria-selected', 'true');
                    }
                }
            }

            seledascorm_tree_node.closeAll();
            var url_prefix = M.cfg.wwwroot + '/mod/seledascorm/loadSCO.php?';
            var el_old_api = document.getElementById('seledascormapi123');
            if (el_old_api) {
                el_old_api.parentNode.removeChild(el_old_api);
            }

            var obj = document.createElement('iframe');
            obj.setAttribute('id', 'seledascorm_object');
            obj.setAttribute('type', 'text/html');
            obj.setAttribute('allowfullscreen', 'allowfullscreen');
            obj.setAttribute('webkitallowfullscreen', 'webkitallowfullscreen');
            obj.setAttribute('mozallowfullscreen', 'mozallowfullscreen');
            obj.setAttribute('onload', "seledascorm_iframe_onload(this)");
            var labelEl = document.createElement('div');
            labelEl.innerHTML = node.label || '';
            var frameTitle = (labelEl.textContent || labelEl.innerText || '').replace(/\s+/g, ' ').trim().replace(/ \| [^|]*$/, '').replace(/["']/g, '');
            obj.setAttribute('title', frameTitle || M.util.get_string('contenttitle', 'mod_seledascorm'));

            if (!window_name && node.title != null) {
                obj.setAttribute('src', url_prefix + node.title);
            }
            // Attach unload observers to the iframe. The seledascorm package may be observing these unload events
            // and trying to save progress when they occur. We need to ensure we use the Beacon API in those
            // situations.
            if (typeof mod_seledascorm_monitorForBeaconRequirement !== 'undefined') {
                mod_seledascorm_monitorForBeaconRequirement(obj);
            }
            if (window_name) {
                var mine = window.open('','','width=1,height=1,left=0,top=0,scrollbars=no');
                if(! mine) {
                    alert(M.util.get_string('popupsblocked', 'mod_seledascorm'));
                }
                mine.close();
            }

            if (old) {
                if(window_name) {
                    var cwidth = seledascormplayerdata.cwidth;
                    var cheight = seledascormplayerdata.cheight;
                    var poptions = seledascormplayerdata.popupoptions;
                    poptions = poptions + ',resizable=yes'; // Added for IE (MDL-32506).
                    seledascorm_openpopup(M.cfg.wwwroot + "/mod/seledascorm/loadSCO.php?" + node.title, window_name, poptions, cwidth, cheight);
                }
            } else {
                content.prepend(obj);
            }

            if (seledascorm_hide_nav == false) {
                if (nav_display === 1 && navposition_left > 0 && navposition_top > 0) {
                    Y.one('#seledascorm_object').addClass(cssclasses.seledascorm_nav_under_content);
                }
                seledascorm_fixnav();
            }
            seledascorm_tree_node.openAll();
            seledascorm_navigating = false;
        };

        mod_seledascorm_activate_item = seledascorm_activate_item;

        /**
         * Enables/disables navigation buttons as needed.
         * @return void
         */
        var seledascorm_fixnav = function() {
            launch_sco_fix = launch_sco;
            var skipprevnode = seledascorm_skipprev(seledascorm_current_node);
            var prevnode = seledascorm_prev(seledascorm_current_node);
            var upnode = seledascorm_up(seledascorm_current_node);
            var nextnode = seledascorm_next(seledascorm_current_node, true, true);
            var skipnextnode = seledascorm_skipnext(seledascorm_current_node, true, true);

            // "Previous within this level" only steps between same-level siblings, so it
            // must be disabled at the first item of a level. seledascorm_skipprev climbs to
            // the parent when there is no previous sibling (plain "Previous" relies on that
            // fallback), which would otherwise leave this button wrongly enabled at a level
            // boundary, so gate it on the authoritative prevsibling metadata too (issue #63).
            seledascorm_buttons[0].set('disabled', ((skipprevnode === null) ||
                        (typeof scoes_nav[launch_sco].prevsibling === 'undefined') ||
                        (typeof(skipprevnode.scoid) === 'undefined') ||
                        (scoes_nav[skipprevnode.scoid].isvisible === "false") ||
                        (skipprevnode.title === null) ||
                        (scoes_nav[launch_sco].hideprevious === 1)));

            seledascorm_buttons[1].set('disabled', ((prevnode === null) ||
                        (typeof(prevnode.scoid) === 'undefined') ||
                        (scoes_nav[prevnode.scoid].isvisible === "false") ||
                        (prevnode.title === null) ||
                        (scoes_nav[launch_sco].hideprevious === 1)));

            seledascorm_buttons[2].set('disabled', (upnode === null) ||
                        (typeof(upnode.scoid) === 'undefined') ||
                        (scoes_nav[upnode.scoid].isvisible === "false") ||
                        (upnode.title === null));

            seledascorm_buttons[3].set('disabled', ((nextnode === null) ||
                        ((nextnode.title === null) && (scoes_nav[launch_sco].flow !== 1)) ||
                        (typeof(nextnode.scoid) === 'undefined') ||
                        (scoes_nav[nextnode.scoid].isvisible === "false") ||
                        (scoes_nav[launch_sco].hidecontinue === 1)));

            // "Next within this level" mirrors the button above: disable it at the last item
            // of a level. seledascorm_skipnext climbs to the parent's next sibling when the
            // current item has no next sibling, which is why this button was inconsistently
            // enabled at some level boundaries; gate it on the nextsibling metadata (issue #63).
            seledascorm_buttons[4].set('disabled', ((skipnextnode === null) ||
                        (typeof scoes_nav[launch_sco].nextsibling === 'undefined') ||
                        (skipnextnode.title === null) ||
                        (typeof(skipnextnode.scoid) === 'undefined') ||
                        (scoes_nav[skipnextnode.scoid].isvisible === "false") ||
                        scoes_nav[launch_sco].hidecontinue === 1));
        };

        var seledascorm_toggle_toc = function(windowresize) {
            var toc = Y.one('#seledascorm_toc');
            var seledascorm_content = Y.one('#seledascorm_content');
            var seledascorm_toc_toggle_btn = Y.one('#seledascorm_toc_toggle_btn');
            var toc_disabled = toc.hasClass('disabled');
            var disabled_by = toc.getAttribute('disabled-by');
            // Remove width element style from resize handle.
            toc.setStyle('width', null);
            seledascorm_content.setStyle('width', null);
            if (windowresize === true) {
                if (disabled_by === 'user') {
                    return;
                }
                var body = Y.one('body');
                if (body.get('winWidth') < collapsetocwinsize) {
                    toc.addClass(cssclasses.disabled)
                        .setAttribute('disabled-by', 'screen-size');
                    seledascorm_toc_toggle_btn.setHTML('&gt;').addClass('collapsed').removeClass('uncollapsed')
                        .set('title', M.util.get_string('show', 'moodle'))
                        .setAttribute('aria-expanded', 'false');
                    seledascorm_content.removeClass(cssclasses.seledascorm_grid_content_toc_visible)
                        .addClass(cssclasses.seledascorm_grid_content_toc_hidden);
                } else if (body.get('winWidth') > collapsetocwinsize) {
                    toc.removeClass(cssclasses.disabled)
                        .removeAttribute('disabled-by');
                    seledascorm_toc_toggle_btn.setHTML('&lt;').addClass('uncollapsed').removeClass('collapsed')
                        .set('title', M.util.get_string('hide', 'moodle'))
                        .setAttribute('aria-expanded', 'true');
                    seledascorm_content.removeClass(cssclasses.seledascorm_grid_content_toc_hidden)
                        .addClass(cssclasses.seledascorm_grid_content_toc_visible);
                }
                return;
            }
            if (toc_disabled) {
                toc.removeClass(cssclasses.disabled)
                    .removeAttribute('disabled-by');
                seledascorm_toc_toggle_btn.setHTML('&lt;').addClass('uncollapsed').removeClass('collapsed')
                    .set('title', M.util.get_string('hide', 'moodle'))
                    .setAttribute('aria-expanded', 'true');
                seledascorm_content.removeClass(cssclasses.seledascorm_grid_content_toc_hidden)
                    .addClass(cssclasses.seledascorm_grid_content_toc_visible);
            } else {
                toc.addClass(cssclasses.disabled)
                    .setAttribute('disabled-by', 'user');
                seledascorm_toc_toggle_btn.setHTML('&gt;').addClass('collapsed').removeClass('uncollapsed')
                    .set('title', M.util.get_string('show', 'moodle'))
                    .setAttribute('aria-expanded', 'false');
                seledascorm_content.removeClass(cssclasses.seledascorm_grid_content_toc_visible)
                    .addClass(cssclasses.seledascorm_grid_content_toc_hidden);
            }
        };

        var seledascorm_resize_layout = function() {
            if (window_name) {
                return;
            }

            // make sure that the max width of the TOC doesn't go to far

            var seledascorm_toc_node = Y.one('#seledascorm_toc');
            var maxwidth = parseInt(Y.one('#seledascorm_layout').getComputedStyle('width'), 10);
            seledascorm_toc_node.setStyle('maxWidth', (maxwidth - 200));
            var cwidth = parseInt(seledascorm_toc_node.getComputedStyle('width'), 10);
            if (cwidth > (maxwidth - 1)) {
                seledascorm_toc_node.setStyle('width', (maxwidth - 50));
            }

            let layout = document.querySelector('#seledascorm_layout');
            let iFrame = document.querySelector('#seledascorm_object');
            if (iFrame.contentWindow.document.body && layout) {
                iFrame.style.height = (iFrame.contentWindow.document.body.scrollHeight + 50) + 'px';
                layout.style.height = (iFrame.contentWindow.document.body.scrollHeight + 50) + 'px';
            }
        };


        var seledascorm_up = function(node, update_launch_sco) {
            if (node.parent && node.parent.parent && typeof scoes_nav[launch_sco].parentscoid !== 'undefined') {
                var parentscoid = scoes_nav[launch_sco].parentscoid;
                var parent = node.parent;
                if (parent.title !== scoes_nav[parentscoid].url) {
                    parent = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[parentscoid].url);
                    if (parent === null) {
                        parent = seledascorm_tree_node.rootNode.children[0];
                        parent.title = scoes_nav[parentscoid].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = parentscoid;
                }
                return parent;
            }
            return null;
        };

        var seledascorm_lastchild = function(node) {
            if (node.children.length) {
                return seledascorm_lastchild(node.children[node.children.length - 1]);
            } else {
                return node;
            }
        };

        var seledascorm_prev = function(node, update_launch_sco) {
            if (node.previous() && node.previous().children.length &&
                    typeof scoes_nav[launch_sco].prevscoid !== 'undefined') {
                node = seledascorm_lastchild(node.previous());
                if (node) {
                    var prevscoid = scoes_nav[launch_sco].prevscoid;
                    if (node.title !== scoes_nav[prevscoid].url) {
                        node = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[prevscoid].url);
                        if (node === null) {
                            node = seledascorm_tree_node.rootNode.children[0];
                            node.title = scoes_nav[prevscoid].url;
                        }
                    }
                    if (update_launch_sco) {
                        launch_sco = prevscoid;
                    }
                    return node;
                } else {
                    return null;
                }
            }
            return seledascorm_skipprev(node, update_launch_sco);
        };

        var seledascorm_skipprev = function(node, update_launch_sco) {
            if (node.previous() && typeof scoes_nav[launch_sco].prevsibling !== 'undefined') {
                var prevsibling = scoes_nav[launch_sco].prevsibling;
                var previous = node.previous();
                var prevscoid = scoes_nav[launch_sco].prevscoid;
                if (previous.title !== scoes_nav[prevscoid].url) {
                    previous = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[prevsibling].url);
                    if (previous === null) {
                        previous = seledascorm_tree_node.rootNode.children[0];
                        previous.title = scoes_nav[prevsibling].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = prevsibling;
                }
                return previous;
            } else if (node.parent && node.parent.parent && typeof scoes_nav[launch_sco].parentscoid !== 'undefined') {
                var parentscoid = scoes_nav[launch_sco].parentscoid;
                var parent = node.parent;
                if (parent.title !== scoes_nav[parentscoid].url) {
                    parent = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[parentscoid].url);
                    if (parent === null) {
                        parent = seledascorm_tree_node.rootNode.children[0];
                        parent.title = scoes_nav[parentscoid].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = parentscoid;
                }
                return parent;
            }
            return null;
        };

        var seledascorm_next = function(node, update_launch_sco, test) {
            if (node === false) {
                return seledascorm_tree_node.children[0];
            }
            if (node.children.length && typeof scoes_nav[launch_sco_fix].nextscoid != 'undefined') {
                node = node.children[0];
                var nextscoid = scoes_nav[launch_sco_fix].nextscoid;
                if (node.title !== scoes_nav[nextscoid].url) {
                    node = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[nextscoid].url);
                    if (node === null) {
                        node = seledascorm_tree_node.rootNode.children[0];
                        node.title = scoes_nav[nextscoid].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco_fix = nextscoid;
                    if (!test) {
                        launch_sco = launch_sco_fix;
                    }
                }
                return node;
            }
            return seledascorm_skipnext(node, update_launch_sco, test);
        };

        var seledascorm_skipnext = function(node, update_launch_sco, test) {
            var next = node.next();
            if (next && next.title && typeof scoes_nav[launch_sco_fix] !== 'undefined' &&
                        typeof scoes_nav[launch_sco_fix].nextsibling !== 'undefined') {
                var nextsibling = scoes_nav[launch_sco_fix].nextsibling;
                if (next.title !== scoes_nav[nextsibling].url) {
                    next = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[nextsibling].url);
                    if (next === null) {
                        next = seledascorm_tree_node.rootNode.children[0];
                        next.title = scoes_nav[nextsibling].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco_fix = nextsibling;
                    if (!test) {
                        launch_sco = launch_sco_fix;
                    }
                }
                return next;
            } else if (node.parent && node.parent.parent && typeof scoes_nav[launch_sco_fix].parentscoid !== 'undefined') {
                var parentscoid = scoes_nav[launch_sco_fix].parentscoid;
                var parent = node.parent;
                if (parent.title !== scoes_nav[parentscoid].url) {
                    parent = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[parentscoid].url);
                    if (parent === null) {
                        parent = seledascorm_tree_node.rootNode.children[0];
                    }
                }
                if (update_launch_sco) {
                    launch_sco_fix = parentscoid;
                    if (!test) {
                        launch_sco = launch_sco_fix;
                    }
                }
                return seledascorm_skipnext(parent, update_launch_sco, test);
            }
            return null;
        };

        /**
         * Sends a request to the sequencing handler script on the server.
         * @param {string} datastring
         * @returns {string|boolean|*}
         */
        var seledascorm_dorequest_sequencing = function(datastring) {
            var myRequest = NewHttpReq();
            var result = DoRequest(
                myRequest,
                M.cfg.wwwroot + '/mod/seledascorm/datamodels/sequencinghandler.php?' + datastring,
                '',
                false
            );
            return result;
        };

        // Launch prev sco
        var seledascorm_launch_prev_sco = function() {
            var result = null;
            if (scoes_nav[launch_sco].flow === 1) {
                var datastring = scoes_nav[launch_sco].url + '&function=seledascorm_seq_flow&request=backward';
                result = seledascorm_dorequest_sequencing(datastring);

                // Check the seledascorm_ajax_result, it may be false.
                if (result === false) {
                    // Either the outcome was a failure, or we are unloading and simply just don't know
                    // what the outcome actually was.
                    result = {};
                } else {
                    result = Y.JSON.parse(result);
                }

                if (typeof result.nextactivity !== 'undefined' && typeof result.nextactivity.id !== 'undefined') {
                        var node = seledascorm_prev(seledascorm_tree_node.getSelectedNodes()[0]);
                        if (node == null) {
                            // Avoid use of TreeView for Navigation.
                            node = seledascorm_tree_node.getSelectedNodes()[0];
                        }
                        if (node.title !== scoes_nav[result.nextactivity.id].url) {
                            node = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[result.nextactivity.id].url);
                            if (node === null) {
                                node = seledascorm_tree_node.rootNode.children[0];
                                node.title = scoes_nav[result.nextactivity.id].url;
                            }
                        }
                        launch_sco = result.nextactivity.id;
                        seledascorm_activate_item(node);
                        seledascorm_fixnav();
                } else {
                        seledascorm_activate_item(seledascorm_prev(seledascorm_tree_node.getSelectedNodes()[0], true));
                }
            } else {
                seledascorm_activate_item(seledascorm_prev(seledascorm_tree_node.getSelectedNodes()[0], true));
            }
        };

        // Launch next sco
        var seledascorm_launch_next_sco = function () {
            launch_sco_fix = launch_sco;
            var result = null;
            if (scoes_nav[launch_sco].flow === 1) {
                var datastring = scoes_nav[launch_sco].url + '&function=seledascorm_seq_flow&request=forward';
                result = seledascorm_dorequest_sequencing(datastring);

                // Check the seledascorm_ajax_result, it may be false.
                if (result === false) {
                    // Either the outcome was a failure, or we are unloading and simply just don't know
                    // what the outcome actually was.
                    result = {};
                } else {
                    result = Y.JSON.parse(result);
                }

                if (typeof result.nextactivity !== 'undefined' && typeof result.nextactivity.id !== 'undefined') {
                    var node = seledascorm_next(seledascorm_tree_node.getSelectedNodes()[0]);
                    if (node === null) {
                        // Avoid use of TreeView for Navigation.
                        node = seledascorm_tree_node.getSelectedNodes()[0];
                    }
                    node = seledascorm_tree_node.getNodeByAttribute('title', scoes_nav[result.nextactivity.id].url);
                    if (node === null) {
                        node = seledascorm_tree_node.rootNode.children[0];
                        node.title = scoes_nav[result.nextactivity.id].url;
                    }
                    launch_sco = result.nextactivity.id;
                    launch_sco_fix = launch_sco;
                    seledascorm_activate_item(node);
                    seledascorm_fixnav();
                } else {
                    seledascorm_activate_item(seledascorm_next(seledascorm_tree_node.getSelectedNodes()[0], true, false));
                }
            } else {
                seledascorm_activate_item(seledascorm_next(seledascorm_tree_node.getSelectedNodes()[0], true,false));
            }
        };

        mod_seledascorm_launch_prev_sco = seledascorm_launch_prev_sco;
        mod_seledascorm_launch_next_sco = seledascorm_launch_next_sco;

        var cssclasses = {
                // YUI grid class: use 100% of the available width to show only content, TOC hidden.
                seledascorm_grid_content_toc_hidden: 'yui3-u-1',
                // YUI grid class: use 1/5 of the available width to show TOC.
                seledascorm_grid_toc: 'yui3-u-1-5',
                // YUI grid class: use 1/24 of the available width to show TOC toggle button.
                seledascorm_grid_toggle: 'yui3-u-1-24',
                // YUI grid class: use 3/4 of the available width to show content, TOC visible.
                seledascorm_grid_content_toc_visible: 'yui3-u-3-4',
                // Reduce height of #seledascorm_object to accomodate nav buttons under content.
                seledascorm_nav_under_content: 'seledascorm_nav_under_content',
                disabled: 'disabled'
            };
        // layout
        Y.one('#seledascorm_toc_title').setHTML(toc_title);

        if (seledascorm_disable_toc) {
            Y.one('#seledascorm_toc').addClass(cssclasses.disabled);
            Y.one('#seledascorm_toc_toggle').addClass(cssclasses.disabled);
            Y.one('#seledascorm_content').addClass(cssclasses.seledascorm_grid_content_toc_hidden);
        } else {
            Y.one('#seledascorm_toc').addClass(cssclasses.seledascorm_grid_toc);
            Y.one('#seledascorm_toc_toggle').addClass(cssclasses.seledascorm_grid_toggle);
            Y.one('#seledascorm_toc_toggle_btn')
                .setHTML('&lt;')
                .setAttribute('title', M.util.get_string('hide', 'moodle'))
                .setAttribute('aria-expanded', 'true');
            Y.one('#seledascorm_content').addClass(cssclasses.seledascorm_grid_content_toc_visible);
            seledascorm_toggle_toc(true);
        }

        // hide the TOC if that is the default
        if (!seledascorm_disable_toc) {
            if (seledascorm_hide_toc == true) {
                Y.one('#seledascorm_toc').addClass(cssclasses.disabled);
                Y.one('#seledascorm_toc_toggle_btn')
                    .setHTML('&gt;')
                    .setAttribute('title', M.util.get_string('show', 'moodle'))
                    .setAttribute('aria-expanded', 'false');
                Y.one('#seledascorm_content')
                    .removeClass(cssclasses.seledascorm_grid_content_toc_visible)
                    .addClass(cssclasses.seledascorm_grid_content_toc_hidden);
            }
        }

        // Basic initialization completed, show the elements.
        Y.one('#seledascorm_toc').removeClass('loading');
        Y.one('#seledascorm_toc_toggle').removeClass('loading');

        // TOC Resize handle.
        var layout_width = parseInt(Y.one('#seledascorm_layout').getComputedStyle('width'), 10);
        var seledascorm_resize_handle = new Y.Resize({
            node: '#seledascorm_toc',
            handles: 'r',
            defMinWidth: 0.2 * layout_width
        });
        // TOC tree
        var toc_source = Y.one('#seledascorm_tree > ul');
        var toc = seledascorm_parse_toc_tree(toc_source);
        // Empty container after parsing toc.
        var el = document.getElementById('seledascorm_tree');
        el.innerHTML = '';
        var tree = new Y.TreeView({
            container: '#seledascorm_tree',
            nodes: toc,
            multiSelect: false,
            lazyRender: false
        });
        seledascorm_tree_node = tree;
        // Trigger after instead of on, avoid recursive calls.
        tree.after('select', function(e) {
            // Skip select events triggered by programmatic state changes inside
            // seledascorm_activate_item (closeAll/openAll). See seledascorm_navigating
            // declaration for context.
            if (seledascorm_navigating) {
                return;
            }
            var node = e.node;
            if (node.title == '' || node.title == null) {
                return; //this item has no navigation
            }

            // If item is already active, return; avoid recursive calls.
            if (obj = Y.one('#seledascorm_object')) {
                var seledascorm_active_url = obj.getAttribute('src');
                var node_full_url = M.cfg.wwwroot + '/mod/seledascorm/loadSCO.php?' + node.title;
                if (node_full_url === seledascorm_active_url) {
                    return;
                }
            } else if(seledascorm_current_node == node){
                return;
            }

            // Update launch_sco.
            if (typeof node.scoid !== 'undefined') {
                launch_sco = node.scoid;
            }
            seledascorm_activate_item(node);
            if (node.children.length) {
                seledascorm_bloody_labelclick = true;
            }
        });
        if (!seledascorm_disable_toc) {
            tree.on('close', function(e) {
                if (seledascorm_bloody_labelclick) {
                    seledascorm_bloody_labelclick = false;
                    return false;
                }
            });
            tree.subscribe('open', function(e) {
                if (seledascorm_bloody_labelclick) {
                    seledascorm_bloody_labelclick = false;
                    return false;
                }
            });
        }
        tree.render();
        tree.openAll();

        // Prevent href="#" on TOC anchors from scrolling to page top.
        Y.one('#seledascorm_tree').delegate('click', function(e) {
            e.preventDefault();
        }, 'a');

        // On getting the window, always set the focus on the current item
        Y.one(Y.config.win).on('focus', function (e) {
            var current = seledascorm_tree_node.getSelectedNodes()[0];
            var toc_disabled = Y.one('#seledascorm_toc').hasClass('disabled');
            if (current.id && !toc_disabled) {
                Y.one('#' + current.id).focus();
            }
        });

        // navigation
        if (seledascorm_hide_nav == false) {
            var navbuttonshtml = `<span id="seledascorm_nav">
                                    <button id="nav_skipprev" class="bg-primary"
                                            title="${M.util.get_string('player:skipprev', 'mod_seledascorm')}">
                                        <span class="sr-only">${M.util.get_string('player:skipprev', 'mod_seledascorm')}</span>
                                    </button><button id="nav_prev" class="bg-primary"
                                            title="${M.util.get_string('player:prev', 'mod_seledascorm')}">
                                        <span class="sr-only">${M.util.get_string('player:prev', 'mod_seledascorm')}</span>
                                    </button><button id="nav_up" class="bg-primary"
                                            title="${M.util.get_string('player:up', 'mod_seledascorm')}">
                                        <span class="sr-only">${M.util.get_string('player:up', 'mod_seledascorm')}</span>
                                    </button><button id="nav_next" class="bg-primary"
                                            title="${M.util.get_string('player:next', 'mod_seledascorm')}">
                                        <span class="sr-only">${M.util.get_string('player:next', 'mod_seledascorm')}</span>
                                    </button><button id="nav_skipnext" class="bg-primary"
                                            title="${M.util.get_string('player:skipnext', 'mod_seledascorm')}">
                                        <span class="sr-only">${M.util.get_string('player:skipnext', 'mod_seledascorm')}</span>
                                    </button>
                                </span>`;
            if (nav_display === 1) {
                Y.one('#seledascorm_navpanel').setHTML(navbuttonshtml);
            } else {
                // Nav panel is floating type.
                var navposition = null;
                if (navposition_left < 0 && navposition_top < 0) {
                    // Set default XY.
                    navposition = Y.one('#seledascorm_toc').getXY();
                    navposition[1] += 200;
                } else {
                    // Set user defined XY.
                    navposition = [];
                    navposition[0] = parseInt(navposition_left, 10);
                    navposition[1] = parseInt(navposition_top, 10);
                }
                seledascorm_nav_panel = new Y.Panel({
                    fillHeight: "body",
                    headerContent: M.util.get_string('navigation', 'mod_seledascorm'),
                    visible: true,
                    xy: navposition,
                    zIndex: 999
                });
                seledascorm_nav_panel.set('bodyContent', navbuttonshtml);
                seledascorm_nav_panel.removeButton('close');
                seledascorm_nav_panel.plug(Y.Plugin.Drag, {handles: ['.yui3-widget-hd']});
                seledascorm_nav_panel.render();
            }

            seledascorm_buttons[0] = new Y.Button({
                srcNode: '#nav_skipprev',
                render: true,
                on: {
                        'click' : function(ev) {
                            seledascorm_activate_item(seledascorm_skipprev(seledascorm_tree_node.getSelectedNodes()[0], true));
                        },
                        'keydown' : function(ev) {
                            if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                                seledascorm_activate_item(seledascorm_skipprev(seledascorm_tree_node.getSelectedNodes()[0], true));
                            }
                        }
                    }
            });
            seledascorm_buttons[1] = new Y.Button({
                srcNode: '#nav_prev',
                render: true,
                on: {
                    'click' : function(ev) {
                        seledascorm_launch_prev_sco();
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            seledascorm_launch_prev_sco();
                        }
                    }
                }
            });
            seledascorm_buttons[2] = new Y.Button({
                srcNode: '#nav_up',
                render: true,
                on: {
                    'click' : function(ev) {
                        seledascorm_activate_item(seledascorm_up(seledascorm_tree_node.getSelectedNodes()[0], true));
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            seledascorm_activate_item(seledascorm_up(seledascorm_tree_node.getSelectedNodes()[0], true));
                        }
                    }
                }
            });
            seledascorm_buttons[3] = new Y.Button({
                srcNode: '#nav_next',
                render: true,
                on: {
                    'click' : function(ev) {
                        seledascorm_launch_next_sco();
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            seledascorm_launch_next_sco();
                        }
                    }
                }
            });
            seledascorm_buttons[4] = new Y.Button({
                srcNode: '#nav_skipnext',
                render: true,
                on: {
                    'click' : function(ev) {
                        launch_sco_fix = launch_sco;
                        seledascorm_activate_item(seledascorm_skipnext(seledascorm_tree_node.getSelectedNodes()[0], true, false));
                    },
                    'keydown' : function(ev) {
                        launch_sco_fix = launch_sco;
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            seledascorm_activate_item(seledascorm_skipnext(seledascorm_tree_node.getSelectedNodes()[0], true, false));
                        }
                    }
                }
            });
        }

        // finally activate the chosen item
        var seledascorm_first_url = null;
        if (typeof tree.rootNode.children[0] !== 'undefined') {
            if (tree.rootNode.children[0].title !== scoes_nav[launch_sco].url) {
                var node = tree.getNodeByAttribute('title', scoes_nav[launch_sco].url);
                if (node !== null) {
                    seledascorm_first_url = node;
                }
            } else {
                seledascorm_first_url = tree.rootNode.children[0];
            }
        }

        if (seledascorm_first_url == null) { // This is probably a single sco with no children (AICC Direct uses this).
            seledascorm_first_url = tree.rootNode;
        }
        seledascorm_first_url.title = scoes_nav[launch_sco].url;
        seledascorm_activate_item(seledascorm_first_url);

        // resizing
        seledascorm_resize_layout();

        // Collapse/expand TOC.
        Y.one('#seledascorm_toc_toggle').on('click', seledascorm_toggle_toc);
        Y.one('#seledascorm_toc_toggle').on('key', seledascorm_toggle_toc, 'down:enter,32');
        // fix layout if window resized
        Y.on("windowresize", function() {
            seledascorm_resize_layout();
            var toc_displayed = Y.one('#seledascorm_toc').getComputedStyle('display') !== 'none';
            if ((!seledascorm_disable_toc && !seledascorm_hide_toc) || toc_displayed) {
                seledascorm_toggle_toc(true);
            }
            // Set 20% as minWidth constrain of TOC.
            var layout_width = parseInt(Y.one('#seledascorm_layout').getComputedStyle('width'), 10);
            seledascorm_resize_handle.set('defMinWidth', 0.2 * layout_width);
        });
        // On resize drag, change width of seledascorm_content.
        seledascorm_resize_handle.on('resize:resize', function() {
            var tocwidth = parseInt(Y.one('#seledascorm_toc').getComputedStyle('width'), 10);
            var layoutwidth = parseInt(Y.one('#seledascorm_layout').getStyle('width'), 10);
            Y.one('#seledascorm_content').setStyle('width', (layoutwidth - tocwidth - 60));
        });
    });
};

M.mod_seledascorm.connectPrereqCallback = {

    success: function(id, o) {
        if (o.responseText !== undefined) {
            var snode = null,
                stitle = null;
            if (seledascorm_tree_node && o.responseText) {
                snode = seledascorm_tree_node.getSelectedNodes()[0];
                stitle = null;
                if (snode) {
                    stitle = snode.title;
                }
                // All gone with clear, add new root node.
                seledascorm_tree_node.clear(seledascorm_tree_node.createNode());
            }
            // Make sure the temporary tree element is not there.
            var el_old_tree = document.getElementById('seledascormtree123');
            if (el_old_tree) {
                el_old_tree.parentNode.removeChild(el_old_tree);
            }
            var el_new_tree = document.createElement('div');
            var pagecontent = document.getElementById("page-content");
            if (!pagecontent) {
                pagecontent = document.getElementById("content");
            }
            if (!pagecontent) {
                pagecontent = document.getElementById("seledascormpage");
            }
            el_new_tree.setAttribute('id','seledascormtree123');
            el_new_tree.innerHTML = o.responseText;
            // Make sure it does not show.
            el_new_tree.style.display = 'none';
            pagecontent.appendChild(el_new_tree);
            // Ignore the first level element as this is the title.
            var startNode = el_new_tree.firstChild.firstChild;
            if (startNode.tagName == 'LI') {
                // Go back to the beginning.
                startNode = el_new_tree;
            }
            var toc_source = Y.one('#seledascormtree123 > ul');
            var toc = mod_seledascorm_parse_toc_tree(toc_source);
            seledascorm_tree_node.appendNode(seledascorm_tree_node.rootNode, toc);
            var el = document.getElementById('seledascormtree123');
            el.parentNode.removeChild(el);
            seledascorm_tree_node.render();
            seledascorm_tree_node.openAll();
            if (stitle !== null) {
                snode = seledascorm_tree_node.getNodeByAttribute('title', stitle);
                // Do not let destroyed node to be selected.
                if (snode && !snode.state.destroyed) {
                    snode.select();
                    var toc_disabled = Y.one('#seledascorm_toc').hasClass('disabled');
                    if (!toc_disabled) {
                        if (!snode.state.selected) {
                            snode.select();
                        }
                    }
                }
            }
        }
    },

    failure: function(id, o) {
        // TODO: do some sort of error handling.
    }

};

/**
 * Resizes iFrame and container height to iframes body size.
 * This function is declared on windows namespace so iframe onload event can find it.
 *  Used as mutation observer callback.
 *
 */
var seledascorm_resize = function() {
    let iFrame = document.querySelector('#seledascorm_object');
    let layout = document.querySelector('#seledascorm_layout');
    if (iFrame.contentWindow.document.body && layout) {
        iFrame.style.height = (iFrame.contentWindow.document.body.scrollHeight + 50) + 'px';
        layout.style.height = (iFrame.contentWindow.document.body.scrollHeight + 50) + 'px';
    }
};

/**
 * IFrame's onload handler. Used to keep iFrame's height dynamic, varying on iFrame's contents.
 *
 * @param {Element} iFrame
 */
var seledascorm_iframe_onload = function(iFrame) {
    seledascorm_resize([], null);
    // Set a mutation observer, so we can adapt to changes from iFrame's javascript (such
    // as tab clicks o hide/show sections).
    const config = {attributes: true, childList: true, subtree: true};
    const observer = new MutationObserver(seledascorm_resize);
    observer.observe(iFrame.contentWindow.document.body, config);
};
