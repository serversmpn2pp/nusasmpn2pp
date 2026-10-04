<dl class="agenda-facts">
    <div><dt>Media luar</dt><dd>{{ $dataKliping['nama_media'] }}</dd></div><div><dt>Jenis media</dt><dd>{{ \App\Models\KlipingBeritaHumas::JENIS[$dataKliping['jenis']] }}</dd></div>
    <div><dt>Tanggal terbit / tayang</dt><dd>{{ $dataKliping['tanggal_terbit'] }}</dd></div><div><dt>Topik</dt><dd>{{ \App\Models\KlipingBeritaHumas::TOPIK[$dataKliping['topik']] }}</dd></div>
    <div><dt>Penulis / wartawan</dt><dd>{{ $dataKliping['penulis'] ?: '-' }}</dd></div><div><dt>Edisi, halaman, atau jam tayang</dt><dd>{{ $dataKliping['rujukan'] ?: '-' }}</dd></div>
    <div><dt>Status</dt><dd>{{ \App\Models\KlipingBeritaHumas::STATUS[$dataKliping['status']] }}</dd></div>
    @if ($dataKliping['tautan'])<div><dt>Tautan sumber berita</dt><dd><a class="kliping-url" href="{{ $dataKliping['tautan'] }}" target="_blank" rel="noopener noreferrer">{{ $dataKliping['tautan'] }}</a></dd></div>@endif
</dl>
@if ($dataKliping['ringkasan'])<h3>Ringkasan pemberitaan</h3><div class="agenda-text">{{ $dataKliping['ringkasan'] }}</div>@endif
@if ($dataKliping['catatan'])<h3>Catatan arsip</h3><div class="agenda-text">{{ $dataKliping['catatan'] }}</div>@endif
