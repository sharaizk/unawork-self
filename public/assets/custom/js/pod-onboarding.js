/**
 * Guided POD setup (UNA-011), on top of the shared step form (company-onboarding.js).
 *
 * - [data-pod-import]: copies a company worker-requirement template into a new POD requirement row
 *   (a snapshot: the row keeps the template id only as its origin; the server never live-links it).
 * - Publishing asks for confirmation (SweetAlert2 from layouts.admin when available).
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-pod-setup]');
    if (!form) {
        return;
    }

    function setValue(row, field, value) {
        var input = row.querySelector('[name$="[' + field + ']"]');
        if (input && value !== null && value !== undefined) {
            input.value = String(value);
        }
    }

    function importTemplate(template) {
        if (typeof form.coAddRequirement !== 'function') {
            return;
        }
        var row = form.coAddRequirement();
        if (!row) {
            return;
        }

        ['job_title_id', 'headcount', 'minimum_years_experience', 'minimum_skill_proficiency',
            'preferred_work_mode', 'preferred_engagement_type', 'location', 'description'].forEach(function (field) {
            setValue(row, field, template[field]);
        });

        var source = row.querySelector('[data-co-source]');
        if (source) {
            source.value = String(template.id);
        }
        var badge = row.querySelector('[data-co-source-badge]');
        if (badge) {
            badge.classList.remove('d-none');
        }

        var skills = row.querySelector('select[name$="[skill_ids][]"]');
        if (skills) {
            var wanted = (template.skill_ids || []).map(String);
            Array.prototype.forEach.call(skills.options, function (option) {
                option.selected = wanted.indexOf(option.value) !== -1;
            });
            var $ = window.jQuery;
            if ($ && $(skills).hasClass('select2-hidden-accessible')) {
                $(skills).trigger('change');
            }
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-pod-import]');
        if (!button || !form.contains(button)) {
            return;
        }
        event.preventDefault();
        try {
            importTemplate(JSON.parse(button.getAttribute('data-template') || '{}'));
        } catch (e) {
            // malformed data attribute: nothing to import
        }
    });

    var publishForm = document.querySelector('[data-pod-publish]');
    if (publishForm) {
        var confirmed = false;
        publishForm.addEventListener('submit', function (event) {
            if (confirmed) {
                return;
            }
            event.preventDefault();
            var message = publishForm.getAttribute('data-confirm-message') || '';
            var go = function () {
                confirmed = true;
                publishForm.submit();
            };
            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({ text: message, icon: 'question', showCancelButton: true, reverseButtons: true })
                    .then(function (result) {
                        if (result && (result.isConfirmed || result.value)) {
                            go();
                        }
                    });
            } else if (window.confirm(message)) {
                go();
            }
        });
    }
})();
