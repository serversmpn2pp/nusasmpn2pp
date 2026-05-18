@extends('layouts.app')

@section('title', 'Detail Kelas - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail kelas</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kelas.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('kelas.edit', $kelas) }}" class="button button-dark">Edit</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data anggota kelas yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">{{ mb_substr($kelas->nama, 0, 3) }}</div>
                <h2>{{ $kelas->nama }}</h2>
                <p>{{ $kelas->tahunPelajaran?->nama ?: 'Tahun pelajaran belum tersedia' }}</p>

                <div style="margin-top: 16px;">
                    @if ($kelas->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @if ($kelas->aktif)
                <form action="{{ route('kelas.destroy', $kelas) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan kelas ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                </form>
            @endif
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Kelas</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $kelas->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Wali kelas</dt>
                        <dd>{{ $kelas->waliKelas?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tingkat</dt>
                        <dd>{{ $teks($kelas->tingkat) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kapasitas</dt>
                        <dd>{{ $teks($kelas->kapasitas) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Anggota</dt>
                        <dd>{{ $kelas->anggota_kelas_count }} siswa</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($kelas->keterangan) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Tambah Siswa ke Kelas</h2>

                @php
                    $kelasPenuh = $kelas->kapasitas && $kelas->anggota_kelas_count >= $kelas->kapasitas;
                @endphp

                @if ($kelasPenuh)
                    <p class="help-text">Kapasitas kelas sudah penuh. Keluarkan siswa dari kelas ini lebih dulu jika perlu mengganti anggota.</p>
                @elseif ($siswaTersedia->isEmpty())
                    <p class="help-text">Belum ada siswa aktif yang bebas dari kelas pada tahun pelajaran ini.</p>
                @else
                    <form action="{{ route('anggota-kelas.store', $kelas) }}" method="POST">
                        @csrf

                        <div class="form-grid">
                            <div class="field span-2">
                                <label for="siswa_id">Siswa</label>
                                <select id="siswa_id" name="siswa_id" class="select" required>
                                    <option value="">Pilih siswa</option>
                                    @foreach ($siswaTersedia as $siswa)
                                        <option value="{{ $siswa->id }}" @selected((string) old('siswa_id') === (string) $siswa->id)>
                                            {{ $siswa->nama_lengkap }}{{ $siswa->nisn ? ' - NISN ' . $siswa->nisn : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="nomor_absen">Nomor absen</label>
                                <input id="nomor_absen" name="nomor_absen" type="number" min="1" max="500" value="{{ old('nomor_absen') }}" class="input">
                            </div>

                            <div class="field">
                                <label for="tanggal_masuk">Tanggal masuk</label>
                                <input id="tanggal_masuk" name="tanggal_masuk" type="date" value="{{ old('tanggal_masuk', $kelas->tahunPelajaran?->tanggal_mulai?->format('Y-m-d')) }}" class="input">
                            </div>

                            <div class="field span-2">
                                <label for="keterangan">Keterangan</label>
                                <textarea id="keterangan" name="keterangan" class="textarea">{{ old('keterangan') }}</textarea>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="button button-primary">Tambah ke kelas</button>
                        </div>
                    </form>
                @endif
            </section>

            <section class="panel">
                <div class="desktop-only table-wrap">
                    <table class="employee-table" style="min-width: 1100px;">
                        <thead>
                            <tr>
                                <th>No. absen</th>
                                <th>Siswa</th>
                                <th>Tanggal masuk</th>
                                <th>Keterangan</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($anggotaKelas as $item)
                                <tr>
                                    <td style="width: 120px;">
                                        <form id="ubah-anggota-desktop-{{ $item->id }}" action="{{ route('anggota-kelas.update', $item) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <input form="ubah-anggota-desktop-{{ $item->id }}" name="nomor_absen" type="number" min="1" max="500" value="{{ $item->nomor_absen }}" class="input input-sm">
                                    </td>
                                    <td>
                                        <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                        <p class="person-meta">NIS: {{ $item->siswa?->nis ?: '-' }} · NISN: {{ $item->siswa?->nisn ?: '-' }}</p>
                                    </td>
                                    <td style="width: 160px;">
                                        <input form="ubah-anggota-desktop-{{ $item->id }}" name="tanggal_masuk" type="date" value="{{ $item->tanggal_masuk ? $item->tanggal_masuk->format('Y-m-d') : '' }}" class="input input-sm">
                                    </td>
                                    <td>
                                        <input form="ubah-anggota-desktop-{{ $item->id }}" name="keterangan" type="text" value="{{ $item->keterangan }}" class="input input-sm">
                                    </td>
                                    <td>
                                        <div class="member-actions">
                                            <button form="ubah-anggota-desktop-{{ $item->id }}" type="submit" class="button button-dark">Simpan</button>
                                            <form action="{{ route('anggota-kelas.destroy', $item) }}" method="POST" onsubmit="return confirm('Keluarkan siswa ini dari kelas? Data siswa tidak akan dihapus.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="button button-danger">Keluarkan</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">Belum ada siswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($anggotaKelas as $item)
                        <article class="mobile-card">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                    <p class="person-meta">NISN: {{ $item->siswa?->nisn ?: '-' }}</p>
                                </div>
                                <span class="badge badge-active">No. {{ $item->nomor_absen ?: '-' }}</span>
                            </div>

                            <form id="ubah-anggota-mobile-{{ $item->id }}" action="{{ route('anggota-kelas.update', $item) }}" method="POST" style="margin-top: 14px;">
                                @csrf
                                @method('PATCH')

                                <div class="form-grid">
                                    <div class="field">
                                        <label for="nomor_absen_mobile_{{ $item->id }}">Nomor absen</label>
                                        <input id="nomor_absen_mobile_{{ $item->id }}" name="nomor_absen" type="number" min="1" max="500" value="{{ $item->nomor_absen }}" class="input input-sm">
                                    </div>

                                    <div class="field">
                                        <label for="tanggal_masuk_mobile_{{ $item->id }}">Tanggal masuk</label>
                                        <input id="tanggal_masuk_mobile_{{ $item->id }}" name="tanggal_masuk" type="date" value="{{ $item->tanggal_masuk ? $item->tanggal_masuk->format('Y-m-d') : '' }}" class="input input-sm">
                                    </div>

                                    <div class="field span-2">
                                        <label for="keterangan_mobile_{{ $item->id }}">Keterangan</label>
                                        <input id="keterangan_mobile_{{ $item->id }}" name="keterangan" type="text" value="{{ $item->keterangan }}" class="input input-sm">
                                    </div>
                                </div>
                            </form>

                            <div class="member-actions" style="margin-top: 14px;">
                                <button form="ubah-anggota-mobile-{{ $item->id }}" type="submit" class="button button-dark">Simpan</button>
                                <form action="{{ route('anggota-kelas.destroy', $item) }}" method="POST" onsubmit="return confirm('Keluarkan siswa ini dari kelas? Data siswa tidak akan dihapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button button-danger">Keluarkan</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada siswa di kelas ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
