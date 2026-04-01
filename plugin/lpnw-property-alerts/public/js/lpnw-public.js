/**
 * LPNW Property Alerts - Frontend JavaScript
 */
(function () {
    'use strict';

    var data = window.lpnwData || {};

    function post(action, params) {
        params.action = action;
        params.nonce = data.nonce;

        return fetch(data.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()
        }).then(function (res) { return res.json(); });
    }

    function initPreferencesForm() {
        var form = document.getElementById('lpnw-preferences-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(form);
            var params = {};

            formData.forEach(function (value, key) {
                if (key.endsWith('[]')) {
                    var clean = key.replace('[]', '');
                    if (!params[clean]) params[clean] = [];
                    params[clean].push(value);
                } else {
                    params[key] = value;
                }
            });

            var btn = form.querySelector('[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            post('lpnw_save_preferences', params).then(function (res) {
                btn.disabled = false;
                btn.textContent = 'Save Preferences';
                if (res.success) {
                    showNotice('Preferences saved.', 'success');
                } else {
                    showNotice('Could not save preferences. Please try again.', 'error');
                }
            });
        });
    }

    function initSaveButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.lpnw-save-property');
            if (!btn) return;

            var propertyId = btn.getAttribute('data-property-id');
            if (!propertyId) return;

            btn.disabled = true;

            post('lpnw_save_property', { property_id: propertyId }).then(function (res) {
                if (res.success) {
                    var textSpan = btn.querySelector('.lpnw-btn--bookmark__text');
                    if (textSpan) {
                        textSpan.textContent = 'Saved';
                    } else {
                        btn.textContent = 'Saved';
                    }
                    btn.classList.add('lpnw-btn--saved');
                } else {
                    btn.disabled = false;
                }
            });
        });
    }

    function initUnsaveButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.lpnw-unsave-property');
            if (!btn) return;

            var propertyId = btn.getAttribute('data-property-id');
            if (!propertyId) return;

            btn.disabled = true;

            post('lpnw_unsave_property', { property_id: propertyId }).then(function (res) {
                if (res.success) {
                    var item = btn.closest('.lpnw-property-list__item');
                    if (item) {
                        item.remove();
                    }
                    var list = document.getElementById('lpnw-saved-properties-list');
                    if (list && !list.querySelector('.lpnw-property-list__item')) {
                        window.location.reload();
                    }
                    showNotice('Removed from saved list.', 'success');
                } else {
                    btn.disabled = false;
                    showNotice('Could not remove property. Try again.', 'error');
                }
            });
        });
    }

    function showNotice(message, type) {
        var el = document.createElement('div');
        el.className = 'lpnw-notice lpnw-notice--' + type;
        el.textContent = message;
        el.style.cssText = 'position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:6px;z-index:9999;font-size:14px;font-weight:600;';

        if (type === 'success') {
            el.style.background = '#D1FAE5';
            el.style.color = '#065F46';
        } else {
            el.style.background = '#FEE2E2';
            el.style.color = '#991B1B';
        }

        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPreferencesForm();
        initSaveButtons();
        initUnsaveButtons();
    });
})();
