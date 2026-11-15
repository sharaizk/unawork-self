/**
 * Guided step forms ([data-co-form]): company onboarding / Company Profile (UNA-010) and guided POD
 * setup (UNA-011). Plain JS; select2/jQuery from layouts.admin (optional).
 *
 * - Steps: one form, one panel per step ([data-co-step]); the active step is shown, written into the
 *   hidden `step` input and the URL (?step=). Without JS every panel is visible and still saves.
 *   After a validation error the panel holding the first invalid field opens.
 * - Worker requirements: [data-co-add] clones <template id="co-requirement-template"> (__INDEX__ →
 *   unique index); [data-co-remove] (delegated) removes a row; the server deletes rows not submitted.
 * - Industry prioritizing: every .js-co-industry-sorted select is regrouped into "Suggested for your
 *   industry" (tagged with it), "All industries" (untagged) and "Other industries". Nothing is hidden
 *   or rejected; selected values are kept.
 * - Logo preview and a description character counter.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-co-form]');
    if (!form) {
        return;
    }

    var i18n = {};
    try {
        i18n = JSON.parse(form.getAttribute('data-i18n') || '{}');
    } catch (e) {
        i18n = {};
    }

    var panels = Array.prototype.slice.call(form.querySelectorAll('[data-co-step]'));
    var order = panels.map(function (panel) { return panel.getAttribute('data-co-step'); });
    var stepInput = form.querySelector('[data-co-step-input]');
    // The company page has an industry select; the POD page passes the company industry as data.
    var industrySelect = form.querySelector('#industry_id');
    function currentIndustry() {
        return industrySelect ? industrySelect.value : (form.getAttribute('data-industry-id') || '');
    }

    // layouts.admin loads jQuery/select2 more than once (a later copy replaces window.jQuery), so
    // always resolve it at call time instead of capturing it when this file runs.
    function jq() {
        return window.jQuery && window.jQuery.fn ? window.jQuery : null;
    }

    // ------------------------------------------------------------------ steps

    function showStep(key, focus) {
        if (order.indexOf(key) === -1) {
            key = order[0];
        }
        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-co-step') === key);
        });
        document.querySelectorAll('[data-co-step-link]').forEach(function (link) {
            var active = link.getAttribute('data-co-step-link') === key;
            link.classList.toggle('is-active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (stepInput) {
            stepInput.value = key;
        }
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('step', key);
            window.history.replaceState(null, '', url.toString());
        }
        if (focus) {
            var panel = form.querySelector('[data-co-step="' + key + '"]');
            var heading = panel ? panel.querySelector('h5') : null;
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus();
            }
        }
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-co-step-link]');
        if (link) {
            event.preventDefault();
            showStep(link.getAttribute('data-co-step-link'), true);
            return;
        }
        if (!form.contains(event.target)) {
            return;
        }

        var prev = event.target.closest('[data-co-prev]');
        if (prev) {
            event.preventDefault();
            var current = order.indexOf(stepInput ? stepInput.value : order[0]);
            showStep(order[Math.max(0, current - 1)], true);
            return;
        }

        var submit = event.target.closest('[data-co-submit]');
        if (submit && stepInput) {
            stepInput.value = submit.getAttribute('data-co-submit');
        }

        var add = event.target.closest('[data-co-add]');
        if (add) {
            event.preventDefault();
            addRequirement();
            return;
        }

        var remove = event.target.closest('[data-co-remove]');
        if (remove) {
            event.preventDefault();
            var row = remove.closest('[data-co-row]');
            if (row) {
                var $r = jq();
                if ($r && $r.fn.select2) {
                    $r(row).find('select.select2-hidden-accessible').select2('destroy');
                }
                row.parentNode.removeChild(row);
                updateEmptyState();
            }
        }
    });

    // ------------------------------------------------------------------ worker requirements

    var rows = form.querySelector('[data-co-rows]');

    function updateEmptyState() {
        var empty = form.querySelector('[data-co-rows-empty]');
        if (empty && rows) {
            empty.classList.toggle('d-none', rows.querySelectorAll('[data-co-row]').length > 0);
        }
    }

    function initSkillSelects(scope) {
        var $ = jq();
        if (!($ && typeof $.fn.select2 === 'function')) {
            return;
        }
        $(scope).find('select.js-co-skills').each(function () {
            var $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            $select.select2({
                width: '100%',
                placeholder: $select.data('placeholder') || '',
                dir: document.documentElement.getAttribute('dir') === 'rtl' ? 'rtl' : 'ltr'
            });
        });
    }

    function addRequirement() {
        var template = document.getElementById('co-requirement-template');
        if (!template || !rows) {
            return null;
        }
        var index = parseInt(rows.getAttribute('data-next-index') || '0', 10);
        rows.setAttribute('data-next-index', String(index + 1));

        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index)).trim();
        var row = wrapper.firstElementChild;
        rows.appendChild(row);

        prioritize(row);
        initSkillSelects(row);
        updateEmptyState();

        var first = row.querySelector('select, input:not([type="hidden"])');
        if (first) {
            first.focus();
        }

        return row;
    }

    // Let page-specific scripts (pod-onboarding.js) add rows and refresh selects.
    form.coAddRequirement = addRequirement;
    form.coPrioritize = prioritize;

    // ------------------------------------------------------------------ industry prioritizing

    function tagsOf(option) {
        var tags = option.getAttribute('data-industries') || '';
        return tags === '' ? [] : tags.split(',');
    }

    function regroup(select, industryId) {
        var options = Array.prototype.filter.call(select.querySelectorAll('option'), function (option) {
            return option.value !== '';
        });
        options.forEach(function (option, i) {
            if (!option.hasAttribute('data-order')) {
                option.setAttribute('data-order', String(i));
            }
        });
        var selected = options.filter(function (option) { return option.selected; }).map(function (option) { return option.value; });

        Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function (group) {
            group.parentNode.removeChild(group);
        });
        options.forEach(function (option) {
            if (option.parentNode) {
                option.parentNode.removeChild(option);
            }
        });
        options.sort(function (a, b) {
            return parseInt(a.getAttribute('data-order'), 10) - parseInt(b.getAttribute('data-order'), 10);
        });

        if (!industryId) {
            options.forEach(function (option) { select.appendChild(option); });
        } else {
            var groups = [
                { label: i18n.suggested, items: [] },
                { label: i18n.general, items: [] },
                { label: i18n.other, items: [] }
            ];
            options.forEach(function (option) {
                var tags = tagsOf(option);
                var target = tags.indexOf(industryId) !== -1 ? 0 : (tags.length === 0 ? 1 : 2);
                groups[target].items.push(option);
            });
            groups.forEach(function (group) {
                if (!group.items.length) {
                    return;
                }
                var optgroup = document.createElement('optgroup');
                optgroup.label = group.label || '';
                group.items.forEach(function (option) { optgroup.appendChild(option); });
                select.appendChild(optgroup);
            });
        }

        options.forEach(function (option) {
            option.selected = selected.indexOf(option.value) !== -1;
        });
        if (!select.multiple && selected.length === 0) {
            select.selectedIndex = 0;
        }
        var $s = jq();
        if ($s && $s(select).hasClass('select2-hidden-accessible')) {
            $s(select).trigger('change.select2');
        }
    }

    function prioritize(scope) {
        var industryId = currentIndustry();
        (scope || form).querySelectorAll('.js-co-industry-sorted').forEach(function (select) {
            regroup(select, industryId);
        });
    }

    // ------------------------------------------------------------------ logo preview / counter

    function updateCounter(textarea) {
        var text = form.querySelector('[data-co-counter-text]');
        if (!text) {
            return;
        }
        var min = parseInt(textarea.getAttribute('data-co-counter') || '0', 10);
        var count = textarea.value.trim().length;
        text.textContent = (text.getAttribute('data-template') || '').replace('__COUNT__', String(count));
        text.classList.toggle('text-success', count >= min);
    }

    form.addEventListener('change', function (event) {
        var target = event.target;
        if (target === industrySelect) {
            prioritize();
        }
        if (target.matches('[data-co-logo-input]') && target.files && target.files[0] && window.FileReader) {
            var preview = form.querySelector('[data-co-logo-preview]');
            var reader = new FileReader();
            reader.onload = function (e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
            };
            reader.readAsDataURL(target.files[0]);
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.matches('[data-co-counter]')) {
            updateCounter(event.target);
        }
    });

    // ------------------------------------------------------------------ init

    form.classList.add('co-js');
    prioritize();
    // After every layout script has run (see jq()).
    if (document.readyState === 'complete') {
        initSkillSelects(form);
    } else {
        window.addEventListener('load', function () { initSkillSelects(form); });
    }

    var firstInvalid = form.querySelector('.is-invalid');
    var initial = form.getAttribute('data-initial-step') || order[0];
    if (firstInvalid && firstInvalid.closest('[data-co-step]')) {
        initial = firstInvalid.closest('[data-co-step]').getAttribute('data-co-step');
    }
    showStep(initial, false);
})();
