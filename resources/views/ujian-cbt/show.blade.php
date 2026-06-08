@extends('layouts.app')

@section('title', 'Detail Paket CBT - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $badgeStatus = $ujianCbt->status === 'terjadwal' || $ujianCbt->status === 'berlangsung'
            ? 'badge-active'
            : ($ujianCbt->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning');
        $soalPaket = $ujianCbt->soalUjianCbt
            ->sortBy(fn ($item) => $item->nomor_urut ?? PHP_INT_MAX)
            ->values();
        $jumlahSoalPaket = $soalPaket->count();
        $totalBobotPaket = $soalPaket->sum(fn ($item) => (float) $item->bobot);
        $jumlahSesi = $ujianCbt->sesi_ujian_cbt_count ?? $ujianCbt->sesiUjianCbt->count();
        $jumlahPeserta = $ujianCbt->peserta_ujian_cbt_count ?? $ujianCbt->pesertaUjianCbt->count();
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Detail paket CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Kembali</a>
            @izin('cbt.kelola')
                <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-primary">Monitoring</a>
                <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Hasil</a>
                <a href="{{ route('ujian-cbt.ruang.index', $ujianCbt) }}" class="button button-muted">Ruang</a>
                <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-muted">Peserta & sesi</a>
                <a href="{{ route('ujian-cbt.kartu-peserta.index', $ujianCbt) }}" class="button button-muted">Kartu peserta</a>
                <a href="{{ route('ujian-cbt.soal.edit', $ujianCbt) }}" class="button button-muted">Kelola soal</a>
                <form action="{{ route('ujian-cbt.koreksi-otomatis.store', $ujianCbt) }}" method="POST" onsubmit="return confirm('Jalankan koreksi otomatis untuk jawaban objektif pada paket ini?')">
                    @csrf
                    <button type="submit" class="button button-muted">Koreksi otomatis</button>
                </form>
                <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Koreksi manual</a>
                <a href="{{ route('ujian-cbt.edit', $ujianCbt) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">CB</div>
                <h2>{{ $ujianCbt->nama }}</h2>
                <p>{{ $ujianCbt->kode }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    <span class="badge {{ $badgeStatus }}">{{ $ujianCbt->labelStatus() }}</span>
                    <span class="badge {{ $ujianCbt->token ? 'badge-active' : 'badge-muted' }}">{{ $ujianCbt->token ? 'Token' : 'Tanpa token' }}</span>
                </div>
            </div>

            @izin('cbt.kelola')
                @if ($ujianCbt->status !== 'nonaktif')
                    <form action="{{ route('ujian-cbt.destroy', $ujianCbt) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan paket CBT ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Paket</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Jenis ujian</dt>
                        <dd>{{ $ujianCbt->jenisUjianCbt?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Mata pelajaran</dt>
                        <dd>{{ $ujianCbt->mataPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $ujianCbt->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Semester</dt>
                        <dd>{{ ucfirst($ujianCbt->semester) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tingkat</dt>
                        <dd>Kelas {{ $ujianCbt->tingkat }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jumlah soal</dt>
                        <dd>{{ $ujianCbt->jumlah_soal }} soal</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Durasi</dt>
                        <dd>{{ $ujianCbt->durasi_menit }} menit</dd>
                    </div>
                    <div class="detail-item">
                        <dt>KKM</dt>
                        <dd>{{ $teks($ujianCbt->kkm) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Jadwal</dt>
                        <dd>{{ $ujianCbt->labelWaktu() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Token</dt>
                        <dd>{{ $teks($ujianCbt->token) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Dibuat oleh</dt>
                        <dd>{{ $ujianCbt->dibuatOleh?->nama ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Pengaturan CBT</h2>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Acak soal</dt><dd>{{ $ujianCbt->acak_soal ? 'Ya' : 'Tidak' }}</dd></div>
                    <div class="detail-item"><dt>Acak jawaban</dt><dd>{{ $ujianCbt->acak_jawaban ? 'Ya' : 'Tidak' }}</dd></div>
                    <div class="detail-item"><dt>Satu perangkat</dt><dd>{{ $ujianCbt->batasi_satu_perangkat ? 'Dibatasi' : 'Tidak dibatasi' }}</dd></div>
                    <div class="detail-item"><dt>Pindah tab</dt><dd>{{ $ujianCbt->deteksi_pindah_tab ? 'Dicatat' : 'Tidak dicatat' }}</dd></div>
                    <div class="detail-item"><dt>Fullscreen</dt><dd>{{ $ujianCbt->wajib_fullscreen ? 'Wajib' : 'Tidak wajib' }}</dd></div>
                    <div class="detail-item"><dt>Hasil siswa</dt><dd>{{ $ujianCbt->tampilkan_hasil ? 'Ditampilkan' : 'Tidak ditampilkan' }}</dd></div>
                    <div class="detail-item span-2"><dt>Petunjuk</dt><dd style="white-space: pre-line;">{{ $teks($ujianCbt->petunjuk) }}</dd></div>
                    <div class="detail-item span-2"><dt>Catatan internal</dt><dd style="white-space: pre-line;">{{ $teks($ujianCbt->keterangan) }}</dd></div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <div style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <h2 class="panel-title">Soal Paket</h2>
                    @izin('cbt.kelola')
                        <a href="{{ route('ujian-cbt.soal.edit', $ujianCbt) }}" class="button button-muted">Atur soal</a>
                    @endizin
                </div>

                <div class="stats-grid" style="margin-top: 16px;">
                    <div class="panel stat">
                        <p class="stat-label">Soal terhubung</p>
                        <p class="stat-value">{{ $jumlahSoalPaket }}</p>
                    </div>
                    <div class="panel stat active">
                        <p class="stat-label">Target tampil</p>
                        <p class="stat-value">{{ $ujianCbt->jumlah_soal }}</p>
                    </div>
                    <div class="panel stat">
                        <p class="stat-label">Total bobot</p>
                        <p class="stat-value">{{ number_format($totalBobotPaket, 2, ',', '.') }}</p>
                    </div>
                </div>

                <div class="table-wrap" style="margin-top: 14px;">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Soal</th>
                                <th>Jenis</th>
                                <th>Kesulitan</th>
                                <th>Bobot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($soalPaket as $index => $relasiSoal)
                                @php($soal = $relasiSoal->soalCbt)
                                <tr>
                                    <td>{{ $relasiSoal->nomor_urut ?: $index + 1 }}</td>
                                    <td>
                                        <p class="person-name">{{ $soal?->kode ?: '-' }}</p>
                                        <p class="person-meta">{{ $soal ? str(strip_tags($soal->pertanyaan))->limit(120) : 'Soal tidak ditemukan' }}</p>
                                    </td>
                                    <td>{{ $soal?->labelJenis() ?: '-' }}</td>
                                    <td>{{ $soal?->labelKesulitan() ?: '-' }}</td>
                                    <td>{{ number_format((float) $relasiSoal->bobot, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        Belum ada soal yang dihubungkan ke paket CBT ini.
                                        @izin('cbt.kelola')
                                            <div style="margin-top: 12px;">
                                                <a href="{{ route('ujian-cbt.soal.edit', $ujianCbt) }}" class="button button-primary">Pilih soal dari bank soal</a>
                                            </div>
                                        @endizin
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel panel-pad">
                <div style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <h2 class="panel-title">Peserta dan Sesi</h2>
                    @izin('cbt.kelola')
                        <div class="actions">
                            <a href="{{ route('ujian-cbt.monitoring.index', $ujianCbt) }}" class="button button-primary">Monitoring</a>
                            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Hasil CBT</a>
                            <a href="{{ route('ujian-cbt.ruang.index', $ujianCbt) }}" class="button button-muted">Ruang</a>
                            <a href="{{ route('ujian-cbt.koreksi-manual.index', $ujianCbt) }}" class="button button-muted">Koreksi manual</a>
                            <a href="{{ route('ujian-cbt.kartu-peserta.index', $ujianCbt) }}" class="button button-muted">Cetak kartu</a>
                            <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-muted">Atur peserta</a>
                        </div>
                    @endizin
                </div>

                <div class="stats-grid" style="margin-top: 16px;">
                    <div class="panel stat">
                        <p class="stat-label">Sesi ujian</p>
                        <p class="stat-value">{{ $jumlahSesi }}</p>
                    </div>
                    <div class="panel stat active">
                        <p class="stat-label">Peserta CBT</p>
                        <p class="stat-value">{{ $jumlahPeserta }}</p>
                    </div>
                    <div class="panel stat">
                        <p class="stat-label">Kelas peserta</p>
                        <p class="stat-value">{{ $ujianCbt->kelasUjianCbt->count() }}</p>
                    </div>
                </div>

                <div class="table-wrap" style="margin-top: 14px;">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Sesi</th>
                                <th>Waktu</th>
                                <th>Kapasitas</th>
                                <th>Peserta</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ujianCbt->sesiUjianCbt->sortBy('kode') as $sesi)
                                <tr>
                                    <td>
                                        <p class="person-name">{{ $sesi->nama }}</p>
                                        <p class="person-meta">{{ $sesi->kode }}</p>
                                    </td>
                                    <td>{{ $sesi->labelWaktu() }}</td>
                                    <td>{{ $sesi->kapasitas ?: 'Tidak dibatasi' }}</td>
                                    <td>{{ $sesi->pesertaUjianCbt->count() }}</td>
                                    <td>
                                        <span class="badge {{ $sesi->status === 'aktif' ? 'badge-active' : ($sesi->status === 'nonaktif' ? 'badge-inactive' : 'badge-warning') }}">{{ $sesi->labelStatus() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        Belum ada sesi CBT.
                                        @izin('cbt.kelola')
                                            <div style="margin-top: 12px;">
                                                <a href="{{ route('ujian-cbt.peserta.index', $ujianCbt) }}" class="button button-primary">Buat sesi dan peserta</a>
                                            </div>
                                        @endizin
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Kelas Peserta</h2>
                <div class="table-wrap" style="margin-top: 14px;">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Komponen nilai</th>
                                <th>Guru mapel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ujianCbt->kelasUjianCbt->sortBy(fn ($item) => $item->kelas?->nama) as $kelasUjian)
                                <tr>
                                    <td>
                                        <p class="person-name">{{ $kelasUjian->kelas?->nama ?: '-' }}</p>
                                        <p class="person-meta">Kelas {{ $kelasUjian->kelas?->tingkat ?: '-' }}</p>
                                    </td>
                                    <td>{{ $kelasUjian->komponenNilai?->nama ?: 'Tanpa target nilai' }}</td>
                                    <td>{{ $kelasUjian->komponenNilai?->guruMataPelajaran?->pegawai?->nama_lengkap ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="empty-state">Belum ada kelas peserta.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection
