/**
 * /my-profile editor (UNA-007). Plain JS; SweetAlert2 comes from layouts.admin (optional).
 *
 * - Repeatable rows: [data-profile-add="type"] clones <template id="profile-template-type">
 *   into [data-profile-rows="type"], replacing __INDEX__ with a fresh index. Removal uses a
 *   delegated [data-profile-remove] handler; the server deletes records missing from the submit.
 * - "I currently work here" disables and clears the end date.
 * - Availability "specific date" reveals the date field.
 * - Industry filter: the Industry select narrows every .js-industry-filtered select (skills,
 *   current job title) and the preferred-role list to entries tagged with that industry
 *   (data-industries="1,4") plus general, untagged ones. Select options are rebuilt rather than
 *   hidden (Safari ignores hidden <option>s); a selected value is always kept. "Show ... from all
 *   industries" disables the filter.
 * - Changing the industry clears the industry-related answers (skills, current job title, preferred
 *   roles) after a confirmation; cancelling restores the previous industry. Nothing is saved until
 *   the form is submitted.
 * - Preferred roles are chips: clicking a role in [data-role-options] moves it into
 *   [data-role-chips] (with a hidden preferred_role_ids[] input) and hides it from the list; the
 *   chip's x button returns it.
 * Skills and roles come from the administrator-managed catalogs; employees cannot create them here.
 */
