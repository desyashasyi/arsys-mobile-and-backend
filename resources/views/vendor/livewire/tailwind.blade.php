@if ($paginator->hasPages())
@php
    $current  = $paginator->currentPage();
    $last     = $paginator->lastPage();
    $total    = $paginator->total();
    // Build window: always show 1, last, current-1, current, current+1
    $show = collect([1, $last, $current - 1, $current, $current + 1])
        ->filter(fn($p) => $p >= 1 && $p <= $last)
        ->unique()->sort()->values();
@endphp

<nav class="flex items-center justify-center gap-1 py-2 flex-wrap">

    {{-- Prev --}}
    @if ($paginator->onFirstPage())
        <span class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-400 text-xs font-semibold select-none">&lsaquo;</span>
    @else
        <button wire:click="previousPage" wire:loading.attr="disabled"
                class="px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-gray-700 text-xs font-semibold shadow-sm hover:bg-gray-50 active:scale-95 transition-all">&lsaquo;</button>
    @endif

    {{-- Page numbers with ellipsis --}}
    @php $prev = null; @endphp
    @foreach ($show as $page)
        @if ($prev !== null && $page - $prev > 1)
            <span class="px-1 text-xs text-gray-400">…</span>
        @endif

        @if ($page === $current)
            <span class="px-2.5 py-1 rounded-lg bg-purple-600 text-white text-xs font-bold">{{ $page }}</span>
        @else
            <button wire:click="gotoPage({{ $page }})" wire:loading.attr="disabled"
                    class="px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-gray-700 text-xs font-semibold shadow-sm hover:bg-gray-50 active:scale-95 transition-all">{{ $page }}</button>
        @endif

        @php $prev = $page; @endphp
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <button wire:click="nextPage" wire:loading.attr="disabled"
                class="px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-gray-700 text-xs font-semibold shadow-sm hover:bg-gray-50 active:scale-95 transition-all">&rsaquo;</button>
    @else
        <span class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-400 text-xs font-semibold select-none">&rsaquo;</span>
    @endif

</nav>
@endif
