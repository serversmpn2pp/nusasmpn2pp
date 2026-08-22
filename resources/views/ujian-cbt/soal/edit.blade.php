@extends('layouts.app')

@section('title', 'Kelola Soal Paket CBT - NUSA')

@section('content')
    @php
        $asesmenKelas = $ujianCbt->asesmenKelas();
        $tautanDetail = route($asesmenKelas ? 'asesmen-kelas-cbt.show' : 'ujian-cbt.show', $ujianCbt);
        $tautanDaftar = route($asesmenKelas ? 'asesmen-kelas-cbt.index' : 'ujian-cbt.index');
        $jumlahDipilih = $soalDipilih->count();
        $totalBobot = $soalDipilih->sum(fn ($item) => (float) $item->bobot);
    @endphp

    <style>
        .package-question-head {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            justify-content: space-between;
        }

        .package-question-check {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .package-question-check input {
            margin-top: 3px;
        }

        .package-question-controls {
            display: grid;
            grid-template-columns: 110px 110px;
            gap: 10px;
            min-width: 230px;
        }

        .package-question-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        @media (max-width: 760px) {
            .package-question-head {
                display: block;
            }

            .package-question-controls {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                min-width: 0;
                margin-top: 12px;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $asesmenKelas ? 'Asesmen Kelas' : 'CBT' }}</p>
            <h1 class="page-title">{{ $asesmenKelas ? 'Pilih soal asesmen' : 'Kelola soal paket CBT' }}</h1>
        </div>

        <div class="actions">
            <a href="{{ $tautanDetail }}" class="button button-muted">Detail {{ $asesmenKelas ? 'asesmen' : 'paket' }}</a>
            <a href="{{ $tautanDaftar }}" class="button button-muted">Daftar {{ $asesmenKelas ? 'asesmen' : 'paket' }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Soal terpilih</p>
            <p class="stat-value" data-selected-count>{{ $jumlahDipilih }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Target tampil</p>
            <p class="stat-value">{{ $ujianCbt->jumlah_soal }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Total bobot</p>
            <p class="stat-value">{{ number_format($totalBobot, 2, ',', '.') }}</p>
        </div>
    </div>

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <h2 class="panel-title">{{ $ujianCbt->nama }}</h2>
        <dl class="quick-facts" style="margin-top: 16px;">
            <div><dt>Mapel</dt><dd>{{ $ujianCbt->mataPelajaran?->nama ?: '-' }}</dd></div>
            <div><dt>Tingkat</dt><dd>Kelas {{ $ujianCbt->tingkat }}</dd></div>
            <div><dt>Jenis</dt><dd>{{ $ujianCbt->jenisUjianCbt?->nama ?: '-' }}</dd></div>
            <div><dt>Pengacakan</dt><dd>{{ $ujianCbt->acak_soal ? 'Soal diacak' : 'Urut nomor' }}</dd></div>
        </dl>
        <p class="help-text" style="margin-top: 12px;">Jika soal terpilih lebih banyak dari target tampil dan acak soal aktif, NUSA dapat memakai daftar ini sebagai kolam soal saat modul pengerjaan siswa dibuat.</p>
    </section>

    <section class="panel panel-pad" style="margin-bottom: 24px;">
        <h2 class="panel-title">Ringkasan Komposisi</h2>
        <div class="stats-grid" style="margin: 16px 0 0;">
            <div class="panel panel-pad">
                <p class="stat-label">Jenis soal</p>
                <dl class="quick-facts" style="margin-top: 12px;">
                    @forelse ($daftarJenisSoal as $kode => $label)
                        <div><dt>{{ $label }}</dt><dd>{{ $ringkasanJenis[$kode] ?? 0 }}</dd></div>
                    @empty
                        <div><dt>-</dt><dd>0</dd></div>
                    @endforelse
                </dl>
            </div>
            <div class="panel panel-pad">
                <p class="stat-label">Kesulitan</p>
                <dl class="quick-facts" style="margin-top: 12px;">
                    @foreach ($daftarKesulitan as $kode => $label)
                        <div><dt>{{ $label }}</dt><dd>{{ $ringkasanKesulitan[$kode] ?? 0 }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>

    <form action="{{ route('ujian-cbt.soal.update', $ujianCbt) }}" method="POST">
        @csrf
        @method('PUT')

        <section class="panel">
            <div class="desktop-only table-wrap" data-form-layout="desktop">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Soal</th>
                            <th>Jenis</th>
                            <th>Kesulitan</th>
                            <th>Nomor</th>
                            <th>Bobot</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($soalCbt as $item)
                            @php
                                $relasi = $soalDipilih[$item->id] ?? null;
                                $dipilih = (bool) $relasi;
                                $nomorUrut = old("soal.{$item->id}.nomor_urut", $relasi?->nomor_urut);
                                $bobot = old("soal.{$item->id}.bobot", $relasi?->bobot ?? $item->skor_maksimal);
                                $bisaDipilih = $item->aktif && $item->status === 'siap';
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="soal[{{ $item->id }}][dipilih]" value="0">
                                    <label class="package-question-check" for="soal_{{ $item->id }}">
                                        <input id="soal_{{ $item->id }}" type="checkbox" name="soal[{{ $item->id }}][dipilih]" value="1" @checked($dipilih) @disabled(! $bisaDipilih) data-question-check data-question-locked="{{ $bisaDipilih ? '0' : '1' }}">
                                        <span>
                                            {{ $item->kode }}
                                            <span class="person-meta" style="display:block; font-weight:500;">{{ str(strip_tags($item->pertanyaan))->limit(100) }}</span>
                                        </span>
                                    </label>
                                    @unless ($bisaDipilih)
                                        <p class="error-text">Soal ini belum siap atau tidak aktif.</p>
                                    @endunless
                                </td>
                                <td>{{ $item->labelJenis() }}</td>
                                <td>{{ $item->labelKesulitan() }}</td>
                                <td>
                                    <input name="soal[{{ $item->id }}][nomor_urut]" type="number" min="1" max="999" value="{{ $nomorUrut }}" class="input" data-question-input>
                                </td>
                                <td>
                                    <input name="soal[{{ $item->id }}][bobot]" type="number" min="0.25" max="100" step="0.25" value="{{ $bobot }}" class="input" data-question-input>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada soal siap untuk mata pelajaran dan tingkat paket ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only mobile-list" data-form-layout="mobile">
                @forelse ($soalCbt as $item)
                    @php
                        $relasi = $soalDipilih[$item->id] ?? null;
                        $dipilih = (bool) $relasi;
                        $nomorUrut = old("soal.{$item->id}.nomor_urut", $relasi?->nomor_urut);
                        $bobot = old("soal.{$item->id}.bobot", $relasi?->bobot ?? $item->skor_maksimal);
                        $bisaDipilih = $item->aktif && $item->status === 'siap';
                    @endphp
                    <article class="mobile-card">
                        <input type="hidden" name="soal[{{ $item->id }}][dipilih]" value="0">
                        <div class="package-question-head">
                            <label class="package-question-check" for="mobile_soal_{{ $item->id }}">
                                <input id="mobile_soal_{{ $item->id }}" type="checkbox" name="soal[{{ $item->id }}][dipilih]" value="1" @checked($dipilih) @disabled(! $bisaDipilih) data-question-check data-question-locked="{{ $bisaDipilih ? '0' : '1' }}">
                                <span>
                                    {{ $item->kode }}
                                    <span class="person-meta" style="display:block; font-weight:500;">{{ str(strip_tags($item->pertanyaan))->limit(120) }}</span>
                                </span>
                            </label>
                            <div class="package-question-controls">
                                <div class="field">
                                    <label>Nomor</label>
                                    <input name="soal[{{ $item->id }}][nomor_urut]" type="number" min="1" max="999" value="{{ $nomorUrut }}" class="input" data-question-input>
                                </div>
                                <div class="field">
                                    <label>Bobot</label>
                                    <input name="soal[{{ $item->id }}][bobot]" type="number" min="0.25" max="100" step="0.25" value="{{ $bobot }}" class="input" data-question-input>
                                </div>
                            </div>
                        </div>
                        <div class="package-question-meta">
                            <span class="badge badge-muted">{{ $item->labelJenis() }}</span>
                            <span class="badge badge-muted">{{ $item->labelKesulitan() }}</span>
                            <span class="badge {{ $bisaDipilih ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelStatus() }}</span>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada soal siap untuk mata pelajaran dan tingkat paket ini.</div>
                @endforelse
            </div>
        </section>

        <div class="form-actions" style="margin-top: 20px;">
            <a href="{{ $tautanDetail }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">Simpan soal paket</button>
        </div>
    </form>

    <script>
        (() => {
            const checks = document.querySelectorAll('[data-question-check]');
            const counter = document.querySelector('[data-selected-count]');
            const layouts = document.querySelectorAll('[data-form-layout]');
            const media = window.matchMedia('(max-width: 760px)');

            const layoutAktif = (layout) => {
                if (! layout) {
                    return true;
                }

                return layout.dataset.formLayout === (media.matches ? 'mobile' : 'desktop');
            };

            const refresh = () => {
                let total = 0;

                checks.forEach((check) => {
                    const container = check.closest('tr') || check.closest('.mobile-card');
                    const layout = check.closest('[data-form-layout]');
                    const aktif = layoutAktif(layout);
                    const inputs = container.querySelectorAll('[data-question-input]');

                    check.disabled = ! aktif || check.dataset.questionLocked === '1';

                    container.querySelectorAll('input[type="hidden"]').forEach((input) => {
                        input.disabled = ! aktif;
                    });

                    if (aktif && check.checked) {
                        total += 1;
                    }

                    inputs.forEach((input) => {
                        input.disabled = ! aktif || ! check.checked;
                    });
                });

                if (counter) {
                    counter.textContent = total;
                }
            };

            checks.forEach((check) => check.addEventListener('change', refresh));
            layouts.forEach((layout) => {
                layout.querySelectorAll('input, select, textarea').forEach((input) => {
                    input.addEventListener('change', refresh);
                });
            });

            if (media.addEventListener) {
                media.addEventListener('change', refresh);
            } else {
                media.addListener(refresh);
            }

            refresh();
        })();
    </script>
@endsection
