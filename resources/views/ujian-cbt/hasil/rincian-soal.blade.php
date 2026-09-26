@extends('layouts.app')

@section('title', 'Rincian Jawaban dan Pengecoh - NUSA')

@section('content')
    @php
        $relasiSoal = $item['soal'];
        $soal = $relasiSoal->soalCbt;
        $pemetaan = $item['rincian_pemetaan']['tersedia'];
        $rincian = $pemetaan ? $item['rincian_pemetaan'] : $item['rincian_pilihan'];
        $judul = match ($soal->jenis_soal) {
            'benar_salah' => 'Rincian jawaban per pernyataan',
            'menjodohkan' => 'Rincian jawaban dan pasangan',
            default => 'Rincian jawaban dan pengecoh',
        };
        $pgk = $soal->jenis_soal === 'pilihan_ganda_kompleks';
        $formatPersen = fn ($nilai) => $nilai === null ? '-' : number_format($nilai, 2, ',', '.').'%';
        $indikator = [
            'kunci' => 'Kunci jawaban',
            'belum_ada_data' => 'Belum ada data',
            'sampel_terbatas' => 'Sampel terbatas',
            'belum_dipilih' => 'Pengecoh belum dipilih',
            'jarang_dipilih' => 'Pengecoh jarang dipilih',
            'dipilih' => 'Dipilih minimal 5%',
            'sebaran_pgk' => 'Sebaran pilihan',
            'periksa_data' => 'Periksa data',
        ];
    @endphp

    <style>
        .rincian-scope { margin: 0 0 16px; color: var(--muted); }
        .rincian-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin: 0; border-block: 1px solid var(--line); background: #fff; }
        .rincian-stats > div { padding: 16px 20px; }
        .rincian-stats dt { color: var(--muted); font-size: .85rem; }
        .rincian-stats dd { margin: 6px 0 0; font-size: 1.4rem; font-weight: 800; }
        .rincian-section { padding: 24px 0; border-bottom: 1px solid var(--line); min-width: 0; }
        .rincian-section h2 { margin: 0 0 12px; font-size: 1.1rem; }
        .rincian-stimulus { margin: 16px 0; padding-left: 16px; border-left: 3px solid var(--accent); }
        .rincian-text { white-space: pre-line; overflow-wrap: anywhere; }
        .rincian-question { font-weight: 700; }
        .rincian-caption { margin: 8px 0; color: var(--muted); font-size: .85rem; }
        .rincian-table-wrap { max-width: 100%; overflow-x: auto; }
        .rincian-opsi-table { width: 100%; min-width: 780px; border-collapse: collapse; background: #fff; }
        .rincian-opsi-table > thead > tr > th { background: var(--soft); text-align: left; font-size: .82rem; }
        .rincian-opsi-table > thead > tr > th, .rincian-opsi-table > tbody > tr > td { padding: 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .rincian-opsi-table .opsi-isi { min-width: 220px; max-width: 420px; overflow-wrap: anywhere; }
        .opsi-isi .badge { margin-bottom: 8px; }
        .opsi-jumlah { white-space: nowrap; font-weight: 700; }
        .opsi-persen { min-width: 150px; }
        .opsi-persen strong { display: block; font-variant-numeric: tabular-nums; }
        .opsi-persen progress { display: block; width: 130px; height: 8px; margin-top: 10px; border: 0; border-radius: 4px; overflow: hidden; background: #e7ebef; color: #63788c; }
        .opsi-persen progress::-webkit-progress-bar { background: #e7ebef; }
        .opsi-persen progress::-webkit-progress-value { background: #63788c; }
        .opsi-persen progress::-moz-progress-bar { background: #63788c; }
        .opsi-kunci .opsi-persen progress { color: #16805d; }
        .opsi-kunci .opsi-persen progress::-webkit-progress-value { background: #16805d; }
        .opsi-kunci .opsi-persen progress::-moz-progress-bar { background: #16805d; }
        .opsi-catatan { min-width: 185px; max-width: 260px; }
        .rincian-notice { margin: 16px 0; padding: 12px 16px; border-left: 3px solid #b7791f; background: #fff8df; color: #6d4c12; }
        .rincian-method { margin-top: 20px; color: var(--muted); font-size: .85rem; }
        .rincian-method summary { cursor: pointer; padding: 12px 14px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--text); font-weight: 700; }
        .rincian-method summary:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .rincian-method h3 { margin: 18px 0 8px; font-size: .9rem; color: var(--text); }
        .rincian-method li { margin: 6px 0; }
        .rincian-score { display: flex; flex-wrap: wrap; gap: 12px 24px; padding: 16px 0; }
        .rincian-score span { white-space: nowrap; }
        .rincian-butir { padding: 20px 0; border-bottom: 1px solid var(--line); }
        .rincian-butir h3 { margin: 0 0 10px; font-size: 1rem; }
        .rincian-butir .rincian-score { gap: 10px 20px; font-size: .9rem; }
        .rincian-butir summary { cursor: pointer; padding: 10px 14px; border: 1px solid var(--line); border-radius: 6px; background: #fff; font-weight: 700; }
        .rincian-butir details[open] summary { margin-bottom: 12px; }
        .rincian-butir .rincian-opsi-table { min-width: 680px; }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Analisis soal</p>
            <h1 class="page-title">{{ $judul }}</h1>
            <p class="page-subtitle">{{ $ujianCbt->nama }} · Soal {{ $relasiSoal->nomor_urut }}</p>
        </div>
        <div class="actions">
            <a class="button button-muted" href="{{ route('ujian-cbt.hasil.analisis-soal', ['ujianCbt' => $ujianCbt, 'kelas_id' => $kelasId]) }}#soal-{{ $relasiSoal->id }}">Kembali ke analisis</a>
        </div>
    </div>

    <p class="rincian-scope">{{ $ujianCbt->mataPelajaran?->nama }} · {{ $kelasId ? ($namaKelas ?: 'Kelas yang dipilih') : 'Semua kelas' }} · {{ $soal->labelJenis() }}</p>
    <dl class="rincian-stats">
        <div><dt>Mendapat soal ini</dt><dd>{{ $item['disajikan'] }} siswa</dd></div>
        <div><dt>Menjawab</dt><dd>{{ $item['terjawab'] }} siswa</dd></div>
        <div><dt>Tidak menjawab</dt><dd>{{ $item['belum_dijawab'] }} siswa</dd></div>
        @if ($pemetaan)
            <div><dt>{{ $soal->jenis_soal === 'benar_salah' ? 'Jumlah pernyataan' : 'Jumlah pasangan' }}</dt><dd>{{ count($rincian['butir']) }} butir</dd></div>
        @else
            <div><dt>Pengecoh perlu ditinjau</dt><dd>{{ ! $pgk && $rincian['kunci_valid'] && $rincian['jawaban_tidak_dikenali'] === 0 && $item['disajikan'] >= $rincian['minimal_peserta'] ? $rincian['perlu_ditinjau'].' opsi' : '-' }}</dd></div>
        @endif
    </dl>
    @if (! $pemetaan && ! $pgk)
        <p class="rincian-caption"><strong>Perlu ditinjau</strong> berarti pilihan pengecoh jarang atau belum dipilih siswa. Ini saran untuk memeriksa pilihan jawaban, bukan keputusan bahwa soal salah. Nilai siswa tidak berubah.</p>
    @endif

    @if (! $rincian['kunci_valid'])
        <div class="rincian-notice">{{ $pemetaan ? 'Ada butir dengan kunci yang belum valid. Jumlah benar dan salah pada butir tersebut belum dapat ditentukan; sebaran jawaban tetap ditampilkan.' : 'Kunci jawaban belum sesuai dengan pilihan yang tersimpan. Penanda kunci dan penilaian pengecoh ditunda sampai data diperiksa.' }}</div>
    @endif
    @if ($rincian['jawaban_tidak_dikenali'] > 0)
        <div class="rincian-notice">Ada {{ $rincian['jawaban_tidak_dikenali'] }} jawaban siswa yang tidak sesuai dengan pilihan saat ini. {{ $pemetaan ? 'Jawaban pada butir yang tidak dikenali dipisahkan dari benar, salah, dan kosong. Nomor butir yang sudah tidak tersedia tidak dimasukkan ke rincian.' : 'Jumlah pilihan mungkin tidak lengkap; penilaian pengecoh ditunda.' }} Periksa kemungkinan perubahan soal setelah ujian.</div>
    @endif
    @if ($item['disajikan'] === 0)
        <div class="rincian-notice">Belum ada peserta selesai yang mendapat soal ini pada kelas yang dipilih.</div>
    @elseif ($item['disajikan'] < $rincian['minimal_peserta'])
        <div class="rincian-notice">Sampel terbatas: baru {{ $item['disajikan'] }} siswa. {{ $pemetaan || $pgk ? 'Jumlah dan persentase merupakan gambaran kelompok ini, bukan kesimpulan kualitas soal.' : 'Jumlah dan persentase tetap ditampilkan, tetapi penanda pengecoh perlu ditinjau menunggu minimal '.$rincian['minimal_peserta'].' siswa.' }}</div>
    @endif

    <section class="rincian-section" aria-label="Isi soal">
        <h2>Soal {{ $relasiSoal->nomor_urut }}</h2>
        @if (filled($soal->stimulus) || filled(data_get($soal->media, 'konten.stimulus')))
            <div class="rincian-stimulus">
                <div class="rincian-text" data-inline-math>{{ $soal->stimulus }}</div>
                <x-media-soal :media="data_get($soal->media, 'konten.stimulus', [])" stimulus />
            </div>
        @endif
        <x-media-soal :media="$soal->media" />
        <div class="rincian-text rincian-question" data-inline-math>{{ $soal->pertanyaan }}</div>
        <p class="rincian-caption">Skor maksimal {{ number_format((float) $relasiSoal->bobot, 2, ',', '.') }}. Kunci dan isi pilihan mengikuti data soal saat ini.</p>
    </section>

    @if ($pemetaan)
        @include('ujian-cbt.hasil.rincian-pemetaan')
    @else
    <section class="rincian-section" aria-labelledby="pilihan-title">
        <h2 id="pilihan-title">Pilihan siswa</h2>
        <p class="rincian-caption">Persentase menggunakan {{ $item['disajikan'] }} peserta selesai yang mendapat soal ini, termasuk yang tidak menjawab. Kode pilihan mengikuti bank soal, bukan urutan huruf yang diacak di layar siswa.</p>
        @if ($pgk)
            <p class="rincian-caption">Satu siswa dapat memilih beberapa opsi. Jumlah persentase dapat melebihi 100%; angka ini bukan persentase nilai siswa. Tidak dipilih juga mencakup siswa yang mengosongkan soal.</p>
        @endif
        <div class="rincian-table-wrap" tabindex="0" aria-label="Sebaran pilihan jawaban">
            <table class="rincian-opsi-table">
                <thead>
                    <tr>
                        <th scope="col">Opsi</th>
                        <th scope="col">Isi pilihan</th>
                        <th scope="col">Dipilih</th>
                        @if ($pgk)<th scope="col">Tidak dipilih</th>@endif
                        <th scope="col">Persentase</th>
                        <th scope="col">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rincian['opsi'] as $opsi)
                        <tr @class(['opsi-kunci' => $opsi['kunci']])>
                            <td class="opsi-jumlah">{{ $opsi['kode'] }}</td>
                            <td class="opsi-isi">
                                <span class="badge {{ $opsi['kunci'] ? 'badge-active' : 'badge-muted' }}">{{ $opsi['kunci'] === null ? 'Kunci belum valid' : ($opsi['kunci'] ? 'Kunci jawaban' : 'Pengecoh') }}</span>
                                <div class="rincian-text" data-inline-math>{{ $opsi['teks'] }}</div>
                                <x-media-soal :media="data_get($soal->media, 'konten.pilihan_'.$opsi['kode'], [])" />
                            </td>
                            <td class="opsi-jumlah">{{ $opsi['dipilih'] }} siswa</td>
                            @if ($pgk)<td class="opsi-jumlah">{{ $opsi['tidak_dipilih'] }} siswa</td>@endif
                            <td class="opsi-persen">
                                <strong>{{ $formatPersen($opsi['persen']) }}</strong>
                                @if ($opsi['persen'] !== null)
                                    <progress max="100" value="{{ $opsi['persen'] }}" aria-label="Persentase pemilih opsi {{ $opsi['kode'] }}"></progress>
                                @endif
                            </td>
                            <td class="opsi-catatan">
                                <strong>{{ $indikator[$opsi['indikator']] }}</strong>
                                @if (in_array($opsi['indikator'], ['belum_dipilih', 'jarang_dipilih'], true))
                                    <p class="rincian-caption">Dipilih kurang dari 5% peserta. Periksa apakah pilihan ini masuk akal dan sesuai materi. Ini saran peninjauan, bukan berarti soal salah.</p>
                                @elseif ($opsi['indikator'] === 'sebaran_pgk')
                                    <p class="rincian-caption">Frekuensi saja belum menentukan mutu pengecoh PG kompleks.</p>
                                @elseif ($opsi['kunci'] && $pgk)
                                    <p class="rincian-caption">{{ $opsi['tidak_dipilih'] }} siswa belum memilih kunci ini.</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="rincian-score">
            <span>Skor penuh: <strong>{{ $item['skor_penuh'] }} siswa</strong></span>
            <span>Skor sebagian: <strong>{{ $item['skor_sebagian'] }} siswa</strong></span>
            <span>Skor nol: <strong>{{ $item['skor_nol'] }} siswa</strong></span>
            <span>Belum dinilai: <strong>{{ $item['belum_dinilai'] }} siswa</strong></span>
        </div>
        @if ($pgk)
            <p class="rincian-caption">Pilihan ganda kompleks hanya menampilkan sebaran pilihan, tanpa penanda pengecoh perlu ditinjau berdasarkan patokan 5%. Analisis ini tidak mengubah kunci, bobot, atau nilai.</p>
        @else
            <details class="rincian-method">
                <summary>Apa arti "Perlu ditinjau"? Penjelasan dan contoh</summary>
                <p><strong>Pengecoh</strong> adalah pilihan yang bukan kunci jawaban, tetapi tetap masuk akal bagi siswa yang belum memahami materi. Penanda ini mengajak guru memeriksa pilihan tersebut, bukan mewajibkan soal diubah atau dihapus.</p>
                <h3>Kapan penanda muncul?</h3>
                <p>Pada pilihan ganda satu jawaban, pengecoh yang dipilih kurang dari 5% peserta, termasuk tidak dipilih sama sekali, ditandai untuk ditinjau. Persentase dihitung sebelum pembulatan. Tepat 5% tidak mendapat penanda ini.</p>
                <p>Diperlukan minimal {{ $rincian['minimal_peserta'] }} peserta selesai yang mendapat soal tersebut. Perhitungan mengikuti filter kelas, termasuk peserta yang mengosongkan jawaban. Siswa yang tidak ikut atau belum selesai tidak dihitung. Penanda ditunda jika kunci belum valid atau ada jawaban yang tidak dikenali.</p>
                <h3>Contoh: 30 siswa, kunci jawaban A</h3>
                <ul>
                    <li>A dipilih 24 siswa: kunci jawaban, bukan pengecoh.</li>
                    <li>B dipilih 5 siswa (16,67%): tidak mendapat penanda.</li>
                    <li>C dipilih 1 siswa (3,33%): perlu ditinjau karena jarang dipilih.</li>
                    <li>D dipilih 0 siswa (0%): perlu ditinjau karena belum dipilih.</li>
                </ul>
                <h3>Apa yang perlu diperiksa guru?</h3>
                <ul>
                    <li>Apakah pengecoh masuk akal dan masih berkaitan dengan materi?</li>
                    <li>Apakah ada petunjuk bahasa atau isi yang membuat pilihan terlalu mudah disingkirkan?</li>
                    <li>Apakah pilihan ambigu atau justru dapat dianggap benar?</li>
                </ul>
                <p>Pengecoh jarang dipilih juga dapat terjadi karena siswa sudah menguasai materi. Pertimbangkan kemampuan kelompok dan tujuan pembelajaran sebelum memutuskan perubahan. Pilihan yang tidak ditandai pun belum tentu sudah baik.</p>
                <p><strong>Analisis ini tidak mengubah kunci, bobot, atau nilai siswa.</strong> Patokan 5% ini hanya diterapkan pada pilihan ganda satu jawaban, bukan PG kompleks, benar-salah, atau menjodohkan.</p>
                <p>Rujukan frekuensi pengecoh: <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC2713226/" target="_blank" rel="noopener noreferrer">penelitian analisis pengecoh pilihan ganda</a>.</p>
            </details>
        @endif
    </section>
    @endif
@endsection
