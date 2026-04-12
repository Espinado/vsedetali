/**
 * PWA: SW, баннер при beforeinstallprompt, скрытие после установки (в т.ч. из меню браузера).
 */
(function () {
    'use strict';

    var STORAGE_INSTALLED = 'pwa-installed';
    var STORAGE_DISMISSED = 'pwa-install-dismissed';

    function hideBannerEl() {
        var el = document.getElementById('pwa-install-banner');
        if (el) {
            el.hidden = true;
        }
    }

    function hideIosHintEl() {
        var el = document.getElementById('pwa-ios-hint');
        if (el) {
            el.hidden = true;
        }
    }

    function hideAllInstallUi() {
        hideBannerEl();
        hideIosHintEl();
    }

    function markInstalled() {
        try {
            localStorage.setItem(STORAGE_INSTALLED, '1');
        } catch (e) {}
        hideAllInstallUi();
    }

    function dismissed() {
        try {
            return sessionStorage.getItem(STORAGE_DISMISSED) === '1';
        } catch (e) {
            return false;
        }
    }

    function wasRecordedInstalled() {
        try {
            return localStorage.getItem(STORAGE_INSTALLED) === '1';
        } catch (e) {
            return false;
        }
    }

    function isStandalone() {
        if (window.matchMedia('(display-mode: standalone)').matches) {
            return true;
        }
        if (window.matchMedia('(display-mode: window-controls-overlay)').matches) {
            return true;
        }
        if (typeof navigator.standalone === 'boolean' && navigator.standalone) {
            return true;
        }
        return false;
    }

    if (isStandalone() || wasRecordedInstalled()) {
        hideAllInstallUi();
    }

    if ('serviceWorker' in navigator) {
        var swUrl = document.body ? document.body.getAttribute('data-pwa-sw') : null;
        if (!swUrl) {
            swUrl = '/sw.js';
        }
        navigator.serviceWorker.register(swUrl, { scope: '/' }).catch(function () {});
    }

    if (isStandalone() || wasRecordedInstalled()) {
        return;
    }

    if (!('serviceWorker' in navigator)) {
        return;
    }

    var deferredPrompt = null;

    function showBanner() {
        if (dismissed() || wasRecordedInstalled()) {
            return;
        }
        var el = document.getElementById('pwa-install-banner');
        if (!el) {
            return;
        }
        el.hidden = false;
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        showBanner();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        markInstalled();
    });

    try {
        var dm = window.matchMedia('(display-mode: standalone)');
        dm.addEventListener('change', function () {
            if (dm.matches) {
                markInstalled();
            }
        });
    } catch (e) {}

    document.addEventListener(
        'click',
        function (ev) {
            var t = ev.target;
            if (!(t instanceof Element)) {
                return;
            }
            var installBtn = t.closest('#pwa-install-btn');
            if (installBtn) {
                ev.preventDefault();
                ev.stopPropagation();
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.finally(function () {
                        deferredPrompt = null;
                        hideBannerEl();
                    });
                } else {
                    hideBannerEl();
                    try {
                        sessionStorage.setItem(STORAGE_DISMISSED, '1');
                    } catch (err) {}
                }
                return;
            }
            var dismissBtn = t.closest('#pwa-install-dismiss');
            if (dismissBtn) {
                ev.preventDefault();
                ev.stopPropagation();
                hideAllInstallUi();
                try {
                    sessionStorage.setItem(STORAGE_DISMISSED, '1');
                } catch (err) {}
            }
        },
        true,
    );

    if (!dismissed() && !wasRecordedInstalled()) {
        var iosHint = document.getElementById('pwa-ios-hint');
        if (iosHint && /iPhone|iPad|iPod/.test(navigator.userAgent || '')) {
            iosHint.hidden = false;
        }
    }
})();
