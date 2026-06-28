(function () {
    'use strict';

    function isDocumentsPage() {
        var path = window.location.pathname || '';
        return path === '/documents' || path.indexOf('/documents?') === 0 || path.indexOf('/documents/') === 0;
    }

    function normalizeText(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    function findActionsColumnIndex(table) {
        var headers = table.querySelectorAll('thead th');
        for (var i = 0; i < headers.length; i++) {
            if (normalizeText(headers[i].textContent).indexOf('إجراءات') !== -1) {
                return i;
            }
        }
        return headers.length ? headers.length - 1 : -1;
    }

    function hasActionControls(cell) {
        if (!cell) return false;
        var text = normalizeText(cell.textContent);
        return cell.querySelector('a, button, form, input[type="submit"]') &&
            (text.indexOf('عرض') !== -1 ||
             text.indexOf('تعديل') !== -1 ||
             text.indexOf('حذف') !== -1 ||
             text.indexOf('طباعة') !== -1 ||
             text.indexOf('استعادة') !== -1);
    }

    function wrapCellActions(cell) {
        if (!hasActionControls(cell)) return;
        cell.classList.add('documents-actions-fixed');

        if (cell.querySelector(':scope > .documents-actions-inline')) {
            return;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'documents-actions-inline';

        var children = Array.prototype.slice.call(cell.childNodes);
        children.forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE && !normalizeText(node.textContent)) {
                cell.removeChild(node);
                return;
            }
            wrapper.appendChild(node);
        });

        cell.appendChild(wrapper);
    }

    function applyFix() {
        if (!isDocumentsPage()) return;
        document.body.classList.add('documents-page');

        var tables = document.querySelectorAll('table');
        tables.forEach(function (table) {
            var actionIndex = findActionsColumnIndex(table);
            if (actionIndex < 0) return;

            var headerCells = table.querySelectorAll('thead th');
            if (headerCells[actionIndex]) {
                headerCells[actionIndex].classList.add('documents-actions-fixed');
            }

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var cells = row.children;
                var target = cells[actionIndex] || cells[cells.length - 1];
                wrapCellActions(target);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyFix);
    } else {
        applyFix();
    }
})();