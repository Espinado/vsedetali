{{-- Широкие таблицы: не раздвигать весь layout за пределы экрана; скролл остаётся внутри .fi-ta-content (Filament). --}}
<style>
    /* Главная колонка: w-screen (100vw) + сайдбар даёт горизонтальный скролл всей страницы */
    .fi-layout > .fi-main-ctn {
        width: auto !important;
        min-width: 0;
        max-width: 100%;
    }

    .fi-main {
        min-width: 0;
        max-width: 100%;
    }

    .fi-page {
        min-width: 0;
        max-width: 100%;
    }

    .fi-ta {
        min-width: 0;
        max-width: 100%;
    }

    .fi-ta-ctn {
        min-width: 0;
        max-width: 100%;
    }

    .fi-ta-content {
        -webkit-overflow-scrolling: touch;
    }
</style>
