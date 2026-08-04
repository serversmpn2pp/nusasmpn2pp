@extends('layouts.app')

@section('title', ($konteksGuruWali ? 'Tindak Lanjut Siswa Wali' : 'Tindak Lanjut Siswa').' - NUSA')

@section('content')
    @php
        $ruteIndex = $konteksGuruWali ? 'pendampingan-siswa-wali.index' : 'pendampingan-siswa.index';
        $ruteEdit = $konteksGuruWali ? 'pendampingan-siswa-wali.edit' : 'pendampingan-siswa.edit';
        $ruteProfil = $konteksGuruWali ? 'rekap-poin-siswa-wali.show' : 'rekap-poin-siswa.show';
    @endphp
    <style>
        .follow-summary{display:grid;gap:14px;grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:20px;max-width:720px}
        .follow-stat{background:#fff;border:1px solid var(--border);border-radius:8px;padding:18px}
        .follow-stat.is-process{border-top:4px solid var(--secondary)}
        .follow-stat.is-done{border-top:4px solid #2f8f5b}
        .follow-stat span{color:var(--muted);display:block;font-size:13px;font-weight:700}
        .follow-stat strong{color:var(--primary-dark);display:block;font-size:30px;line-height:1;margin-top:10px}
        .follow-note{color:var(--muted);font-size:13px;line-height:1.5;max-width:380px}
        @media(max-width:640px){.follow-summary{grid-template-columns:1fr}.follow-stat{padding:15px}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $konteksGuruWali ? 'Guru Wali' : 'Kesiswaan & BK' }}</p>
            <h1 class="page-title">{{ $konteksGuruWali ? 'Tindak Lanjut Siswa Wali' : 'Tindak Lanjut Siswa' }}</h1>
            <p class="page-subtitle">{{ $konteksGuruWali ? 'Pantau tindak lanjut khusus siswa yang menjadi tanggung jawab pendampingan Anda.' : 'Pantau pendampingan siswa yang masih berjalan dan yang telah diselesaikan.' }}</p>
        </div>
        @unless($konteksGuruWali)
            <a class="button button-muted" href="{{ route('peringatan-dini-siswa.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}">
                Buka Peringatan Dini
            </a>
        @endunless
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif
    @if(session('gagal'))
        <div class="alert alert-danger">{{ session('gagal') }}</div>
    @endif

    @php
        $tautanStatus = fn (string $nilai) => route($ruteIndex, array_filter([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'kelas_id' => $kelasId,
            'status' => $nilai,
            'kata_kunci' => $kataKunci,
        ]));
    @endphp
    <section class="follow-summary" aria-label="Ringkasan tindak lanjut">
        <a class="follow-stat is-process" href="{{ $tautanStatus('dalam_proses') }}">
            <span>Masih dalam proses</span>
            <strong>{{ number_format($ringkasan['dalam_proses'], 0, ',', '.') }}</strong>
        </a>
        <a class="follow-stat is-done" href="{{ $tautanStatus('selesai') }}">
            <span>Sudah selesai</span>
            <strong>{{ number_format($ringkasan['selesai'], 0, ',', '.') }}</strong>
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
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="">Semua status</option>
                    @foreach(\App\Models\PendampinganSiswa::DAFTAR_STATUS as $kode => $label)
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
            <a href="{{ route($ruteIndex) }}" class="button button-muted">Reset</a>
            <button class="button button-dark">Terapkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width:920px">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Tindakan</th>
                        <th>Petugas</th>
                        <th>Catatan/Hasil</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarPendampingan as $item)
                        @php $kelas = $item->siswa?->anggotaKelas?->first()?->kelas; @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                <p class="person-meta">{{ $kelas?->nama ?: '-' }} &middot; NISN {{ $item->siswa?->nisn ?: '-' }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $item->labelJenis() }}</p>
                                <p class="person-meta">{{ $item->tanggal_tindak_lanjut?->translatedFormat('d M Y') }}</p>
                                @if($item->peringatanDiniSiswa)
                                    <p class="person-meta">Dari: {{ $item->peringatanDiniSiswa->labelJenis() }}</p>
                                @endif
                            </td>
                            <td>{{ $item->petugasPegawai?->nama_lengkap ?: 'Belum ditentukan' }}</td>
                            <td>
                                <p class="follow-note">{{ str($item->hasil ?: $item->catatan)->limit(115) }}</p>
                            </td>
                            <td>
                                <span class="badge {{ $item->status === 'selesai' ? 'badge-active' : 'badge-warning' }}">
                                    {{ $item->labelStatus() }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="actions" style="justify-content:flex-end">
                                    <a class="button button-muted button-sm" href="{{ route($ruteProfil, ['siswa' => $item->siswa_id, 'tahun_pelajaran_id' => $item->tahun_pelajaran_id]) }}">Profil</a>
                                    @izin('poin_siswa.pendampingan_kelola')
                                        <a class="button button-primary button-sm" href="{{ route($ruteEdit, $item) }}">
                                            {{ $item->status === 'selesai' ? 'Lihat/Edit' : 'Lanjutkan' }}
                                        </a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada tindak lanjut yang sesuai dengan filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse($daftarPendampingan as $item)
                @php $kelas = $item->siswa?->anggotaKelas?->first()?->kelas; @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                            <p class="person-meta">{{ $kelas?->nama ?: '-' }} &middot; {{ $item->tanggal_tindak_lanjut?->translatedFormat('d M Y') }}</p>
                        </div>
                        <span class="badge {{ $item->status === 'selesai' ? 'badge-active' : 'badge-warning' }}">{{ $item->labelStatus() }}</span>
                    </div>
                    <p class="person-name" style="margin-top:12px">{{ $item->labelJenis() }}</p>
                    <p class="help-text" style="margin-top:5px">{{ str($item->hasil ?: $item->catatan)->limit(140) }}</p>
                    <p class="person-meta" style="margin-top:8px">Petugas: {{ $item->petugasPegawai?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                    <div class="actions" style="margin-top:12px">
                        <a class="button button-muted button-sm" href="{{ route($ruteProfil, ['siswa' => $item->siswa_id, 'tahun_pelajaran_id' => $item->tahun_pelajaran_id]) }}">Profil</a>
                        @izin('poin_siswa.pendampingan_kelola')
                            <a class="button button-primary button-sm" href="{{ route($ruteEdit, $item) }}">
                                {{ $item->status === 'selesai' ? 'Lihat/Edit' : 'Lanjutkan' }}
                            </a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada tindak lanjut yang sesuai dengan filter ini.</div>
            @endforelse
        </div>
    </section>

    @if($daftarPendampingan->hasPages())
        <div style="margin-top:18px">{{ $daftarPendampingan->links() }}</div>
    @endif
@endsection
