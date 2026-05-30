/* LouVentory — app.js
 * Handles: confirm modals, barcode clipboard copy, barcode scanner detection
 */

(function () {
    'use strict';

    // ── Confirm modal ────────────────────────────────────────────────────────
    var modal   = document.getElementById('confirm-modal');
    var mTitle  = document.getElementById('modal-title');
    var mBody   = document.getElementById('modal-body');
    var mCancel = document.getElementById('modal-cancel');
    var mOk     = document.getElementById('modal-confirm');

    var pendingForm = null;

    if (modal && mCancel && mOk) {
        mCancel.addEventListener('click', function () { modal.style.display = 'none'; pendingForm = null; });

        mOk.addEventListener('click', function () {
            modal.style.display = 'none';
            if (pendingForm) { pendingForm.submit(); pendingForm = null; }
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) { modal.style.display = 'none'; pendingForm = null; }
        });
    }

    // Wire up data-confirm buttons
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;

        var msg  = btn.getAttribute('data-confirm') || 'Are you sure?';
        var form = btn.closest('form');
        if (!form || !modal) return;

        e.preventDefault();
        e.stopPropagation();

        if (mBody)  mBody.textContent  = msg;
        if (mTitle) mTitle.textContent = 'Confirm action';

        pendingForm = form;
        modal.style.display = 'flex';
    });

    // ── Clipboard copy for barcode text ──────────────────────────────────────
    document.addEventListener('click', function (e) {
        var el = e.target.closest('.barcode-text[data-copy]');
        if (!el) return;

        var text = el.getAttribute('data-copy');
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopied(el);
            }).catch(function () { fallbackCopy(text, el); });
        } else {
            fallbackCopy(text, el);
        }
    });

    function fallbackCopy(text, el) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showCopied(el); } catch (e) {}
        document.body.removeChild(ta);
    }

    function showCopied(el) {
        var orig = el.textContent;
        el.textContent = 'Copied!';
        el.classList.add('copied');
        setTimeout(function () {
            el.textContent = orig;
            el.classList.remove('copied');
        }, 1500);
    }

    // ── Barcode scanner detection ─────────────────────────────────────────────
    // Barcode scanners type characters rapidly (< 50ms apart) then fire Enter.
    // We attach this to any input with [data-barcode-scan] or #barcode-search.
    var scanTargets = document.querySelectorAll('[data-barcode-scan], #barcode-search, #item-search-input');

    scanTargets.forEach(function (input) {
        var buffer   = [];
        var lastTime = 0;
        var THRESHOLD = 50; // ms between chars to count as scanner
        var MIN_LEN   = 4;  // minimum chars to auto-submit

        input.addEventListener('keydown', function (e) {
            var now = Date.now();

            if (e.key === 'Enter') {
                if (buffer.length >= MIN_LEN) {
                    // Scanner completed — submit the form
                    var form = input.form;
                    if (form) { e.preventDefault(); form.submit(); }
                }
                buffer = [];
                return;
            }

            if (e.key.length === 1) { // printable char
                if (now - lastTime < THRESHOLD) {
                    buffer.push(e.key);
                } else {
                    buffer = [e.key];
                }
                lastTime = now;
            }
        });
    });

    // ── Auto-dismiss alerts after 6s ─────────────────────────────────────────
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity    = '0';
            setTimeout(function () { el.style.display = 'none'; }, 500);
        }, 6000);
    });

})();
