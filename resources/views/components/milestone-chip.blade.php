@props(['code', 'phase' => null])

@php
    $color = match(true) {
        str_contains($code, 'Rejected')    => 'text-red-500',
        str_contains($code, 'Final')       => 'text-purple-700',
        str_contains($code, 'Pre-defense') => 'text-orange-500',
        str_contains($code, 'Review')      => 'text-blue-500',
        str_contains($code, 'Proposal')    => 'text-teal-600',
        default                            => 'text-purple-500',
    };
    $iconColor = match(true) {
        str_contains($code, 'Rejected')    => 'text-red-400',
        str_contains($code, 'Final')       => 'text-purple-500',
        str_contains($code, 'Pre-defense') => 'text-orange-400',
        str_contains($code, 'Review')      => 'text-blue-400',
        str_contains($code, 'Proposal')    => 'text-teal-500',
        default                            => 'text-purple-400',
    };
@endphp

<div class="flex items-center gap-1.5">
    <x-icon name="o-flag" class="h-3.5 w-3.5 {{ $iconColor }} shrink-0" />
    <span class="text-[11px] font-semibold {{ $color }}">{{ $code }}</span>
    @if($phase)
        <span class="text-[11px] text-gray-400">|</span>
        <span class="text-[11px] italic text-gray-500">{{ $phase }}</span>
    @endif
</div>
