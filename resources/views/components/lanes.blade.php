{{-- طناب‌های مسیر (خطوط نقطه‌چین). رنگ از currentColor می‌آید: class="text-white/25" --}}
<svg {{ $attributes->merge(['class' => 'pointer-events-none']) }} viewBox="0 0 1200 600" preserveAspectRatio="none" fill="none" aria-hidden="true">
    @foreach ([50, 140, 230, 320, 410, 500, 590] as $y)
        <line x1="0" y1="{{ $y }}" x2="1200" y2="{{ $y }}" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="0.1 20" vector-effect="non-scaling-stroke"/>
    @endforeach
</svg>
