@props(['label', 'value', 'tone' => 'cyan', 'icon' => 'box', 'trend' => null, 'sub' => null])
@php
    $tones = [
        'cyan' => ['text-cyan-300', 'bg-cyan-400/10', 'from-cyan-400/50'],
        'gold' => ['text-gold', 'bg-gold/10', 'from-gold/50'],
        'red' => ['text-red-400', 'bg-red-500/10', 'from-red-500/60'],
        'green' => ['text-emerald-300', 'bg-emerald-400/10', 'from-emerald-400/50'],
    ];
    [$text, $bg, $line] = $tones[$tone] ?? $tones['cyan'];
@endphp
<div {{ $attributes->merge(['class' => 'card overflow-hidden !p-3.5']) }}>
    <span class="absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r {{ $line }} to-transparent"></span>
    <div class="flex items-start justify-between gap-2">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $bg }} {{ $text }}"><x-icon :name="$icon" class="h-4 w-4" /></span>
    </div>
    <p data-val class="mt-1.5 text-[28px] font-extrabold leading-none tracking-tight text-slate-900">{{ is_numeric($value) ? number_format($value) : $value }}</p>
    <p class="mt-1.5 flex items-center gap-1 text-[11px]">
        @if ($trend !== null)
            <span class="inline-flex items-center gap-0.5 font-semibold {{ $trend > 0 ? ($tone === 'green' ? 'text-emerald-400' : 'text-gold') : ($trend < 0 ? 'text-cyan-300' : 'text-slate-500') }}">
                <x-icon :name="$trend >= 0 ? 'up' : 'down'" class="h-3 w-3" /> {{ abs($trend) }}%
            </span><span class="text-slate-500">vs prior 7 days</span>
        @else
            <span class="text-slate-500">{{ $sub ?? 'live count' }}</span>
        @endif
    </p>
</div>
