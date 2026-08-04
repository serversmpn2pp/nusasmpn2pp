@extends('layouts.app')

@section('title', 'Siswa Wali Saya - NUSA')

@section('content')
    <style>
        .guardian-summary{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:20px}
        .guardian-stat{border-top:4px solid var(--primary);min-width:0;padding:18px}
        .guardian-stat.is-gold{border-top-color:var(--secondary)}
        .guardian-stat-label{color:var(--muted);font-size:13px;font-weight:700;margin:0}
        .guardian-stat-value{color:var(--primary-dark);display:block;font-size:30px;font-weight:900;line-height:1;margin:10px 0 7px}
        .guardian-stat-note{color:var(--muted);font-size:12px;margin:0}
        .guardian-filter{margin-bottom:20px}
        .guardian-filter .form-grid{align-items:end;grid-template-columns:2fr repeat(2,minmax(160px,1fr))}
        .guardian-student-cell{align-items:center;display:flex;gap:12px;min-width:220px}
        .guardian-student-cell>div:last-child{min-width:0}
        .guardian-student-cell .person-name{overflow-wrap:anywhere}
        .guardian-class-name{color:var(--primary-dark);font-weight:800;margin:0}
        .guardian-point{color:var(--primary-dark);font-size:20px;font-weight:900;line-height:1}
        .guardian-context{align-items:center;display:flex;gap:8px;flex-wrap:wrap;margin-top:7px}
        .guardian-context .badge{font-size:11px}
        .guardian-empty{padding:42px 20px;text-align:center}
        .guardian-empty strong{color:var(--primary-dark);display:block;font-size:17px;margin-bottom:6px}
        @media(max-width:1020px){.guardian-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.guardian-filter .form-grid{grid-template-columns:1fr 1fr}.guardian-filter .field:first-child{grid-column:1/-1}}
        @media(max-width:640px){
            .guardian-summary{grid-template-columns:1fr 1fr;gap:10px}
            .guardian-stat{padding:14px}
            .guardian-stat-value{font-size:25px}
            .guardian-filter .form-grid{grid-template-columns:1fr}
            .guardian-filter .field:first-child{grid-column:auto}
            .guardian-filter .actions{display:grid;grid-template-columns:1fr 1fr;width:100%}
            .guardian-filter .button{justify-content:center;width:100%}
            .guardian-mobile-meta{display:grid;gap:10px;grid-template-columns:1fr 1fr;margin-top:14px}
            .guardian-mobile-meta div{min-width:0}
            .guardian-mobile-meta span{color:var(--muted);display:block;font-size:11px;font-weight:700;margin-bottom:3px;text-transform:uppercase}
            .guardian-mobile-meta strong{color:var(--text);display:block;font-size:13px;overflow-wrap:anywhere}
        }
    </style>

    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $jenisKelamin = fn (?string $value) => $value === 'L' ? 'Laki-laki' : ($value === 'P' ? 'Perempuan' : '-');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Guru Wali</p>
            <h1 class="page-title">Siswa Wali Saya</h1>
            <p class="page-subtitle">
                Siswa lintas kelas yang menjadi tanggung jawab pendampingan Anda
                @if($tahunPelajaran)
                    pada tahun pelajaran {{ $tahunPelajaran->nama }}.
                @else
                    .
                @endif
            </p>
        </div>
    </div>

    <section class="guardian-summary" aria-label="Ringkasan siswa wali">
        <div class="panel guardian-stat">
            <p class="guardian-stat-label">Siswa dampingan</p>
            <strong class="guardian-stat-value">{{ number_format($ringkasan['siswa'], 0, ',', '.') }}</strong>
            <p class="guardian-stat-note">Penugasan aktif</p>
        </div>
        <div class="panel guardian-stat">
            <p class="guardian-stat-label">Asal kelas</p>
            <strong class="guardian-stat-value">{{ number_format($ringkasan['kelas'], 0, ',', '.') }}</strong>
            <p class="guardian-stat-note">Lintas tingkat diperbolehkan</p>
        </div>
        <div class="panel guardian-stat is-gold">
            <p class="guardian-stat-label">Laki-laki / Perempuan</p>
            <strong class="guardian-stat-value">{{ $ringkasan['laki_laki'] }} / {{ $ringkasan['perempuan'] }}</strong>
            <p class="guardian-stat-note">Komposisi siswa dampingan</p>
        </div>
        <div class="panel guardian-stat is-gold">
            <p class="guardian-stat-label">Memiliki poin</p>
            <strong class="guardian-stat-value">{{ number_format($ringkasan['memiliki_poin'], 0, ',', '.') }}</strong>
            <p class="guardian-stat-note">Poin resmi tahun aktif</p>
        </div>
    </section>

    <form method="GET" class="panel panel-pad guardian-filter">
        <div class="form-grid">
            <div class="field">
                <label for="kata_kunci">Cari siswa</label>
                <input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Nama, NIS, atau NISN">
            </div>
            <div class="field">
                <label for="tingkat">Tingkat</label>
                <select id="tingkat" name="tingkat" class="select">
                    <option value="">Semua tingkat</option>
                    <option value="7" @selected($tingkat === 7)>VII</option>
                    <option value="8" @selected($tingkat === 8)>VIII</option>
                    <option value="9" @selected($tingkat === 9)>IX</option>
                </select>
            </div>
            <div class="field">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id" class="select">
                    <option value="">Semua kelas</option>
                    @foreach([7 => 'Kelas VII', 8 => 'Kelas VIII', 9 => 'Kelas IX'] as $nilaiTingkat => $labelTingkat)
                        @if($daftarKelas->where('tingkat', $nilaiTingkat)->isNotEmpty())
                            <optgroup label="{{ $labelTingkat }}">
                                @foreach($daftarKelas->where('tingkat', $nilaiTingkat) as $kelas)
                                    <option value="{{ $kelas->id }}" @selected((string)$kelasId === (string)$kelas->id)>{{ $kelas->nama }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                    @foreach($daftarKelas->whereNotIn('tingkat', [7, 8, 9]) as $kelas)
                        <option value="{{ $kelas->id }}" @selected((string)$kelasId === (string)$kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:14px">
            <a href="{{ route('siswa-wali-saya.index') }}" class="button button-muted">Reset</a>
            <button class="button button-dark" type="submit">Tampilkan</button>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas Saat Ini</th>
                        <th>Identitas</th>
                        <th>Pendampingan</th>
                        <th>Mulai Didampingi</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarSiswa as $siswa)
                        @php
                            $anggota = $siswa->anggotaKelas->first();
                            $penugasan = $siswa->penugasanGuruWaliSiswa->first();
                            $poin = max(0, (int) $siswa->total_poin);
                        @endphp
                        <tr>
                            <td>
                                <div class="guardian-student-cell">
                                    <div class="avatar avatar-sm">
                                        @if($siswa->foto)
                                            <img src="{{ asset('storage/'.$siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                                        @else
                                            {{ strtoupper(mb_substr($siswa->nama_lengkap, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <p class="person-name">{{ $siswa->nama_lengkap }}</p>
                                        <p class="person-meta">{{ $jenisKelamin($siswa->jenis_kelamin) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <p class="guardian-class-name">{{ $anggota?->kelas?->nama ?: '-' }}</p>
                                <p class="person-meta">No. absen {{ $anggota?->nomor_absen ?: '-' }}</p>
                            </td>
                            <td>
                                <p class="person-name">NISN {{ $teks($siswa->nisn) }}</p>
                                <p class="person-meta">NIS {{ $teks($siswa->nis) }}</p>
                            </td>
                            <td>
                                <span class="guardian-point">{{ $poin }}</span>
                                <div class="guardian-context">
                                    <span class="badge {{ $poin > 0 ? 'badge-warning' : 'badge-active' }}">poin resmi</span>
                                    <span class="person-meta">{{ $siswa->jumlah_laporan }} laporan</span>
                                </div>
                            </td>
                            <td>{{ $penugasan?->tanggal_mulai?->format('d/m/Y') ?: '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('siswa-wali-saya.show', $siswa) }}" class="button button-primary button-sm">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="guardian-empty">
                                    <strong>Belum ada siswa yang ditampilkan</strong>
                                    <span>{{ $ringkasan['siswa'] > 0 ? 'Coba ubah pencarian atau filter kelas.' : 'Admin belum menugaskan siswa kepada akun Guru Wali ini.' }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse($daftarSiswa as $siswa)
                @php
                    $anggota = $siswa->anggotaKelas->first();
                    $penugasan = $siswa->penugasanGuruWaliSiswa->first();
                    $poin = max(0, (int) $siswa->total_poin);
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-main">
                        <div class="avatar avatar-md">
                            @if($siswa->foto)
                                <img src="{{ asset('storage/'.$siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                            @else
                                {{ strtoupper(mb_substr($siswa->nama_lengkap, 0, 1)) }}
                            @endif
                        </div>
                        <div style="min-width:0">
                            <p class="person-name">{{ $siswa->nama_lengkap }}</p>
                            <p class="person-meta">{{ $anggota?->kelas?->nama ?: 'Belum ditempatkan' }} &middot; NISN {{ $teks($siswa->nisn) }}</p>
                        </div>
                    </div>

                    <div class="guardian-mobile-meta">
                        <div><span>Jenis kelamin</span><strong>{{ $jenisKelamin($siswa->jenis_kelamin) }}</strong></div>
                        <div><span>Nomor absen</span><strong>{{ $anggota?->nomor_absen ?: '-' }}</strong></div>
                        <div><span>Poin resmi</span><strong>{{ $poin }} poin</strong></div>
                        <div><span>Mulai didampingi</span><strong>{{ $penugasan?->tanggal_mulai?->format('d/m/Y') ?: '-' }}</strong></div>
                    </div>

                    <div class="actions" style="margin-top:14px">
                        <a href="{{ route('siswa-wali-saya.show', $siswa) }}" class="button button-primary button-full">Lihat Data Siswa</a>
                    </div>
                </article>
            @empty
                <div class="guardian-empty">
                    <strong>Belum ada siswa yang ditampilkan</strong>
                    <span>{{ $ringkasan['siswa'] > 0 ? 'Coba ubah pencarian atau filter kelas.' : 'Admin belum menugaskan siswa kepada akun Guru Wali ini.' }}</span>
                </div>
            @endforelse
        </div>
    </section>

    @if($daftarSiswa->hasPages())
        {{ $daftarSiswa->links() }}
    @endif
@endsection
