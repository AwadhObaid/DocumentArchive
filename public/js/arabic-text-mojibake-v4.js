(function () {
    'use strict';

    var meem = String.fromCharCode(0x0645);
    var badReplacement = String.fromCharCode(0xFFFD);
    var badLatinReplacement = String.fromCharCode(0x00EF, 0x00BF, 0x00BD);
    var badZahEllipsis = String.fromCharCode(0x0638, 0x2026);
    var badZahDots = String.fromCharCode(0x0638) + String.fromCharCode(46, 46, 46);
    var badEntityDec = '&' + '#65533;';
    var badEntityHex = '&' + '#xFFFD;';
    var badEntityHexLower = '&' + '#xfffd;';

    var replacements = [
        [badZahEllipsis, meem],
        [badZahDots, meem],
        [badReplacement, meem],
        [badLatinReplacement, meem],
        [badEntityDec, meem],
        [badEntityHex, meem],
        [badEntityHexLower, meem]
    ];

    function fixText(value) {
        if (typeof value !== 'string' || value.length === 0) {
            return value;
        }
        var out = value;
        for (var i = 0; i < replacements.length; i++) {
            var from = replacements[i][0];
            var to = replacements[i][1];
            while (out.indexOf(from) !== -1) {
                out = out.split(from).join(to);
            }
        }
        return out;
    }

    function skipElement(el) {
        if (!el || !el.tagName) {
            return false;
        }
        var tag = el.tagName.toLowerCase();
        return tag === 'script' || tag === 'style' || tag === 'noscript' || tag === 'svg' || tag === 'canvas' || tag === 'code' || tag === 'pre';
    }

    function fixAttributes(el) {
        if (!el || !el.getAttribute) {
            return;
        }
        var attrs = ['title', 'aria-label', 'placeholder', 'alt', 'value'];
        for (var i = 0; i < attrs.length; i++) {
            var name = attrs[i];
            var current = el.getAttribute(name);
            if (current !== null) {
                var fixed = fixText(current);
                if (fixed !== current) {
                    el.setAttribute(name, fixed);
                }
            }
        }
    }

    function walk(node) {
        if (!node) {
            return;
        }
        if (node.nodeType === 3) {
            var fixed = fixText(node.nodeValue);
            if (fixed !== node.nodeValue) {
                node.nodeValue = fixed;
            }
            return;
        }
        if (node.nodeType !== 1 || skipElement(node)) {
            return;
        }
        fixAttributes(node);
        var child = node.firstChild;
        while (child) {
            walk(child);
            child = child.nextSibling;
        }
    }

    function run() {
        document.title = fixText(document.title);
        walk(document.body);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'characterData') {
                    walk(mutation.target);
                }
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    walk(mutation.addedNodes[j]);
                }
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true, characterData: true });
    }
})();