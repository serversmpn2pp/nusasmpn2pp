@extends('layouts.app')

@section('title', 'Detail Asesmen Kelas - NUSA')

@section('content')
    @php
        $jumlahSoal = $ujianCbt->soal_ujian_cbt_count;
        $jumlahPeserta = $ujianCbt->peserta_ujian_cbt_count;
        $siapSoal = $jumlahSoal >= $ujianCbt->jumlah_soal;
        $badge = in_array($ujianCbt->status, ['terjadwal', 'berlangsung'], true) ? 'badge-active' : ($ujianCbt->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Asesmen Kelas</p>
            <h1 class="page-title">{{ $ujianCbt->nama }}</h1>
            <p class="help-text" style="margin-top: 8px;">{{ $ujianCbt->mataPelajaran?->nama }} · Kelas {{ $ujianCbt->tingkat }} · {{ ucfirst($ujianCbt->semester) }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('asesmen-kelas-cbt.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('asesmen-kelas-cbt.edit', $ujianCbt) }}" class="button button-dark">Edit</a>
        </div>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <div class="stats-grid">
        <div class="panel stat {{ $siapSoal ? 'active' : '' }}"><p class="stat-label">Soal dipilih</p><p class="stat-value">{{ $jumlahSoal }}/{{ $ujianCbt->jumlah_soal }}</p></div>
        <div class="panel stat active"><p class="stat-label">Peserta otomatis</p><p class="stat-value">{{ $jumlahPeserta }}</p></div>
        <div class="panel stat"><p class="stat-label">Kelas</p><p class="stat-value">{{ $ujianCbt->kelasUjianCbt->count() }}</p></div>
        <div class="panel stat"><p class="stat-label">Status</p><p style="margin-top: 12px;"><span class="badge {{ $badge }}">{{ $ujianCbt->labelStatus() }}</span></p></div>
    </div>

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <h2 class="panel-title">Langkah pelaksanaan</h2>
            <ol class="flow-list" style="margin-top: 16px;">
                <li class="{{ $siapSoal ? 'done' : 'active' }}"><span>1</span><div><strong>Pilih soal</strong><p>{{ $siapSoal ? 'Jumlah soal sudah mencukupi.' : 'Pilih soal dari Bank Soal.' }}</p></div></li>
                <li class="{{ $jumlahPeserta > 0 ? 'done' : '' }}"><span>2</span><div><strong>Peserta</strong><p>Siswa kelas terpilih dimasukkan otomatis.</p></div></li>
                <li class="{{ in_array($ujianCbt->status, ['terjadwal', 'berlangsung'], true) ? 'done' : '' }}"><span>3</span><div><strong>Buka asesmen</strong><p>Gunakan status Terjadwal atau Berlangsung.</p></div></li>
            </ol>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <div class="section-heading">
                    <div><h2 class="panel-title">Soal asesmen</h2><p class="help-text">Bank soal otomatis disaring sesuai mapel dan tingkat.</p></div>
                    <a href="{{ route('ujian-cbt.soal.edit', $ujianCbt) }}" class="button button-primary">{{ $jumlahSoal ? 'Ubah pilihan soal' : 'Pilih soal' }}</a>
                </div>
                @unless ($siapSoal)
                    <div class="alert alert-warning" style="margin-top: 14px;">Target {{ $ujianCbt->jumlah_soal }} soal, tetapi baru {{ $jumlahSoal }} soal dipilih.</div>
                @endunless
            </section>

            <section class="panel panel-pad">
                <div class="section-heading">
                    <div><h2 class="panel-title">Pelaksanaan dan hasil</h2><p class="help-text">Pantau pengerjaan, koreksi jawaban, lalu masukkan hasil ke nilai siswa.</p></div>
                    <div class="actions">
                        <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-primary">Monitoring</a>
                        <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Hasil & nilai</a>
                        <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Koreksi uraian</a>
                    </div>
                </div>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi asesmen</h2>
                <dl class="detail-grid" style="margin-top: 16px;">
                    <div class="detail-item"><dt>Dibuka</dt><dd>{{ $ujianCbt->tanggal_mulai?->format('d-m-Y H:i') ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Ditutup</dt><dd>{{ $ujianCbt->tanggal_selesai?->format('d-m-Y H:i') ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Durasi</dt><dd>{{ $ujianCbt->durasi_menit }} menit</dd></div>
                    <div class="detail-item"><dt>KKM</dt><dd>{{ $ujianCbt->kkm ?? '-' }}</dd></div>
                    <div class="detail-item span-2"><dt>Petunjuk siswa</dt><dd>{{ $ujianCbt->petunjuk ?: '-' }}</dd></div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Kelas dan tujuan nilai</h2>
                <div class="table-wrap" style="margin-top: 14px;">
                    <table class="employee-table">
                        <thead><tr><th>Kelas</th><th>Komponen nilai</th><th>Peserta</th></tr></thead>
                        <tbody>
                            @foreach ($ujianCbt->kelasUjianCbt->sortBy(fn ($item) => $item->kelas?->nama) as $kelas)
                                <tr><td><strong>{{ $kelas->kelas?->nama ?: '-' }}</strong></td><td>{{ $kelas->komponenNilai?->nama ?: '-' }}</td><td>{{ $kelas->pesertaUjianCbt()->count() }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($ujianCbt->status !== 'nonaktif')
                <form action="{{ route('asesmen-kelas-cbt.destroy', $ujianCbt) }}" method="POST" onsubmit="return confirm('Nonaktifkan asesmen ini?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="button button-danger">Nonaktifkan asesmen</button>
                </form>
            @endif
        </div>
    </div>
@endsection
