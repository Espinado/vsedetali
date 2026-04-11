@extends('layouts.storefront')

@section('title', $page->meta_title ?: $page->title)

@section('content')
    @php
        $hasContactBlock = $page->isContactsPage() && ($page->contact_email || $page->contact_phone || $page->contact_address);
        $hasBody = filled($page->body);
    @endphp

    <article>
        <h1 class="mb-4 text-xl font-bold text-slate-900 sm:mb-6 sm:text-2xl">{{ $page->title }}</h1>

        @if($hasContactBlock)
            <div class="mb-8 max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:p-6">
                @if($page->contact_email)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Email</p>
                        <a href="mailto:{{ e($page->contact_email) }}" class="text-orange-600 hover:text-orange-800 font-medium">{{ $page->contact_email }}</a>
                    </div>
                @endif
                @if($page->contact_phone)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Телефон</p>
                        <a href="tel:{{ preg_replace('/\s+/', '', $page->contact_phone) }}" class="text-slate-900 font-medium hover:text-orange-700">{{ $page->contact_phone }}</a>
                    </div>
                @endif
                @if($page->contact_address)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Адрес</p>
                        <p class="text-slate-800 whitespace-pre-line">{{ $page->contact_address }}</p>
                    </div>
                @endif
            </div>
        @endif

        @if($hasBody)
            <div class="prose prose-slate max-w-none overflow-x-auto break-words">{!! $page->body !!}</div>
        @elseif(! $hasContactBlock)
            <p class="text-slate-500">Содержимое страницы пока не заполнено.</p>
        @endif
    </article>
@endsection
