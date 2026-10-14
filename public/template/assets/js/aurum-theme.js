/*
 * Aurum dark-mode adapter.
 * Many module views carry their own <style> blocks with hardcoded light colours.
 * In dark mode this finds anything still painted light (or with dark ink) and
 * remaps it to theme tokens via inline !important styles. Colour only — no
 * markup, classes or behaviour are touched — and it fully reverts on light mode.
 */
(function () {
    'use strict';

    var ROOT_SELECTOR = '.page-wrapper, .modal, .dropdown-menu, .select2-container, .swal2-container, .app-tour-card, .admin-alert-modal';
    var SKIP_TAGS = { IMG: 1, SVG: 1, PATH: 1, CANVAS: 1, VIDEO: 1, IFRAME: 1, SCRIPT: 1, STYLE: 1, LINK: 1, BR: 1, HR: 1, OPTION: 1 };
    var touched = new Set();
    var pending = [];
    var timer = null;

    function isDark() {
        return document.body && document.body.getAttribute('data-erp-theme') === 'dark';
    }

    function parse(color) {
        var m = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/.exec(color || '');
        if (!m) return null;
        return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
    }

    function hsl(c) {
        var r = c.r / 255, g = c.g / 255, b = c.b / 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var l = (max + min) / 2, h = 0, s = 0;
        if (max !== min) {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
            else if (max === g) h = (b - r) / d + 2;
            else h = (r - g) / d + 4;
            h *= 60;
        }
        return { h: Math.round(h), s: s, l: l };
    }

    function luminance(c) {
        return (0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b) / 255;
    }

    function setProp(el, prop, value) {
        if (!el.__auOrig) {
            el.__auOrig = {};
        }
        if (!(prop in el.__auOrig)) {
            el.__auOrig[prop] = [el.style.getPropertyValue(prop), el.style.getPropertyPriority(prop)];
        }
        el.style.setProperty(prop, value, 'important');
        touched.add(el);
    }

    function fixBackground(el, cs) {
        var image = cs.backgroundImage;
        if (image && image !== 'none') {
            if (image.indexOf('url(') !== -1) return;
            // Light gradients: flatten to the surface colour.
            var stops = image.match(/rgba?\([^)]+\)/g) || [];
            var allLight = stops.length > 0 && stops.every(function (stop) {
                var c = parse(stop);
                return c && (c.a < 0.35 || luminance(c) > 0.82);
            });
            if (allLight) {
                setProp(el, 'background-image', 'none');
                setProp(el, 'background-color', 'var(--au-surface)');
            }
            return;
        }
        var bg = parse(cs.backgroundColor);
        if (!bg || bg.a < 0.4 || luminance(bg) <= 0.78) return;
        var tone = hsl(bg);
        if (tone.s < 0.2 || tone.l > 0.985) {
            setProp(el, 'background-color', tone.l > 0.97 ? 'var(--au-surface)' : 'var(--au-surface-2)');
        } else {
            // Pastel status tints become a translucent jewel tone.
            setProp(el, 'background-color', 'hsla(' + tone.h + ', 60%, 55%, 0.14)');
        }
    }

    // Background actually painted behind an element (first opaque ancestor).
    function backdropLuminance(el) {
        for (var node = el; node && node.nodeType === 1; node = node.parentElement) {
            var cs = window.getComputedStyle(node);
            if (cs.backgroundImage && cs.backgroundImage !== 'none' && cs.backgroundImage.indexOf('gradient') !== -1) {
                var stop = parse((cs.backgroundImage.match(/rgba?\([^)]+\)/) || [''])[0]);
                if (stop && stop.a > 0.5) return luminance(stop);
            }
            var bg = parse(cs.backgroundColor);
            if (bg && bg.a > 0.5) return luminance(bg);
        }
        return 0;
    }

    function fixInk(el, cs) {
        var ink = parse(cs.color);
        if (!ink || ink.a < 0.4 || luminance(ink) >= 0.42) return;
        // Dark ink on a light/gold surface (e.g. primary buttons) is intentional.
        if (backdropLuminance(el) > 0.45) return;
        var tone = hsl(ink);
        if (tone.s < 0.25) {
            setProp(el, 'color', luminance(ink) < 0.22 ? 'var(--au-text)' : 'var(--au-text-2)');
        } else {
            setProp(el, 'color', 'hsl(' + tone.h + ', ' + Math.round(Math.min(tone.s, 0.8) * 100) + '%, 70%)');
        }
    }

    function fixBorder(el, cs) {
        if (parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth) + parseFloat(cs.borderLeftWidth) + parseFloat(cs.borderRightWidth) === 0) return;
        var sides = ['Top', 'Right', 'Bottom', 'Left'];
        for (var i = 0; i < sides.length; i++) {
            var c = parse(cs['border' + sides[i] + 'Color']);
            if (c && c.a > 0.3 && luminance(c) > 0.8 && hsl(c).s < 0.35) {
                setProp(el, 'border-color', 'var(--au-border-strong)');
                return;
            }
        }
    }

    function processElement(el) {
        if (el.nodeType !== 1 || SKIP_TAGS[el.tagName.toUpperCase()]) return;
        if (el.closest('.au-dash, [data-au-keep], .btn-primary, .btn-warning, .btn-success, .swal2-confirm')) return;
        var cs = window.getComputedStyle(el);
        if (cs.display === 'none') return;
        fixBackground(el, cs);
        fixInk(el, cs);
        fixBorder(el, cs);
    }

    function processTree(root) {
        if (!root || root.nodeType !== 1) return;
        processElement(root);
        var all = root.getElementsByTagName('*');
        for (var i = 0; i < all.length; i++) processElement(all[i]);
    }

    function applyAll() {
        if (!isDark()) return;
        document.querySelectorAll(ROOT_SELECTOR).forEach(processTree);
    }

    function revertAll() {
        touched.forEach(function (el) {
            var orig = el.__auOrig || {};
            Object.keys(orig).forEach(function (prop) {
                if (orig[prop][0]) el.style.setProperty(prop, orig[prop][0], orig[prop][1]);
                else el.style.removeProperty(prop);
            });
            el.__auOrig = null;
        });
        touched.clear();
    }

    function flush() {
        timer = null;
        if (!isDark()) {
            pending = [];
            return;
        }
        var batch = pending;
        pending = [];
        batch.forEach(function (node) {
            if (!node.isConnected) return;
            if (node.closest && node.closest(ROOT_SELECTOR)) processTree(node);
            else if (node.querySelectorAll) node.querySelectorAll(ROOT_SELECTOR).forEach(processTree);
        });
    }

    function schedule(node) {
        pending.push(node);
        if (!timer) timer = window.setTimeout(flush, 60);
    }

    function start() {
        applyAll();
        // DataTables redraws, modals and AJAX fragments add nodes later.
        new MutationObserver(function (mutations) {
            if (!isDark()) return;
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) schedule(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });

        // Modals/dropdowns often only get their final colours once shown.
        document.addEventListener('shown.bs.modal', function (e) { if (isDark()) processTree(e.target); });
        document.addEventListener('shown.bs.dropdown', function () { if (isDark()) document.querySelectorAll('.dropdown-menu.show').forEach(processTree); });
        document.addEventListener('erp:theme-change', function () {
            revertAll();
            if (isDark()) window.requestAnimationFrame(applyAll);
        });
        window.addEventListener('load', applyAll);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
