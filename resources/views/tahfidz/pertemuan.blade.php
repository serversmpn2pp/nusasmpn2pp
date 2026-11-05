@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Pertemuan Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Pertemuan</p><h1 class="page-title">{{ $pertemuan->tanggal->locale('id')->translatedFormat('l, j F Y') }}</h1><p class="page-subtitle">{{ $kelompok->nama }} &middot; {{ $kelompok->tahunPelajaran->nama }}</p></div>
            <div class="actions"><a href="{{ route('tahfidz.show', $kelompok) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kelompok</a></div>
        </div>
        @include('tahfidz._pesan')

        <section class="tf-surface tf-document-status" aria-labelledby="tf-status-pertemuan">
            <header class="tf-band"><h2 id="tf-status-pertemuan">Status pertemuan</h2><span>{{ $pertemuan->selesai_pada ? 'Selesai' : 'Terbuka' }}</span></header>
            @if ($pertemuan->selesai_pada || ($bolehCatat && $setoran->total() > 0))
                <div class="tf-student-row">
                    @if ($pertemuan->selesai_pada)
                        <p class="person-meta">Dibagikan ke siswa dan orang tua pada {{ $pertemuan->selesai_pada->format('d/m/Y H:i') }}</p>
                    @else
                        <p class="person-meta">{{ $setoran->total() }} setoran siap dibagikan</p>
                        <form method="POST" action="{{ route('tahfidz.pertemuan.selesaikan', [$kelompok, $pertemuan]) }}" onsubmit="return confirm('Selesaikan pertemuan dan bagikan rangkuman ke siswa dan orang tua/wali? Setoran baru tidak dapat ditambahkan setelahnya.')">
                            @csrf
                            <button type="submit" class="button button-primary"><i data-lucide="check" aria-hidden="true"></i> Selesaikan pertemuan</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        @if ($bolehCatat)
            @php
                $pilihSiswa = old('anggota_tahfidz_id', session('tahfidz_anggota'));
                $pilihSurah = old('nomor_surah');
                $token = old('token_setoran');
                $nilai = old('nilai');
                $catatan = old('catatan');
            @endphp
            <section class="tf-surface" aria-labelledby="tf-input-setoran">
                <header class="tf-band"><h2 id="tf-input-setoran">Catat setoran</h2></header>
                @if ($peserta->isEmpty())
                    <p class="empty-state">Belum ada siswa aktif di kelompok ini.</p>
                @else
                    <form method="POST" action="{{ route('tahfidz.setoran.store', [$kelompok, $pertemuan]) }}">
                        @csrf
                        <input type="hidden" name="token_setoran" value="{{ is_string($token) && \Illuminate\Support\Str::isUuid($token) ? $token : \Illuminate\Support\Str::uuid() }}">
                        <div class="tf-form-body form-grid">
                            <div class="field"><label for="tf-siswa-setoran">Siswa</label>
                                <select id="tf-siswa-setoran" name="anggota_tahfidz_id" class="select" required>
                                    <option value="">Pilih siswa</option>
                                    @foreach ($peserta as $item)
                                        <option value="{{ $item->id }}" @selected(is_numeric($pilihSiswa) && (int) $pilihSiswa === $item->id)>{{ $item->siswa->nama_lengkap }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field"><label for="tf-surah-setoran">Surah</label>
                                <select id="tf-surah-setoran" name="nomor_surah" class="select" required>
                                    <option value="">Pilih surah Juz 30</option>
                                    @foreach ($surah as $item)
                                        <option value="{{ $item->nomor }}" @selected(is_numeric($pilihSurah) && (int) $pilihSurah === $item->nomor)>{{ $item->nomor }}. {{ $item->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field"><label for="tf-nilai-setoran">Nilai (0-100)</label>
                                <input id="tf-nilai-setoran" name="nilai" class="input" inputmode="decimal" maxlength="6" pattern="[0-9]{1,3}([,.][0-9]{1,2})?" value="{{ is_scalar($nilai) ? $nilai : '' }}" required>
                            </div>
                            <div class="field"><label for="tf-catatan-setoran">Catatan (opsional)</label>
                                <textarea id="tf-catatan-setoran" name="catatan" class="textarea" rows="2" maxlength="500">{{ is_string($catatan) ? $catatan : '' }}</textarea>
                            </div>
                        </div>
                        <div class="tf-save"><button type="submit" class="button button-primary"><i data-lucide="save" aria-hidden="true"></i> Simpan setoran</button></div>
                    </form>
                @endif
            </section>
        @endif

        @include('tahfidz._setoran', ['riwayatSiswa' => false])
    </div>
@endsection
