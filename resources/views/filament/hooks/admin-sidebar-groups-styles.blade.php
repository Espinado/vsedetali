{{-- Стили навигации: яркие кнопки раскрытия групп (аккордеон). !important — перебить утилиты Filament для icon-button. --}}
<style>
    .fi-sidebar-group .fi-sidebar-group-collapse-button {
        background-color: rgb(219 234 254) !important;
        border: 1px solid rgb(59 130 246) !important;
        color: rgb(29 78 216) !important;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.06);
    }

    .fi-sidebar-group .fi-sidebar-group-collapse-button:hover {
        background-color: rgb(191 219 254) !important;
        border-color: rgb(37 99 235) !important;
        color: rgb(30 64 175) !important;
    }

    .fi-sidebar-group .fi-sidebar-group-collapse-button:focus-visible {
        outline: 2px solid rgb(59 130 246);
        outline-offset: 2px;
    }

    .dark .fi-sidebar-group .fi-sidebar-group-collapse-button {
        background-color: rgb(30 58 138 / 0.5) !important;
        border-color: rgb(96 165 250) !important;
        color: rgb(191 219 254) !important;
    }

    .dark .fi-sidebar-group .fi-sidebar-group-collapse-button:hover {
        background-color: rgb(30 64 175 / 0.6) !important;
        border-color: rgb(147 197 253) !important;
        color: rgb(239 246 255) !important;
    }

    /* Группа «Поддержка»: есть диалоги без ответа оператора */
    .fi-sidebar-group--support-awaiting-reply > .fi-sidebar-group-button {
        background-color: rgb(254 243 199) !important;
        border: 1px solid rgb(245 158 11) !important;
        border-radius: 0.375rem;
        box-shadow: 0 0 0 1px rgb(251 191 36 / 0.4);
    }

    .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-label {
        color: rgb(146 64 14) !important;
        font-weight: 600;
    }

    .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-label::after {
        content: '!';
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-inline-start: 0.35rem;
        min-width: 1.25rem;
        height: 1.25rem;
        padding: 0 0.2rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 800;
        line-height: 1;
        color: #fff;
        background: rgb(220 38 38);
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.15);
    }

    .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-collapse-button {
        background-color: rgb(254 215 170) !important;
        border-color: rgb(234 88 12) !important;
        color: rgb(154 52 18) !important;
    }

    .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-collapse-button:hover {
        background-color: rgb(253 186 116) !important;
        border-color: rgb(194 65 12) !important;
        color: rgb(124 45 18) !important;
    }

    .dark .fi-sidebar-group--support-awaiting-reply > .fi-sidebar-group-button {
        background-color: rgb(120 53 15 / 0.45) !important;
        border-color: rgb(245 158 11) !important;
        box-shadow: 0 0 0 1px rgb(251 191 36 / 0.25);
    }

    .dark .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-label {
        color: rgb(254 243 199) !important;
    }

    .dark .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-collapse-button {
        background-color: rgb(154 52 18 / 0.6) !important;
        border-color: rgb(251 146 60) !important;
        color: rgb(255 237 213) !important;
    }

    .dark .fi-sidebar-group--support-awaiting-reply .fi-sidebar-group-collapse-button:hover {
        background-color: rgb(194 65 12 / 0.75) !important;
        border-color: rgb(253 186 116) !important;
        color: #fff !important;
    }
</style>
