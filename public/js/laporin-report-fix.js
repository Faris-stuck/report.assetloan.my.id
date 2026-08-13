/*
 * LAPORIN public report form guard.
 *
 * The report wizard hides conditional fields with CSS, but native browser
 * constraint validation still evaluates required controls unless they are
 * disabled. Keep the DOM state aligned with the selected report type so
 * hidden violation/damage fields cannot block a valid submission.
 */
(function () {
    'use strict';

    function syncReportTypeFields(type) {
        var groups = document.querySelectorAll('[data-report-type-content]');
        groups.forEach(function (group) {
            var active = group.getAttribute('data-report-type-content') === type;
            group.classList.toggle('d-none', !active);

            group.querySelectorAll('input, select, textarea, button').forEach(function (field) {
                field.disabled = !active;
            });
        });
    }

    function syncReporterFields(type) {
        document.querySelectorAll('[data-reporter-role]').forEach(function (group) {
            var active = group.getAttribute('data-reporter-role') === type;
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
        var reporterType = form.querySelector('#reporter_type');

        if (reportType) syncReportTypeFields(reportType.value);
        if (reporterType) syncReporterFields(reporterType.value);

        form.addEventListener('change', function (event) {
            var target = event.target;

            if (target && target.name === 'report_type') {
                syncReportTypeFields(target.value);
            }

            if (target && target.name === 'reporter_type') {
                syncReporterFields(target.value);
            }
        });

        // Defensive synchronization immediately before submission. Disabled
        // controls are excluded from HTML constraint validation and FormData.
        form.addEventListener('submit', function () {
            var selectedType = form.querySelector('input[name="report_type"]:checked');
            var selectedReporter = form.querySelector('#reporter_type');
            if (selectedType) syncReportTypeFields(selectedType.value);
            if (selectedReporter) syncReporterFields(selectedReporter.value);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
