/**
 * Employee POD recommendations (UNA-017). Delegated handlers on [data-pr-root]:
 *  - [data-pr-apply]      POST to the existing api.pods.join endpoint (URL from data-url)
 *  - [data-pr-dismiss]    POST to pod-recommendations.dismiss (URL from data-url)
 *  - [data-pr-why-toggle] expand/collapse "Why this matches" (aria-expanded / aria-controls)
 * All copy comes from translated data-* attributes; server messages are shown as returned.
 * Uses fetch with the CSRF token, X-Requested-With and Accept: application/json.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-pr-root]');
    if (!root) {
        return;
    }

    // The theme switches dark mode by swapping stylesheets; mirror it on the feature root.
    if (document.querySelector('link[href*="style-dark.css"]')) {
        root.classList.add('pr-theme-dark');
    }

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var live = root.querySelector('[data-pr-live]');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');

    function text(key) {
        return root.getAttribute('data-' + key) || '';
    }

    function notify(type, message) {
        var title = type === 'success' ? text('title-success') : text('title-error');
        if (typeof window.show_toastr === 'function') {
            window.show_toastr(title, message, type);
        }
        if (live) {
            live.textContent = '';
            window.setTimeout(function () { live.textContent = message; }, 50);
        }
    }

    function post(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                return { status: response.status, ok: response.ok, body: body || {} };
            });
        });
    }

    function busy(button, label) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.dataset.prLabel = button.innerHTML;
        button.textContent = label;
    }

    function restore(button) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.prLabel) {
            button.innerHTML = button.dataset.prLabel;
        }
    }

    function markSent(button) {
        var slot = button.closest('[data-pr-apply-slot]');
        var status = document.createElement('span');
        status.className = 'pr-status';
        status.setAttribute('tabindex', '-1');
        status.innerHTML = '<i class="ti ti-clock" aria-hidden="true"></i> ';
        status.appendChild(document.createTextNode(text('label-sent')));
        slot.replaceChildren(status);
        status.focus();
    }

    function apply(button) {
        busy(button, text('msg-applying'));
        post(button.getAttribute('data-url')).then(function (res) {
            if (res.ok) {
                markSent(button);
                notify('success', res.body.message || text('msg-applied'));
            } else if (res.status === 409) {
                // Already handled: reflect the real state instead of offering Apply again.
                markSent(button);
                notify('info', res.body.message || text('label-sent'));
            } else {
                restore(button);
                notify('error', res.body.message || text('msg-error'));
            }
        }).catch(function () {
            restore(button);
            notify('error', text('msg-error'));
        });
    }

    function updateCount(delta) {
        root.querySelectorAll('[data-pr-count]').forEach(function (el) {
            var value = parseInt(el.textContent, 10);
            if (!isNaN(value)) {
                el.textContent = String(Math.max(0, value + delta));
            }
        });
    }

    function removeCard(card) {
        var next = card.nextElementSibling || card.previousElementSibling;
        var finish = function () {
            card.remove();
            updateCount(-1);
            if (!root.querySelector('[data-pr-card]')) {
                var empty = root.querySelector('[data-pr-empty]');
                if (empty) {
                    empty.hidden = false;
                    var heading = empty.querySelector('[data-pr-empty-title]');
                    if (heading) { heading.focus(); }
                }
            } else if (next) {
                var link = next.querySelector('[data-pr-title-link]');
                if (link) { link.focus(); }
            }
        };
        if (reduceMotion) {
            finish();
            return;
        }
        card.classList.add('is-leaving');
        window.setTimeout(finish, 260);
    }

    function dismiss(button) {
        var card = button.closest('[data-pr-card]');
        busy(button, text('msg-dismissing'));
        post(button.getAttribute('data-url')).then(function (res) {
            if (res.ok) {
                removeCard(card);
                notify('success', res.body.message || text('msg-dismissed'));
            } else {
                restore(button);
                notify('error', res.body.message || text('msg-error'));
            }
        }).catch(function () {
            restore(button);
            notify('error', text('msg-error'));
        });
    }

    function toggleWhy(button) {
        var panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) {
            return;
        }
        var open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', open ? 'false' : 'true');
        panel.hidden = open;
    }

    root.addEventListener('click', function (event) {
        var target = event.target.closest('[data-pr-apply], [data-pr-dismiss], [data-pr-why-toggle]');
        if (!target || !root.contains(target) || target.disabled) {
            return;
        }
        event.preventDefault();
        if (target.hasAttribute('data-pr-apply')) {
            apply(target);
        } else if (target.hasAttribute('data-pr-dismiss')) {
            dismiss(target);
        } else {
            toggleWhy(target);
        }
    });
})();
