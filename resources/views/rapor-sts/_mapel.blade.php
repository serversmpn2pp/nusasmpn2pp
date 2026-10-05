@php
    $mapelPilihan = $pilihanMapel['mapel'];
    $pengaturanMapel = $pilihanMapel['pengaturan'];
    $jumlahDipilih = $mapelPilihan->where('dipilih', true)->count();
    $pilihanLama = old('mapel_ids');
    $idsLama = is_array($pilihanLama) ? collect($pilihanLama)->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id))->map(fn ($id) => (int) $id) : null;
@endphp
<section class="sts-section sts-subject-section" aria-labelledby="sts-subject-title">
    <details data-sts-mapel-details @if ($errors->hasAny(['mapel_ids', 'versi_mapel', 'sidik_mapel', 'alasan_mapel'])) open @endif>
        <summary>
            <span><strong id="sts-subject-title">Mata pelajaran rapor STS</strong><span class="sts-subject-scope">Tingkat {{ $kelas->tingkat }} · {{ $pilihanMapel['kelas']->count() }} kelas paralel</span></span>
            <span class="sts-badge is-complete">{{ $jumlahDipilih }} diikutkan · {{ $mapelPilihan->count() - $jumlahDipilih }} dikecualikan</span>
        </summary>
        @if ($mapelPilihan->where('dipulihkan', true)->isNotEmpty())
            <p class="sts-notice">{{ $mapelPilihan->where('dipulihkan', true)->pluck('mapel.nama')->join(', ') }} kembali diikutkan karena memiliki sumber STS baru. Pengaturan perlu diperiksa kembali.</p>
        @endif
        @if ($dapatMengaturMapel)
            <form method="POST" action="{{ route('rapor-sts.mapel', [$kegiatan, $kelas]) }}" id="sts-mapel-form" data-tingkat="{{ $kelas->tingkat }}">
                @csrf @method('PUT')
                <input type="hidden" name="versi_mapel" value="{{ $pengaturanMapel?->versi ?? 0 }}">
                <input type="hidden" name="sidik_mapel" value="{{ $pilihanMapel['sidik'] }}">
        @else
            <div>
                <p class="help-text">Pilihan ditetapkan oleh administrator atau waka kurikulum.</p>
        @endif
            <div class="sts-subject-list">
                @foreach ($mapelPilihan as $item)
                    @php $mid = $item['mapel']->id; $dicentang = $item['wajib'] || ($idsLama ? $idsLama->contains($mid) : $item['dipilih']); @endphp
                    <label class="sts-subject-option" for="sts-mapel-{{ $mid }}">
                        <input id="sts-mapel-{{ $mid }}" type="checkbox" name="mapel_ids[]" value="{{ $mid }}" data-sts-mapel-choice data-saved="{{ (int) $item['dipilih'] }}"
                            @checked($dicentang) @disabled(! $dapatMengaturMapel || $item['wajib'])>
                        <span><strong>{{ $item['mapel']->nama }}</strong><span class="sts-original">{{ $item['sumber'] }} · {{ count($item['kelas_ids']) }}/{{ $pilihanMapel['kelas']->count() }} kelas</span></span>
                        @if ($item['wajib'])<span class="sts-subject-required" title="Mapel dengan jadwal CBT atau komponen STS aktif tetap diikutkan.">Wajib</span>@endif
                    </label>
                    @if ($dapatMengaturMapel && $item['wajib'])<input type="hidden" name="mapel_ids[]" value="{{ $mid }}">@endif
                @endforeach
            </div>
            @if ($mapelPilihan->isEmpty())<p class="help-text">Belum ada mata pelajaran angka untuk tingkat ini.</p>@endif
            @if ($dapatMengaturMapel)
                <div class="sts-subject-footer">
                    <div class="field">
                        <label for="sts-mapel-alasan">Alasan penetapan mapel</label>
                        <textarea class="input" id="sts-mapel-alasan" name="alasan_mapel" rows="2" maxlength="500" required placeholder="Contoh: Pendidikan Inklusi tidak menyelenggarakan STS.">{{ old('alasan_mapel') }}</textarea>
                    </div>
                    <div>
                        <p class="help-text" data-sts-mapel-count>{{ $jumlahDipilih }} mapel diikutkan</p>
                        <button class="button button-primary" @disabled($mapelPilihan->isEmpty())>Simpan pilihan mapel</button>
                    </div>
                </div>
                <p class="sts-status sts-review-state is-pending" data-sts-mapel-unsaved hidden>Perubahan pilihan mapel belum disimpan.</p>
                <p class="help-text">Mapel dikecualikan: tidak masuk jumlah dan rata-rata. Mapel dengan sumber STS aktif: wajib diikutkan.</p>
            @endif
        @if ($dapatMengaturMapel)</form>@else</div>@endif
        @if ($pengaturanMapel)
            <p class="sts-original">Penetapan terakhir: {{ $pengaturanMapel->pengubah?->nama ?? 'Akun tidak tersedia' }} · {{ $pengaturanMapel->updated_at->format('d-m-Y H:i') }} · Versi {{ $pengaturanMapel->versi }}</p>
            <details class="sts-subject-history"><summary>Riwayat penetapan</summary>
                <ol>
                    @foreach ($pilihanMapel['riwayat'] as $riwayat)
                        <li><strong>Versi {{ $riwayat->versi }} · {{ $riwayat->nama ?? 'Akun tidak tersedia' }}</strong><span class="sts-original">{{ \Carbon\Carbon::parse($riwayat->created_at)->format('d-m-Y H:i') }}</span><p>{{ $riwayat->alasan }}</p></li>
                    @endforeach
                </ol>
            </details>
        @endif
    </details>
</section>
