import { createEcho } from './echo-setup';

function playChatNotificationSound() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) {
            return;
        }
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.value = 0.07;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.12);
    } catch (_) {
        /* ignore */
    }
}

let lastUnreadCustomerCount = null;

function syncUnreadCountFromSidebarDom() {
    const link = document.querySelector('a[href*="chat-conversations"]');
    if (!link) {
        return;
    }
    const item = link.closest('li') ?? link.closest('[class*="sidebar-item"]') ?? link.parentElement;
    const badge = item?.querySelector('.fi-badge') ?? link.querySelector('.fi-badge');
    if (badge) {
        const n = parseInt(String(badge.textContent).trim(), 10);
        if (!Number.isNaN(n)) {
            lastUnreadCustomerCount = n;
        }
    }
}

function updateChatNavBadge(count) {
    lastUnreadCustomerCount = count;

    const link = document.querySelector('a[href*="chat-conversations"]');
    if (!link) {
        return;
    }

    const item = link.closest('li') ?? link.closest('[class*="sidebar-item"]') ?? link.parentElement;
    if (!item) {
        return;
    }

    let existing = item.querySelector('.fi-badge');
    if (!existing && link.parentElement) {
        existing = link.parentElement.querySelector('.fi-badge');
    }
    if (!existing) {
        existing = link.querySelector('.fi-badge');
    }

    if (count === 0) {
        if (existing) {
            existing.remove();
        }

        return;
    }

    if (existing) {
        existing.textContent = String(count);

        return;
    }

    const span = document.createElement('span');
    span.className =
        'fi-badge flex items-center justify-center gap-x-1 rounded-md bg-danger-500/10 px-0.5 min-w-[theme(spacing.6)] text-xs font-medium text-danger-600 dark:text-danger-400';
    span.textContent = String(count);
    if (link.parentElement && link.parentElement !== item) {
        link.parentElement.appendChild(span);
    } else {
        item.appendChild(span);
    }
}

function bootAdminChatEcho() {
    if (window.__adminChatEchoBooted) {
        return;
    }

    const echo = createEcho();
    if (!echo) {
        return;
    }

    window.__adminChatEchoBooted = true;
    if (!window.Echo) {
        window.Echo = echo;
    }

    const channel = echo.private('admin.chat');

    channel.listen('.message.created', (payload) => {
        if (payload?.message?.sender === 'customer') {
            playChatNotificationSound();
        }
        const n = payload?.unread_customer_messages;
        if (typeof n === 'number') {
            updateChatNavBadge(n);
        }
        if (window.Livewire?.dispatch) {
            window.Livewire.dispatch('filament-chat-list-refresh');
        }
    });

    channel.listen('.customer-messages.read', (payload) => {
        const n = payload?.unread_customer_messages;
        if (typeof n === 'number') {
            updateChatNavBadge(n);
        }
        if (window.Livewire?.dispatch) {
            window.Livewire.dispatch('filament-chat-list-refresh');
        }
    });
}

document.addEventListener('livewire:init', bootAdminChatEcho);
document.addEventListener('DOMContentLoaded', () => {
    bootAdminChatEcho();
    syncUnreadCountFromSidebarDom();
});

document.addEventListener('livewire:navigated', () => {
    syncUnreadCountFromSidebarDom();
    if (typeof lastUnreadCustomerCount === 'number') {
        updateChatNavBadge(lastUnreadCustomerCount);
    }
});
