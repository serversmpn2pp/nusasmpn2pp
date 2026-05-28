@extends('layouts.app')

@section('title', 'Detail Jam Absensi Pegawai - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi Pegawai</p>
            <h1 class="page-title">Detail jam absensi pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pengaturan-absensi-pegawai.index') }}" class="button button-muted">Kembali</a>
            @izin('absensi.pengaturan_kelola')
                <a href="{{ route('pengaturan-absensi-pegawai.edit', $pengaturanAbsensiPegawai) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">JP</div>
                <h2>{{ $pengaturanAbsensiPegawai->nama_jadwal }}</h2>
                <p>{{ $pengaturanAbsensiPegawai->labelHari() }} · {{ $pengaturanAbsensiPegawai->labelSasaran() }}</p>

                <div style="margin-top: 16px;">
                    @if ($pengaturanAbsensiPegawai->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('absensi.pengaturan_kelola')
                @if ($pengaturanAbsensiPegawai->aktif)
                    <form action="{{ route('pengaturan-absensi-pegawai.destroy', $pengaturanAbsensiPegawai) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan pengaturan absensi pegawai ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Sasaran Jadwal</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Cakupan</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->labelCakupan() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Hari</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->labelHari() }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Sasaran</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->labelSasaran() }}</dd>
                    </div>
                    @if ($pengaturanAbsensiPegawai->pegawai)
                        <div class="detail-item">
                            <dt>NIP</dt>
                            <dd>{{ $teks($pengaturanAbsensiPegawai->pegawai->nip) }}</dd>
                        </div>
                        <div class="detail-item">
                            <dt>Jabatan/Jenis</dt>
                            <dd>{{ $teks($pengaturanAbsensiPegawai->pegawai->jabatan_utama ?: $pengaturanAbsensiPegawai->pegawai->jenis_pegawai) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Jam Masuk</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Mulai scan</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_masuk_mulai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jam masuk resmi</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_masuk) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tutup scan</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_masuk_selesai) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Jam Pulang</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Mulai scan</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_pulang_mulai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jam pulang resmi</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_pulang) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tutup scan</dt>
                        <dd>{{ $pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_scan_pulang_selesai) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($pengaturanAbsensiPegawai->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
