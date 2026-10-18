@props(['href', 'icon', 'label', 'context' => '', 'variant' => 'muted'])
@php
    $tampilanIkon = auth()->check();
    $variant = in_array($variant, ['muted', 'dark', 'primary'], true) ? $variant : 'muted';
@endphp
<a href="{{ $href }}" {{ $attributes->class(['button', 'button-'.$variant, 'button-sm' => $tampilanIkon, 'button-icon' => $tampilanIkon, 'school-row-action' => $tampilanIkon]) }} @if ($tampilanIkon) aria-label="{{ trim($label.' '.$context) }}" data-admin-tooltip="{{ $label }}" @endif>
    @if ($tampilanIkon)<i data-lucide="{{ $icon }}" aria-hidden="true"></i>@else{{ $label }}@endif
</a>
