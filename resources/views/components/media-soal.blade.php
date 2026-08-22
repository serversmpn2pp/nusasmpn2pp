@props(['media' => []])

@php
    $gambar = data_get($media, 'gambar');
    $gambarUrl = filled(data_get($gambar, 'path'))
        ? \Illuminate\Support\Facades\Storage::disk('public')->url(data_get($gambar, 'path'))
        : null;
    $barisTabel = data_get($media, 'tabel.baris', []);
    $rumus = data_get($media, 'rumus');
@endphp

@if ($gambarUrl || $barisTabel !== [] || filled(data_get($rumus, 'latex')))
    <div class="question-media-content">
        @if ($gambarUrl)
            <figure class="question-media-figure">
                <img src="{{ $gambarUrl }}" alt="{{ data_get($gambar, 'alt', 'Gambar pendukung soal') }}">
                @if (filled(data_get($gambar, 'keterangan')))
                    <figcaption>{{ data_get($gambar, 'keterangan') }}</figcaption>
                @endif
            </figure>
        @endif

        @if ($barisTabel !== [])
            <figure class="question-media-table-wrap">
                @if (filled(data_get($media, 'tabel.judul')))
                    <figcaption>{{ data_get($media, 'tabel.judul') }}</figcaption>
                @endif
                <div class="question-media-table-scroll">
                    <table class="question-media-table">
                        <thead>
                            <tr>
                                @foreach (($barisTabel[0] ?? []) as $sel)
                                    <th>{{ $sel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($barisTabel, 1) as $baris)
                                <tr>
                                    @foreach ($baris as $sel)
                                        <td>{{ $sel }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </figure>
        @endif

        @if (filled(data_get($rumus, 'latex')))
            <figure class="question-media-formula">
                <div data-rumus-latex="{{ data_get($rumus, 'latex') }}">{{ data_get($rumus, 'latex') }}</div>
                @if (filled(data_get($rumus, 'keterangan')))
                    <figcaption>{{ data_get($rumus, 'keterangan') }}</figcaption>
                @endif
            </figure>
        @endif
    </div>
@endif

@once
    @push('styles')
        <style>
            .question-media-content { display:grid; gap:16px; margin:16px 0; }
            .question-media-content figure { margin:0; }
            .question-media-figure { text-align:center; }
            .question-media-figure img { display:block; width:auto; max-width:100%; max-height:430px; margin:0 auto; border:1px solid #dfe7f0; border-radius:7px; object-fit:contain; }
            .question-media-content figcaption { margin-top:7px; color:#71717a; font-size:.78rem; text-align:center; }
            .question-media-table-wrap > figcaption { margin:0 0 7px; color:#18181b; font-size:.84rem; font-weight:800; text-align:left; }
            .question-media-table-scroll { overflow-x:auto; }
            .question-media-table { width:100%; min-width:420px; border-collapse:collapse; }
            .question-media-table th,.question-media-table td { border:1px solid #dfe7f0; padding:9px 10px; text-align:left; vertical-align:top; }
            .question-media-table th { background:#e8f0f8; color:#0f355c; font-weight:800; }
            .question-media-formula { overflow-x:auto; border:1px solid #dfe7f0; border-radius:7px; background:#fff; padding:14px; text-align:center; }
            .question-media-formula [data-rumus-latex] { min-width:max-content; font-size:1.08rem; }
            .question-media-formula .is-invalid-formula { color:#b91c1c; font-family:inherit; font-size:.82rem; }
            @media(max-width:620px){.question-media-figure img{max-height:320px}.question-media-table th,.question-media-table td{padding:8px}.question-media-formula{padding:11px}}
        </style>
    @endpush

    @push('scripts')
        @vite('resources/js/soal-editor.js')
    @endpush
@endonce
