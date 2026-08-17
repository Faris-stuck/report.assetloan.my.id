/* LAPORIN public report form helpers. */
(function () {
    'use strict';
    function syncReportTypeFields(type) {
        document.querySelectorAll('[data-report-type-content]').forEach(function (group) {
            var active = group.getAttribute('data-report-type-content') === type;
            group.classList.toggle('d-none', !active);
            group.querySelectorAll('input, select, textarea, button').forEach(function (field) {
                field.disabled = !active;
            });
        });
    }

    function init() {
        var form = document.getElementById('form-laporan');
        if (!form) return;

        var reportType = form.querySelector('input[name="report_type"]:checked');
        if (reportType) syncReportTypeFields(reportType.value);

        form.addEventListener('change', function (event) {
            var target = event.target;
            if (target && target.name === 'report_type') {
                syncReportTypeFields(target.value);
            }
        });

        form.addEventListener('submit', function () {
            var selectedType = form.querySelector('input[name="report_type"]:checked');
            if (selectedType) syncReportTypeFields(selectedType.value);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
