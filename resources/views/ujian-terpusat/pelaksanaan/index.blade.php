@extends('layouts.app')

@section('title', ($tahapAktif === 5 ? 'Penetapan Ruang' : ($tahapAktif === 6 ? 'Pembagian Peserta' : 'Jadwal Ujian')).' - NUSA')

@section('content')
    <style>
        .execution-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 20px;
            align-items: center;
            margin-bottom: 18px;
            padding: 20px 22px;
            background: var(--primary);
            color: #fff;
        }
        .execution-hero h2 { margin: 4px 0 0; color: #fff; font-size: 1.25rem; }
        .execution-hero p { margin: 6px 0 0; color: rgba(255, 255, 255, .78); }
        .execution-hero-stats { display: flex; gap: 10px; }
        .execution-hero-stat { min-width: 92px; padding: 11px 13px; border: 1px solid rgba(255,255,255,.2); border-radius: 7px; background: rgba(255,255,255,.08); }
        .execution-hero-stat strong, .execution-hero-stat span { display: block; }
        .execution-hero-stat strong { color: #fff; font-size: 1.2rem; }
        .execution-hero-stat span { margin-top: 2px; color: rgba(255,255,255,.72); font-size: .72rem; font-weight: 700; }
        .execution-section { margin-top: 20px; }
        .execution-section-head { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
        .execution-section-head h2 { margin: 0; font-size: 1.04rem; }
        .execution-section-head p { margin: 5px 0 0; color: var(--muted); font-size: .83rem; }
        .grade-block { padding: 18px 0; border-bottom: 1px solid var(--line); }
        .grade-block:last-child { padding-bottom: 0; border-bottom: 0; }
        .grade-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-bottom: 15px; }
        .grade-head h3 { margin: 0; font-size: 1rem; }
        .grade-head p { margin: 4px 0 0; color: var(--muted); font-size: .78rem; }
        .grade-status { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 7px; }
        .distribution-form { display: grid; grid-template-columns: minmax(190px,.55fr) minmax(0,1fr) minmax(0,1.15fr); gap: 14px; align-items: start; }
        .choice-box { min-width: 0; padding: 13px; border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; }
        .choice-box-title { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 10px; font-size: .78rem; font-weight: 800; }
        .choice-list { display: grid; gap: 8px; }
        .choice-item { display: grid; grid-template-columns: 20px minmax(0,1fr) auto; gap: 8px; align-items: center; min-width: 0; padding: 8px 9px; border: 1px solid var(--line); border-radius: 6px; background: #fff; }
        .choice-item input { width: 17px; height: 17px; accent-color: var(--primary); }
        .choice-item strong, .choice-item span { min-width: 0; }
        .choice-item strong { overflow-wrap: anywhere; font-size: .79rem; }
        .choice-item span { color: var(--muted); font-size: .7rem; font-weight: 700; }
        .choice-item.unavailable { opacity: .5; background: #f1f5f9; }
        .distribution-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .distribution-summary span { padding: 7px 9px; border-radius: 6px; background: var(--primary-soft); color: var(--primary-dark); font-size: .74rem; font-weight: 800; }
        .distribution-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 14px; }
        .schedule-builder { padding:18px 0; border-bottom:1px solid var(--line); }
        .schedule-fields { display:grid; grid-template-columns:180px minmax(240px,1fr) minmax(220px,.9fr); gap:12px; align-items:end; }
        .schedule-level-section { margin-top:16px; padding-top:15px; border-top:1px solid var(--line); }
        .schedule-level-head { display:flex; justify-content:space-between; gap:14px; align-items:end; margin-bottom:10px; }
        .schedule-level-head strong,.schedule-level-head span { display:block; }
        .schedule-level-head strong { font-size:.8rem; }
        .schedule-level-head span { margin-top:3px; color:var(--muted); font-size:.72rem; font-weight:650; }
        .level-options { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
        .level-option { display:grid; grid-template-columns:20px minmax(0,1fr); gap:9px; align-items:center; min-height:62px; padding:11px 12px; border:1px solid var(--line); border-left:4px solid var(--primary); border-radius:7px; background:#fff; cursor:pointer; }
        .level-option:has(input:checked) { border-color:var(--primary); border-left-color:var(--accent); background:var(--primary-soft); box-shadow:0 0 0 2px rgba(21,71,122,.07); }
        .level-option.unavailable { border-left-color:var(--line); background:#f4f6f8; cursor:not-allowed; opacity:.62; }
        .level-option input { width:17px; height:17px; margin:0; accent-color:var(--primary); }
        .level-option-copy strong,.level-option-copy span { display:block; }
        .level-option-copy strong { color:var(--primary-dark); font-size:.8rem; }
        .level-option-copy span { margin-top:3px; color:var(--muted); font-size:.7rem; font-weight:700; }
        .schedule-builder-actions { display:flex; justify-content:flex-end; margin-top:14px; }
        .schedule-list { display: grid; gap: 10px; margin-top: 16px; }
        .schedule-row { display: grid; grid-template-columns: 120px minmax(170px,.85fr) minmax(0,1.15fr) minmax(180px,.8fr) auto; gap: 14px; align-items: center; padding: 13px 14px; border: 1px solid var(--line); border-left: 4px solid var(--primary); border-radius: 7px; background: #fff; }
        .schedule-date strong, .schedule-date span, .schedule-main strong, .schedule-main span, .schedule-room strong, .schedule-room span { display: block; }
        .schedule-date strong, .schedule-main strong, .schedule-room strong { font-size: .8rem; }
        .schedule-date span, .schedule-main span, .schedule-room span { margin-top: 3px; color: var(--muted); font-size: .72rem; font-weight: 700; }
        .schedule-level { display: inline-grid; width: 44px; height: 38px; place-items: center; border-radius: 6px; background: var(--accent-soft); color: var(--accent-text); font-weight: 900; }
        .schedule-actions { display: flex; gap: 7px; }
        .schedule-edit { grid-column: 1 / -1; padding-top: 12px; border-top: 1px solid var(--line); }
        .schedule-edit form { display: grid; grid-template-columns: 170px minmax(220px,1fr) minmax(180px,1fr) auto; gap: 12px; align-items: end; }
        .inline-empty { padding: 26px 14px; text-align: center; color: var(--muted); }
        .central-wizard-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:20px; }
        @media (max-width: 1400px) {
            .distribution-form { grid-template-columns: 1fr 1fr; }
            .distribution-form > .field { grid-column: 1 / -1; }
            .schedule-fields { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .schedule-fields .field:last-child { grid-column:1 / -1; }
            .schedule-row { grid-template-columns: 100px 52px minmax(0,1fr) auto; }
            .schedule-room { grid-column: 3 / 4; }
            .schedule-actions { grid-column: 4; grid-row: 1 / span 2; }
        }
        @media (max-width: 720px) {
            .execution-hero { grid-template-columns: 1fr; }
            .execution-hero-stats { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); }
            .execution-hero-stat { min-width: 0; }
            .execution-section-head, .grade-head { display: grid; }
            .grade-status { justify-content: flex-start; }
            .distribution-form, .schedule-fields, .schedule-edit form { grid-template-columns: 1fr; }
            .distribution-form > .field, .schedule-fields .field:last-child { grid-column: auto; }
            .schedule-level-head { display:grid; }
            .level-options { grid-template-columns:1fr; }
            .schedule-builder-actions .button { width:100%; }
            .distribution-actions { justify-content: stretch; }
            .distribution-actions .button, .distribution-actions form { flex: 1 1 100%; width: 100%; }
            .schedule-row { grid-template-columns: 52px minmax(0,1fr); }
            .schedule-date { grid-column: 1 / -1; }
            .schedule-level { grid-column: 1; }
            .schedule-main { grid-column: 2; }
            .schedule-room { grid-column: 1 / -1; }
            .schedule-actions { grid-column: 1 / -1; grid-row: auto; }
            .schedule-actions .button, .schedule-actions form { flex: 1 1 0; }
            .central-wizard-actions { align-items:stretch; flex-direction:column-reverse; }
            .central-wizard-actions .button { width:100%; text-align:center; }
        }
    </style>

    @php
        $jumlahPenempatan = $kegiatan->kelompokPesertaKegiatanUjianCbt->sum('penempatan_peserta_ujian_cbt_count');
        $panitiaSiap = $kegiatan->panitiaUjianCbt?->isNotEmpty() ?? true;
        $sesiSiap = $kegiatan->sesiKegiatanUjianCbt->isNotEmpty();
        $ruangSiap = $kegiatan->ruangKegiatanUjianCbt->isNotEmpty();
        $judulTahap = [5 => 'Penetapan ruang', 6 => 'Pembagian peserta', 7 => 'Jadwal ujian'][$tahapAktif];
        $subjudulTahap = [
            5 => 'Tentukan kelas, sesi, dan ruang yang digunakan oleh setiap tingkat.',
            6 => 'Bangkitkan pembagian siswa otomatis setelah penetapan ruang selesai.',
            7 => 'Susun mata pelajaran dan tanggal ujian setelah seluruh peserta terbagi.',
        ][$tahapAktif];
        $pemakaianRuang = [];
        foreach ($kegiatan->kelompokPesertaKegiatanUjianCbt as $kelompokPemakai) {
            foreach ($kelompokPemakai->ruangKegiatanUjianCbt as $ruangPemakai) {
                $pemakaianRuang[$ruangPemakai->id][] = [
                    'sesi' => $kelompokPemakai->sesi_kegiatan_ujian_cbt_id,
                    'tingkat' => $kelompokPemakai->tingkat,
                ];
            }
        }
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat · Tahap {{ $tahapAktif }}</p>
            <h1 class="page-title">{{ $judulTahap }}</h1>
            <p class="page-subtitle">{{ $subjudulTahap }}</p>
        </div>
    </div>

    @if (session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <section class="panel execution-hero">
        <div>
            <p class="eyebrow" style="color: var(--accent);">{{ $kegiatan->jenisUjianCbt?->nama }}</p>
            <h2>{{ $kegiatan->nama }}</h2>
            <p>{{ $kegiatan->tahunPelajaran?->nama }} · {{ $kegiatan->labelPeriode() }}</p>
        </div>
        <div class="execution-hero-stats">
            <div class="execution-hero-stat"><strong>{{ $kegiatan->kelompokPesertaKegiatanUjianCbt->count() }}</strong><span>Tingkat dibagi</span></div>
            <div class="execution-hero-stat"><strong>{{ $jumlahPenempatan }}</strong><span>Peserta</span></div>
            <div class="execution-hero-stat"><strong>{{ $kegiatan->jadwalUjianCbt->count() }}</strong><span>Jadwal</span></div>
        </div>
    </section>

    @include('ujian-terpusat.partials.alur')

    @if ($tahapAktif === 5)
    <section class="panel panel-pad execution-section">
        <div class="execution-section-head">
            <div><h2>Penetapan kelas, sesi, dan ruang</h2><p>Tahap ini hanya menyimpan pengaturan ruang. Siswa baru dibagi otomatis pada tahap 6.</p></div>
            <span class="badge {{ $kegiatan->kelompokPesertaKegiatanUjianCbt->isNotEmpty() ? 'badge-active' : 'badge-warning' }}">{{ $kegiatan->kelompokPesertaKegiatanUjianCbt->count() }} tingkat ditetapkan</span>
        </div>

        @foreach ([7, 8, 9] as $tingkat)
            @php
                $kelasTingkat = $daftarKelas->get($tingkat, collect());
                $kelompok = $kelompokPerTingkat->get($tingkat);
                $kelasTerpilih = $kelompok?->kelas->modelKeys() ?? [];
                $ruangTerpilih = $kelompok?->ruangKegiatanUjianCbt->modelKeys() ?? [];
                $jumlahSiswaTingkat = $kelasTingkat->sum('jumlah_siswa_aktif');
                $memilikiJadwal = $kegiatan->jadwalUjianCbt->where('tingkat', $tingkat)->isNotEmpty();
            @endphp
            <div class="grade-block" id="tingkat-{{ $tingkat }}" data-distribution-card data-level="{{ $tingkat }}">
                <div class="grade-head">
                    <div><h3>Tingkat {{ $tingkat }}</h3><p>{{ $kelasTingkat->count() }} kelas · {{ $jumlahSiswaTingkat }} siswa aktif</p></div>
                    <div class="grade-status">
                        @if ($kelompok)
                            <span class="badge badge-active">Ruang ditetapkan</span>
                            <span class="badge badge-muted">{{ $kelompok->sesiKegiatanUjianCbt?->nama }}</span>
                        @else
                            <span class="badge badge-warning">Belum ditetapkan</span>
                        @endif
                    </div>
                </div>

                @if ($bolehKelola)
                    <form action="{{ route('ujian-terpusat.peserta.atur', $kegiatan) }}" method="POST">
                        @csrf
                        <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                        <div class="distribution-form">
                            <div class="field">
                                <label for="sesi_{{ $tingkat }}">Sesi tingkat {{ $tingkat }}</label>
                                <select id="sesi_{{ $tingkat }}" name="sesi_kegiatan_ujian_cbt_id" class="input" data-session-select required>
                                    <option value="">Pilih sesi</option>
                                    @foreach ($kegiatan->sesiKegiatanUjianCbt as $sesi)
                                        <option value="{{ $sesi->id }}" @selected((int) $kelompok?->sesi_kegiatan_ujian_cbt_id === (int) $sesi->id)>{{ $sesi->nama }} · {{ $sesi->labelWaktu() }}</option>
                                    @endforeach
                                </select>
                                <p class="help-text" style="margin-top:8px;">Satu tingkat menggunakan sesi yang sama selama rangkaian ujian.</p>
                            </div>
                            <div class="choice-box">
                                <div class="choice-box-title"><span>Kelas peserta</span><span data-student-total>0 siswa</span></div>
                                <div class="choice-list">
                                    @forelse ($kelasTingkat as $kelas)
                                        <label class="choice-item">
                                            <input type="checkbox" name="kelas[]" value="{{ $kelas->id }}" data-class-choice data-count="{{ $kelas->jumlah_siswa_aktif }}" @checked(in_array($kelas->id, $kelasTerpilih, true) || ! $kelompok)>
                                            <strong>{{ $kelas->nama }}</strong><span>{{ $kelas->jumlah_siswa_aktif }} siswa</span>
                                        </label>
                                    @empty
                                        <span class="help-text">Belum ada kelas aktif pada tingkat ini.</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="choice-box">
                                <div class="choice-box-title"><span>Ruang yang digunakan</span><span data-capacity-total>0 kursi</span></div>
                                <div class="choice-list">
                                    @forelse ($kegiatan->ruangKegiatanUjianCbt as $ruang)
                                        <label class="choice-item" data-room-item>
                                            <input type="checkbox" name="ruang[]" value="{{ $ruang->id }}" data-room-choice data-capacity="{{ $ruang->kapasitas }}" data-uses='@json($pemakaianRuang[$ruang->id] ?? [])' @checked(in_array($ruang->id, $ruangTerpilih, true))>
                                            <strong>{{ $ruang->nama }}</strong><span>{{ $ruang->kapasitas }} kursi</span>
                                        </label>
                                    @empty
                                        <span class="help-text">Tambahkan ruang pada Persiapan awal.</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        <div class="distribution-summary"><span data-balance>Lengkapi sesi dan ruang.</span></div>
                        <div class="distribution-actions">
                            @if ($kelompok)
                                @if ($kelompok->jumlah_peserta > 0)
                                    <a href="{{ route('ujian-terpusat.peserta.show', [$kegiatan, $kelompok]) }}" class="button button-muted">Lihat pembagian lama</a>
                                @endif
                            @endif
                            <button type="submit" class="button button-primary" @disabled($kelasTingkat->isEmpty() || $kegiatan->sesiKegiatanUjianCbt->isEmpty() || $kegiatan->ruangKegiatanUjianCbt->isEmpty())>{{ $kelompok ? 'Simpan perubahan' : 'Simpan penetapan' }}</button>
                        </div>
                    </form>
                    @if ($kelompok && ! $memilikiJadwal)
                        <form action="{{ route('ujian-terpusat.peserta.destroy', [$kegiatan, $kelompok]) }}" method="POST" style="margin-top:8px;text-align:right;" onsubmit="return confirm('Kosongkan pembagian peserta tingkat {{ $tingkat }}?')">
                            @csrf @method('DELETE')
                            <button class="button button-danger" type="submit">Hapus penetapan</button>
                        </form>
                    @endif
                @elseif ($kelompok)
                    <div class="distribution-actions"><a href="{{ route('ujian-terpusat.peserta.show', [$kegiatan, $kelompok]) }}" class="button button-primary">Lihat pembagian</a></div>
                @endif
            </div>
        @endforeach
    </section>

    <div class="central-wizard-actions">
        <a href="{{ route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 4]) }}" class="button button-muted">Kembali ke Ruang</a>
        <a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 6]) }}" class="button button-primary">Lanjut ke Pembagian Peserta</a>
    </div>
    @endif

    @if ($tahapAktif === 6)
    <section class="panel panel-pad execution-section">
        <div class="execution-section-head">
            <div><h2>Pembagian peserta otomatis</h2><p>NUSA mengurutkan siswa berdasarkan kelas lalu nama A-Z, kemudian mengisi ruang sesuai kapasitas.</p></div>
            <span class="badge {{ $jumlahPenempatan > 0 ? 'badge-active' : 'badge-warning' }}">{{ $jumlahPenempatan }} peserta</span>
        </div>

        @foreach ([7, 8, 9] as $tingkat)
            @php
                $kelompok = $kelompokPerTingkat->get($tingkat);
                $jumlahTerbagi = (int) ($kelompok?->penempatan_peserta_ujian_cbt_count ?? 0);
            @endphp
            <div class="grade-block">
                <div class="grade-head">
                    <div>
                        <h3>Tingkat {{ $tingkat }}</h3>
                        @if ($kelompok)
                            <p>{{ $kelompok->kelas->pluck('nama')->join(', ') }} · {{ $kelompok->sesiKegiatanUjianCbt?->nama }} · {{ $kelompok->ruangKegiatanUjianCbt->pluck('nama')->join(', ') }}</p>
                        @else
                            <p>Kelas, sesi, dan ruang belum ditetapkan pada tahap 5.</p>
                        @endif
                    </div>
                    <div class="grade-status">
                        @if ($jumlahTerbagi > 0)
                            <span class="badge badge-active">{{ $jumlahTerbagi }} siswa terbagi</span>
                        @elseif ($kelompok)
                            <span class="badge badge-warning">Siap dibagi</span>
                        @else
                            <span class="badge badge-muted">Belum ditetapkan</span>
                        @endif
                    </div>
                </div>

                @if ($kelompok)
                    <div class="distribution-summary">
                        <span>{{ $kelompok->kelas->count() }} kelas</span>
                        <span>{{ $kelompok->ruangKegiatanUjianCbt->count() }} ruang</span>
                        <span>{{ $kelompok->total_kapasitas }} kursi tersedia</span>
                    </div>
                    <div class="distribution-actions">
                        @if ($jumlahTerbagi > 0)
                            <a href="{{ route('ujian-terpusat.peserta.show', [$kegiatan, $kelompok]) }}" class="button button-muted">Lihat hasil pembagian</a>
                        @endif
                        @if ($bolehKelola)
                            <form action="{{ route('ujian-terpusat.peserta.bangkitkan', [$kegiatan, $kelompok]) }}" method="POST">
                                @csrf
                                <button type="submit" class="button button-primary" @if($jumlahTerbagi > 0) onclick="return confirm('Susun ulang pembagian peserta tingkat {{ $tingkat }}?')" @endif>{{ $jumlahTerbagi > 0 ? 'Susun ulang otomatis' : 'Bagi peserta otomatis' }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <div class="central-wizard-actions">
        <a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 5]) }}" class="button button-muted">Kembali ke Penetapan Ruang</a>
        <a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]) }}" class="button button-primary">Lanjut ke Jadwal Ujian</a>
    </div>
    @endif

    @if ($tahapAktif === 7)
    <section class="panel panel-pad execution-section">
        <div class="execution-section-head">
            <div><h2>Jadwal ujian</h2><p>Satu mata pelajaran dapat ditambahkan sekaligus untuk beberapa tingkat yang pesertanya sudah dibagi.</p></div>
            <span class="badge {{ $kegiatan->jadwalUjianCbt->isNotEmpty() ? 'badge-active' : 'badge-warning' }}">{{ $kegiatan->jadwalUjianCbt->count() }} jadwal</span>
        </div>

        @if ($bolehKelola)
            <form action="{{ route('ujian-terpusat.jadwal.store', $kegiatan) }}" method="POST" class="schedule-builder" data-schedule-form>
                @csrf
                <div class="schedule-fields">
                    <div class="field"><label for="tanggal_jadwal">Tanggal ujian</label><input id="tanggal_jadwal" name="tanggal" type="date" min="{{ $kegiatan->tanggal_mulai?->format('Y-m-d') }}" max="{{ $kegiatan->tanggal_selesai?->format('Y-m-d') }}" value="{{ old('tanggal', $kegiatan->tanggal_mulai?->format('Y-m-d')) }}" class="input" required></div>
                    <div class="field"><label for="mata_pelajaran_jadwal">Mata pelajaran</label><select id="mata_pelajaran_jadwal" name="mata_pelajaran_id" class="input" data-subject-select required><option value="">Pilih mata pelajaran</option>@foreach($daftarMataPelajaran as $mapel)<option value="{{ $mapel->id }}" data-levels='@json($mapel->tingkat_tersedia)' @selected((int) old('mata_pelajaran_id') === (int) $mapel->id)>{{ $mapel->nama }}</option>@endforeach</select></div>
                    <div class="field"><label for="catatan_jadwal">Catatan</label><input id="catatan_jadwal" name="keterangan" value="{{ old('keterangan') }}" class="input" placeholder="Opsional, misalnya hari pertama"></div>
                </div>
                <div class="schedule-level-section">
                    <div class="schedule-level-head"><div><strong>Pilih tingkat peserta</strong><span>Sesi dan kelas mengikuti pembagian peserta yang sudah disiapkan.</span></div><span>Pilih satu atau beberapa tingkat</span></div>
                    <div class="level-options">
                        @foreach([7,8,9] as $tingkat)
                            @php($kelompok=$kelompokPerTingkat->get($tingkat))
                            @php($kelompokSiap=$kelompok && (int) $kelompok->penempatan_peserta_ujian_cbt_count > 0)
                            <label class="level-option {{ $kelompokSiap ? '' : 'unavailable' }}">
                                <input type="checkbox" name="tingkat[]" value="{{ $tingkat }}" data-schedule-level @checked(in_array($tingkat, array_map('intval', (array) old('tingkat', [])), true)) @disabled(! $kelompokSiap)>
                                <span class="level-option-copy"><strong>Tingkat {{ $tingkat }}</strong><span>{{ $kelompokSiap ? (($kelompok->sesiKegiatanUjianCbt?->nama ?: 'Sesi tersedia').' · '.$kelompok->jumlah_peserta.' peserta') : 'Selesaikan pembagian peserta terlebih dahulu' }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="schedule-builder-actions"><button type="submit" class="button button-primary">Tambahkan jadwal</button></div>
            </form>
        @endif

        <div class="schedule-list">
            @forelse ($kegiatan->jadwalUjianCbt as $jadwal)
                @php($kelompok=$kelompokPerTingkat->get($jadwal->tingkat))
                <div class="schedule-row">
                    <div class="schedule-date"><strong>{{ $jadwal->tanggal?->locale('id')->translatedFormat('D, d M Y') }}</strong><span>{{ $jadwal->sesiKegiatanUjianCbt?->labelWaktu() ?: $jadwal->labelWaktu() }}</span></div>
                    <span class="schedule-level">T{{ $jadwal->tingkat }}</span>
                    <div class="schedule-main"><strong>{{ $jadwal->mataPelajaran?->nama ?: 'Mata pelajaran belum diisi' }}</strong><span>{{ $jadwal->sesiKegiatanUjianCbt?->nama }} · {{ $jadwal->kelas->pluck('nama')->join(', ') }}</span></div>
                    <div class="schedule-room"><strong>{{ $kelompok?->ruangKegiatanUjianCbt->pluck('nama')->join(', ') ?: 'Ruang belum dibagi' }}</strong><span>{{ $kelompok?->jumlah_peserta ?? 0 }} peserta</span></div>
                    @if ($bolehKelola)
                        <div class="schedule-actions">
                            <a href="{{ route('paket-soal-terpusat.show', $jadwal) }}" class="button {{ $jadwal->ujianCbt && in_array($jadwal->ujianCbt->status, ['terjadwal', 'berlangsung', 'selesai'], true) ? 'button-dark' : 'button-muted' }}">Paket</a>
                            <button type="button" class="button button-muted" data-schedule-edit-toggle>Edit</button>
                            <form action="{{ route('ujian-terpusat.jadwal.destroy', [$kegiatan, $jadwal]) }}" method="POST" onsubmit="return confirm('Hapus jadwal {{ $jadwal->mataPelajaran?->nama }} tingkat {{ $jadwal->tingkat }}?')">@csrf @method('DELETE')<button type="submit" class="button button-danger">Hapus</button></form>
                        </div>
                        <div class="schedule-edit" hidden data-schedule-edit-panel>
                            <form action="{{ route('ujian-terpusat.jadwal.update', [$kegiatan, $jadwal]) }}" method="POST">
                                    @csrf @method('PUT')
                                    <div class="field"><label for="tanggal_{{ $jadwal->id }}">Tanggal</label><input id="tanggal_{{ $jadwal->id }}" name="tanggal" type="date" min="{{ $kegiatan->tanggal_mulai?->format('Y-m-d') }}" max="{{ $kegiatan->tanggal_selesai?->format('Y-m-d') }}" value="{{ $jadwal->tanggal?->format('Y-m-d') }}" class="input" required></div>
                                    <div class="field"><label for="mapel_{{ $jadwal->id }}">Mata pelajaran</label><select id="mapel_{{ $jadwal->id }}" name="mata_pelajaran_id" class="input" required>@foreach($daftarMataPelajaran as $mapel) @if(in_array($jadwal->tingkat,$mapel->tingkat_tersedia,true))<option value="{{ $mapel->id }}" @selected($jadwal->mata_pelajaran_id===$mapel->id)>{{ $mapel->nama }}</option>@endif @endforeach</select></div>
                                    <div class="field"><label for="catatan_{{ $jadwal->id }}">Catatan</label><input id="catatan_{{ $jadwal->id }}" name="keterangan" value="{{ $jadwal->keterangan }}" class="input"></div>
                                    <div class="actions"><button class="button button-primary" type="submit">Simpan</button></div>
                                </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="inline-empty">Belum ada jadwal. Selesaikan pembagian peserta, lalu tambahkan jadwal pertama.</div>
            @endforelse
        </div>
    </section>

    <div class="central-wizard-actions">
        <a href="{{ route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 6]) }}" class="button button-muted">Kembali ke Pembagian Peserta</a>
        <a href="{{ route('paket-soal-terpusat.index', ['kegiatan' => $kegiatan->id]) }}" class="button button-primary">Lanjut ke Paket Soal</a>
    </div>
    @endif

    <script>
        document.querySelectorAll('[data-distribution-card]').forEach((card) => {
            const level = Number(card.dataset.level);
            const session = card.querySelector('[data-session-select]');
            const classChoices = [...card.querySelectorAll('[data-class-choice]')];
            const roomChoices = [...card.querySelectorAll('[data-room-choice]')];
            const studentTotal = card.querySelector('[data-student-total]');
            const capacityTotal = card.querySelector('[data-capacity-total]');
            const balance = card.querySelector('[data-balance]');
            const refresh = () => {
                const selectedSession = Number(session?.value || 0);
                roomChoices.forEach((input) => {
                    const uses = JSON.parse(input.dataset.uses || '[]');
                    const conflict = uses.some((use) => Number(use.sesi) === selectedSession && Number(use.tingkat) !== level);
                    input.disabled = conflict;
                    input.closest('[data-room-item]')?.classList.toggle('unavailable', conflict);
                    if (conflict) input.checked = false;
                });
                const students = classChoices.filter((input) => input.checked).reduce((sum, input) => sum + Number(input.dataset.count || 0), 0);
                const capacity = roomChoices.filter((input) => input.checked && !input.disabled).reduce((sum, input) => sum + Number(input.dataset.capacity || 0), 0);
                studentTotal.textContent = `${students} siswa`;
                capacityTotal.textContent = `${capacity} kursi`;
                const remaining = capacity - students;
                balance.textContent = !selectedSession ? 'Pilih sesi.' : remaining >= 0 ? `${remaining} kursi tersisa` : `Kurang ${Math.abs(remaining)} kursi`;
                balance.style.background = remaining < 0 ? 'var(--danger-soft)' : 'var(--primary-soft)';
                balance.style.color = remaining < 0 ? 'var(--danger)' : 'var(--primary-dark)';
            };
            session?.addEventListener('change', refresh);
            [...classChoices, ...roomChoices].forEach((input) => input.addEventListener('change', refresh));
            refresh();
        });

        const scheduleForm = document.querySelector('[data-schedule-form]');
        if (scheduleForm) {
            const subject = scheduleForm.querySelector('[data-subject-select]');
            const levels = [...scheduleForm.querySelectorAll('[data-schedule-level]')];
            const refreshLevels = () => {
                const option = subject.options[subject.selectedIndex];
                const available = JSON.parse(option?.dataset.levels || '[]').map(Number);
                levels.forEach((input) => {
                    const allowed = available.includes(Number(input.value));
                    const hasDistribution = !input.closest('.level-option').classList.contains('unavailable');
                    input.disabled = !allowed || !hasDistribution;
                    if (input.disabled) input.checked = false;
                });
            };
            subject.addEventListener('change', refreshLevels);
            refreshLevels();
        }

        document.querySelectorAll('[data-schedule-edit-toggle]').forEach((button) => {
            const row = button.closest('.schedule-row');
            const panel = row?.querySelector('[data-schedule-edit-panel]');
            button.addEventListener('click', () => {
                if (!panel) return;
                panel.hidden = !panel.hidden;
                button.textContent = panel.hidden ? 'Edit' : 'Tutup';
            });
        });
    </script>
@endsection
