@extends('layouts.app')

@section('title', 'Detail Siswa Wali - NUSA')

@section('content')
    <style>
        .guardian-detail-summary{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:20px}
        .guardian-detail-stat{border-left:4px solid var(--primary);min-width:0;padding:16px}
        .guardian-detail-stat.is-gold{border-left-color:var(--secondary)}
        .guardian-detail-stat span{color:var(--muted);display:block;font-size:12px;font-weight:700;margin-bottom:7px}
        .guardian-detail-stat strong{color:var(--primary-dark);display:block;font-size:18px;overflow-wrap:anywhere}
        .guardian-profile-meta{color:var(--muted);font-size:13px;margin:5px 0 0}
        .guardian-report-list{display:flex;flex-direction:column}
        .guardian-report-row{align-items:flex-start;border-top:1px solid var(--border);display:grid;gap:16px;grid-template-columns:minmax(110px,.7fr) minmax(180px,1.5fr) minmax(130px,1fr) auto;padding:15px 0}
        .guardian-report-row:first-child{border-top:0;padding-top:0}
        .guardian-report-row:last-child{padding-bottom:0}
        .guardian-report-label{color:var(--muted);font-size:11px;font-weight:700;margin:0 0 4px;text-transform:uppercase}
        .guardian-report-value{color:var(--text);font-size:13px;font-weight:700;margin:0;overflow-wrap:anywhere}
        .guardian-report-points{color:var(--primary-dark);font-size:18px;font-weight:900;white-space:nowrap}
        @media(max-width:980px){.guardian-detail-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.guardian-report-row{grid-template-columns:1fr 1fr}}
        @media(max-width:640px){
            .guardian-detail-summary{grid-template-columns:1fr 1fr;gap:10px}
            .guardian-detail-stat{padding:13px}
            .guardian-detail-stat strong{font-size:15px}
            .guardian-report-row{grid-template-columns:1fr}
        }
    </style>

    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $tanggal = fn (mixed $value) => $value ? $value->format('d-m-Y') : '-';
        $tempatTanggalLahir = function ($siswa) use ($teks, $tanggal) {
            $bagian = array_filter([$siswa->tempat_lahir, $siswa->tanggal_lahir ? $tanggal($siswa->tanggal_lahir) : null]);

            return $bagian === [] ? '-' : implode(', ', $bagian);
        };
        $jenisKelamin = $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : '-');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Guru Wali</p>
            <h1 class="page-title">Detail Siswa Wali</h1>
            <p class="page-subtitle">Data siswa yang menjadi tanggung jawab pendampingan Anda.</p>
        </div>
        <div class="actions">
            <a href="{{ route('siswa-wali-saya.index') }}" class="button button-muted">Kembali</a>
            @izin('poin_siswa.lihat')
                <a href="{{ route('rekap-poin-siswa-wali.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaran?->id]) }}" class="button button-primary">Rekap Poin</a>
            @endizin
        </div>
    </div>

    <section class="guardian-detail-summary" aria-label="Ringkasan siswa">
        <div class="panel guardian-detail-stat">
            <span>Kelas saat ini</span>
            <strong>{{ $anggotaKelas?->kelas?->nama ?: '-' }}</strong>
        </div>
        <div class="panel guardian-detail-stat">
            <span>Nomor absen</span>
            <strong>{{ $anggotaKelas?->nomor_absen ?: '-' }}</strong>
        </div>
        <div class="panel guardian-detail-stat is-gold">
            <span>Poin resmi</span>
            <strong>{{ $totalPoin }} poin</strong>
        </div>
        <div class="panel guardian-detail-stat is-gold">
            <span>Riwayat laporan</span>
            <strong>{{ $jumlahLaporan }} laporan</strong>
        </div>
    </section>

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">
                    @if($siswa->foto)
                        <img src="{{ asset('storage/'.$siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                    @else
                        {{ strtoupper(mb_substr($siswa->nama_lengkap, 0, 1)) }}
                    @endif
                </div>
                <h2>{{ $siswa->nama_lengkap }}</h2>
                <p>NISN {{ $teks($siswa->nisn) }}</p>
                <p class="guardian-profile-meta">{{ $anggotaKelas?->kelas?->nama ?: 'Belum ditempatkan di kelas' }}</p>
                <div style="margin-top:16px">
                    <span class="badge {{ $siswa->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $siswa->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
            </div>

            <dl class="quick-facts" style="margin-top:22px">
                <div>
                    <dt>Mulai didampingi</dt>
                    <dd>{{ $tanggal($penugasan->tanggal_mulai) }}</dd>
                </div>
                <div>
                    <dt>Nomor SK</dt>
                    <dd>{{ $teks($penugasan->nomor_sk) }}</dd>
                </div>
                <div>
                    <dt>Tahun pelajaran</dt>
                    <dd>{{ $tahunPelajaran?->nama ?: '-' }}</dd>
                </div>
            </dl>
        </aside>

        <div class="section-stack" style="min-width:0">
            <section class="panel panel-pad">
                <h2 class="panel-title">Identitas Siswa</h2>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Nama lengkap</dt><dd>{{ $teks($siswa->nama_lengkap) }}</dd></div>
                    <div class="detail-item"><dt>Jenis kelamin</dt><dd>{{ $jenisKelamin }}</dd></div>
                    <div class="detail-item"><dt>NIS</dt><dd>{{ $teks($siswa->nis) }}</dd></div>
                    <div class="detail-item"><dt>NISN</dt><dd>{{ $teks($siswa->nisn) }}</dd></div>
                    <div class="detail-item"><dt>NIK</dt><dd>{{ $teks($siswa->nik) }}</dd></div>
                    <div class="detail-item"><dt>Tempat, tanggal lahir</dt><dd>{{ $tempatTanggalLahir($siswa) }}</dd></div>
                    <div class="detail-item"><dt>Agama</dt><dd>{{ $teks($siswa->agama) }}</dd></div>
                    <div class="detail-item"><dt>Sekolah asal</dt><dd>{{ $teks($siswa->sekolah_asal) }}</dd></div>
                    <div class="detail-item"><dt>Status dalam keluarga</dt><dd>{{ $teks($siswa->status_dalam_keluarga) }}</dd></div>
                    <div class="detail-item"><dt>Anak ke</dt><dd>{{ $teks($siswa->anak_ke) }}</dd></div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Orang Tua & Wali</h2>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Nama ayah</dt><dd>{{ $teks($siswa->nama_ayah) }}</dd></div>
                    <div class="detail-item"><dt>Nomor WA ayah</dt><dd>{{ $teks($siswa->nomor_wa_ayah) }}</dd></div>
                    <div class="detail-item"><dt>Pekerjaan ayah</dt><dd>{{ $teks($siswa->pekerjaan_ayah) }}</dd></div>
                    <div class="detail-item"><dt>Nama ibu</dt><dd>{{ $teks($siswa->nama_ibu) }}</dd></div>
                    <div class="detail-item"><dt>Nomor WA ibu</dt><dd>{{ $teks($siswa->nomor_wa_ibu) }}</dd></div>
                    <div class="detail-item"><dt>Pekerjaan ibu</dt><dd>{{ $teks($siswa->pekerjaan_ibu) }}</dd></div>
                    <div class="detail-item"><dt>Nama wali lain</dt><dd>{{ $teks($siswa->nama_wali) }}</dd></div>
                    <div class="detail-item"><dt>Hubungan wali</dt><dd>{{ $teks($siswa->hubungan_wali) }}</dd></div>
                    <div class="detail-item"><dt>Nomor WA wali lain</dt><dd>{{ $teks($siswa->nomor_wa_wali) }}</dd></div>
                    <div class="detail-item"><dt>Kontak presensi utama</dt><dd>{{ $teks($siswa->kontak_absensi_utama ? str($siswa->kontak_absensi_utama)->headline()->toString() : null) }}</dd></div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Alamat & Catatan</h2>
                <dl class="detail-grid">
                    <div class="detail-item span-2"><dt>Alamat</dt><dd style="white-space:pre-line">{{ $teks($siswa->alamat) }}</dd></div>
                    <div class="detail-item span-2"><dt>Keterangan data siswa</dt><dd style="white-space:pre-line">{{ $teks($siswa->keterangan) }}</dd></div>
                    @if(filled($penugasan->catatan))
                        <div class="detail-item span-2"><dt>Catatan penugasan Guru Wali</dt><dd style="white-space:pre-line">{{ $penugasan->catatan }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="panel panel-pad">
                <div class="page-header" style="align-items:center;margin-bottom:16px">
                    <div>
                        <h2 class="panel-title" style="margin-bottom:4px">Riwayat Pembinaan Terbaru</h2>
                        <p class="help-text">Lima laporan terakhir pada tahun pelajaran aktif.</p>
                    </div>
                    @izin('poin_siswa.lihat')
                        <a href="{{ route('pembinaan-siswa-wali.index', ['kata_kunci' => $siswa->nisn ?: $siswa->nama_lengkap]) }}" class="button button-muted button-sm">Lihat Semua</a>
                    @endizin
                </div>

                <div class="guardian-report-list">
                    @forelse($laporanTerbaru as $laporan)
                        <div class="guardian-report-row">
                            <div>
                                <p class="guardian-report-label">Tanggal</p>
                                <p class="guardian-report-value">{{ $tanggal($laporan->tanggal_kejadian) }}</p>
                            </div>
                            <div>
                                <p class="guardian-report-label">Laporan</p>
                                <p class="guardian-report-value">{{ $laporan->nomor_laporan }}</p>
                                <p class="person-meta">{{ $laporan->kategoriPembinaanSiswa?->nama ?: str($laporan->jenis_laporan)->replace('_', ' ')->headline() }}</p>
                            </div>
                            <div>
                                <p class="guardian-report-label">Status</p>
                                <p class="guardian-report-value">{{ str($laporan->status_verifikasi)->replace('_', ' ')->headline() }}</p>
                            </div>
                            <div class="guardian-report-points">{{ max(0, (int)$laporan->total_poin) }} poin</div>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada riwayat pembinaan pada tahun pelajaran aktif.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
