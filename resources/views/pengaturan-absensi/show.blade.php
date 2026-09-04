@extends('layouts.app')

@section('title', 'Detail Pengaturan Presensi - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi</p>
            <h1 class="page-title">Detail pengaturan presensi</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Kembali</a>
            @izin('absensi.pengaturan_kelola')
                <a href="{{ route('pengaturan-absensi.edit', $pengaturanAbsensi) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">JA</div>
                <h2>{{ $pengaturanAbsensi->labelHari() }}</h2>
                <p>
                    Masuk {{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk) }} -
                    @if ($pengaturanAbsensi->pulangJumatDibedakan())
                        Pulang siswi {{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang_perempuan) }},
                        laki-laki {{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang) }}
                    @else
                        Pulang {{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang) }}
                    @endif
                </p>

                <div style="margin-top: 16px;">
                    @if ($pengaturanAbsensi->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('absensi.pengaturan_kelola')
                @if ($pengaturanAbsensi->aktif)
                    <form action="{{ route('pengaturan-absensi.destroy', $pengaturanAbsensi) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan pengaturan presensi ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Jam Masuk</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Mulai scan</dt>
                        <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_masuk_mulai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jam masuk resmi</dt>
                        <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tutup scan</dt>
                        <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_masuk_selesai) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Jam Pulang</h2>
                @if ($pengaturanAbsensi->pulangJumatDibedakan())
                    <p class="help-text" style="margin-bottom: 16px;">Jadwal Jumat dibedakan. Data jenis kelamin yang belum lengkap mengikuti jadwal siswa laki-laki.</p>
                @endif
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>{{ $pengaturanAbsensi->pulangJumatDibedakan() ? 'Mulai scan laki-laki' : 'Mulai scan' }}</dt>
                        <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_pulang_mulai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>{{ $pengaturanAbsensi->pulangJumatDibedakan() ? 'Pulang resmi laki-laki' : 'Jam pulang resmi' }}</dt>
                        <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>{{ $pengaturanAbsensi->pulangJumatDibedakan() ? 'Tutup scan laki-laki' : 'Tutup scan' }}</dt>
                        <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_pulang_selesai) }}</dd>
                    </div>
                    @if ($pengaturanAbsensi->pulangJumatDibedakan())
                        <div class="detail-item">
                            <dt>Mulai scan siswi</dt>
                            <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_pulang_perempuan_mulai) }}</dd>
                        </div>
                        <div class="detail-item">
                            <dt>Pulang resmi siswi</dt>
                            <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang_perempuan) }}</dd>
                        </div>
                        <div class="detail-item">
                            <dt>Tutup scan siswi</dt>
                            <dd>{{ $pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_scan_pulang_perempuan_selesai) }}</dd>
                        </div>
                    @endif
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($pengaturanAbsensi->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
