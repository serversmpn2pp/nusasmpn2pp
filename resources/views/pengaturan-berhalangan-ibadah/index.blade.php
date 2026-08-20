@extends('layouts.app')

@section('title', 'Pengaturan Berhalangan - NUSA')

@section('content')
    <style>
        .absence-setting-grid { display:grid; grid-template-columns:minmax(260px,.72fr) minmax(0,1.28fr); gap:18px; align-items:start; }
        .absence-choice { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .absence-choice label { display:flex; align-items:flex-start; gap:10px; min-height:78px; padding:13px; border:1px solid var(--line); border-radius:7px; background:#fff; cursor:pointer; }
        .absence-choice label:has(input:checked) { border-color:var(--primary); background:#eef5fb; box-shadow:inset 0 0 0 1px var(--primary); }
        .absence-choice strong { display:block; color:var(--primary-dark); }
        .absence-choice small { display:block; margin-top:3px; color:var(--muted); line-height:1.4; }
        .class-choice-wrap { margin-top:14px; padding:14px; border:1px solid var(--line); border-radius:7px; background:#f8fafc; }
        .class-choice-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-top:12px; }
        .class-choice-group { display:grid; gap:8px; align-content:start; }
        .class-choice-group h3 { margin:0 0 2px; color:var(--primary-dark); font-size:.84rem; }
        .class-check { display:flex; align-items:center; gap:8px; min-height:36px; padding:8px 10px; border:1px solid var(--line); border-radius:6px; background:#fff; font-weight:750; cursor:pointer; }
        .companion-name { margin:0; color:var(--primary-dark); font-weight:900; }
        .companion-scope { display:flex; flex-wrap:wrap; gap:6px; margin-top:7px; }
        .privacy-note { border-left:4px solid var(--warning); background:#fff9df; padding:13px 14px; border-radius:6px; color:#624b00; line-height:1.55; }
        @media (max-width:900px) { .absence-setting-grid { grid-template-columns:1fr; } }
        @media (max-width:680px) { .absence-choice, .class-choice-grid { grid-template-columns:1fr; } .absence-choice label { min-height:0; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kegiatan Ibadah</p>
            <h1 class="page-title">Pengaturan Berhalangan</h1>
            <p class="page-subtitle">Atur batas pengingat dan guru perempuan yang mendampingi konfirmasi secara privat.</p>
        </div>
        <a href="{{ route('kegiatan-ibadah.index') }}" class="button button-muted">Kegiatan ibadah</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Data belum dapat disimpan.</strong>
            <ul style="margin:7px 0 0;padding-left:19px;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @unless ($tahunPelajaran)
        <section class="panel panel-pad">
            <h2 class="panel-title">Tahun pelajaran belum aktif</h2>
            <p class="help-text">Aktifkan tahun pelajaran sebelum mengatur batas dan pendamping ibadah siswi.</p>
            <a href="{{ route('tahun-pelajaran.index') }}" class="button button-primary">Buka tahun pelajaran</a>
        </section>
    @else
        <div class="stats-grid">
            <div class="panel stat"><p class="stat-label">Tahun pelajaran</p><p class="stat-value" style="font-size:1.25rem;">{{ $tahunPelajaran->nama }}</p></div>
            <div class="panel stat active"><p class="stat-label">Pendamping aktif</p><p class="stat-value">{{ $penugasanPendamping->count() }}</p></div>
            <div class="panel stat inactive"><p class="stat-label">Kelas tercakup</p><p class="stat-value">{{ $jumlahKelasTercakup }} / {{ $daftarKelas->count() }}</p></div>
        </div>

        <div class="absence-setting-grid" style="margin-top:18px;">
            <section class="panel panel-pad">
                <h2 class="panel-title">Batas konfirmasi</h2>
                <p class="help-text">Batas ini hanya menjadi pengingat untuk pendamping. Siswi tidak otomatis dianggap melakukan pelanggaran.</p>

                <form method="POST" action="{{ route('pengaturan-berhalangan-ibadah.update') }}" style="margin-top:18px;">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="batas_hari_konfirmasi">Batas hari kalender</label>
                        <input id="batas_hari_konfirmasi" name="batas_hari_konfirmasi" type="number" min="1" max="30" class="input @error('batas_hari_konfirmasi') is-invalid @enderror" value="{{ old('batas_hari_konfirmasi', $pengaturan->batas_hari_konfirmasi) }}" required>
                        <p class="help-text">Setelah melewati batas ini, status berubah menjadi Perlu konfirmasi privat.</p>
                    </div>
                    <label class="status-toggle" style="margin-top:15px;">
                        <span><span class="form-label" style="margin-bottom:0;">Pengingat aktif</span><span class="help-text">Aktifkan pemantauan batas berhalangan pada tahun pelajaran ini.</span></span>
                        <input type="hidden" name="aktif" value="0">
                        <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $pengaturan->aktif))>
                    </label>
                    <button type="submit" class="button button-primary button-full" style="margin-top:17px;">Simpan pengaturan</button>
                </form>

                <div class="privacy-note" style="margin-top:18px;">
                    Informasi berhalangan bersifat privat. Tidak ada pemeriksaan fisik dan hasil konfirmasi tidak ditampilkan pada rekap umum.
                </div>
            </section>

            <section class="panel panel-pad" id="form-pendamping">
                <div class="section-heading">
                    <div>
                        <h2 class="panel-title">{{ $penugasanDiedit ? 'Atur ulang pendamping' : 'Tambah pendamping' }}</h2>
                        <p class="help-text">Daftar hanya memuat guru perempuan yang masih aktif.</p>
                    </div>
                    @if ($penugasanDiedit)
                        <a href="{{ route('pengaturan-berhalangan-ibadah.index') }}" class="button button-muted button-sm">Batal ubah</a>
                    @endif
                </div>

                @php
                    $pegawaiTerpilih = (int) old('pegawai_id', $penugasanDiedit?->pegawai_id);
                    $semuaKelas = (string) old('semua_kelas', $penugasanDiedit ? ($penugasanDiedit->semua_kelas ? '1' : '0') : '1');
                    $kelasTerpilih = collect(old('kelas_ids', $penugasanDiedit?->kelas?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
                @endphp

                <form method="POST" action="{{ route('pengaturan-berhalangan-ibadah.pendamping.store') }}" style="margin-top:18px;">
                    @csrf
                    <div class="field">
                        <label for="pegawai_id">Guru pendamping perempuan</label>
                        @if ($penugasanDiedit)<input type="hidden" name="pegawai_id" value="{{ $penugasanDiedit->pegawai_id }}">@endif
                        <select id="pegawai_id" @unless($penugasanDiedit) name="pegawai_id" @endunless class="select @error('pegawai_id') is-invalid @enderror" required @disabled($penugasanDiedit)>
                            <option value="">Pilih guru</option>
                            @foreach ($daftarPegawaiPerempuan as $pegawai)
                                <option value="{{ $pegawai->id }}" @selected($pegawaiTerpilih === $pegawai->id)>
                                    {{ $pegawai->nama_lengkap }}{{ $pegawai->jabatan_utama ? ' - '.$pegawai->jabatan_utama : '' }}{{ ! $pegawai->pengguna?->aktif ? ' (akun belum aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($daftarPegawaiPerempuan->isEmpty())
                            <p class="error-text">Belum ada data guru perempuan aktif. Periksa jenis pegawai dan jenis kelaminnya pada data pegawai.</p>
                        @endif
                    </div>

                    <div class="field" style="margin-top:16px;">
                        <span class="form-label">Cakupan pendampingan</span>
                        <div class="absence-choice">
                            <label>
                                <input type="radio" name="semua_kelas" value="1" @checked($semuaKelas === '1')>
                                <span><strong>Seluruh kelas</strong><small>Menjadi pendamping umum dan dapat menerima tindak lanjut dari semua kelas.</small></span>
                            </label>
                            <label>
                                <input type="radio" name="semua_kelas" value="0" @checked($semuaKelas === '0')>
                                <span><strong>Kelas tertentu</strong><small>Pilih satu atau beberapa kelas yang menjadi tanggung jawab guru.</small></span>
                            </label>
                        </div>
                    </div>

                    <div class="class-choice-wrap" data-class-choice>
                        <strong>Pilih kelas</strong>
                        <p class="help-text">Satu pendamping dapat menangani beberapa kelas sekaligus.</p>
                        <div class="class-choice-grid">
                            @foreach ($daftarKelas->groupBy('tingkat') as $tingkat => $kelasTingkat)
                                <div class="class-choice-group">
                                    <h3>Tingkat {{ $tingkat }}</h3>
                                    @foreach ($kelasTingkat as $kelas)
                                        <label class="class-check">
                                            <input type="checkbox" name="kelas_ids[]" value="{{ $kelas->id }}" @checked($kelasTerpilih->contains($kelas->id))>
                                            <span>{{ $kelas->nama }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-actions">
                        @if ($penugasanDiedit)<a href="{{ route('pengaturan-berhalangan-ibadah.index') }}" class="button button-muted">Batal</a>@endif
                        <button type="submit" class="button button-primary" @disabled($daftarPegawaiPerempuan->isEmpty())>Simpan pendamping</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="panel" style="margin-top:18px;">
            <div class="panel-pad" style="border-bottom:1px solid var(--line);">
                <h2 class="panel-title">Pendamping aktif</h2>
                <p class="help-text">Nonaktifkan pendamping yang tidak lagi bertugas. Riwayat penugasan sebelumnya tetap tersimpan.</p>
            </div>

            <div class="desktop-only table-wrap">
                <table class="employee-table">
                    <thead><tr><th>Guru pendamping</th><th>Cakupan</th><th>Ditugaskan oleh</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($penugasanPendamping as $penugasan)
                            <tr>
                                <td><p class="companion-name">{{ $penugasan->pegawai?->nama_lengkap ?? '-' }}</p><p class="person-meta">{{ $penugasan->pegawai?->nip ?: 'NIP belum diisi' }}</p></td>
                                <td>
                                    @if ($penugasan->semua_kelas)
                                        <span class="badge badge-active">Seluruh kelas</span>
                                    @else
                                        <div class="companion-scope">@foreach ($penugasan->kelas->sortBy(fn ($kelas) => sprintf('%02d-%s', $kelas->tingkat, $kelas->nama)) as $kelas)<span class="badge badge-muted">{{ $kelas->nama }}</span>@endforeach</div>
                                    @endif
                                </td>
                                <td><p class="person-name">{{ $penugasan->ditugaskanOlehPengguna?->nama ?: '-' }}</p><p class="person-meta">{{ $penugasan->updated_at?->format('d/m/Y H:i') }}</p></td>
                                <td><div class="actions" style="justify-content:flex-end;"><a href="{{ route('pengaturan-berhalangan-ibadah.index', ['ubah' => $penugasan->id]) }}#form-pendamping" class="button button-muted button-sm">Atur</a><form method="POST" action="{{ route('pengaturan-berhalangan-ibadah.pendamping.destroy', $penugasan) }}" onsubmit="return confirm('Nonaktifkan pendamping ini?')">@csrf @method('DELETE')<button type="submit" class="button button-danger button-sm">Nonaktifkan</button></form></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Belum ada pendamping ibadah siswi yang ditugaskan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only mobile-list">
                @forelse ($penugasanPendamping as $penugasan)
                    <article class="mobile-card">
                        <div class="mobile-card-head"><div><p class="companion-name">{{ $penugasan->pegawai?->nama_lengkap ?? '-' }}</p><p class="person-meta">{{ $penugasan->pegawai?->nip ?: 'NIP belum diisi' }}</p></div><span class="badge badge-active">Aktif</span></div>
                        <div class="companion-scope">@if($penugasan->semua_kelas)<span class="badge badge-active">Seluruh kelas</span>@else @foreach($penugasan->kelas->sortBy(fn ($kelas) => sprintf('%02d-%s', $kelas->tingkat, $kelas->nama)) as $kelas)<span class="badge badge-muted">{{ $kelas->nama }}</span>@endforeach @endif</div>
                        <div class="actions" style="margin-top:14px;"><a href="{{ route('pengaturan-berhalangan-ibadah.index', ['ubah' => $penugasan->id]) }}#form-pendamping" class="button button-muted button-sm">Atur</a><form method="POST" action="{{ route('pengaturan-berhalangan-ibadah.pendamping.destroy', $penugasan) }}" onsubmit="return confirm('Nonaktifkan pendamping ini?')">@csrf @method('DELETE')<button type="submit" class="button button-danger button-sm">Nonaktifkan</button></form></div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada pendamping ibadah siswi yang ditugaskan.</div>
                @endforelse
            </div>
        </section>
    @endunless
@endsection

@push('scripts')
    <script>
        (() => {
            const pilihan = document.querySelectorAll('input[name="semua_kelas"]');
            const daftarKelas = document.querySelector('[data-class-choice]');
            if (!pilihan.length || !daftarKelas) return;

            const perbarui = () => {
                const kelasTertentu = document.querySelector('input[name="semua_kelas"]:checked')?.value === '0';
                daftarKelas.hidden = !kelasTertentu;
            };

            pilihan.forEach((item) => item.addEventListener('change', perbarui));
            perbarui();
        })();
    </script>
@endpush
