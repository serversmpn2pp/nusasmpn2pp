@extends('layouts.app')

@section('title', 'Kandidat Penghargaan STS - NUSA')

@push('styles')
    <style>
        .award-actions { display:flex; flex-wrap:wrap; gap:8px; }
        .award-filter { display:grid; grid-template-columns:minmax(220px,1.7fr) repeat(5,minmax(140px,1fr)) auto; gap:12px; align-items:end; margin-bottom:18px; padding:18px; }
        .award-filter .field { margin:0; }
        .award-note { margin-bottom:18px; padding:12px 15px; border-left:3px solid var(--accent); background:#fffbea; color:#66510a; font-size:.78rem; line-height:1.5; }
        .award-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
        .award-summary .stat { min-height:102px; }
        .award-summary .stat-value { font-size:1.4rem; }
        .award-summary .stat-note { margin:6px 0 0; color:var(--muted); font-size:.72rem; line-height:1.35; }
        .award-summary .is-primary { border-top:3px solid var(--primary); }
        .award-summary .is-accent { border-top:3px solid var(--accent); }
        .award-panel { margin-bottom:18px; padding:0; overflow:hidden; }
        .award-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:18px; border-bottom:1px solid var(--line); }
        .award-head h2 { margin:0; font-size:1.05rem; }
        .award-head p { margin:5px 0 0; color:var(--muted); font-size:.78rem; }
        .award-count { padding:5px 8px; border-radius:4px; background:#eaf1f7; color:var(--primary-dark); font-size:.72rem; font-weight:800; }
        .award-table-wrap { overflow:auto; }
        .award-table { width:100%; min-width:820px; border-collapse:collapse; }
        .award-table th,.award-table td { padding:11px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; }
        .award-table th { background:#f1f5f8; color:var(--muted); font-size:.69rem; text-transform:uppercase; }
        .award-table tbody tr:last-child td { border-bottom:0; }
        .award-table .numeric { text-align:right; font-variant-numeric:tabular-nums; }
        .award-table .center { text-align:center; }
        .award-rank { display:inline-grid; width:32px; height:32px; place-items:center; border:1px solid #9fb4c8; border-radius:50%; color:var(--primary-dark); font-weight:850; }
        .award-rank.is-top { border-color:#d8ad19; background:#fff7d2; color:#5d4900; }
        .award-student strong,.award-student span { display:block; }
        .award-student span { margin-top:3px; color:var(--muted); font-size:.7rem; }
        .award-label { display:inline-flex; padding:4px 7px; border-radius:4px; background:#e9f5ee; color:#17683b; font-size:.68rem; font-weight:800; }
        .award-mapel-name { font-weight:750; }
        .award-winners { max-width:420px; line-height:1.4; }
        .award-empty { padding:28px; color:var(--muted); text-align:center; }
        .award-foot { padding:11px 18px; border-top:1px solid var(--line); background:#fafbfc; color:var(--muted); font-size:.72rem; }
        @media (max-width:1280px) {
            .award-filter { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .award-summary { grid-template-columns:repeat(3,minmax(0,1fr)); }
        }
        @media (max-width:760px) {
            .award-filter,.award-summary { grid-template-columns:1fr 1fr; }
            .award-filter .button { width:100%; }
            .award-actions { width:100%; }
            .award-actions .button { flex:1 1 180px; }
        }
        @media (max-width:520px) {
            .award-filter,.award-summary { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Kandidat Penghargaan STS</h1>
            <p class="help-text" style="margin-top:7px;">Temukan peringkat keseluruhan dan siswa terbaik setiap mata pelajaran.</p>
        </div>
        <div class="award-actions">
            <a class="button button-muted" href="{{ route('leger-sts.index', ['mode' => $cakupan, 'kegiatan_id' => $kegiatan?->id, 'kelas_id' => $cakupan === 'kelas' ? $kelas?->id : null, 'tingkat' => $cakupan === 'tingkat' ? $tingkat : null]) }}">Kembali ke Leger</a>
        </div>
    </div>

    <form class="panel award-filter" method="GET" action="{{ route('leger-sts.penghargaan') }}">
        <div class="field">
            <label for="award-kegiatan">Kegiatan STS</label>
            <select class="select" id="award-kegiatan" name="kegiatan_id" required>
                @forelse ($daftarKegiatan as $pilihan)
                    <option value="{{ $pilihan->id }}" @selected($kegiatan?->id === $pilihan->id)>{{ $pilihan->nama }} · {{ $pilihan->tahunPelajaran->nama }}</option>
                @empty
                    <option value="">Belum ada kegiatan STS</option>
                @endforelse
            </select>
        </div>
        <div class="field">
            <label for="award-cakupan">Cakupan</label>
            <select class="select" id="award-cakupan" name="cakupan">
                <option value="kelas" @selected($cakupan === 'kelas')>Per kelas</option>
                <option value="tingkat" @selected($cakupan === 'tingkat')>Per tingkat</option>
            </select>
        </div>
        <div class="field">
            @if ($cakupan === 'tingkat')
                <label for="award-tingkat">Tingkat</label>
                <select class="select" id="award-tingkat" name="tingkat" required @disabled($daftarTingkat->isEmpty())>
                    @forelse ($daftarTingkat as $pilihan)
                        <option value="{{ $pilihan }}" @selected((int) $tingkat === (int) $pilihan)>Tingkat {{ $pilihan }}</option>
                    @empty
                        <option value="">Belum ada tingkat</option>
                    @endforelse
                </select>
            @else
                <label for="award-kelas">Kelas</label>
                <select class="select" id="award-kelas" name="kelas_id" required @disabled($daftarKelas->isEmpty())>
                    @forelse ($daftarKelas as $pilihan)
                        <option value="{{ $pilihan->id }}" @selected($kelas?->id === $pilihan->id)>{{ $pilihan->nama }}</option>
                    @empty
                        <option value="">Belum ada kelas</option>
                    @endforelse
                </select>
            @endif
        </div>
        <div class="field">
            <label for="award-kategori">Kategori</label>
            <select class="select" id="award-kategori" name="kategori">
                <option value="keseluruhan" @selected($kategori === 'keseluruhan')>Nilai keseluruhan</option>
                <option value="mapel" @selected($kategori === 'mapel')>Mata pelajaran</option>
            </select>
        </div>
        <div class="field">
            <label for="award-mapel">Mata pelajaran</label>
            <select class="select" id="award-mapel" name="mapel_id" @disabled($kategori !== 'mapel' || ! $leger || $leger['mapel']->isEmpty())>
                @if ($kategori !== 'mapel')
                    <option value="">Tidak digunakan</option>
                @else
                    @forelse ($leger['mapel'] as $pilihan)
                        <option value="{{ $pilihan->id }}" @selected((int) $mapelId === (int) $pilihan->id)>{{ $pilihan->nama }}</option>
                    @empty
                        <option value="">Belum ada mata pelajaran</option>
                    @endforelse
                @endif
            </select>
        </div>
        <div class="field">
            <label for="award-batas">Peringkat</label>
            <select class="select" id="award-batas" name="batas">
                <option value="1" @selected($batas === 1)>Juara 1</option>
                <option value="3" @selected($batas === 3)>3 Besar</option>
                <option value="10" @selected($batas === 10)>10 Besar</option>
            </select>
        </div>
        <button class="button button-primary" type="submit" @disabled(! $kegiatan || ! $leger)>Terapkan</button>
    </form>

    <div class="award-note">
        <strong>Aturan pemilihan:</strong> ranking keseluruhan mensyaratkan semua nilai STS final. Ranking mata pelajaran cukup memakai nilai final pada mapel yang dipilih. Siswa dengan nilai sama memperoleh ranking sama dan seluruhnya tetap menjadi kandidat.
    </div>

    @if ($penghargaan && $leger)
        @php
            $ringkasanPenghargaan = $penghargaan['ringkasan'];
            $cakupanLabel = $cakupan === 'kelas' ? $kelas->nama : 'Tingkat '.$tingkat;
            $kategoriLabel = $kategori === 'mapel' ? $penghargaan['mapel_terpilih']?->nama : 'Nilai keseluruhan';
        @endphp
        @if ($leger['ranking_sementara'])
            <p class="help-text" style="margin-bottom:18px;">Leger masih memuat ranking sementara. Kandidat di sini hanya menggunakan nilai final; ranking keseluruhan dihitung ulang dari siswa yang seluruh nilainya lengkap dan final, bukan dari ranking sementara.</p>
        @endif
        <section class="award-summary" aria-label="Ringkasan kandidat penghargaan">
            <div class="panel stat"><p class="stat-label">Cakupan</p><p class="stat-value" style="font-size:1rem;">{{ $cakupanLabel }}</p><p class="stat-note">{{ $kegiatan->nama }}</p></div>
            <div class="panel stat"><p class="stat-label">Kategori</p><p class="stat-value" style="font-size:1rem;">{{ $kategoriLabel }}</p><p class="stat-note">Peringkat 1 sampai {{ $batas }}</p></div>
            <div class="panel stat is-primary"><p class="stat-label">Jumlah kandidat</p><p class="stat-value">{{ $ringkasanPenghargaan['jumlah_kandidat'] }}</p><p class="stat-note">Dapat melebihi batas jika nilainya sama</p></div>
            <div class="panel stat"><p class="stat-label">Nilai tertinggi</p><p class="stat-value">{{ $ringkasanPenghargaan['nilai_tertinggi'] === null ? '-' : number_format($ringkasanPenghargaan['nilai_tertinggi'], 2, ',', '.') }}</p><p class="stat-note">Batas kandidat {{ $ringkasanPenghargaan['nilai_batas'] === null ? '-' : number_format($ringkasanPenghargaan['nilai_batas'], 2, ',', '.') }}</p></div>
            <div class="panel stat is-accent"><p class="stat-label">Data tersedia</p><p class="stat-value">{{ $ringkasanPenghargaan['jumlah_tersedia'] }}</p><p class="stat-note">{{ $ringkasanPenghargaan['jumlah_tanpa_data'] }} siswa belum memiliki data lengkap</p></div>
        </section>

        <section class="panel award-panel">
            <div class="award-head">
                <div><h2>{{ $batas === 1 ? 'Juara 1' : $batas.' Besar' }} · {{ $kategoriLabel }}</h2><p>{{ $cakupanLabel }} · Urutan berdasarkan nilai final tertinggi.</p></div>
                <span class="award-count">{{ $penghargaan['kandidat']->count() }} kandidat</span>
            </div>
            @if ($penghargaan['kandidat']->isNotEmpty())
                <div class="award-table-wrap">
                    <table class="award-table">
                        <thead><tr><th class="center">Rank</th><th>Siswa</th><th>Kelas</th><th class="numeric">Nilai</th><th class="center">Data final</th><th>Rekomendasi</th></tr></thead>
                        <tbody>
                            @foreach ($penghargaan['kandidat'] as $item)
                                <tr>
                                    <td class="center"><span class="award-rank {{ $item['ranking'] <= 3 ? 'is-top' : '' }}">{{ $item['ranking'] }}</span></td>
                                    <td><div class="award-student"><strong>{{ $item['anggota']->siswa->nama_lengkap }}</strong><span>NIS {{ $item['anggota']->siswa->nis ?: '-' }} · NISN {{ $item['anggota']->siswa->nisn ?: '-' }}</span></div></td>
                                    <td><strong>{{ $item['kelas']?->nama ?? '-' }}</strong></td>
                                    <td class="numeric"><strong>{{ number_format($item['nilai'], 2, ',', '.') }}</strong></td>
                                    <td class="center">{{ $kategori === 'mapel' ? 'Nilai mapel final' : $item['jumlah_nilai_final'].'/'.$item['jumlah_mapel'].' mapel' }}</td>
                                    <td><span class="award-label">{{ $item['label_penghargaan'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="award-foot">Daftar ini membantu penyaringan kandidat. Penetapan penerima penghargaan tetap menjadi keputusan sekolah.</div>
            @else
                <div class="award-empty">Belum ada siswa dengan nilai final yang memenuhi kategori ini.</div>
            @endif
        </section>

        <section class="panel award-panel">
            <div class="award-head">
                <div><h2>Siswa terbaik setiap mata pelajaran</h2><p>Peringkat pertama dapat terdiri dari beberapa siswa apabila nilainya sama.</p></div>
                <span class="award-count">{{ $penghargaan['ringkasan_mapel']->count() }} mapel</span>
            </div>
            <div class="award-table-wrap">
                <table class="award-table">
                    <thead><tr><th>Mata pelajaran</th><th class="numeric">Nilai tertinggi</th><th>Siswa peringkat 1</th><th class="center">Nilai final tersedia</th><th class="center">Rincian</th></tr></thead>
                    <tbody>
                        @forelse ($penghargaan['ringkasan_mapel'] as $statistik)
                            <tr>
                                <td class="award-mapel-name">{{ $statistik['mapel']->nama }}</td>
                                <td class="numeric"><strong>{{ $statistik['nilai_tertinggi'] === null ? '-' : number_format($statistik['nilai_tertinggi'], 2, ',', '.') }}</strong></td>
                                <td class="award-winners">{{ $statistik['juara']->isEmpty() ? 'Belum ada nilai final' : $statistik['juara']->pluck('anggota.siswa.nama_lengkap')->join(', ') }}</td>
                                <td class="center">{{ $statistik['jumlah_nilai_final'] }}/{{ $leger['baris']->count() }}</td>
                                <td class="center"><a class="button button-muted" href="{{ route('leger-sts.penghargaan', ['cakupan' => $cakupan, 'kegiatan_id' => $kegiatan->id, 'kelas_id' => $cakupan === 'kelas' ? $kelas?->id : null, 'tingkat' => $cakupan === 'tingkat' ? $tingkat : null, 'kategori' => 'mapel', 'mapel_id' => $statistik['mapel']->id, 'batas' => 10]) }}">Lihat 10 besar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="award-empty">Belum ada mata pelajaran pada cakupan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="panel award-empty">Belum ada kegiatan STS atau kelas dalam kewenangan Anda.</div>
    @endif
@endsection
