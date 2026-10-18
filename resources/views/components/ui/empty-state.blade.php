@props(['title', 'description' => null, 'icon' => 'inbox'])
<div {{ $attributes->class(['empty-state', 'ui-empty-state']) }}>
    @auth
        <span class="ui-empty-icon"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span>
    @endauth
    <p class="ui-empty-title">{{ $title }}</p>
    @if ($description)<p class="ui-empty-description">{{ $description }}</p>@endif
    @isset ($actions)<div class="ui-empty-actions">{{ $actions }}</div>@endisset
    {{ $slot }}
</div>
