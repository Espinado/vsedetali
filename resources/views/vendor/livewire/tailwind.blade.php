@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
            <div class="flex flex-1 justify-between sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <span class="relative inline-flex cursor-default items-center rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium leading-5 text-stone-400">
                            {!! __('pagination.previous') !!}
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="relative inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98] disabled:opacity-50">
                            {!! __('pagination.previous') !!}
                        </button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="relative ml-3 inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98] disabled:opacity-50">
                            {!! __('pagination.next') !!}
                        </button>
                    @else
                        <span class="relative ml-3 inline-flex cursor-default items-center rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium leading-5 text-stone-400">
                            {!! __('pagination.next') !!}
                        </span>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm leading-5 text-stone-600">
                        <span>{!! __('Showing') !!}</span>
                        <span class="font-semibold text-stone-900">{{ $paginator->firstItem() }}</span>
                        <span>{!! __('to') !!}</span>
                        <span class="font-semibold text-stone-900">{{ $paginator->lastItem() }}</span>
                        <span>{!! __('of') !!}</span>
                        <span class="font-semibold text-stone-900">{{ $paginator->total() }}</span>
                        <span>{!! __('results') !!}</span>
                    </p>
                </div>

                <div>
                    <span class="relative isolate inline-flex -space-x-px overflow-hidden rounded-lg shadow-sm rtl:flex-row-reverse">
                        <span>
                            @if ($paginator->onFirstPage())
                                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                                    <span class="relative inline-flex cursor-default items-center rounded-l-lg border border-stone-200 bg-stone-50 px-2 py-2 text-sm font-medium leading-5 text-stone-400 rtl:rounded-l-none rtl:rounded-r-lg" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @else
                                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="relative inline-flex items-center rounded-l-lg border border-stone-200 bg-white px-2 py-2 text-sm font-medium leading-5 text-stone-600 transition hover:z-[1] hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:z-[1] focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-0 rtl:rounded-l-none rtl:rounded-r-lg" aria-label="{{ __('pagination.previous') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>

                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <span aria-disabled="true">
                                    <span class="relative inline-flex min-h-[2.5rem] min-w-[2.5rem] cursor-default items-center justify-center border border-stone-200 bg-white px-3 py-2 text-sm font-medium leading-5 text-stone-500">{{ $element }}</span>
                                </span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page">
                                                <span class="relative z-10 inline-flex min-h-[2.5rem] min-w-[2.75rem] items-center justify-center border border-orange-600 bg-orange-600 px-4 py-2 text-sm font-bold leading-5 text-white shadow-md shadow-orange-600/40 ring-2 ring-orange-400/90">{{ $page }}</span>
                                            </span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="relative inline-flex min-h-[2.5rem] min-w-[2.75rem] items-center justify-center border border-stone-200 bg-white px-4 py-2 text-sm font-semibold leading-5 text-stone-700 transition hover:z-[5] hover:border-orange-500 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300 focus:z-[5] focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-0 active:scale-[0.98]" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        <span>
                            @if ($paginator->hasMorePages())
                                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="relative inline-flex items-center rounded-r-lg border border-stone-200 bg-white px-2 py-2 text-sm font-medium leading-5 text-stone-600 transition hover:z-[1] hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:z-[1] focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-0 rtl:rounded-r-none rtl:rounded-l-lg" aria-label="{{ __('pagination.next') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @else
                                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                                    <span class="relative inline-flex cursor-default items-center rounded-r-lg border border-stone-200 bg-stone-50 px-2 py-2 text-sm font-medium leading-5 text-stone-400 rtl:rounded-r-none rtl:rounded-l-lg" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