(function () {
    'use strict';

    var form = document.getElementById('my-profile-form');
    if (!form) {
        return;
    }

    var i18n = {};
    try {
        i18n = JSON.parse(form.getAttribute('data-i18n') || '{}');
    } catch (e) {
        i18n = {};
    }

    var industrySelect = document.getElementById('industry_id');
    var showAll = document.getElementById('profile-show-all-catalog');
    var rolePicker = form.querySelector('[data-role-picker]');

    function currentIndustry() {
        return showAll && showAll.checked ? '' : (industrySelect ? industrySelect.value : '');
    }

    /** True for every entry without an industry filter, untagged (general) entries and tagged matches. */
    function inIndustry(element, industryId) {
        if (!industryId) {
            return true;
        }
        var tags = element.getAttribute('data-industries') || '';
        return tags === '' || tags.split(',').indexOf(industryId) !== -1;
    }

    // ------------------------------------------------------------------ repeatable rows

    function addRow(type) {
        var container = document.querySelector('[data-profile-rows="' + type + '"]');
        var template = document.getElementById('profile-template-' + type);
        if (!container || !template) {
            return null;
        }
        var index = parseInt(container.getAttribute('data-next-index') || '0', 10);
        container.setAttribute('data-next-index', String(index + 1));

        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index)).trim();
        var row = wrapper.firstElementChild;
        container.appendChild(row);

        applyIndustryFilter(row);

        var firstField = row.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstField) {
            firstField.focus();
        }

        return row;
    }

    // ------------------------------------------------------------------ industry filter (selects)

    var allOptions = typeof WeakMap === 'function' ? new WeakMap() : null;

    function originalOptions(select) {
        if (!allOptions) {
            return null;
        }
        if (!allOptions.has(select)) {
            allOptions.set(select, Array.prototype.map.call(select.options, function (option) {
                return option.cloneNode(true);
            }));
        }
        return allOptions.get(select);
    }

    function filterSelect(select, industryId) {
        var options = originalOptions(select);
        if (!options) {
            return;
        }
        var selected = Array.prototype.filter.call(select.options, function (option) {
            return option.selected && option.value !== '';
        }).map(function (option) {
            return option.value;
        });

        while (select.options.length) {
            select.remove(0);
        }
        options.forEach(function (option) {
            var isSelected = selected.indexOf(option.value) !== -1;
            if (isSelected || option.value === '' || inIndustry(option, industryId)) {
                var copy = option.cloneNode(true);
                copy.selected = isSelected;
                select.appendChild(copy);
            }
        });
        if (!select.multiple && selected.length === 0 && select.options.length) {
            select.selectedIndex = 0;
        }
    }

    function applyIndustryFilter(scope) {
        var industryId = currentIndustry();
        (scope || form).querySelectorAll('.js-industry-filtered').forEach(function (select) {
            filterSelect(select, industryId);
        });
        if (!scope) {
            refreshRoleOptions();
        }
    }

    // ------------------------------------------------------------------ preferred-role chips

    function roleChips() {
        return rolePicker ? rolePicker.querySelector('[data-role-chips]') : null;
    }

    function selectedRoleIds() {
        var chips = roleChips();
        if (!chips) {
            return [];
        }
        return Array.prototype.map.call(chips.querySelectorAll('[data-role-chip]'), function (chip) {
            return chip.getAttribute('data-role-chip');
        });
    }

    function refreshRoleOptions() {
        if (!rolePicker) {
            return;
        }
        var industryId = currentIndustry();
        var search = rolePicker.querySelector('[data-role-search]');
        var term = search ? search.value.trim().toLowerCase() : '';
        var selected = selectedRoleIds();
        var visible = 0;

        rolePicker.querySelectorAll('[data-role-option]').forEach(function (option) {
            var id = option.getAttribute('data-role-option');
            var label = option.textContent.trim().toLowerCase();
            var show = selected.indexOf(id) === -1
                && inIndustry(option, industryId)
                && (term === '' || label.indexOf(term) !== -1);
            option.hidden = !show;
            if (show) {
                visible++;
            }
        });

        var optionsEmpty = rolePicker.querySelector('[data-role-options-empty]');
        if (optionsEmpty) {
            optionsEmpty.classList.toggle('d-none', visible > 0);
        }
        var chipsEmpty = rolePicker.querySelector('[data-role-chips-empty]');
        if (chipsEmpty) {
            chipsEmpty.classList.toggle('d-none', selected.length > 0);
        }
    }

    function addRoleChip(option) {
        var id = option.getAttribute('data-role-option');
        var chips = roleChips();
        if (!chips || selectedRoleIds().indexOf(id) !== -1) {
            return;
        }
        var label = option.textContent.trim();

        var chip = document.createElement('span');
        chip.className = 'una-role-chip';
        chip.setAttribute('role', 'listitem');
        chip.setAttribute('data-role-chip', id);

        var text = document.createElement('span');
        text.textContent = label;

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'preferred_role_ids[]';
        input.value = id;

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'una-role-chip__remove';
        remove.setAttribute('data-role-remove', '');
        remove.setAttribute('aria-label', (i18n.remove || 'Remove :role').replace(':role', label));
        remove.title = i18n.removeTitle || 'Remove';
        var icon = document.createElement('i');
        icon.className = 'ti ti-x';
        icon.setAttribute('aria-hidden', 'true');
        remove.appendChild(icon);

        chip.appendChild(text);
        chip.appendChild(input);
        chip.appendChild(remove);
        chips.appendChild(chip);

        // Keep keyboard focus in the list: move to the next visible role, if any.
        var next = option.nextElementSibling;
        refreshRoleOptions();
        while (next && next.hidden) {
            next = next.nextElementSibling;
        }
        (next || remove).focus();
    }

    function removeRoleChip(chip) {
        var id = chip.getAttribute('data-role-chip');
        chip.parentNode.removeChild(chip);
        refreshRoleOptions();
        var option = rolePicker.querySelector('[data-role-option="' + id + '"]');
        if (option && !option.hidden) {
            option.focus();
        }
    }

    // ------------------------------------------------------------------ industry change resets

    var previousIndustry = industrySelect ? industrySelect.value : '';

    function hasIndustryAnswers() {
        var skillRows = form.querySelectorAll('[data-profile-rows="skill"] [data-profile-row]').length;
        var jobTitle = document.getElementById('job_title_id');
        return skillRows > 0 || (jobTitle && jobTitle.value !== '') || selectedRoleIds().length > 0;
    }

    function clearIndustryAnswers() {
        form.querySelectorAll('[data-profile-rows="skill"] [data-profile-row]').forEach(function (row) {
            row.parentNode.removeChild(row);
        });
        var jobTitle = document.getElementById('job_title_id');
        if (jobTitle) {
            jobTitle.value = '';
        }
        var chips = roleChips();
        if (chips) {
            while (chips.firstChild) {
                chips.removeChild(chips.firstChild);
            }
        }
        var search = rolePicker ? rolePicker.querySelector('[data-role-search]') : null;
        if (search) {
            search.value = '';
        }
    }

    function confirmReset() {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: i18n.resetTitle,
                text: i18n.resetText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: i18n.resetConfirm,
                cancelButtonText: i18n.cancel,
                reverseButtons: true
            }).then(function (result) {
                return Boolean(result && (result.isConfirmed || result.value));
            });
        }
        return Promise.resolve(window.confirm((i18n.resetTitle || '') + '\n\n' + (i18n.resetText || '')));
    }

    function onIndustryChange() {
        if (industrySelect.value === previousIndustry) {
            return;
        }
        if (!hasIndustryAnswers()) {
            previousIndustry = industrySelect.value;
            applyIndustryFilter();
            return;
        }
        confirmReset().then(function (confirmed) {
            if (confirmed) {
                clearIndustryAnswers();
                previousIndustry = industrySelect.value;
            } else {
                industrySelect.value = previousIndustry;
            }
            applyIndustryFilter();
        });
    }

    // ------------------------------------------------------------------ events

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            return;
        }

        var add = event.target.closest('[data-profile-add]');
        if (add) {
            event.preventDefault();
            addRow(add.getAttribute('data-profile-add'));
            return;
        }

        var remove = event.target.closest('[data-profile-remove]');
        if (remove) {
            event.preventDefault();
            var row = remove.closest('[data-profile-row]');
            if (row) {
                row.parentNode.removeChild(row);
            }
            return;
        }

        var roleOption = event.target.closest('[data-role-option]');
        if (roleOption) {
            event.preventDefault();
            addRoleChip(roleOption);
            return;
        }

        var roleRemove = event.target.closest('[data-role-remove]');
        if (roleRemove) {
            event.preventDefault();
            removeRoleChip(roleRemove.closest('[data-role-chip]'));
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.matches('[data-role-search]')) {
            refreshRoleOptions();
        }
    });

    // Enter in the role search adds the first visible role instead of submitting the form.
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.matches('[data-role-search]')) {
            event.preventDefault();
            var first = rolePicker.querySelector('[data-role-option]:not([hidden])');
            if (first) {
                addRoleChip(first);
                event.target.value = '';
                refreshRoleOptions();
                event.target.focus();
            }
        }
    });

    form.addEventListener('change', function (event) {
        var target = event.target;

        if (target.classList.contains('js-experience-current')) {
            var row = target.closest('[data-profile-row]');
            var end = row ? row.querySelector('.js-experience-end') : null;
            if (end) {
                if (target.checked) {
                    end.value = '';
                }
                end.disabled = target.checked;
            }
        }

        if (target === industrySelect) {
            onIndustryChange();
        }

        if (target === showAll) {
            applyIndustryFilter();
        }

        if (target.id === 'availability_status') {
            var group = document.getElementById('available-from-group');
            if (group) {
                group.classList.toggle('d-none', target.value !== 'specific_date');
            }
        }
    });

    applyIndustryFilter();
})();
