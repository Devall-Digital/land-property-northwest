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
            // Build body with URLSearchParams.append so PHP receives repeated keys
            // (e.g. alert_types[]=x&alert_types[]=y). Passing arrays into URLSearchParams
            // via a plain object coerces them to comma-separated strings, which breaks
            // $_POST['alert_types'] and corrupts saved preferences.
            var usp = new URLSearchParams();
            formData.forEach(function (value, key) {
                usp.append(key, value);
            });
            usp.set('action', 'lpnw_save_preferences');
            usp.set('nonce', data.nonce);

            var btn = form.querySelector('[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            fetch(data.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: usp.toString()
            }).then(function (res) { return res.json(); }).then(function (res) {
                btn.disabled = false;
                btn.textContent = 'Save Preferences';
                if (res.success) {
                    showNotice('Preferences saved.', 'success');
                } else {
                    showNotice('Could not save preferences. Please try again.', 'error');
                }
            }).catch(function () {
                btn.disabled = false;
                btn.textContent = 'Save Preferences';
                showNotice('Could not save preferences. Please try again.', 'error');
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

    function getAjaxMessage(res) {
        if (!res || typeof res.data === 'undefined') {
            return '';
        }
        if (typeof res.data === 'string') {
            return res.data;
        }
        if (res.data && typeof res.data.message === 'string') {
            return res.data.message;
        }
        return '';
    }

    function initContactForm() {
        var form = document.getElementById('lpnw-contact-form');
        if (!form || !data.ajaxUrl) {
            return;
        }

        var feedback = form.querySelector('.lpnw-contact-form__feedback');
        var submitBtn = form.querySelector('#lpnw-contact-submit');
        var defaultLabel = submitBtn ? submitBtn.textContent : '';

        function setFeedback(text, isError) {
            if (!feedback) {
                return;
            }
            feedback.textContent = text || '';
            feedback.hidden = !text;
            feedback.classList.remove('lpnw-contact-form__feedback--error', 'lpnw-contact-form__feedback--success');
            if (text) {
                feedback.classList.add(isError ? 'lpnw-contact-form__feedback--error' : 'lpnw-contact-form__feedback--success');
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            setFeedback('');

            var formData = new FormData(form);
            var params = {};
            formData.forEach(function (value, key) {
                params[key] = value;
            });
            params.action = 'lpnw_contact_form';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending…';
            }

            fetch(data.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params).toString()
            })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = defaultLabel;
                    }
                    var msg = getAjaxMessage(res);
                    if (res.success) {
                        setFeedback(msg || 'Thank you. We have received your message.', false);
                        showNotice(msg || 'Message sent.', 'success');
                        form.reset();
                    } else {
                        var err = msg || 'Something went wrong. Please try again.';
                        setFeedback(err, true);
                        showNotice(err, 'error');
                    }
                })
                .catch(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = defaultLabel;
                    }
                    var err = 'Could not reach the server. Please try again.';
                    setFeedback(err, true);
                    showNotice(err, 'error');
                });
        });
    }

    function initHeroPhotos() {
        var root = document.querySelector('.lpnw-hero__photos[data-lpnw-hero-photos]');
        if (!root) {
            return;
        }
        var slides = root.querySelectorAll('.lpnw-hero__photo');
        if (slides.length < 2) {
            return;
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        var i = 0;
        var period = 7000;
        window.setInterval(function () {
            slides[i].classList.remove('is-active');
            i = (i + 1) % slides.length;
            slides[i].classList.add('is-active');
        }, period);
    }

    function initPropertySearchFilters() {
        var root = document.querySelector('[data-lpnw-property-search]');
        if (!root) {
            return;
        }
        var shell = root.querySelector('.lpnw-property-search__filters-shell');
        if (!shell || typeof window.matchMedia !== 'function') {
            return;
        }
        var mq = window.matchMedia('(max-width: 639px)');
        function apply() {
            if (mq.matches) {
                shell.removeAttribute('open');
            } else {
                shell.setAttribute('open', '');
            }
        }
        apply();
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', apply);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(apply);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPreferencesForm();
        initContactForm();
        initSaveButtons();
        initUnsaveButtons();
        initPropertySearchFilters();
        initHeroPhotos();
    });
})();
