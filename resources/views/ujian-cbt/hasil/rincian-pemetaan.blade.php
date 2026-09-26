@php $benarSalah = $soal->jenis_soal === 'benar_salah'; @endphp

<section class="rincian-section" aria-labelledby="butir-title">
    <h2 id="butir-title">{{ $benarSalah ? 'Hasil setiap pernyataan' : 'Hasil setiap pasangan' }}</h2>
    <p class="rincian-caption">Setiap butir dibandingkan dengan {{ $item['disajikan'] }} peserta selesai yang mendapat soal ini. Kosong berarti butir tersebut tidak dijawab, meskipun butir lainnya telah diisi. Siswa yang belum selesai atau tidak mengikuti ujian tidak dihitung.</p>
    @if (! $benarSalah)
        <p class="rincian-caption">Pasangan dikelompokkan berdasarkan isi jawaban, bukan huruf pilihan yang diacak. Pengecoh tambahan adalah pilihan ekstra di luar pasangan yang disiapkan guru; pasangan milik butir lain juga dapat dipilih keliru. Frekuensi pilihan tidak otomatis menentukan mutu pengecoh.</p>
    @endif
    @foreach ($rincian['butir'] as $butir)
        <article class="rincian-butir" aria-label="{{ $benarSalah ? 'Pernyataan' : 'Pasangan' }} {{ $butir['nomor'] }}">
            <h3>{{ $benarSalah ? 'Pernyataan' : 'Pasangan' }} {{ $butir['nomor'] }}</h3>
            <div class="rincian-text" data-inline-math>{{ $butir['teks'] }}</div>
            <x-media-soal :media="data_get($soal->media, 'konten.'.($butir['media_key'] ?? ''), [])" />
            <p class="rincian-caption">Kunci: <strong data-inline-math>{{ $butir['kunci'] ?? 'Belum valid' }}</strong></p>
            <div class="rincian-score">
                <span>Benar: <strong>{{ $butir['benar'] ?? '-' }} siswa</strong></span>
                <span>Salah: <strong>{{ $butir['salah'] ?? '-' }} siswa</strong></span>
                <span>Kosong: <strong>{{ $butir['kosong'] }} siswa</strong></span>
                @if ($butir['tidak_dikenali'] > 0)
                    <span>Tidak dikenali: <strong>{{ $butir['tidak_dikenali'] }} siswa</strong></span>
                @endif
                <span>Persentase benar: <strong>{{ $formatPersen($butir['persen_benar']) }}</strong></span>
            </div>
            <details @if ($loop->first) open @endif>
                <summary>{{ $benarSalah ? 'Pilihan Benar / Salah' : 'Pilihan pasangan siswa' }} · Butir {{ $butir['nomor'] }}</summary>
                <div class="rincian-table-wrap" tabindex="0" aria-label="Sebaran jawaban butir {{ $butir['nomor'] }}">
                    <table class="rincian-opsi-table">
                        <thead><tr>
                            <th scope="col">{{ $benarSalah ? 'Jawaban siswa' : 'Isi pilihan pasangan' }}</th>
                            <th scope="col">Keterangan</th>
                            <th scope="col">Dipilih</th>
                            <th scope="col">Persentase</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($butir['pilihan'] as $pilihan)
                                <tr @class(['opsi-kunci' => $pilihan['kunci']])>
                                    <td class="opsi-isi">
                                        <div class="rincian-text" data-inline-math>{{ $pilihan['teks'] }}</div>
                                        <x-media-soal :media="data_get($soal->media, 'konten.'.($pilihan['media_key'] ?? ''), [])" />
                                    </td>
                                    <td>
                                        @if ($pilihan['kunci'] === null)
                                            <span class="badge badge-muted">Kunci belum valid</span>
                                        @elseif ($pilihan['kunci'])
                                            <span class="badge badge-active">Cocok dengan kunci</span>
                                        @elseif ($pilihan['pengecoh_tambahan'])
                                            <span class="badge badge-warning">Pengecoh tambahan</span>
                                        @else
                                            <span class="badge badge-muted">{{ $benarSalah ? 'Tidak sesuai kunci' : 'Pasangan tidak sesuai' }}</span>
                                        @endif
                                    </td>
                                    <td class="opsi-jumlah">{{ $pilihan['dipilih'] }} siswa</td>
                                    <td class="opsi-persen">
                                        <strong>{{ $formatPersen($pilihan['persen']) }}</strong>
                                        @if ($pilihan['persen'] !== null)
                                            <progress max="100" value="{{ $pilihan['persen'] }}" aria-label="Persentase pilihan {{ $loop->iteration }} pada butir {{ $butir['nomor'] }}"></progress>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </article>
    @endforeach
    <div class="rincian-score">
        <span>Skor penuh: <strong>{{ $item['skor_penuh'] }} siswa</strong></span>
        <span>Skor sebagian: <strong>{{ $item['skor_sebagian'] }} siswa</strong></span>
        <span>Skor nol: <strong>{{ $item['skor_nol'] }} siswa</strong></span>
        <span>Belum dinilai: <strong>{{ $item['belum_dinilai'] }} siswa</strong></span>
    </div>
    <p class="rincian-caption">Benar dan salah per butir mengikuti kunci saat ini serta aturan pencocokan koreksi CBT. Ringkasan skor berasal dari nilai tersimpan. Analisis tidak melakukan koreksi ulang atau mengubah nilai siswa.</p>
</section>
