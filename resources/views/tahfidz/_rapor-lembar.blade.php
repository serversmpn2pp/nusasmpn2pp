@foreach (collect($dokumen['baris'])->chunk(26) as $halaman)
    <article class="tf-print-sheet tf-report" aria-label="Rapor {{ $dokumen['nama'] }}">
        @include('tahfidz._kop')
        <h1>Rapor Tahfidz</h1>
        <p class="tf-print-subtitle">Juz 30 &middot; Tahun Pelajaran {{ $dokumen['tahun'] }}</p>
        <dl class="tf-print-identitas">
            <div><dt>Nama siswa</dt><dd>{{ $dokumen['nama'] }}</dd></div>
            <div><dt>Kelas</dt><dd>{{ $dokumen['kelas'] }}</dd></div>
            <div><dt>Kelompok</dt><dd>{{ $dokumen['kelompok'] }}</dd></div>
            <div><dt>NIS / NISN</dt><dd>{{ $dokumen['nis'] ?: '-' }} / {{ $dokumen['nisn'] ?: '-' }}</dd></div>
        </dl>
        <table class="tf-print-grades">
            <colgroup><col class="tf-col-no"><col><col class="tf-col-score"><col class="tf-col-status"></colgroup>
            <thead><tr><th scope="col">No.</th><th scope="col">Surah</th><th scope="col">Nilai</th><th scope="col">Keterangan</th></tr></thead>
            <tbody>
                @foreach ($halaman as $nomor => $item)
                    <tr><td>{{ $nomor + 1 }}</td><td>{{ $item['surah'] ?? '-' }}</td><td>{{ $item['nilai'] ?? '-' }}</td><td>{{ $item['nilai'] !== null ? 'Sudah disetor' : 'Belum disetor' }}</td></tr>
                @endforeach
                @if ($loop->last)
                    <tr class="tf-print-total"><th colspan="2" scope="row">Rata-rata</th><td>{{ $dokumen['rata'] ?? '-' }}</td><td>{{ $dokumen['predikat'] ?? 'Belum ada setoran' }}</td></tr>
                @endif
            </tbody>
        </table>
        @if ($loop->last)
            <p class="tf-print-note"><strong>{{ $dokumen['sudah'] }}/{{ $dokumen['target'] }} surah target disetor.</strong> Rata-rata = jumlah nilai {{ $dokumen['jumlah'] }} dibagi {{ $dokumen['target'] }}. {{ $dokumen['mode'] === 'bebas' ? 'Mode bebas memakai nilai tertinggi sejumlah target dari nilai terbaru tiap surah.' : 'Nilai menggunakan setoran terbaru untuk setiap surah yang ditetapkan.' }}</p>
            <div class="tf-print-signatures">
                <div><p>Orang tua / wali</p><div class="tf-sign-space"></div><strong>....................................</strong></div>
                <div><p>Padang Panjang, {{ \Illuminate\Support\Carbon::parse($dokumen['tanggal'])->locale('id')->translatedFormat('j F Y') }}</p><p>Guru pembimbing</p><div class="tf-sign-space"></div><strong>{{ $dokumen['pembimbing'] }}</strong></div>
            </div>
        @endif
        <p class="tf-print-page-number">{{ $loop->iteration }} / {{ $loop->count }}</p>
    </article>
@endforeach
