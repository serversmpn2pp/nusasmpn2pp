@php
    $simpan = $perilakuSiswa['tersimpan'];
    $halamanPerilaku = app(\App\Services\Nilai\LampiranPerilakuStsService::class)->halaman($perilakuSiswa['baris'], $simpan->catatan);
@endphp
@foreach($halamanPerilaku as $daftar)
    <article class="sheet sheet--behavior" data-behavior-sheet data-behavior-member="{{ $item['anggota']->id }}">
        @if($pratinjau)<div class="draft">DRAF PRATINJAU · Lampiran perilaku telah diperiksa</div>@endif
        <header class="report-header">
            <div class="report-logo report-logo--city"><img src="{{ asset('images/logo-padang-panjang.png') }}" alt="Logo Kota Padang Panjang"></div>
            <div class="report-title"><h1>SMP NEGERI 2 PADANG PANJANG</h1><h2>LAPORAN PERILAKU DAN PEMBINAAN SISWA</h2><h2 class="semester">LAMPIRAN RAPOR SUMATIF TENGAH SEMESTER {{ $kegiatan->semester === 'ganjil' ? 'I' : 'II' }}</h2><p>TAHUN PELAJARAN {{ $kegiatan->tahunPelajaran->nama }}</p></div>
            <div class="report-logo"><img src="{{ asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo SMP Negeri 2 Padang Panjang"></div>
        </header>
        <div class="header-rule" aria-hidden="true"></div>
        <section class="identity" aria-label="Identitas siswa"><span class="identity-label">Nama siswa</span><span class="identity-value">: {{ $item['anggota']->siswa->nama_lengkap }}</span><span class="identity-label identity-class-label">Kelas</span><span class="identity-value">: {{ $kelas->nama }}</span></section>
        <p class="behavior-period">Periode laporan: {{ $pengaturan->tanggal_awal_presensi->format('d-m-Y') }} s.d. {{ $pengaturan->tanggal_akhir_presensi->format('d-m-Y') }} · Lampiran {{ $loop->iteration }}/{{ $halamanPerilaku->count() }}</p>
        <table class="grades behavior-print">
            <colgroup><col style="width:5%"><col style="width:13%"><col style="width:28%"><col style="width:28%"><col style="width:7%"><col style="width:19%"></colgroup>
            <thead><tr><th>No.</th><th>Tanggal</th><th>Kejadian / pelanggaran</th><th>Teguran / tindak lanjut</th><th>Poin</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($daftar as $r)
                    <tr data-behavior-key="{{ $r['kunci'] }}"><td class="number">{{ $perilakuSiswa['baris']->search(fn ($b) => $b['kunci'] === $r['kunci']) + 1 }}</td><td>{{ \Carbon\Carbon::parse($r['tanggal'])->format('d-m-Y') }}</td><td>{{ $r['kejadian'] }}</td><td>{{ $r['tindakan'] }}</td><td class="score">{{ $r['poin'] ?: '—' }}</td><td class="status">{{ $r['status'] }}</td></tr>
                @empty<tr><td colspan="6">{{ $perilakuSiswa['baris']->isEmpty() ? 'Tidak ada catatan pelanggaran terverifikasi pada periode ini.' : 'Lanjutan ringkasan dan catatan pembinaan.' }}</td></tr>@endforelse
            </tbody>
        </table>
        @if($loop->last)
            <section class="behavior-totals" aria-label="Ringkasan poin">
                <div><span>Jumlah kejadian</span><strong>{{ $simpan->ringkasan['jumlah_kejadian'] }}</strong></div><div><span>Poin masuk periode</span><strong>{{ $simpan->ringkasan['poin_masuk'] }}</strong></div><div><span>Pengurangan / koreksi</span><strong>{{ $simpan->ringkasan['poin_dikurangi'] }}</strong></div><div><span>Saldo sampai batas laporan</span><strong>{{ $simpan->ringkasan['saldo'] }}</strong></div>
            </section>
            @if($simpan->catatan)<section class="behavior-remarks"><strong>Catatan pembinaan:</strong><br>{{ $simpan->catatan }}</section>@endif
        @endif
        <p class="behavior-date">Padang Panjang, {{ $pengaturan->tanggal_rapor->locale('id')->translatedFormat('d F Y') }}</p>
        <section class="signatures signatures--behavior" aria-label="Tanda tangan laporan perilaku">
            <div class="signature-block"><p>Orang Tua / Wali Siswa</p><div class="signature-space"></div><p class="signature-parent">(.....................................)</p></div>
            <div class="signature-block"><p>Guru BK Tingkat {{ $kelas->tingkat }}</p><div class="signature-space"></div><p><span class="signature-name">{{ $simpan->guruBk->nama_lengkap }}</span></p><p>NIP. {{ $simpan->guruBk->nip ?: '-' }}</p></div>
            <div class="signature-block"><p>Wakil Kepala Sekolah<br>Bidang Kesiswaan</p><div class="signature-space"></div><p><span class="signature-name">{{ $simpan->wakilKesiswaan->nama_lengkap }}</span></p><p>NIP. {{ $simpan->wakilKesiswaan->nip ?: '-' }}</p></div>
        </section>
        <footer class="notes"><p>Saldo mencakup transaksi tahun pelajaran sampai batas laporan. Sanksi lanjutan tidak menambah poin untuk kedua kalinya.</p><p>Laporan ini bersifat pribadi untuk siswa dan orang tua/wali.</p></footer>
    </article>
@endforeach
