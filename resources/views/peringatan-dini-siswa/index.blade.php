@extends('layouts.app')

@section('title', 'Peringatan Dini Siswa - NUSA')

@section('content')
    <style>
        .warning-grid{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:20px}
        .warning-stat{background:#fff;border:1px solid var(--border);border-radius:8px;color:inherit;display:block;min-width:0;padding:18px;text-decoration:none}
        .warning-stat.is-yellow{border-top:4px solid var(--secondary)}
        .warning-stat.is-red{border-top:4px solid #c2413a}
        .warning-stat.is-blue{border-top:4px solid #2582bd}
        .warning-stat span{color:var(--muted);display:block;font-size:13px;font-weight:700}
        .warning-stat strong{color:var(--primary-dark);display:block;font-size:32px;line-height:1;margin:10px 0 7px}
        .warning-stat small{color:var(--muted);display:block;line-height:1.4}
        .warning-reason{max-width:400px}
        .warning-reason strong{color:var(--primary-dark);display:block;margin-bottom:5px}
        .warning-reason p{color:var(--muted);font-size:13px;line-height:1.45;margin:0}
        .warning-support{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
        .warning-support span{background:#eef4f9;border-radius:5px;color:#36566f;font-size:12px;font-weight:700;padding:5px 7px}
        .warning-level-important{background:#fee9e7;color:#a72c27}
        .warning-level-warning{background:#fff5cf;color:#795b00}
        @media(max-width:1050px){.warning-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.warning-grid{grid-template-columns:1fr}.warning-stat{padding:15px}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Peringatan Dini Siswa</h1>
            <p class="page-subtitle">Temukan pola yang memerlukan pendampingan sebelum berkembang menjadi masalah yang lebih berat.</p>
        </div>
        @izin('poin_siswa.pengaturan')
            <form method="POST" action="{{ route('peringatan-dini-siswa.proses') }}">
                @csrf
                <input type="hidden" name="tahun_pelajaran_id" value="{{ $tahunPelajaranId }}">
                <button class="button button-primary">Perbarui Peringatan</button>
            </form>
        @endizin
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @php
        $tautanRingkasan = fn (array $tambahan) => route('peringatan-dini-siswa.index', array_filter(array_merge([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'kelas_id' => $kelasId,
            'status' => 'aktif',
        ], $tambahan)));
    @endphp
    <section class="warning-grid" aria-label="Ringkasan peringatan dini">
        <a class="warning-stat is-blue" href="{{ $tautanRingkasan([]) }}">
            <span>Peringatan aktif</span>
            <strong>{{ number_format($ringkasan['total_aktif'], 0, ',', '.') }}</strong>
            <small>{{ $ringkasan['penting'] }} di antaranya berstatus penting</small>
        </a>
        <a class="warning-stat is-yellow" href="{{ $tautanRingkasan(['jenis' => 'mendekati_sanksi']) }}">
            <span>Mendekati sanksi</span>
            <strong>{{ number_format($ringkasan['mendekati_sanksi'], 0, ',', '.') }}</strong>
            <small>Saldo poin mendekati ambang berikutnya</small>
        </a>
        <a class="warning-stat is-yellow" href="{{ $tautanRingkasan(['jenis' => 'pelanggaran_berulang']) }}">
            <span>Pola berulang</span>
            <strong>{{ number_format($ringkasan['pola_berulang'], 0, ',', '.') }}</strong>
            <small>Pelanggaran atau keterlambatan berulang</small>
        </a>
        <a class="warning-stat is-red" href="{{ $tautanRingkasan(['jenis' => 'sanksi_belum_selesai']) }}">
            <span>Sanksi belum selesai</span>
            <strong>{{ number_format($ringkasan['sanksi_aktif'], 0, ',', '.') }}</strong>
            <small>Pelaksanaan masih menunggu atau diproses</small>
        </a>
    </section>

    <form method="GET" class="panel panel-pad" style="margin-bottom:20px">
        <div class="form-grid">
            <div class="field">
                <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                    @forelse($daftarTahunPelajaran as $tahun)
                        <option value="{{ $tahun->id }}" @selected((string)$tahunPelajaranId === (string)$tahun->id)>
                            {{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}
                        </option>
                    @empty
                        <option value="">Belum ada tahun pelajaran</option>
                    @endforelse
                </select>
            </div>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string)$kelasId === (string)$kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="jenis">Jenis peringatan</label>
                <select id="jenis" name="jenis" class="select">
                    <option value="">Semua jenis</option>
                    @foreach(\App\Models\PeringatanDiniSiswa::DAFTAR_JENIS as $kode => $label)
                        <option value="{{ $kode }}" @selected($jenis === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" class="select">
                    <option value="">Semua tingkat</option>
                    @foreach(\App\Models\PeringatanDiniSiswa::DAFTAR_TINGKAT as $kode => $label)
                        <option value="{{ $kode }}" @selected($tingkat === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    @foreach(\App\Models\PeringatanDiniSiswa::DAFTAR_STATUS as $kode => $label)
                        <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="kata_kunci">Cari siswa</label>
                <input id="kata_kunci" name="kata_kunci" class="input" value="{{ $kataKunci }}" placeholder="Nama, NIS, atau NISN">
            </div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:12px">
            <a href="{{ route('peringatan-dini-siswa.index') }}" class="button button-muted">Reset</a>
            <button class="button button-dark">Terapkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width:980px">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Peringatan</th>
                        <th>Data pendukung</th>
                        <th>Status</th>
                        <th>Terakhir terdeteksi</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarPeringatan as $peringatan)
                        @php
                            $siswa = $peringatan->siswa;
                            $data = $peringatan->data_pendukung ?? [];
                            $kelas = $siswa?->anggotaKelas?->first()?->kelas;
                            $guruWali = $siswa?->penugasanGuruWaliSiswa?->first()?->guruWali;
                            $pendampinganAktif = $siswa?->pendampinganSiswa?->first();
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $kelas?->nama ?: '-' }} · NISN {{ $siswa?->nisn ?: '-' }}</p>
                                <p class="person-meta">Guru Wali: {{ $guruWali?->nama_lengkap ?: '-' }}</p>
                            </td>
                            <td>
                                <div class="warning-reason">
                                    <strong>{{ $peringatan->labelJenis() }}</strong>
                                    <p>{{ $peringatan->pesan }}</p>
                                </div>
                            </td>
                            <td>
                                <div class="warning-support">
                                    @if($peringatan->jenis === 'mendekati_sanksi')
                                        <span>{{ $data['total_poin'] ?? 0 }} poin</span>
                                        <span>Sisa {{ $data['jarak_poin'] ?? 0 }} poin</span>
                                    @elseif($peringatan->jenis === 'pelanggaran_berulang')
                                        <span>{{ $data['jumlah_pelanggaran'] ?? 0 }} pelanggaran</span>
                                        <span>{{ $data['periode_hari'] ?? 0 }} hari</span>
                                    @elseif($peringatan->jenis === 'sering_terlambat')
                                        <span>{{ $data['jumlah_keterlambatan'] ?? 0 }} kali</span>
                                        <span>{{ $data['total_menit'] ?? 0 }} menit</span>
                                    @else
                                        <span>{{ str($data['status_sanksi'] ?? 'aktif')->replace('_', ' ')->title() }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $peringatan->tingkat === 'penting' ? 'warning-level-important' : 'warning-level-warning' }}">
                                    {{ $peringatan->labelTingkat() }}
                                </span>
                                <p class="person-meta" style="margin-top:6px">{{ $peringatan->labelStatus() }} · Siklus {{ $peringatan->siklus }}</p>
                            </td>
                            <td>{{ $peringatan->terakhir_terdeteksi_pada?->translatedFormat('d M Y, H:i') }}</td>
                            <td class="text-right">
                                <div class="actions" style="justify-content:flex-end">
                                    @if($peringatan->sanksiPoinSiswa)
                                        <a class="button button-muted button-sm" href="{{ route('sanksi-poin-siswa.show', $peringatan->sanksiPoinSiswa) }}">Sanksi</a>
                                    @endif
                                    @if($siswa)
                                        <a class="button button-primary button-sm" href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}">Profil</a>
                                    @endif
                                    @izin('poin_siswa.pendampingan_kelola')
                                        @if($pendampinganAktif)
                                            <a class="button button-dark button-sm" href="{{ route('pendampingan-siswa.edit', $pendampinganAktif) }}">Lanjutkan</a>
                                        @elseif($siswa)
                                            <a class="button button-dark button-sm" href="{{ route('pendampingan-siswa.create', ['peringatan_id' => $peringatan->id]) }}">Tindak Lanjuti</a>
                                        @endif
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Tidak ada peringatan yang sesuai dengan filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse($daftarPeringatan as $peringatan)
                @php
                    $siswa = $peringatan->siswa;
                    $kelas = $siswa?->anggotaKelas?->first()?->kelas;
                    $pendampinganAktif = $siswa?->pendampinganSiswa?->first();
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $kelas?->nama ?: '-' }} · {{ $peringatan->labelJenis() }}</p>
                        </div>
                        <span class="badge {{ $peringatan->tingkat === 'penting' ? 'warning-level-important' : 'warning-level-warning' }}">{{ $peringatan->labelTingkat() }}</span>
                    </div>
                    <p class="help-text" style="margin-top:12px">{{ $peringatan->pesan }}</p>
                    <p class="person-meta" style="margin-top:9px">Siklus {{ $peringatan->siklus }} · {{ $peringatan->terakhir_terdeteksi_pada?->translatedFormat('d M Y, H:i') }}</p>
                    @if($siswa)
                        <a class="button button-primary button-full" style="margin-top:12px" href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}">Lihat profil siswa</a>
                    @endif
                    @izin('poin_siswa.pendampingan_kelola')
                        @if($pendampinganAktif)
                            <a class="button button-dark button-full" style="margin-top:8px" href="{{ route('pendampingan-siswa.edit', $pendampinganAktif) }}">Lanjutkan tindak lanjut</a>
                        @elseif($siswa)
                            <a class="button button-dark button-full" style="margin-top:8px" href="{{ route('pendampingan-siswa.create', ['peringatan_id' => $peringatan->id]) }}">Tindak lanjuti</a>
                        @endif
                    @endizin
                </article>
            @empty
                <div class="empty-state">Tidak ada peringatan yang sesuai dengan filter ini.</div>
            @endforelse
        </div>
    </section>

    @if($daftarPeringatan->hasPages())
        <div style="margin-top:18px">{{ $daftarPeringatan->links() }}</div>
    @endif
@endsection
