@props(['type' => 'neutral'])
@php
    $variant = ['info' => 'info', 'success' => 'active', 'warning' => 'warning', 'danger' => 'danger', 'neutral' => 'muted'][$type] ?? 'muted';
@endphp
<span {{ $attributes->class(['badge', 'badge-'.$variant]) }}>{{ $slot }}</span>
