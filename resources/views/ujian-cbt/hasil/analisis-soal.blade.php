@extends('layouts.app')

@section('title', 'Analisis Soal CBT - NUSA')

@section('content')
    <style>
        .analisis-intro {
            margin: 0 0 20px;
            color: var(--muted);
        }

        .analisis-filter {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0 0 20px;
        }

        .analisis-filter .field {
            flex: 1 1 220px;
            max-width: 400px;
        }

        .analisis-table-wrap {
            overflow-x: auto;
            width: 100%;
        }

        .analisis-table {
            min-width: 1100px;
        }

        .analisis-table th,
        .analisis-table td {
            vertical-align: top;
        }

        .analisis-soal {
            min-width: 220px;
            max-width: 330px;
            overflow-wrap: anywhere;
        }

        .analisis-soal strong,
        .analisis-soal span {
            display: block;
        }

        .analisis-soal span,
        .analisis-subtle {
            color: var(--muted);
            font-size: .82rem;
        }

        .analisis-number {
            font-weight: 800;
            white-space: nowrap;
        }

        .analisis-choice {
            display: flex;
            gap: 6px 12px;
            flex-wrap: wrap;
            min-width: 150px;
        }

        .analisis-choice span {
            white-space: nowrap;
        }

        .analisis-rincian-link {
            margin-top: 10px;
            white-space: nowrap;
        }

        .kesukaran-ringkasan {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            border-block: 1px solid var(--line);
            background: var(--soft);
        }

        .kesukaran-ringkasan > div {
            padding: 14px 20px;
        }

        .kesukaran-ringkasan strong {
            display: block;
            margin-top: 4px;
            font-size: 1.25rem;
        }

        .kesukaran-cell {
            min-width: 170px;
        }

        .kesukaran-cell .badge {
            margin-bottom: 6px;
        }

        .kesukaran-sukar {
            color: #9a3412;
            background: #fff0e5;
        }

        .kesukaran-sedang {
            color: #174a79;
            background: #e8f1fa;
        }

        .kesukaran-mudah {
            color: #166534;
            background: #eaf7ee;
        }

        .kesukaran-metode {
            margin-top: 12px;
        }

        .kesukaran-metode summary {
            cursor: pointer;
            font-weight: 700;
        }
    </style>

    @php
        $formatPersen = fn ($nilai) => $nilai === null ? '-' : number_format($nilai, 2, ',', '.').'%';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Analisis soal</h1>
            <p class="page-subtitle">{{ $ujianCbt->nama }} · {{ $ujianCbt->mataPelajaran?->nama ?: '-' }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-cbt.hasil.index', $ujianCbt) }}" class="button button-muted">Kembali ke hasil</a>
        </div>
    </div>

    <p class="analisis-intro">Hanya peserta yang telah menyelesaikan ujian dihitung. Setiap soal dibandingkan dengan peserta yang benar-benar mendapat soal tersebut.</p>

    @if ($analisis['peserta_selesai'] > 0 && $analisis['peserta_selesai'] < $analisis['minimal_hasil_kesukaran'])
        <div class="alert" style="margin-bottom: 20px;">Data baru berasal dari {{ $analisis['peserta_selesai'] }} peserta selesai. Gunakan persentase ini sebagai gambaran awal, bukan kesimpulan kualitas soal.</div>
    @endif

    <form action="{{ route('ujian-cbt.hasil.analisis-soal', $ujianCbt) }}" method="GET" class="analisis-filter">
        <div class="field">
            <label for="kelas_id">Kelas peserta</label>
            <select id="kelas_id" name="kelas_id" class="select">
                <option value="">Semua kelas</option>
                @foreach ($kelasPeserta as $kelasUjian)
                    <option value="{{ $kelasUjian->kelas_id }}" @selected((string) $kelasId === (string) $kelasUjian->kelas_id)>{{ $kelasUjian->kelas?->nama ?: '-' }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="button button-dark">Terapkan</button>
        @if ($kelasId)
            <a href="{{ route('ujian-cbt.hasil.analisis-soal', $ujianCbt) }}" class="button button-muted">Reset</a>
        @endif
    </form>

    <div class="stats-grid">
        <div class="panel stat active">
            <p class="stat-label">Peserta selesai</p>
            <p class="stat-value">{{ $analisis['peserta_selesai'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Soal dalam analisis</p>
            <p class="stat-value">{{ $analisis['jumlah_soal'] }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Soal dengan koreksi tertunda</p>
            <p class="stat-value">{{ $analisis['soal_belum_lengkap'] }}</p>
        </div>
    </div>

    <section class="panel" style="margin-top: 20px;">
        <div class="panel-pad">
            <h2 class="panel-title">Hasil per soal</h2>
            <p class="help-text">Persentase dihitung dari jawaban yang sudah dinilai. Skor sebagian tidak disamakan dengan skor penuh.</p>
            <details class="kesukaran-metode analisis-subtle">
                <summary>Dasar tingkat kesukaran</summary>
                <p>Indeks = total skor pada soal / (jumlah siswa yang sudah dinilai pada soal tersebut x skor maksimal soal). Semakin tinggi indeks, semakin mudah soal bagi kelompok siswa ini.</p>
                <p>Sukar: indeks sampai 0,30. Sedang: di atas 0,30 sampai 0,70. Mudah: di atas 0,70 sampai 1,00. Kategori ditentukan sebelum pembulatan tampilan.</p>
                <p>Kategori ditampilkan setelah minimal {{ $analisis['minimal_hasil_kesukaran'] }} hasil siswa per soal dan seluruh koreksi peserta selesai pada soal itu lengkap. Batas ini merupakan pengaman sampel kecil, bukan jaminan kualitas soal. Siswa yang belum selesai atau tidak mengikuti ujian tidak dihitung sebagai skor nol.</p>
                <p>Hasil mengikuti kelas yang dipilih dan tidak mengubah tingkat kesulitan dari guru, bobot soal, atau nilai siswa.</p>
            </details>
        </div>
        <div class="kesukaran-ringkasan" aria-label="Ringkasan tingkat kesukaran">
            @foreach (['sukar' => 'Sukar', 'sedang' => 'Sedang', 'mudah' => 'Mudah', 'belum_dikategorikan' => 'Belum dikategorikan'] as $kode => $label)
                <div>
                    <span class="analisis-subtle">{{ $label }}</span>
                    <strong>{{ $analisis['ringkasan_kesukaran'][$kode] }} soal</strong>
                </div>
            @endforeach
        </div>
        <div class="analisis-table-wrap">
            <table class="employee-table analisis-table">
                <thead>
                    <tr>
                        <th>Soal</th>
                        <th>Disajikan</th>
                        <th>Terjawab</th>
                        <th>Dinilai</th>
                        <th>Skor penuh</th>
                        <th>Skor sebagian</th>
                        <th>Rata-rata skor</th>
                        <th>Kesukaran hasil siswa</th>
                        <th>Pilihan siswa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($analisis['soal'] as $item)
                        @php
                            $soal = $item['soal'];
                            $nomor = $soal->nomor_urut ?? $loop->iteration;
                            $bobot = (float) $soal->bobot;
                            $kesukaran = $item['kesukaran'];
                            $labelKesukaran = match ($kesukaran['status']) {
                                'siap' => ucfirst($kesukaran['kategori']),
                                'sampel_terbatas' => 'Sampel terbatas',
                                'menunggu_koreksi' => 'Menunggu koreksi',
                                'skor_tidak_valid' => 'Periksa skor',
                                default => 'Belum ada nilai',
                            };
                        @endphp
                        <tr id="soal-{{ $soal->id }}">
                            <td class="analisis-soal">
                                <strong>Soal {{ $nomor }} · {{ $soal->soalCbt?->labelJenis() ?? 'Soal' }}</strong>
                                <span>{{ \Illuminate\Support\Str::limit(trim(strip_tags($soal->soalCbt?->pertanyaan ?? '')), 115) }}</span>
                                <span>Skor maksimal {{ number_format($bobot, 2, ',', '.') }}</span>
                                <span>Kesulitan dari guru: {{ $soal->soalCbt?->labelKesulitan() ?: '-' }}</span>
                            </td>
                            <td class="analisis-number">{{ $item['disajikan'] }}</td>
                            <td>
                                <strong>{{ $item['terjawab'] }}</strong>
                                <div class="analisis-subtle">{{ $item['belum_dijawab'] }} kosong</div>
                            </td>
                            <td>
                                <strong>{{ $item['dinilai'] }} / {{ $item['disajikan'] }}</strong>
                                @if ($item['belum_dinilai'] > 0)
                                    <div class="analisis-subtle">{{ $item['belum_dinilai'] }} menunggu koreksi</div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $formatPersen($item['persen_skor_penuh']) }}</strong>
                                <div class="analisis-subtle">{{ $item['skor_penuh'] }} siswa</div>
                            </td>
                            <td class="analisis-number">{{ $item['skor_sebagian'] }}</td>
                            <td>
                                <strong>{{ $formatPersen($item['persen_rata_rata_skor']) }}</strong>
                                <div class="analisis-subtle">{{ $item['rata_rata_skor'] === null ? '-' : number_format($item['rata_rata_skor'], 2, ',', '.') }} / {{ number_format($bobot, 2, ',', '.') }}</div>
                            </td>
                            <td>
                                <div class="kesukaran-cell">
                                    <span class="badge {{ $kesukaran['kategori'] ? 'kesukaran-'.$kesukaran['kategori'] : 'badge-muted' }}">{{ $labelKesukaran }}</span>
                                    <div class="analisis-number">Indeks {{ $kesukaran['indeks'] === null ? '-' : number_format($kesukaran['indeks'], 4, ',', '.') }}</div>
                                    <div class="analisis-subtle">{{ $item['dinilai'] }} hasil siswa</div>
                                    @if ($kesukaran['status'] === 'sampel_terbatas')
                                        <div class="analisis-subtle">Kategori mulai {{ $analisis['minimal_hasil_kesukaran'] }} hasil siswa.</div>
                                    @elseif ($kesukaran['status'] === 'menunggu_koreksi')
                                        <div class="analisis-subtle">Indeks sementara; {{ $item['belum_dinilai'] }} belum dinilai.</div>
                                    @elseif ($kesukaran['status'] === 'skor_tidak_valid')
                                        <div class="analisis-subtle">Skor atau bobot di luar rentang yang berlaku.</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($item['pilihan'] !== [])
                                    <div class="analisis-choice">
                                        @foreach (collect($item['pilihan'])->sortKeys() as $kode => $jumlah)
                                            <span>{{ $kode }}: <strong>{{ $jumlah }}</strong></span>
                                        @endforeach
                                    </div>
                                    <a class="button button-muted analisis-rincian-link" href="{{ route('ujian-cbt.hasil.rincian-soal', ['ujianCbt' => $ujianCbt, 'soalUjianCbt' => $soal, 'kelas_id' => $kelasId]) }}">Rincian jawaban</a>
                                    @if ($item['rincian_pilihan']['perlu_ditinjau'] > 0)
                                        <div class="analisis-subtle">{{ $item['rincian_pilihan']['perlu_ditinjau'] }} pengecoh perlu ditinjau</div>
                                        <div class="analisis-subtle">Dipilih kurang dari 5% peserta. Saran pemeriksaan, bukan berarti soal salah.</div>
                                    @endif
                                @elseif ($item['rincian_pemetaan']['tersedia'])
                                    <div class="analisis-subtle">{{ count($item['rincian_pemetaan']['butir']) }} {{ $soal->soalCbt->jenis_soal === 'benar_salah' ? 'pernyataan' : 'pasangan' }}</div>
                                    <a class="button button-muted analisis-rincian-link" href="{{ route('ujian-cbt.hasil.rincian-soal', ['ujianCbt' => $ujianCbt, 'soalUjianCbt' => $soal, 'kelas_id' => $kelasId]) }}">Rincian jawaban</a>
                                @else
                                    <span class="analisis-subtle">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">Belum ada soal dalam paket ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pad analisis-subtle">
            Untuk pilihan ganda kompleks, satu siswa dapat memilih beberapa opsi sehingga jumlah pilihan bisa melebihi jumlah peserta. Jika soal diacak, angka "Disajikan" dapat berbeda antarsoal.
        </div>
    </section>
@endsection
