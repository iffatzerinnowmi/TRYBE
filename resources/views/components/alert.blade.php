{{--
    Flash / validation message box. Section 4 uses this on login and signup.

    <x-alert type="error">Those details did not match our records.</x-alert>
    <x-alert type="success">{{ session('status') }}</x-alert>

    type: info (default) | success | error
--}}

@props(['type' => 'info'])

@php
    $styles = [
        'info'    => 'border-line-hi bg-surface-soft text-ink',
        'success' => 'border-ok/30 bg-ok/10 text-ok',
        'error'   => 'border-danger/30 bg-danger/10 text-danger',
    ];

    $icons = [
        'info' => 'ℹ',
        'success' => '✓',
        'error' => '!',
    ];
@endphp

<div {{ $attributes->merge([
        'class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-[13px] '
                   . ($styles[$type] ?? $styles['info']),
    ]) }}>
    <span class="mt-px font-mono font-semibold">{{ $icons[$type] ?? $icons['info'] }}</span>
    <div>{{ $slot }}</div>
</div>
