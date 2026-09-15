<template id="preview-soal-{{ $item->id }}">
    <label><input type="radio" data-soal-kind checked value="{{ $item->jenis_soal }}"><strong>{{ $item->labelJenis() }}</strong></label>
    <textarea name="pertanyaan">{{ $item->pertanyaan }}</textarea>
    <textarea name="stimulus">{{ $item->stimulus }}</textarea>
    @include('soal-cbt.partials.preview-media-source', ['key' => 'utama', 'media' => $item->media])
    @include('soal-cbt.partials.preview-media-source', ['key' => 'stimulus', 'media' => data_get($item->media, 'konten.stimulus', [])])
    @foreach (data_get($item->opsi, 'pilihan', []) as $code => $text)
        <div class="soal-option-row">
            <textarea name="opsi[{{ $code }}]">{{ is_array($text) ? ($text['teks'] ?? '') : $text }}</textarea>
            @include('soal-cbt.partials.preview-media-source', ['key' => 'pilihan_'.$code, 'media' => data_get($item->media, 'konten.pilihan_'.$code, [])])
        </div>
    @endforeach
    @foreach (data_get($item->opsi, 'pernyataan', []) as $row)
        <div class="soal-option-row">
            <textarea name="pernyataan[]">{{ $row['teks'] ?? '' }}</textarea>
            @include('soal-cbt.partials.preview-media-source', ['key' => $row['media_key'] ?? '', 'media' => data_get($item->media, 'konten.'.($row['media_key'] ?? ''), [])])
        </div>
    @endforeach
    @foreach (data_get($item->opsi, 'pasangan', []) as $row)
        <div class="soal-option-row" data-matching-pair>
            <textarea name="pasangan_kiri[]">{{ $row['kiri'] ?? '' }}</textarea>
            @include('soal-cbt.partials.preview-media-source', ['key' => $row['media_kiri_key'] ?? '', 'media' => data_get($item->media, 'konten.'.($row['media_kiri_key'] ?? ''), [])])
            <textarea name="pasangan_kanan[]">{{ $row['kanan'] ?? '' }}</textarea>
            @include('soal-cbt.partials.preview-media-source', ['key' => $row['media_kanan_key'] ?? '', 'media' => data_get($item->media, 'konten.'.($row['media_kanan_key'] ?? ''), [])])
        </div>
    @endforeach
    @foreach (data_get($item->opsi, 'pengecoh', []) as $text)
        @php $key = collect(data_get($item->opsi, 'pengecoh_media', []))->firstWhere('teks', $text)['media_key'] ?? ''; @endphp
        <div class="soal-option-row">
            <textarea name="pengecoh_menjodohkan[]">{{ $text }}</textarea>
            @include('soal-cbt.partials.preview-media-source', ['key' => $key, 'media' => data_get($item->media, 'konten.'.$key, [])])
        </div>
    @endforeach
</template>
