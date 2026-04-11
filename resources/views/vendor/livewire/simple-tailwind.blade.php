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
        <nav role="navigation" aria-label="Pagination Navigation" class="flex justify-between gap-2">
            <span>
                @if ($paginator->onFirstPage())
                    <span class="relative inline-flex cursor-default items-center rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium leading-5 text-stone-400">
                        {!! __('pagination.previous') !!}
                    </span>
                @else
                    @if(method_exists($paginator,'getCursorName'))
                        <button type="button" dusk="previousPage" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()->encode() }}" wire:click="setPage('{{$paginator->previousCursor()->encode()}}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="relative inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98] disabled:opacity-50">
                                {!! __('pagination.previous') !!}
                        </button>
                    @else
                        <button
                            type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="relative inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98] disabled:opacity-50">
                                {!! __('pagination.previous') !!}
                        </button>
                    @endif
                @endif
            </span>

            <span>
                @if ($paginator->hasMorePages())
                    @if(method_exists($paginator,'getCursorName'))
                        <button type="button" dusk="nextPage" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()->encode() }}" wire:click="setPage('{{$paginator->nextCursor()->encode()}}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="relative inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98] disabled:opacity-50">
                                {!! __('pagination.next') !!}
                        </button>
                    @else
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="relative inline-flex items-center rounded-lg border border-orange-200 bg-white px-4 py-2 text-sm font-medium leading-5 text-stone-800 shadow-sm transition hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1 active:scale-[0.98] disabled:opacity-50">
                                {!! __('pagination.next') !!}
                        </button>
                    @endif
                @else
                    <span class="relative inline-flex cursor-default items-center rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium leading-5 text-stone-400">
                        {!! __('pagination.next') !!}
                    </span>
                @endif
            </span>
        </nav>
    @endif
</div>
