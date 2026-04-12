/**
 * PWA: постоянная подсказка «из меню браузера» на каждом заходе + баннер при beforeinstallprompt.
 * «Не сейчас» / «Скрыть» только на текущей странице. Установлено — localStorage pwa-installed.
 */
(function () {
    'use strict';

    var STORAGE_INSTALLED = 'pwa-installed';

    function isIos() {
        return /iPhone|iPad|iPod/.test(navigator.userAgent || '');
    }

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

    function hidePersistentHintEl() {
        var el = document.getElementById('pwa-persistent-install-hint');
        if (el) {
            el.hidden = true;
        }
    }

    function showPersistentHintEl() {
        if (wasRecordedInstalled() || isIos()) {
            return;
        }
        var el = document.getElementById('pwa-persistent-install-hint');
        if (el) {
            el.hidden = false;
        }
    }

    function hideAllInstallUi() {
        hideBannerEl();
        hideIosHintEl();
        hidePersistentHintEl();
    }

    function markInstalled() {
        try {
            localStorage.setItem(STORAGE_INSTALLED, '1');
        } catch (e) {}
        hideAllInstallUi();
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

    try {
        sessionStorage.removeItem('pwa-install-dismissed');
    } catch (e) {}

    if (isStandalone() || wasRecordedInstalled()) {
        hideAllInstallUi();
        return;
    }

    if ('serviceWorker' in navigator) {
        var swUrl = document.body ? document.body.getAttribute('data-pwa-sw') : null;
        if (!swUrl) {
            swUrl = '/sw.js';
        }
        navigator.serviceWorker.register(swUrl, { scope: '/' }).catch(function () {});
    }

    var deferredPrompt = null;

    function showBanner() {
        if (wasRecordedInstalled()) {
            return;
        }
        hidePersistentHintEl();
        var el = document.getElementById('pwa-install-banner');
        if (!el) {
            return;
        }
        el.hidden = false;
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredPrompt = e;
            showBanner();
        });
    }

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
                        if (!wasRecordedInstalled() && !isIos()) {
                            showPersistentHintEl();
                        }
                    });
                } else {
                    hideBannerEl();
                    if (!isIos()) {
                        showPersistentHintEl();
                    }
                }
                return;
            }
            var dismissBtn = t.closest('#pwa-install-dismiss');
            if (dismissBtn) {
                ev.preventDefault();
                ev.stopPropagation();
                hideBannerEl();
                if (!isIos()) {
                    showPersistentHintEl();
                }
                return;
            }
            var persistentDismiss = t.closest('#pwa-persistent-dismiss');
            if (persistentDismiss) {
                ev.preventDefault();
                ev.stopPropagation();
                hidePersistentHintEl();
            }
        },
        true,
    );

    if (!isIos()) {
        showPersistentHintEl();
    } else {
        var iosHint = document.getElementById('pwa-ios-hint');
        if (iosHint && !wasRecordedInstalled()) {
            iosHint.hidden = false;
        }
    }
})();
