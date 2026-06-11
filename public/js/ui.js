/* ============================================================================
   ALTEN · Gestion des Stagiaires — UI behaviours
   Vanilla-JS interaction layer (Bootstrap-bundle replacement) PLUS a
   first-class notification system:

     • window.toast(message, type, opts)   — popup notifications
     • window.confirmDialog(opts) → Promise — async confirmation modal
     • [data-confirm] on forms/links        — declarative confirmation
     • Component behaviours: dropdown, collapse, tabs, accordion, modal, alert

   Driven by the existing data-bs-* attributes already in the templates, so
   no markup needs to change for the component behaviours.
   ============================================================================ */
(function () {
    'use strict';

    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
    var closest = function (el, sel) { return el && el.closest ? el.closest(sel) : null; };
    var el = function (tag, cls, html) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (html != null) n.innerHTML = html;
        return n;
    };
    var target = function (trigger) {
        var sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('data-target');
        if (!sel && trigger.getAttribute('href')) sel = trigger.getAttribute('href');
        if (!sel || sel === '#') return null;
        try { return document.querySelector(sel); } catch (e) { return null; }
    };

    /* =========================================================================
       Toast notifications
       ===================================================================== */
    var TOAST_ICONS = {
        success: 'bi-check-circle-fill',
        error:   'bi-x-octagon-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill',
    };
    var toastHost = null;
    function host() {
        if (!toastHost) {
            toastHost = el('div', 'toast-host');
            toastHost.setAttribute('role', 'status');
            toastHost.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastHost);
        }
        return toastHost;
    }
    function toast(message, type, opts) {
        type = type || 'info';
        opts = opts || {};
        var duration = opts.duration != null ? opts.duration : (type === 'error' ? 8000 : 5000);
        var node = el('div', 'toast toast-' + type);
        node.setAttribute('role', 'alert');
        node.innerHTML =
            '<span class="toast-icon"><i class="bi ' + (TOAST_ICONS[type] || TOAST_ICONS.info) + '"></i></span>' +
            '<div class="toast-content">' +
                (opts.title ? '<div class="toast-title">' + opts.title + '</div>' : '') +
                '<div class="toast-msg"></div>' +
            '</div>' +
            '<button class="toast-close" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>';
        node.querySelector('.toast-msg').textContent = message;
        host().appendChild(node);
        requestAnimationFrame(function () { node.classList.add('show'); });

        var timer;
        var dismiss = function () {
            if (node.dataset.closing) return;
            node.dataset.closing = '1';
            clearTimeout(timer);
            node.classList.remove('show');
            node.classList.add('hide');
            setTimeout(function () { node.remove(); }, 260);
        };
        node.querySelector('.toast-close').addEventListener('click', dismiss);
        if (duration > 0) timer = setTimeout(dismiss, duration);
        node.addEventListener('mouseenter', function () { clearTimeout(timer); });
        node.addEventListener('mouseleave', function () { if (duration > 0) timer = setTimeout(dismiss, 2000); });
        return { dismiss: dismiss };
    }
    window.toast = toast;

    // Queue API: pages push flash messages before ui.js loads.
    window.__toasts = window.__toasts || [];
    var flush = function () {
        (window.__toasts || []).forEach(function (t) { toast(t.message, t.type, t.opts); });
        window.__toasts = [];
        window.__toasts.push = function (t) { toast(t.message, t.type, t.opts); return 0; };
    };

    /* =========================================================================
       Modal helpers (open/close + dynamic)
       ===================================================================== */
    function openModal(modal) {
        if (!modal) return;
        var backdrop = el('div', 'modal-backdrop');
        backdrop.dataset.for = modal.id || '';
        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');
        modal.classList.add('show');
        modal.removeAttribute('aria-hidden');
        backdrop.addEventListener('click', function () { closeModal(modal); });
        var auto = modal.querySelector('[autofocus], input, textarea, select, button.btn');
        if (auto) setTimeout(function () { auto.focus(); }, 60);
    }
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        $$('.modal-backdrop').forEach(function (b) {
            if (!b.dataset.for || b.dataset.for === (modal.id || '')) b.remove();
        });
        if (!document.querySelector('.modal.show')) document.body.classList.remove('modal-open');
        if (modal.dataset.dynamic) setTimeout(function () { modal.remove(); }, 50);
    }

    /* =========================================================================
       Confirmation dialog (programmatic + declarative)
       ===================================================================== */
    function confirmDialog(opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var modal = el('div', 'modal confirm-modal');
            modal.dataset.dynamic = '1';
            var danger = opts.variant === 'danger';
            modal.innerHTML =
                '<div class="modal-dialog" style="max-width:26rem">' +
                  '<div class="modal-content">' +
                    '<div class="modal-body">' +
                      '<div class="confirm-icon ' + (danger ? 'is-danger' : '') + '">' +
                        '<i class="bi ' + (danger ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill') + '"></i></div>' +
                      '<h3 class="confirm-title"></h3>' +
                      '<p class="confirm-text"></p>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                      '<button class="btn btn-outline-secondary" data-act="cancel"></button>' +
                      '<button class="btn ' + (danger ? 'btn-danger' : 'btn-success') + '" data-act="ok"></button>' +
                    '</div>' +
                  '</div>' +
                '</div>';
            modal.querySelector('.confirm-title').textContent = opts.title || 'Confirmer l’action';
            modal.querySelector('.confirm-text').textContent = opts.message || 'Voulez-vous vraiment continuer ?';
            modal.querySelector('[data-act="cancel"]').textContent = opts.cancelText || 'Annuler';
            modal.querySelector('[data-act="ok"]').textContent = opts.confirmText || 'Confirmer';
            document.body.appendChild(modal);
            openModal(modal);
            var done = function (val) { closeModal(modal); resolve(val); };
            modal.querySelector('[data-act="ok"]').addEventListener('click', function () { done(true); });
            modal.querySelector('[data-act="cancel"]').addEventListener('click', function () { done(false); });
            modal.addEventListener('mousedown', function (e) { if (e.target === modal) done(false); });
            modal._confirmCancel = function () { done(false); };
        });
    }
    window.confirmDialog = confirmDialog;

    /* =========================================================================
       Tabs / collapse / accordion
       ===================================================================== */
    function activateTab(trigger) {
        var pane = target(trigger);
        if (!pane) return;
        var tablist = closest(trigger, '.nav-tabs, .nav') || trigger.parentElement.parentElement;
        if (tablist) $$('.nav-link', tablist).forEach(function (l) { l.classList.remove('active'); l.setAttribute('aria-selected', 'false'); });
        trigger.classList.add('active');
        trigger.setAttribute('aria-selected', 'true');
        var content = pane.parentElement;
        if (content) $$(':scope > .tab-pane', content).forEach(function (p) { p.classList.remove('active', 'show'); });
        pane.classList.add('active', 'show');
    }
    function toggleCollapse(trigger) {
        var node = target(trigger);
        if (!node) return;
        var isOpen = node.classList.contains('show');
        var parentSel = trigger.getAttribute('data-bs-parent');
        if (!isOpen && parentSel) {
            var parent = document.querySelector(parentSel);
            if (parent) $$('.accordion-collapse.show, .collapse.show', parent).forEach(function (c) {
                if (c === node) return;
                c.classList.remove('show');
                var btn = document.querySelector('[data-bs-target="#' + c.id + '"]');
                if (btn) { btn.classList.add('collapsed'); btn.setAttribute('aria-expanded', 'false'); }
            });
        }
        node.classList.toggle('show', !isOpen);
        trigger.classList.toggle('collapsed', isOpen);
        trigger.setAttribute('aria-expanded', String(!isOpen));
    }
    function closeDropdowns(except) {
        $$('.dropdown-menu.show').forEach(function (m) {
            if (m === except) return;
            m.classList.remove('show');
            var t = m.parentElement && m.parentElement.querySelector('[data-bs-toggle="dropdown"]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    }

    /* =========================================================================
       Global click delegation
       ===================================================================== */
    document.addEventListener('click', function (e) {
        var t;

        if ((t = closest(e.target, '[data-bs-dismiss="alert"]'))) {
            var a = closest(t, '.alert'); if (a) a.remove(); return;
        }
        if ((t = closest(e.target, '[data-bs-dismiss="modal"]'))) {
            closeModal(closest(t, '.modal')); return;
        }
        if ((t = closest(e.target, '[data-bs-toggle="modal"]'))) {
            e.preventDefault(); openModal(target(t)); return;
        }
        if ((t = closest(e.target, '[data-bs-toggle="tab"]'))) {
            e.preventDefault(); activateTab(t); return;
        }
        if ((t = closest(e.target, '[data-bs-toggle="collapse"]'))) {
            e.preventDefault(); toggleCollapse(t); return;
        }
        if ((t = closest(e.target, '[data-bs-toggle="dropdown"]'))) {
            e.preventDefault();
            var menu = t.parentElement.querySelector('.dropdown-menu');
            var willOpen = menu && !menu.classList.contains('show');
            closeDropdowns(willOpen ? menu : null);
            if (menu) { menu.classList.toggle('show', willOpen); t.setAttribute('aria-expanded', String(willOpen)); }
            return;
        }
        if (!closest(e.target, '.dropdown-menu')) closeDropdowns();
    });

    /* ---- Declarative confirmation on form submit / link click ------------ */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('[data-confirm]') || form.dataset.confirmed) return;
        e.preventDefault();
        confirmDialog({
            title: form.getAttribute('data-confirm-title') || undefined,
            message: form.getAttribute('data-confirm'),
            confirmText: form.getAttribute('data-confirm-ok') || 'Confirmer',
            variant: form.getAttribute('data-confirm-variant') || 'danger',
        }).then(function (ok) {
            if (ok) { form.dataset.confirmed = '1'; form.submit(); }
        });
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDropdowns();
            var openModalEl = document.querySelector('.modal.show');
            if (openModalEl) {
                if (openModalEl._confirmCancel) openModalEl._confirmCancel();
                else closeModal(openModalEl);
            }
        }
    });

    /* =========================================================================
       Auto-search toolbars: debounced text search + auto-submitting filters
       Markup: <form data-autosearch> with [data-autosearch-input] and selects.
       ===================================================================== */
    function initAutoSearch() {
        $$('form[data-autosearch]').forEach(function (form) {
            if (form.dataset.autosearchReady) return;
            form.dataset.autosearchReady = '1';

            var submit = function () { form.requestSubmit ? form.requestSubmit() : form.submit(); };
            var input = form.querySelector('[data-autosearch-input]');
            var clearBtn = form.querySelector('[data-autosearch-clear]');
            var timer;

            if (input) {
                // Keep focus + caret after a search-triggered reload.
                if (input.value) {
                    input.focus();
                    var v = input.value; input.value = ''; input.value = v;
                }
                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    var val = input.value.trim();
                    if (clearBtn) clearBtn.classList.toggle('is-hidden', val.length === 0);
                    // Launch when cleared (show everything) or after >= 3 characters.
                    if (val.length === 0 || val.length >= 3) {
                        timer = setTimeout(submit, 600);
                    }
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); submit(); }
                });
            }

            if (clearBtn && input) {
                clearBtn.addEventListener('click', function () {
                    input.value = '';
                    clearBtn.classList.add('is-hidden');
                    submit();
                });
            }

            // Filters (selects / checkboxes) submit immediately on change.
            $$('select[data-autosubmit], input[data-autosubmit]', form).forEach(function (ctrl) {
                ctrl.addEventListener('change', submit);
            });
        });
    }
    window.initAutoSearch = initAutoSearch;

    function ready() { flush(); initAutoSearch(); }
    if (document.readyState !== 'loading') ready();
    else document.addEventListener('DOMContentLoaded', ready);
})();
