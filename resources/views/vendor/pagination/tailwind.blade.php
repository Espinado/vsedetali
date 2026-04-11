@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">

        <div class="flex items-center justify-between gap-2 sm:hidden">

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

        </div>

        <div class="hidden gap-2 sm:flex sm:flex-1 sm:items-center sm:justify-between">

            <div>
                <p class="text-sm leading-5 text-stone-600">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-semibold text-stone-900">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-semibold text-stone-900">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-semibold text-stone-900">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="inline-flex isolate -space-x-px overflow-hidden rounded-lg shadow-sm rtl:flex-row-reverse">

                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="inline-flex cursor-not-allowed items-center rounded-l-lg border border-stone-200 bg-stone-50 px-2 py-2 text-sm font-medium leading-5 text-stone-400 rtl:rounded-l-none rtl:rounded-r-lg" aria-hidden="true">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-l-lg border border-stone-200 bg-white px-2 py-2 text-sm font-medium leading-5 text-stone-600 transition hover:z-[1] hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:z-[1] focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-0 rtl:rounded-l-none rtl:rounded-r-lg" aria-label="{{ __('pagination.previous') }}">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex min-h-[2.5rem] min-w-[2.5rem] cursor-default items-center justify-center border border-stone-200 bg-white px-3 py-2 text-sm font-medium leading-5 text-stone-500">{{ $element }}</span>
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative z-10 inline-flex min-h-[2.5rem] min-w-[2.75rem] items-center justify-center border border-orange-600 bg-orange-600 px-4 py-2 text-sm font-bold leading-5 text-white shadow-md shadow-orange-600/40 ring-2 ring-orange-400/90">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex min-h-[2.5rem] min-w-[2.75rem] items-center justify-center border border-stone-200 bg-white px-4 py-2 text-sm font-semibold leading-5 text-stone-700 transition hover:z-[5] hover:border-orange-500 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300 focus:z-[5] focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-0 active:scale-[0.98]" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-r-lg border border-stone-200 bg-white px-2 py-2 text-sm font-medium leading-5 text-stone-600 transition hover:z-[1] hover:border-orange-400 hover:bg-orange-100 hover:text-orange-950 hover:shadow-md hover:ring-2 hover:ring-orange-300/80 focus:z-[1] focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-0 rtl:rounded-r-none rtl:rounded-l-lg" aria-label="{{ __('pagination.next') }}">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="inline-flex cursor-not-allowed items-center rounded-r-lg border border-stone-200 bg-stone-50 px-2 py-2 text-sm font-medium leading-5 text-stone-400 rtl:rounded-r-none rtl:rounded-l-lg" aria-hidden="true">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
