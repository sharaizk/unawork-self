/**
 * Business match-review queue (UNA-018). Delegated handlers on [data-mr-root]:
 *  - [data-mr-action]     open the confirmation dialog for approve / reject / exclude
 *  - [data-mr-form]       submit the decision to the card's data-url
 *  - [data-mr-why-toggle] expand/collapse "Why this match?" (aria-expanded / aria-controls)
 * Copy comes from translated data-* attributes; server messages are shown as returned.
 * Requests send the CSRF token, X-Requested-With and Accept: application/json.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-mr-root]');
    if (!root) {
        return;
    }
    if (document.querySelector('link[href*="style-dark.css"]')) {
        root.classList.add('mr-theme-dark');
    }

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var live = root.querySelector('[data-mr-live]');
    var dialogEl = root.querySelector('[data-mr-dialog]');
    var form = root.querySelector('[data-mr-form]');
    var reasonGroup = root.querySelector('[data-mr-reason-group]');
    var reason = root.querySelector('#mr-reason');
    var confirmBtn = root.querySelector('[data-mr-confirm]');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var modal = dialogEl && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(dialogEl) : null;
    var current = null; // { card, action, trigger }
    var pending = false;

    function text(key) {
        return root.getAttribute('data-' + key) || '';
    }

    function dialogText(key, name) {
        return (dialogEl.getAttribute('data-' + key) || '').split(':name').join(name);
    }

    function notify(type, message) {
        var title = type === 'success' ? text('title-success') : (type === 'info' ? text('title-info') : text('title-error'));
        if (typeof window.show_toastr === 'function') {
            window.show_toastr(title, message, type);
        }
        if (live) {
            live.textContent = '';
            window.setTimeout(function () { live.textContent = message; }, 50);
        }
    }

    function open(card, action, trigger) {
        var name = card.getAttribute('data-name') || '';
        current = { card: card, action: action, trigger: trigger };
        root.querySelector('[data-mr-dialog-title]').textContent = dialogText('title-' + action, name);
        root.querySelector('[data-mr-dialog-text]').textContent = dialogText('text-' + action, name);
        confirmBtn.textContent = dialogText('confirm-' + action, name);
        confirmBtn.className = 'btn ' + (action === 'approve' ? 'mr-btn-primary' : 'mr-btn-danger-solid');
        reasonGroup.hidden = action === 'approve';
        reason.value = '';
        if (modal) {
            modal.show();
        } else if (window.confirm(root.querySelector('[data-mr-dialog-text]').textContent)) {
            submit();
        }
    }

    function setBusy(busy) {
        pending = busy;
        confirmBtn.disabled = busy;
        confirmBtn.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (current) {
            current.card.querySelectorAll('[data-mr-action]').forEach(function (b) {
                if (busy) {
                    b.dataset.mrWasDisabled = b.disabled ? '1' : '0';
                    b.disabled = true;
                } else {
                    b.disabled = b.dataset.mrWasDisabled === '1';
                }
            });
        }
    }

    function adjustCount(name, delta) {
        var el = root.querySelector('[data-mr-count="' + name + '"]');
        if (el) {
            var value = parseInt(el.textContent, 10);
            el.textContent = String(Math.max(0, (isNaN(value) ? 0 : value) + delta));
        }
    }

    function removeCard(card) {
        var next = card.nextElementSibling || card.previousElementSibling;
        adjustCount('pending', -1);
        adjustCount(card.getAttribute('data-qualified') === '1' ? 'qualified' : 'stale', -1);
        var finish = function () {
            card.remove();
            if (!root.querySelector('[data-mr-card]')) {
                var empty = root.querySelector('[data-mr-empty]');
                empty.hidden = false;
                empty.querySelector('[data-mr-empty-title]').focus();
            } else if (next) {
                var heading = next.querySelector('.mr-card__name');
                if (heading) { heading.focus(); }
            }
        };
        if (reduceMotion) {
            finish();
        } else {
            card.classList.add('is-leaving');
            window.setTimeout(finish, 260);
        }
    }

    function submit() {
        if (!current || pending) {
            return;
        }
        var target = current;
        setBusy(true);
        fetch(target.card.getAttribute('data-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: target.action, reason: reason.value })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                return { status: response.status, ok: response.ok, body: body || {} };
            });
        }).then(function (res) {
            setBusy(false);
            if (res.ok || res.status === 409) {
                // 409: another manager already decided; the card is no longer pending either way.
                if (modal) { modal.hide(); }
                current = null;
                removeCard(target.card);
                notify(res.ok ? 'success' : 'info', res.body.message || text('msg-error'));
                return;
            }
            var message = res.body.message || text('msg-error');
            if (res.status === 422 && res.body.errors) {
                message = Object.keys(res.body.errors).map(function (k) { return res.body.errors[k][0]; }).join(' ');
            }
            notify('error', message);
        }).catch(function () {
            setBusy(false);
            notify('error', text('msg-error'));
        });
    }

    if (dialogEl) {
        dialogEl.addEventListener('hidden.bs.modal', function () {
            if (current && current.trigger && document.body.contains(current.trigger)) {
                current.trigger.focus();
            }
            if (!pending) {
                current = null;
            }
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submit();
    });

    root.addEventListener('click', function (event) {
        var action = event.target.closest('[data-mr-action]');
        if (action && !action.disabled && root.contains(action)) {
            event.preventDefault();
            open(action.closest('[data-mr-card]'), action.getAttribute('data-mr-action'), action);
            return;
        }
        var toggle = event.target.closest('[data-mr-why-toggle]');
        if (toggle) {
            var panel = document.getElementById(toggle.getAttribute('aria-controls'));
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (panel) { panel.hidden = expanded; }
        }
    });
})();
