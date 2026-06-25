(function () {
    function isReportsPage() {
        return window.location && window.location.pathname.indexOf('/reports') === 0;
    }

    function readStorage(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    function hasDarkMode() {
        var html = document.documentElement;
        var body = document.body;
        var htmlClasses = html.classList;
        var bodyClasses = body ? body.classList : { contains: function () { return false; } };

        var attrTheme = (html.getAttribute('data-theme') || body.getAttribute('data-theme') || '').toLowerCase();
        var attrBsTheme = (html.getAttribute('data-bs-theme') || body.getAttribute('data-bs-theme') || '').toLowerCase();
        var storageValues = [
            readStorage('theme'),
            readStorage('appearance'),
            readStorage('color-theme'),
            readStorage('documentarchive-theme'),
            readStorage('darkMode')
        ].filter(Boolean).map(function (value) { return String(value).toLowerCase(); });

        if (htmlClasses.contains('dark') || htmlClasses.contains('dark-mode') || htmlClasses.contains('theme-dark')) return true;
        if (bodyClasses.contains('dark') || bodyClasses.contains('dark-mode') || bodyClasses.contains('theme-dark')) return true;
        if (attrTheme === 'dark' || attrBsTheme === 'dark') return true;
        if (storageValues.indexOf('dark') !== -1 || storageValues.indexOf('true') !== -1 || storageValues.indexOf('1') !== -1) return true;

        return false;
    }

    function syncReportsDarkMode() {
        if (!document.body || !isReportsPage()) return;
        document.body.classList.add('reports-dark-fix');
        document.body.classList.toggle('reports-dark-mode-active', hasDarkMode());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncReportsDarkMode);
    } else {
        syncReportsDarkMode();
    }

    var observer = new MutationObserver(syncReportsDarkMode);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme'] });
    document.addEventListener('click', function () { window.setTimeout(syncReportsDarkMode, 50); }, true);
})();