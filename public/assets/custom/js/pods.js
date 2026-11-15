/**
 * POD management page (users/pod.blade.php): the published-POD edit form loaded into #commonModal.
 * New PODs use the guided full-page setup (UNA-011), so there is no create modal any more.
 * Uses the axios, jQuery and Chosen copies provided by layouts.admin.
 *
 * Errors stay inside the modal: 422 shows the field messages next to the fields; other responses
 * show the server's message (e.g. an edit that would leave a published POD incomplete) and, when
 * given, a link to the setup page.
 */
(function () {
    'use strict';

    var config = document.getElementById('pods-page-config');
    if (!config || !window.jQuery || !window.axios) {
        return;
    }
    // Resolve jQuery at call time: layouts.admin may replace window.jQuery after this file runs.
    function jq() {
        return window.jQuery;
    }
    var i18n = {};
    try {
        i18n = JSON.parse(config.getAttribute('data-i18n') || '{}');
    } catch (e) {
        i18n = {};
    }

    function notify(message, type) {
        if (typeof window.show_toastr === 'function') {
            window.show_toastr(type === 'success' ? 'Success' : 'Error', message, type === 'success' ? 'success' : 'error');
        } else if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
        }
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('[data-pod-field-error]').forEach(function (el) { el.parentNode.removeChild(el); });
        var box = form.querySelector('[data-pod-form-error]');
        if (box) {
            box.classList.add('d-none');
            box.textContent = '';
        }
    }

    function showError(form, error) {
        var response = error && error.response ? error.response : null;
        var data = response && response.data ? response.data : {};
        var box = form.querySelector('[data-pod-form-error]');

        if (response && response.status === 422 && data.errors) {
            Object.keys(data.errors).forEach(function (key) {
                var name = key.indexOf('.') === -1 ? key : key.split('.')[0] + '[]';
                var field = form.querySelector('[name="' + name + '"]');
                if (field) {
                    field.classList.add('is-invalid');
                    var feedback = document.createElement('span');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.setAttribute('data-pod-field-error', '');
                    feedback.textContent = data.errors[key][0];
                    field.parentNode.appendChild(feedback);
                }
            });
        }

        if (!box) {
            notify(data.message || i18n.failed, 'danger');
            return;
        }
        box.textContent = (response && response.status === 422) ? (i18n.fixErrors || data.message) : (data.message || i18n.failed);
        var target = data.setup_url || data.onboarding_url;
        if (target) {
            box.appendChild(document.createTextNode(' '));
            var link = document.createElement('a');
            link.href = target;
            link.className = 'alert-link';
            link.textContent = data.setup_url ? i18n.openSetup : i18n.completeProfile;
            box.appendChild(link);
        }
        box.classList.remove('d-none');
    }

    jq()(document).on('shown.bs.modal', function (e) {
        var $ = jq();
        var modal = $(e.target);

        if ($.fn.chosen) {
            modal.find('.chosen-select').chosen({ width: '100%', placeholder_text_multiple: i18n.selectSkills });
        }

        modal.find('.update-pod-form').off('submit').on('submit', function (event) {
            event.preventDefault();
            var form = this;
            clearErrors(form);
            window.axios.post(form.getAttribute('data-url'), new FormData(form), { headers: { Accept: 'application/json' } })
                .then(function () {
                    $('#commonModal').modal('hide');
                    notify(i18n.updated, 'success');
                    setTimeout(function () { window.location.reload(); }, 800);
                })
                .catch(function (error) {
                    showError(form, error);
                });
        });
    });
})();
