@props([
    'id',
    'name',
    'label',
    'autocomplete' => 'current-password',
    'containerClass' => '',
    'autofocus' => false,
])

@php
    $memilikiKesalahan = $errors->has($name);
    $idPetunjuk = $id.'_caps_warning'.($memilikiKesalahan ? ' '.$id.'_error' : '');
@endphp

<div class="field {{ $containerClass }}">
    <label for="{{ $id }}">{{ $label }}</label>
    <div class="password-control" data-password-control>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            autocomplete="{{ $autocomplete }}"
            aria-describedby="{{ $idPetunjuk }}"
            {{ $attributes->class(['input', 'is-invalid' => $memilikiKesalahan]) }}
            @if ($autofocus) autofocus @endif
        >
        <button
            type="button"
            class="password-toggle"
            data-password-toggle
            aria-controls="{{ $id }}"
            aria-label="Tampilkan kata sandi"
            aria-pressed="false"
            title="Tampilkan kata sandi"
        >
            <svg class="password-toggle-icon" data-password-icon-show viewBox="0 0 24 24" aria-hidden="true">
                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                <circle cx="12" cy="12" r="3" />
            </svg>
            <svg class="password-toggle-icon" data-password-icon-hide viewBox="0 0 24 24" aria-hidden="true" hidden>
                <path d="m2 2 20 20" />
                <path d="M6.71 6.71C4.6 8.06 3.15 9.92 2.06 11.65a1 1 0 0 0 0 .7C4.13 15.64 7.57 18 12 18c1.17 0 2.26-.16 3.27-.46" />
                <path d="M10.73 5.08C11.14 5.03 11.56 5 12 5c4.43 0 7.87 2.36 9.94 5.65a1 1 0 0 1 0 .7 13.1 13.1 0 0 1-1.67 2.08" />
                <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88" />
            </svg>
        </button>
    </div>
    <p id="{{ $id }}_caps_warning" class="password-caps-warning" data-caps-lock-warning hidden>Caps Lock aktif.</p>
    @if ($memilikiKesalahan)
        <p id="{{ $id }}_error" class="error-text">{{ $errors->first($name) }}</p>
    @endif
</div>
