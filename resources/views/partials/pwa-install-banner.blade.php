<div id="pwa-persistent-install-hint"
     style="position:fixed;bottom:0;left:0;right:0;z-index:99996;pointer-events:auto;touch-action:manipulation;background:linear-gradient(180deg,#fff7ed 0%,#ffedd5 100%);border-top:2px solid #ea580c;border-left:4px solid #ea580c;padding:14px 14px calc(14px + env(safe-area-inset-bottom)) 16px;font-family:system-ui,sans-serif;box-shadow:0 -6px 24px rgba(234,88,12,.18);"
     hidden
     role="region"
     aria-label="Как установить приложение из меню браузера">
    <div style="max-width:52rem;margin:0 auto;display:flex;align-items:flex-start;gap:12px;justify-content:space-between;">
        <div style="flex:1;min-width:0;">
            <p style="margin:0 0 8px;font-size:clamp(16px,3.5vw,18px);font-weight:800;line-height:1.25;color:#431407;letter-spacing:-0.02em;">
                Для установки приложения
            </p>
            <p style="margin:0;font-size:clamp(14px,3vw,16px);line-height:1.5;color:#292524;font-weight:500;">
                откройте <strong style="color:#c2410c;">меню браузера</strong> (иконка <strong style="color:#c2410c;">⋮</strong> или <strong style="color:#c2410c;">⋯</strong>) и выберите пункт <strong style="color:#c2410c;">«Установить приложение»</strong> — так в Chrome, Яндекс.Браузере и Edge. На компьютере часто: меню → <strong>«Приложения»</strong> → <strong>«Установить страницу как приложение»</strong> (или похожий пункт).
            </p>
        </div>
        <button type="button"
                id="pwa-persistent-dismiss"
                style="flex-shrink:0;cursor:pointer;pointer-events:auto;touch-action:manipulation;border:2px solid #ea580c;border-radius:8px;background:#fff;color:#9a3412;font-size:14px;font-weight:600;padding:10px 14px;box-shadow:0 1px 3px rgba(0,0,0,.08);">
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
<script src="{{ url('/pwa/install-banner.js?v=6') }}" defer></script>
