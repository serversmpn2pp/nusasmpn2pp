@extends('layouts.app')

@section('title', 'Detail Pelaksanaan Sanksi - NUSA')

@section('content')
    @php
        $anggota = $sanksiPoinSiswa->siswa?->anggotaKelas->first();
        $guruWali = $sanksiPoinSiswa->siswa?->penugasanGuruWaliSiswa->first()?->guruWali;
        $statusFinal = $sanksiPoinSiswa->sudahFinal();
        $opsiStatus = match ($sanksiPoinSiswa->status) {
            'menunggu' => ['menunggu', 'diproses', 'dibatalkan'],
            'diproses' => ['diproses', 'selesai', 'dibatalkan'],
            default => [$sanksiPoinSiswa->status],
        };
        $badgeStatus = match ($sanksiPoinSiswa->status) {
            'selesai' => 'badge badge-active',
            'dibatalkan' => 'badge badge-inactive',
            'diproses' => 'badge badge-warning',
            default => 'badge badge-danger',
        };
        $tahap = match ($sanksiPoinSiswa->status) {'menunggu' => 1, 'diproses' => 2, default => 3};
    @endphp

    <style>
        .sanction-summary { align-items:center; background:var(--primary); color:#fff; display:grid; gap:18px; grid-template-columns:minmax(0,1fr) auto; margin-bottom:20px; }
        .sanction-summary h2 { margin:3px 0 10px; }
        .sanction-summary-points { text-align:right; }
        .sanction-summary-points strong { color:var(--secondary); display:block; font-size:38px; }
        .sanction-flow { display:grid; gap:8px; grid-template-columns:repeat(3,minmax(0,1fr)); margin-top:16px; }
        .sanction-step { background:#edf1f5; border-radius:6px; color:var(--muted); font-size:12px; font-weight:800; padding:9px; text-align:center; }
        .sanction-step.done { background:#e6f4ec; color:#21643c; }
        .sanction-step.current { background:#fff3b0; color:#665100; outline:1px solid #e2bd00; }
        .sanction-file-list,.sanction-history { display:grid; gap:10px; margin-top:15px; }
        .sanction-file { align-items:start; border-bottom:1px solid var(--line); display:grid; gap:12px; grid-template-columns:minmax(0,1fr) auto; padding:0 0 12px; }
        .sanction-file:last-child { border-bottom:0; padding-bottom:0; }
        .sanction-file p { overflow-wrap:anywhere; }
        .sanction-history { border-left:2px solid #d7e2ee; margin-left:7px; padding-left:20px; }
        .sanction-history-item { padding:0 0 17px; position:relative; }
        .sanction-history-item::before { background:var(--secondary); border:3px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #b8c9da; content:""; height:12px; left:-27px; position:absolute; top:3px; width:12px; }
        .sanction-history-item:last-child { padding-bottom:0; }
        .sanction-overdue { background:#fff0f0; border:1px solid #e6b4b4; border-radius:7px; color:#8e2424; font-weight:700; margin-top:14px; padding:11px; }
        @media(max-width:780px){.sanction-summary{grid-template-columns:1fr}.sanction-summary-points{text-align:left}.sanction-flow{grid-template-columns:1fr}.sanction-file{grid-template-columns:1fr}.sanction-file .actions{justify-content:flex-start}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Detail Pelaksanaan Sanksi</h1>
            <p class="page-subtitle">{{ $sanksiPoinSiswa->tahunPelajaran?->nama }}</p>
        </div>
        <div class="actions"><a href="{{ route('sanksi-poin-siswa.index') }}" class="button button-muted">Kembali</a><a href="{{ route('laporan-pembinaan-siswa.index', ['kata_kunci' => $sanksiPoinSiswa->siswa?->nisn ?: $sanksiPoinSiswa->siswa?->nama_lengkap]) }}" class="button button-muted">Riwayat Pelanggaran</a></div>
    </div>

    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Ada data yang perlu diperbaiki.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="panel panel-pad sanction-summary">
        <div><p class="eyebrow" style="color:#d9e8f7">Sanksi siswa</p><h2>{{ $sanksiPoinSiswa->aturanSanksiPoin?->nama }}</h2><span class="{{ $badgeStatus }}">{{ $sanksiPoinSiswa->labelStatus() }}</span>@if($sanksiPoinSiswa->terlambat())<div class="sanction-overdue">Batas pelaksanaan telah terlewati sejak {{ $sanksiPoinSiswa->batas_pelaksanaan->format('d/m/Y') }}.</div>@endif</div>
        <div class="sanction-summary-points"><span>Poin saat terpicu</span><strong>{{ $sanksiPoinSiswa->poin_saat_terpicu }}</strong><span>Ambang {{ $sanksiPoinSiswa->aturanSanksiPoin?->batas_poin }} poin</span></div>
    </section>

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile"><div class="avatar avatar-lg">{{ str($sanksiPoinSiswa->siswa?->nama_lengkap)->substr(0, 2)->upper() }}</div><h2>{{ $sanksiPoinSiswa->siswa?->nama_lengkap }}</h2><p>NISN {{ $sanksiPoinSiswa->siswa?->nisn ?: '-' }}</p></div>
            <dl class="quick-facts" style="margin-top:20px">
                <div><dt>Kelas</dt><dd>{{ $anggota?->kelas?->nama ?: '-' }}</dd></div>
                <div><dt>Wali kelas</dt><dd>{{ $anggota?->kelas?->waliKelas?->nama_lengkap ?: '-' }}</dd></div>
                <div><dt>Guru wali</dt><dd>{{ $guruWali?->nama_lengkap ?: '-' }}</dd></div>
                <div><dt>Terpicu pada</dt><dd>{{ $sanksiPoinSiswa->terpicu_pada?->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Proses Pelaksanaan</h2>
                <p class="help-text">Sanksi diproses oleh petugas yang ditugaskan dan ditutup setelah hasil pelaksanaannya dicatat.</p>
                <div class="sanction-flow"><span class="sanction-step {{ $tahap === 1 ? 'current' : 'done' }}">1. Menunggu penugasan</span><span class="sanction-step {{ $tahap === 2 ? 'current' : ($tahap > 2 ? 'done' : '') }}">2. Pelaksanaan</span><span class="sanction-step {{ $tahap === 3 ? 'done' : '' }}">3. Selesai</span></div>

                @if($bolehKelola)
                    <form method="POST" action="{{ route('sanksi-poin-siswa.update', $sanksiPoinSiswa) }}" style="margin-top:20px">
                        @csrf @method('PUT')
                        <div class="form-grid">
                            <div class="field"><label for="status">Status pelaksanaan</label><select id="status" name="status" class="select" required>@foreach($opsiStatus as $kode)<option value="{{ $kode }}" @selected(old('status', $sanksiPoinSiswa->status) === $kode)>{{ \App\Models\SanksiPoinSiswa::DAFTAR_STATUS[$kode] }}</option>@endforeach</select></div>
                            <div class="field"><label for="petugas_pegawai_id">Petugas penanggung jawab</label><select id="petugas_pegawai_id" name="petugas_pegawai_id" class="select"><option value="">Pilih petugas</option>@foreach($daftarPetugas as $petugas)<option value="{{ $petugas->id }}" @selected((string)old('petugas_pegawai_id', $sanksiPoinSiswa->petugas_pegawai_id) === (string)$petugas->id)>{{ $petugas->nama_lengkap }}{{ $petugas->nip ? ' - '.$petugas->nip : '' }}</option>@endforeach</select></div>
                            <div class="field"><label for="batas_pelaksanaan">Batas pelaksanaan</label><input id="batas_pelaksanaan" name="batas_pelaksanaan" type="date" class="input" value="{{ old('batas_pelaksanaan', $sanksiPoinSiswa->batas_pelaksanaan?->toDateString()) }}"></div>
                            <div class="field"><label for="catatan">Catatan atau alasan pembatalan</label><textarea id="catatan" name="catatan" class="textarea" placeholder="Arahan pelaksanaan atau alasan jika dibatalkan">{{ old('catatan', $sanksiPoinSiswa->catatan) }}</textarea></div>
                            <div class="field span-2"><label for="hasil_pelaksanaan">Hasil pelaksanaan</label><textarea id="hasil_pelaksanaan" name="hasil_pelaksanaan" class="textarea" placeholder="Wajib diisi sebelum status diubah menjadi selesai">{{ old('hasil_pelaksanaan', $sanksiPoinSiswa->hasil_pelaksanaan) }}</textarea></div>
                        </div>
                        <div class="actions" style="justify-content:flex-end;margin-top:14px"><button class="button button-primary">Simpan pelaksanaan</button></div>
                    </form>
                @else
                    <dl class="detail-grid" style="margin-top:18px">
                        <div class="detail-item"><dt>Petugas</dt><dd>{{ $sanksiPoinSiswa->petugasPegawai?->nama_lengkap ?: 'Belum ditugaskan' }}</dd></div>
                        <div class="detail-item"><dt>Batas pelaksanaan</dt><dd>{{ $sanksiPoinSiswa->batas_pelaksanaan?->format('d/m/Y') ?: '-' }}</dd></div>
                        <div class="detail-item"><dt>Mulai diproses</dt><dd>{{ $sanksiPoinSiswa->mulai_diproses_pada?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                        <div class="detail-item"><dt>Selesai pada</dt><dd>{{ $sanksiPoinSiswa->dilaksanakan_pada?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                        <div class="detail-item span-2"><dt>Catatan</dt><dd style="white-space:pre-line">{{ $sanksiPoinSiswa->catatan ?: '-' }}</dd></div>
                        <div class="detail-item span-2"><dt>Hasil pelaksanaan</dt><dd style="white-space:pre-line">{{ $sanksiPoinSiswa->hasil_pelaksanaan ?: '-' }}</dd></div>
                    </dl>
                @endif
            </section>

            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom:0"><div><h2 class="panel-title">Bukti Pelaksanaan</h2><p class="help-text">Foto atau PDF disimpan privat dan hanya dapat dibuka oleh pengguna yang memiliki akses.</p></div><span class="badge badge-muted">{{ $sanksiPoinSiswa->buktiPelaksanaanSanksi->count() }} file</span></div>
                <div class="sanction-file-list">
                    @forelse($sanksiPoinSiswa->buktiPelaksanaanSanksi as $bukti)
                        <article class="sanction-file"><div><p class="person-name">{{ $bukti->nama_file_asli }}</p><p class="person-meta">{{ $bukti->ukuranRingkas() }} &middot; {{ $bukti->diunggah_pada?->format('d/m/Y H:i') }} &middot; {{ $bukti->diunggahOlehPengguna?->nama ?: '-' }}</p>@if($bukti->keterangan)<p style="margin:7px 0 0">{{ $bukti->keterangan }}</p>@endif</div><div class="actions"><a href="{{ route('bukti-pelaksanaan-sanksi.download', $bukti) }}" class="button button-muted button-sm">Unduh</a>@if($bolehKelola)<form method="POST" action="{{ route('bukti-pelaksanaan-sanksi.destroy', $bukti) }}" onsubmit="return confirm('Hapus bukti pelaksanaan ini?')">@csrf @method('DELETE')<button class="button button-danger button-sm">Hapus</button></form>@endif</div></article>
                    @empty
                        <div class="empty-state">Belum ada bukti pelaksanaan.</div>
                    @endforelse
                </div>
                @if($bolehKelola)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('bukti-pelaksanaan-sanksi.store', $sanksiPoinSiswa) }}" style="border-top:1px solid var(--line);margin-top:16px;padding-top:16px">@csrf<div class="form-grid"><div class="field"><label for="bukti_sanksi">Tambah foto/PDF</label><input id="bukti_sanksi" name="bukti_sanksi[]" type="file" class="input" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple required><p class="help-text">Maksimal 5 file, masing-masing 10 MB.</p></div><div class="field"><label for="keterangan_bukti">Keterangan</label><input id="keterangan_bukti" name="keterangan_bukti" class="input" placeholder="Contoh: dokumentasi pemanggilan orang tua"></div></div><button class="button button-dark" style="margin-top:12px">Unggah bukti</button></form>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Riwayat Pelaksanaan</h2>
                <p class="help-text">Catatan ini terbentuk otomatis dan tidak dapat diedit.</p>
                <div class="sanction-history">
                    @forelse($sanksiPoinSiswa->riwayatSanksiPoinSiswa as $riwayat)
                        <article class="sanction-history-item"><p class="person-name">{{ $riwayat->judul }}</p><p class="person-meta">{{ $riwayat->terjadi_pada?->format('d/m/Y H:i') }} &middot; {{ $riwayat->dibuatOlehPengguna?->nama ?: 'Sistem NUSA' }}</p>@if($riwayat->status_sebelum !== $riwayat->status_sesudah)<p class="person-meta">{{ $riwayat->status_sebelum ? (\App\Models\SanksiPoinSiswa::DAFTAR_STATUS[$riwayat->status_sebelum] ?? $riwayat->status_sebelum) : 'Sanksi terbentuk' }} &rarr; {{ \App\Models\SanksiPoinSiswa::DAFTAR_STATUS[$riwayat->status_sesudah] ?? $riwayat->status_sesudah }}</p>@endif @if($riwayat->catatan)<p style="margin:7px 0 0;white-space:pre-line">{{ $riwayat->catatan }}</p>@endif</article>
                    @empty
                        <div class="empty-state">Belum ada riwayat pelaksanaan.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
