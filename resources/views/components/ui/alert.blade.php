@props(['type' => 'info', 'title' => null])
@php
    $type = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
    $icon = ['info' => 'info', 'success' => 'circle-check', 'warning' => 'triangle-alert', 'danger' => 'circle-alert'][$type];
@endphp
<div {{ $attributes->class(['alert', 'alert-'.$type, 'ui-alert'])->merge(['role' => $type === 'danger' ? 'alert' : 'status']) }}>
    @auth
        <i data-lucide="{{ $icon }}" aria-hidden="true"></i>
    @endauth
    <div class="ui-alert-content">
        @if ($title)<strong class="ui-alert-title">{{ $title }}</strong>@endif
        {{ $slot }}
    </div>
</div>
