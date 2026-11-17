/**
 * Runtime branding bridge for the Moodle-embedded Seleda editor.
 *
 * Reads window.__MOODLE_SELEDA_BRANDING__, which is injected by editor/index.php
 * from the Moodle 4.5 plugin settings. This intentionally affects only the
 * embedded authoring UI; learner content and editable lesson text are not
 * rewritten.
 *
 * @package mod_seledascorm
 */
(function() {
    'use strict';

    var config = window.__MOODLE_SELEDA_BRANDING__ || {};
    var name = String(config.name || 'Seleda').trim() || 'Seleda';
    var logoUrl = String(config.logoUrl || '').trim();

    function replaceBrandText(value) {
        if (!value || name === 'Seleda') {
            return value;
        }
        return String(value)
            .replace(/\bSeleda Studio\b/g, name)
            .replace(/\bSeleda\b/g, name);
    }

    function applyDocumentTitle() {
        if (config.activityName) {
            document.title = name + ' — ' + String(config.activityName);
        } else {
            document.title = name;
        }
    }

    function applyLogo() {
        if (!logoUrl) {
            return;
        }

        // Main loading screen and per-page loading surfaces.
        document.querySelectorAll('#load-screen-main, #load-screen-node-content, .load-screen').forEach(function(el) {
            el.style.setProperty('background-image', 'url("' + logoUrl.replace(/"/g, '%22') + '")', 'important');
            el.style.setProperty('background-repeat', 'no-repeat', 'important');
            el.style.setProperty('background-position', 'center 40%', 'important');
            el.style.setProperty('background-size', '250px auto', 'important');
        });

        // Product-logo slots used by About/help/error pages in the static editor.
        document.querySelectorAll([
            'img.exe-logo',
            '.error-page-logo img',
            'img[src*="style/workarea/images/exelearning.svg"]',
            'img[src*="/images/logo.svg"]',
            'img[src$="images/logo.svg"]'
        ].join(',')).forEach(function(img) {
            if (img.closest('#node-content, .idevice_node, [contenteditable="true"]')) {
                return;
            }
            img.src = logoUrl;
            img.alt = name + ' logo';
        });
    }

    function applyName() {
        // The About modal is the main product-name surface inside the static editor.
        document.querySelectorAll('#modalAbout .help-modal-content h1').forEach(function(el) {
            el.textContent = replaceBrandText(el.textContent);
        });

        // Replace product branding only in UI attributes, never in authored content.
        document.querySelectorAll('[title], [aria-label], img[alt]').forEach(function(el) {
            if (el.closest('#node-content, .idevice_node, [contenteditable="true"]')) {
                return;
            }
            ['title', 'aria-label', 'alt'].forEach(function(attr) {
                if (el.hasAttribute(attr)) {
                    var before = el.getAttribute(attr);
                    var after = replaceBrandText(before);
                    if (after !== before) {
                        el.setAttribute(attr, after);
                    }
                }
            });
        });
    }

    function applyBranding() {
        applyDocumentTitle();
        applyLogo();
        applyName();
    }

    function start() {
        applyBranding();

        // Several editor surfaces are created lazily. Reapply branding for a
        // bounded period without touching lesson content.
        var observer = new MutationObserver(function() {
            applyBranding();
        });
        observer.observe(document.documentElement, {childList: true, subtree: true});
        window.setTimeout(function() {
            observer.disconnect();
        }, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
    } else {
        start();
    }
})();
