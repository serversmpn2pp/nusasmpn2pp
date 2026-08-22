@extends('layouts.app')

@section('title', 'Detail Soal CBT - NUSA')

@section('content')
    @php
        $badgeStatus = $soalCbt->status === 'siap' ? 'badge-active' : ($soalCbt->status === 'arsip' ? 'badge-inactive' : 'badge-warning');
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Detail soal CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Kembali</a>
            @izin('cbt.kelola', 'cbt.soal_kelola')
                <a href="{{ route('soal-cbt.edit', $soalCbt) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">BS</div>
                <h2>{{ $soalCbt->kode }}</h2>
                <p>{{ $soalCbt->labelJenis() }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    <span class="badge {{ $badgeStatus }}">{{ $soalCbt->labelStatus() }}</span>
                    <span class="badge badge-muted">{{ $soalCbt->labelKategori() }}</span>
                </div>
            </div>

            @izin('cbt.kelola', 'cbt.soal_kelola')
                @if ($soalCbt->aktif)
                    <form action="{{ route('soal-cbt.destroy', $soalCbt) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Arsipkan soal CBT ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Arsipkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Identitas Soal</h2>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Mata pelajaran</dt><dd>{{ $soalCbt->mataPelajaran?->nama ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Tingkat</dt><dd>Kelas {{ $soalCbt->tingkat }}</dd></div>
                    <div class="detail-item"><dt>Tahun pelajaran</dt><dd>{{ $soalCbt->tahunPelajaran?->nama ?: 'Umum/lintas tahun' }}</dd></div>
                    <div class="detail-item"><dt>Kesulitan</dt><dd>{{ $soalCbt->labelKesulitan() }}</dd></div>
                    <div class="detail-item"><dt>Topik</dt><dd>{{ $teks($soalCbt->topik) }}</dd></div>
                    <div class="detail-item"><dt>Materi</dt><dd>{{ $teks($soalCbt->materi) }}</dd></div>
                    <div class="detail-item"><dt>Skor maksimal</dt><dd>{{ $soalCbt->skor_maksimal }}</dd></div>
                    <div class="detail-item"><dt>Dibuat oleh</dt><dd>{{ $soalCbt->dibuatOleh?->nama ?: '-' }}</dd></div>
                    <div class="detail-item span-2"><dt>Tujuan pembelajaran</dt><dd style="white-space: pre-line;">{{ $teks($soalCbt->tujuan_pembelajaran) }}</dd></div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Isi Soal</h2>
                @if ($soalCbt->stimulus)
                    <div class="detail-item" style="margin-bottom: 16px;">
                        <dt>Stimulus</dt>
                        <dd style="white-space: pre-line;">{{ $soalCbt->stimulus }}</dd>
                    </div>
                @endif
                <x-media-soal :media="$soalCbt->media" />
                <div class="detail-item">
                    <dt>Pertanyaan</dt>
                    <dd style="white-space: pre-line;">{{ $soalCbt->pertanyaan }}</dd>
                </div>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Opsi dan Kunci</h2>

                @if (isset($soalCbt->opsi['pilihan']))
                    <div class="table-wrap">
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th>Opsi</th>
                                    <th>Isi</th>
                                    <th>Kunci</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($soalCbt->opsi['pilihan'] as $kode => $isi)
                                    <tr>
                                        <td>{{ $kode }}</td>
                                        <td>{{ $isi }}</td>
                                        <td>
                                            @php $jawaban = $soalCbt->kunci_jawaban['jawaban'] ?? null; @endphp
                                            @if ((is_string($jawaban) && $jawaban === $kode) || (is_array($jawaban) && in_array($kode, $jawaban, true)))
                                                <span class="badge badge-active">Benar</span>
                                            @else
                                                <span class="badge badge-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif (isset($soalCbt->opsi['pernyataan']))
                    <div class="table-wrap">
                        <table class="employee-table">
                            <thead><tr><th>No</th><th>Pernyataan</th><th>Kunci</th></tr></thead>
                            <tbody>
                                @foreach ($soalCbt->opsi['pernyataan'] as $item)
                                    <tr>
                                        <td>{{ $item['nomor'] }}</td>
                                        <td>{{ $item['teks'] }}</td>
                                        <td>{{ ($soalCbt->kunci_jawaban['jawaban'][$item['nomor']] ?? false) ? 'Benar' : 'Salah' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif (isset($soalCbt->opsi['pasangan']))
                    <div class="table-wrap">
                        <table class="employee-table">
                            <thead><tr><th>No</th><th>Kolom A</th><th>Pasangan</th></tr></thead>
                            <tbody>
                                @foreach ($soalCbt->opsi['pasangan'] as $item)
                                    <tr>
                                        <td>{{ $item['nomor'] }}</td>
                                        <td>{{ $item['kiri'] }}</td>
                                        <td>{{ $item['kanan'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <dl class="detail-grid">
                        <div class="detail-item span-2"><dt>Kunci jawaban</dt><dd style="white-space: pre-line;">{{ $teks($soalCbt->kunci_jawaban['jawaban'] ?? null) }}</dd></div>
                        <div class="detail-item span-2"><dt>Rubrik</dt><dd style="white-space: pre-line;">{{ $teks($soalCbt->rubrik['catatan'] ?? null) }}</dd></div>
                    </dl>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Pembahasan</h2>
                <p style="white-space: pre-line;">{{ $teks($soalCbt->pembahasan) }}</p>
            </section>
        </div>
    </div>
@endsection
