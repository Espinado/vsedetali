<div id="pwa-persistent-install-hint"
     style="position:fixed;bottom:0;left:0;right:0;z-index:99996;pointer-events:auto;touch-action:manipulation;background:rgba(254,252,251,.98);border-top:1px solid #e7e5e4;padding:8px 10px calc(8px + env(safe-area-inset-bottom));font-family:system-ui,sans-serif;box-shadow:0 -2px 12px rgba(0,0,0,.06);"
     hidden
     role="region"
     aria-label="Как установить приложение из меню браузера">
    <div style="max-width:52rem;margin:0 auto;display:flex;align-items:flex-start;gap:10px;justify-content:space-between;">
        <p style="margin:0;font-size:12px;line-height:1.45;color:#444;flex:1;min-width:0;">
            Каждый заход: откройте <strong>меню браузера</strong> (⋮ или «⋯») → <strong>«Установить приложение»</strong> (Chrome, Edge, Яндекс). На ПК иногда: меню → «Приложения» → «Установить страницу как приложение».
        </p>
        <button type="button"
                id="pwa-persistent-dismiss"
                style="flex-shrink:0;cursor:pointer;pointer-events:auto;border:1px solid #d6d3d1;border-radius:6px;background:#fff;color:#57534e;font-size:12px;padding:6px 10px;">
            Скрыть
        </button>
    </div>
</div>
<div id="pwa-install-banner"
     style="position:fixed;bottom:0;left:0;right:0;z-index:99999;pointer-events:auto;touch-action:manipulation;background:rgba(255,255,255,.97);border-top:1px solid #d6d3d1;padding:12px 16px calc(12px + env(safe-area-inset-bottom));box-shadow:0 -4px 24px rgba(0,0,0,.12);font-family:system-ui,sans-serif;"
     hidden
     role="region"
     aria-label="Установка приложения">
    <div style="max-width:48rem;margin:0 auto;display:flex;flex-direction:column;gap:8px;align-items:stretch;">
        <p style="margin:0;font-size:14px;color:#292524;line-height:1.4;">
            Установите приложение — быстрый доступ с экрана телефона или ПК.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;">
            <button type="button"
                    id="pwa-install-btn"
                    style="cursor:pointer;pointer-events:auto;touch-action:manipulation;border:none;border-radius:8px;background:#ea580c;color:#fff;font-weight:600;font-size:14px;padding:10px 16px;">
                Установить
            </button>
            <button type="button"
                    id="pwa-install-dismiss"
                    style="cursor:pointer;pointer-events:auto;touch-action:manipulation;border:1px solid #d6d3d1;border-radius:8px;background:#fff;color:#444;font-size:14px;padding:10px 14px;">
                Не сейчас
            </button>
        </div>
    </div>
</div>
<p id="pwa-ios-hint"
   style="position:fixed;bottom:0;left:0;right:0;z-index:99998;margin:0 auto;max-width:48rem;padding:8px 12px calc(8px + env(safe-area-inset-bottom));text-align:center;font-size:12px;color:#57534e;font-family:system-ui,sans-serif;"
   hidden>
    На iPhone и iPad: «Поделиться» → «На экран «Домой»».
</p>
<script src="{{ url('/pwa/install-banner.js?v=5') }}" defer></script>
