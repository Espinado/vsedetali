@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-2">

        @if ($paginator->onFirstPage())
            <span class="inline-flex cursor-not-allowed items-center rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium leading-5 text-stone-400">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98]">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98]">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="inline-flex cursor-not-allowed items-center rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium leading-5 text-stone-400">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
