@extends('layouts.app')
@section('title', 'Rapor STS - NUSA')
@section('content')
    <style>
        .sts-section { padding: 22px 0; border-bottom: 1px solid var(--line); min-width: 0; }
        .sts-section h2 { margin: 0 0 16px; font-size: 1.05rem; }
        .sts-section .button:disabled { opacity: .5; cursor: not-allowed; }
        .sts-filters, .sts-dates { display: grid; grid-template-columns: minmax(220px, 2fr) minmax(150px, 1fr) auto; gap: 16px; align-items: end; }
        .sts-dates { grid-template-columns: repeat(3, minmax(150px, 1fr)) auto; }
        .sts-summary { display: flex; flex-wrap: wrap; gap: 16px 32px; padding: 18px 0; border-bottom: 1px solid var(--line); }
        .sts-summary strong { font-size: 1.25rem; margin-right: 8px; }
        .sts-summary span { color: var(--muted); font-size: .9rem; }
        .sts-table-wrap { overflow-x: auto; max-width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; }
        .sts-table { width: 100%; min-width: 900px; table-layout: fixed; border-collapse: collapse; font-size: .88rem; }
        .sts-table th, .sts-table td { padding: 14px; vertical-align: top; border-bottom: 1px solid #dbe2ea; text-align: left; }
        .sts-table th { background: #f0f4f8; color: #334155; font-size: .8rem; font-weight: 700; }
        .sts-table td + td, .sts-table th + th { border-left: 1px solid #edf0f4; }
        .sts-table tbody tr:last-child td { border-bottom: 0; }
        .sts-table tbody tr:hover { background: #fafcfe; }
        .sts-table .sts-number { width: 100%; height: 42px; min-height: 42px; padding: 6px 4px; text-align: center; font-size: .95rem; font-variant-numeric: tabular-nums; }
        .sts-table .sts-note { display: block; width: 100%; height: 68px; min-height: 68px; padding: 9px 10px; resize: vertical; font-size: .85rem; }
        .sts-original { display: block; color: #64748b; font-size: .75rem; line-height: 1.5; margin-top: 6px; }
        .sts-person { overflow-wrap: anywhere; }
        .sts-identity { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; line-height: 1.5; }
        .sts-order { display: grid; place-items: center; flex: 0 0 28px; height: 28px; border: 1px solid var(--line); border-radius: 4px; background: #f4f7fa; color: #64748b; font-size: .75rem; font-weight: 600; }
        .sts-identity strong { padding-top: 3px; font-size: .88rem; }
        .sts-status { display: block; margin: 8px 0; font-size: .8rem; line-height: 1.5; }
        .sts-badge { display: inline-flex; align-items: center; gap: 6px; width: fit-content; max-width: 100%; padding: 4px 8px; border-radius: 4px; background: #fff7e0; color: #805500; font-size: .75rem; font-weight: 600; line-height: 1.4; }
        .sts-badge.is-complete { background: #eaf6ef; color: #236443; }
        .sts-attendance { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .sts-attendance label { display: block; margin-bottom: 7px; font-size: .78rem; color: #475569; font-weight: 600; text-align: center; }
        .sts-attendance .sts-original { text-align: center; }
        .sts-check { display: flex; gap: 8px; align-items: center; font-size: .85rem; cursor: pointer; line-height: 1.4; margin: 0; }
        .sts-check input { width: 18px; height: 18px; margin: 0; flex: 0 0 auto; accent-color: var(--primary); }
        .sts-review { padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; background: #fff; min-height: 42px; }
        .sts-review:has(input:checked) { border-color: #9fcab0; background: #edf8f1; color: #236443; }
        .sts-review-state { color: #64748b; }
        .sts-review-state.is-pending { color: #946200; }
        .sts-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; padding: 14px 0; }
        .sts-table .button { min-height: 38px; padding: 8px 10px; font-size: .8rem; border-radius: 5px; }
        .sts-detail-button { margin-top: 10px; }
        .sts-report-actions { display: flex; flex-direction: column; gap: 8px; }
        .sts-report-actions .button { width: 100%; text-align: center; }
        .sts-notice { padding: 12px 16px; border-left: 3px solid var(--primary); background: var(--primary-soft); font-size: .85rem; margin: 16px 0; }
        .sts-reset { display: block; background: none; border: 0; margin-top: 8px; padding: 4px 0; color: var(--primary); cursor: pointer; font: inherit; font-size: .78rem; font-weight: 600; text-align: left; text-decoration: underline; text-underline-offset: 3px; }
        .sts-reset:disabled { cursor: not-allowed; opacity: .5; }
        .sts-dialog { width: min(780px, calc(100vw - 32px)); max-width: none; max-height: calc(100dvh - 48px); padding: 0; border: 1px solid #cbd5e1; border-radius: 8px; color: var(--text); background: #fff; box-shadow: 0 24px 64px #0f172a33; }
        .sts-dialog[open] { display: flex; flex-direction: column; }
        .sts-dialog::backdrop { background: #172b46a6; }
        .sts-dialog-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding: 20px 24px; border-bottom: 1px solid var(--line); flex: 0 0 auto; }
        .sts-dialog-header h2 { font-size: 1.15rem; margin: 0 0 6px; }
        .sts-dialog-header p { margin: 0; color: #64748b; font-size: .85rem; overflow-wrap: anywhere; }
        .sts-dialog-header button { flex-shrink: 0; }
        .sts-dialog-body { overflow: auto; min-height: 0; padding: 20px 24px; }
        .sts-grade-summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; font-size: .85rem; color: #475569; }
        .sts-grade-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: .85rem; }
        .sts-grade-table th, .sts-grade-table td { text-align: left; padding: 12px; border-bottom: 1px solid var(--line); overflow-wrap: anywhere; }
        .sts-grade-table th { background: #f0f4f8; color: #475569; font-size: .78rem; }
        .sts-grade-table .sts-grade-score { width: 90px; text-align: right; font-variant-numeric: tabular-nums; }
        .sts-grade-table td:last-child { color: #64748b; font-size: .8rem; }
        .sts-grade-table tfoot th { background: #fff; font-size: .85rem; }
        .sts-dialog-footnote { margin: 16px 0 0; font-size: .8rem; color: #64748b; }
        .sts-exception { margin-top: 10px; }
        .sts-exception .sts-check { font-size: .8rem; font-weight: 600; }
        .sts-exception-reason { display: block; margin-top: 10px; font-size: .78rem; color: #475569; }
        .sts-exception-reason textarea { display: block; width: 100%; min-height: 76px; margin-top: 6px; padding: 8px; resize: vertical; font-size: .8rem; }
        .sts-exception-reason[hidden] { display: none; }
        .sts-exception-save { display: flex; justify-content: flex-end; gap: 12px; align-items: center; padding-top: 16px; margin-top: 16px; border-top: 1px solid var(--line); }
        .sts-exception-save .button:disabled { opacity: .5; cursor: not-allowed; }
        .sts-exception-error { padding: 10px 12px; border-left: 3px solid #b91c1c; color: #991b1b; background: #fff1f2; font-size: .85rem; }
        .sts-exception-stale { color: #946200; font-size: .78rem; margin: 8px 0; }
        .sts-unsaved-warning { padding: 14px 24px; border-top: 1px solid #e7cd89; background: #fff8e8; font-size: .85rem; flex: 0 0 auto; }
        .sts-unsaved-warning p { margin: 0 0 10px; }
        .sts-unsaved-warning .actions { justify-content: flex-end; }
        @media (max-width: 950px) { .sts-dates { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
        @media (max-width: 640px) { .sts-filters, .sts-dates { grid-template-columns: 1fr; } }
    </style>
    <div class="page-header">
        <div><p class="eyebrow">Penilaian</p><h1 class="page-title">Rapor STS</h1></div>
    </div>
    <form method="GET" action="{{ route('rapor-sts.index') }}" class="sts-section sts-filters">
        <div class="field"><label for="sts-kegiatan">Kegiatan STS</label>
            <select class="select" id="sts-kegiatan" name="kegiatan_id" required>
                @forelse ($daftarKegiatan as $item)
                    <option value="{{ $item->id }}" @selected($kegiatan?->id === $item->id)>{{ $item->nama }} · {{ $item->tahunPelajaran?->nama }}</option>
                @empty<option value="">Belum ada kegiatan STS</option>@endforelse
            </select>
        </div>
        <div class="field"><label for="sts-kelas">Kelas</label><select class="select" id="sts-kelas" name="kelas_id">
            @forelse ($daftarKelas as $item)<option value="{{ $item->id }}" @selected($kelas?->id === $item->id)>{{ $item->nama }}</option>@empty<option value="">Belum ada kelas</option>@endforelse
        </select></div>
        <button class="button button-primary" @disabled($daftarKegiatan->isEmpty())>Tampilkan rekap</button>
    </form>
    @if ($laporan)
        @php
            $pengaturan = $laporan['pengaturan'];
            $baris = $laporan['baris'];
            $siap = $baris->where('siap', true)->count();
            $diperiksa = $baris->where('diperiksa', true)->count();
        @endphp
        <section class="sts-section">
            <h2>1. Periode dan tanggal rapor</h2>
            <form method="POST" action="{{ route('rapor-sts.pengaturan', [$kegiatan, $kelas]) }}" class="sts-dates" id="sts-periode-form">
                @csrf @method('PUT')<input type="hidden" name="versi" value="{{ $pengaturan->versi }}">
                <div class="field"><label for="sts-awal">Awal rekap presensi</label><input class="input" id="sts-awal" type="date" name="tanggal_awal_presensi" value="{{ old('tanggal_awal_presensi', $pengaturan->tanggal_awal_presensi->toDateString()) }}" required></div>
                <div class="field"><label for="sts-akhir">Batas rekap presensi</label><input class="input" id="sts-akhir" type="date" name="tanggal_akhir_presensi" value="{{ old('tanggal_akhir_presensi', $pengaturan->tanggal_akhir_presensi->toDateString()) }}" required></div>
                <div class="field"><label for="sts-tanggal">Tanggal pembagian rapor</label><input class="input" id="sts-tanggal" type="date" name="tanggal_rapor" value="{{ old('tanggal_rapor', $pengaturan->tanggal_rapor->toDateString()) }}" required></div>
                <button class="button button-primary">Simpan periode</button>
            </form>
            <p class="help-text">Wali kelas: {{ $kelas->waliKelas?->nama_lengkap ?? 'Belum ditetapkan' }} · Nilai: hasil final CBT pada kegiatan STS ini.</p>
            @if (! $pengaturan->exists)<p class="sts-notice">Periode belum disimpan. Pemeriksaan kehadiran dan cetak final belum tersedia.</p>@endif
            @if ($pengaturan->tanggal_akhir_presensi->isFuture())<p class="sts-notice">Periode presensi belum berakhir. Rapor final belum dapat dicetak.</p>@endif
        </section>
        <div class="sts-summary">
            <div><strong>{{ $baris->count() }}</strong><span>Siswa</span></div>
            <div><strong>{{ $baris->where('nilai_tuntas', true)->count() }}</strong><span>Rekap nilai lengkap</span></div>
            <div><strong>{{ $diperiksa }}</strong><span>Kehadiran diperiksa</span></div>
            <div><strong>{{ $siap }}</strong><span>Siap cetak</span></div>
        </div>
        <section class="sts-section">
            <h2>2. Pemeriksaan nilai dan kehadiran</h2>
            <p class="sts-notice">Rekap awal diambil dari presensi sekolah yang tercatat. Hari tanpa catatan tidak otomatis dihitung alfa. Koreksi di sini hanya berlaku pada rapor, tidak mengubah presensi harian.</p>
            <form method="POST" action="{{ route('rapor-sts.kehadiran', [$kegiatan, $kelas]) }}" id="sts-kehadiran-form">
                @csrf @method('PUT')<input type="hidden" name="versi" value="{{ $pengaturan->versi }}">
                <div class="sts-actions">
                    <label class="sts-check"><input type="checkbox" id="sts-periksa-semua" @disabled(! $pengaturan->exists || $baris->isEmpty())> Semua siswa sudah diperiksa</label>
                    <button class="button button-primary" @disabled(! $pengaturan->exists || $baris->isEmpty())>Simpan pemeriksaan</button>
                </div>
                <div class="sts-table-wrap" tabindex="0" aria-label="Rekap kehadiran dan kelengkapan nilai STS">
                    <table class="sts-table">
                        <colgroup><col style="width: 24%"><col style="width: 25%"><col style="width: 22%"><col style="width: 17%"><col style="width: 12%"></colgroup>
                        <thead><tr>
                        <th scope="col">Siswa & nilai STS</th><th scope="col">Ketidakhadiran <span style="font-weight: 400">(hari)</span></th><th scope="col">Alasan koreksi</th><th scope="col">Pemeriksaan</th><th scope="col">Rapor</th>
                    </tr></thead><tbody>
                    @forelse ($baris as $item)
                        @php $id = $item['anggota']->id; $nama = $item['anggota']->siswa->nama_lengkap; @endphp
                        <tr data-sts-row>
                            <td class="sts-person">
                                <div class="sts-identity"><span class="sts-order">{{ $item['anggota']->nomor_absen }}</span><strong>{{ $nama }}</strong></div>
                                <span class="sts-badge {{ $item['nilai_lengkap'] ? 'is-complete' : '' }}">{{ $item['nilai']->whereNotNull('nilai')->count() }}/{{ $laporan['mapel']->count() }} mapel final</span>
                                @if ($item['jumlah_pengecualian'])<span class="sts-original">{{ $item['jumlah_pengecualian'] }} mapel: tidak mengikuti STS</span>@endif
                                <div><button type="button" class="button button-muted sts-detail-button" data-sts-grades="sts-nilai-{{ $id }}" data-sts-name="{{ $nama }}" aria-haspopup="dialog" aria-controls="sts-grade-dialog" aria-label="Rincian nilai {{ $nama }}">Rincian nilai</button></div>
                            </td>
                            <td>
                                <div class="sts-attendance">
                            @foreach (['sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa'] as $jenis => $label)
                                    <div><label for="sts-{{ $jenis }}-{{ $id }}">{{ $label }}</label><input class="input sts-number" id="sts-{{ $jenis }}-{{ $id }}" type="number" name="siswa[{{ $id }}][{{ $jenis }}]" value="{{ old('siswa.'.$id.'.'.$jenis, $item['kehadiran'][$jenis]) }}" min="0" max="366" step="1" required aria-label="{{ $label }} {{ $nama }}" data-sts-original="{{ $item['sumber'][$jenis] }}" @disabled(! $pengaturan->exists)><span class="sts-original">Rekap awal: {{ $item['sumber'][$jenis] }}</span></div>
                            @endforeach
                                </div>
                                <span class="sts-original">{{ $item['sumber']['hari_tercatat'] }} hari presensi tercatat</span>
                            </td>
                            <td><textarea class="input sts-note" name="siswa[{{ $id }}][catatan_koreksi]" maxlength="500" placeholder="Catatan koreksi..." aria-label="Alasan koreksi {{ $nama }}" @disabled(! $pengaturan->exists)>{{ old('siswa.'.$id.'.catatan_koreksi', $item['koreksi']?->catatan_koreksi) }}</textarea><button type="button" class="sts-reset" data-sts-reset @disabled(! $pengaturan->exists)>Kembalikan ke rekap awal</button></td>
                            <td>
                                <input type="hidden" name="siswa[{{ $id }}][sidik_sumber]" value="{{ $item['sidik_sumber'] }}">
                                <input type="hidden" name="siswa[{{ $id }}][diperiksa]" value="0">
                                <label class="sts-check sts-review"><input type="checkbox" name="siswa[{{ $id }}][diperiksa]" value="1" data-sts-reviewed data-sts-saved="{{ (int) $item['diperiksa'] }}" data-sts-source-changed="{{ (int) $item['sumber_berubah'] }}" @checked(old('siswa.'.$id.'.diperiksa', $item['diperiksa'])) @disabled(! $pengaturan->exists)> Diperiksa</label>
                                <span class="sts-status sts-review-state" data-sts-review-state>{{ $item['sumber_berubah'] ? 'Rekap presensi berubah. Periksa ulang.' : ($item['diperiksa'] ? 'Tersimpan' : 'Belum diperiksa') }}</span>
                                @if ($item['koreksi']?->diperiksa_pada)<span class="sts-original">{{ $item['koreksi']->pemeriksa?->nama }}<br>{{ $item['koreksi']->diperiksa_pada->format('d-m-Y H:i') }}</span>@endif
                            </td>
                            <td><div class="sts-report-actions"><a class="button button-muted" target="_blank" rel="noopener" aria-label="Pratinjau rapor {{ $nama }}" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'anggota_id' => $id, 'pratinjau' => 1]) }}">Pratinjau</a>
                                @if ($item['siap'])<a class="button button-primary" data-sts-print target="_blank" rel="noopener" aria-label="Cetak rapor {{ $nama }}" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'anggota_id' => $id]) }}">Cetak rapor</a>@endif
                                </div>
                            </td>
                        </tr>
                    @empty<tr><td colspan="5">Belum ada siswa aktif pada kelas ini.</td></tr>@endforelse
                    </tbody></table>
                </div>
                <p class="help-text" id="sts-unsaved" hidden>Ada perubahan yang belum disimpan. Cetak menggunakan pemeriksaan terakhir yang tersimpan.</p>
            </form>
        </section>
        <dialog id="sts-grade-dialog" class="sts-dialog" aria-labelledby="sts-grade-title" aria-describedby="sts-grade-student" data-sts-reopen="{{ session('rincian_siswa', old('anggota_id')) }}">
            <div class="sts-dialog-header">
                <div><h2 id="sts-grade-title">Rincian nilai STS</h2><p id="sts-grade-student"></p></div>
                <button type="button" class="button button-muted" data-sts-close autofocus>Tutup</button>
            </div>
            <div class="sts-dialog-body" id="sts-grade-content" tabindex="0" aria-label="Daftar nilai mata pelajaran"></div>
            <div class="sts-unsaved-warning" data-sts-unsaved-warning hidden role="alert">
                <p>Keterangan STS yang diubah belum disimpan.</p>
                <div class="actions"><button type="button" class="button button-muted" data-sts-discard>Tutup tanpa menyimpan</button><button type="button" class="button button-primary" data-sts-continue>Tetap mengedit</button></div>
            </div>
        </dialog>
        @foreach ($baris as $item)
            <template id="sts-nilai-{{ $item['anggota']->id }}">
                @php
                    $bisaCatat = $item['nilai']->contains(fn ($n) => $n['dapat_dikecualikan'] || $n['pengecualian']?->aktif);
                    $isOldStudent = (int) old('anggota_id') === $item['anggota']->id;
                @endphp
                <div class="sts-grade-summary"><span>{{ $kelas->nama }} · {{ $kegiatan->nama }}</span><span class="sts-badge {{ $item['nilai_lengkap'] ? 'is-complete' : '' }}">{{ $item['nilai']->whereNotNull('nilai')->count() }}/{{ $laporan['mapel']->count() }} mapel final</span></div>
                @if ($isOldStudent && $errors->any())
                    <div class="sts-exception-error" role="alert">@foreach ($errors->all() as $pesan)<p>{{ $pesan }}</p>@endforeach</div>
                @endif
                <form method="POST" action="{{ route('rapor-sts.pengecualian', [$kegiatan, $kelas]) }}" data-sts-exception-form>
                @csrf @method('PUT')
                <input type="hidden" name="versi" value="{{ $pengaturan->versi }}">
                <input type="hidden" name="anggota_id" value="{{ $item['anggota']->id }}">
                <p class="sts-exception-error" data-sts-exception-message role="alert" hidden>Simpan perubahan periode dan pemeriksaan kehadiran terlebih dahulu, kemudian simpan keterangan STS.</p>
                <table class="sts-grade-table">
                    <colgroup><col style="width: 39%"><col style="width: 80px"><col></colgroup>
                    <thead><tr><th scope="col">Mata pelajaran</th><th scope="col" class="sts-grade-score">Nilai</th><th scope="col">Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($item['nilai'] as $nilai)
                            <tr><td>{{ $nilai['mapel']->nama }}</td><td class="sts-grade-score"><strong>{{ $nilai['nilai'] === null ? '-' : number_format($nilai['nilai'], 2, ',', '.') }}</strong></td><td>
                                {{ $nilai['nilai'] === null ? $nilai['status'] : $nilai['keterangan'] }}
                                @if ($nilai['dapat_dikecualikan'] || $nilai['pengecualian']?->aktif)
                                    @php
                                        $mid = $nilai['mapel']->id;
                                        $catatan = $nilai['pengecualian'];
                                        $dicentang = $isOldStudent ? old('pengecualian.'.$mid.'.tidak_mengikuti', $catatan?->aktif ?? false) : ($catatan?->aktif ?? false);
                                    @endphp
                                    <div class="sts-exception" data-sts-exception>
                                        <input type="hidden" name="pengecualian[{{ $mid }}][sidik_kondisi]" value="{{ $nilai['sidik_kondisi'] }}">
                                        <input type="hidden" name="pengecualian[{{ $mid }}][tidak_mengikuti]" value="0">
                                        <label class="sts-check"><input type="checkbox" name="pengecualian[{{ $mid }}][tidak_mengikuti]" value="1" data-sts-exempt @checked($dicentang) @disabled(! $pengaturan->exists)> Tidak mengikuti STS</label>
                                        @if ($catatan?->aktif && ! $nilai['dikecualikan'])<p class="sts-exception-stale">Keterangan sebelumnya tidak berlaku karena data ujian berubah. Periksa ulang atau hapus centangnya.</p>@endif
                                        <label class="sts-exception-reason" @if (! $dicentang) hidden @endif>Alasan tidak mengikuti STS dan susulan
                                            <textarea class="input" name="pengecualian[{{ $mid }}][alasan]" maxlength="500" @required($dicentang) @disabled(! $pengaturan->exists || ! $dicentang)>{{ $isOldStudent ? old('pengecualian.'.$mid.'.alasan', $catatan?->alasan) : $catatan?->alasan }}</textarea>
                                        </label>
                                        @if ($catatan?->aktif)<span class="sts-original">Dicatat oleh {{ $catatan->penetap?->nama ?? '-' }}<br>{{ $catatan->ditetapkan_pada->format('d-m-Y H:i') }}</span>@endif
                                    </div>
                                @endif
                            </td></tr>
                        @empty<tr><td colspan="3">Belum ada mata pelajaran.</td></tr>@endforelse
                    </tbody>
                    <tfoot><tr><th scope="row">Rata-rata</th><td class="sts-grade-score"><strong>{{ $item['rata'] === null ? '-' : number_format($item['rata'], 2, ',', '.') }}</strong></td><td>{{ $item['nilai_tuntas'] ? ($item['jumlah_bernilai'] ? 'Dari '.$item['jumlah_bernilai'].' mapel bernilai' : 'Tidak ada mapel bernilai') : 'Menunggu nilai / keterangan lengkap' }}</td></tr></tfoot>
                </table>
                @if (! $item['nilai_lengkap'])<p class="sts-dialog-footnote">Nilai yang belum tersedia tidak dihitung sebagai nol.</p>@endif
                @if ($bisaCatat)
                    <p class="sts-dialog-footnote">Keterangan ini berlaku bagi siswa yang tidak mengikuti STS maupun susulan. Rapor mencantumkan "Tidak mengikuti STS"; alasan disimpan sebagai catatan sekolah.</p>
                    <div class="sts-exception-save"><button class="button button-primary" @disabled(! $pengaturan->exists)>Simpan keterangan STS</button></div>
                @endif
                </form>
            </template>
        @endforeach
        <section class="sts-section">
            <h2>3. Cetak rapor kelas</h2>
            <div class="actions">
                @if ($baris->isNotEmpty())<a class="button button-muted" target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas, 'pratinjau' => 1]) }}">Pratinjau seluruh kelas</a>@endif
                @if ($siap > 0 && $siap === $baris->count())<a class="button button-primary" data-sts-print target="_blank" rel="noopener" href="{{ route('rapor-sts.cetak', [$kegiatan, $kelas]) }}">Cetak seluruh kelas</a>
                @else<button class="button button-primary" disabled>Cetak seluruh kelas</button>@endif
            </div>
            <p class="help-text">{{ $baris->count() - $siap }} siswa belum siap cetak · A4, satu halaman per siswa.</p>
        </section>
    @else
        <p class="sts-notice">Belum ada kegiatan STS atau kelas dalam kewenangan Anda.</p>
    @endif
    <script>
        document.getElementById('sts-kegiatan')?.addEventListener('change', function () {
            document.getElementById('sts-kelas').disabled = true;
        });
        (() => {
            const form = document.getElementById('sts-kehadiran-form');
            if (!form) return;
            const checks = [...form.querySelectorAll('[data-sts-reviewed]')];
            const all = document.getElementById('sts-periksa-semua');
            const dialog = document.getElementById('sts-grade-dialog');
            const content = document.getElementById('sts-grade-content');
            let gradeTrigger = null;
            form.querySelectorAll('[data-sts-grades]').forEach(button => button.addEventListener('click', () => {
                const template = document.getElementById(button.dataset.stsGrades);
                if (!template) return;
                gradeTrigger = button;
                exceptionDirty = false;
                dialog.querySelector('[data-sts-unsaved-warning]').hidden = true;
                document.getElementById('sts-grade-student').textContent = button.dataset.stsName;
                content.replaceChildren(template.content.cloneNode(true));
                dialog.showModal();
                content.scrollTop = 0;
            }));
            const closeGrades = () => {
                if (!exceptionDirty) { dialog.close(); return; }
                dialog.querySelector('[data-sts-unsaved-warning]').hidden = false;
                dialog.querySelector('[data-sts-continue]').focus();
            };
            dialog.querySelector('[data-sts-close]').addEventListener('click', closeGrades);
            dialog.querySelector('[data-sts-discard]').addEventListener('click', () => dialog.close());
            dialog.querySelector('[data-sts-continue]').addEventListener('click', () => {
                dialog.querySelector('[data-sts-unsaved-warning]').hidden = true;
                content.focus();
            });
            dialog.addEventListener('close', () => gradeTrigger?.focus({ preventScroll: true }));
            let dirty = false;
            let exceptionDirty = false;
            content.addEventListener('change', event => {
                if (!event.target.matches('[data-sts-exempt]')) return;
                const field = event.target.closest('[data-sts-exception]').querySelector('.sts-exception-reason');
                field.hidden = !event.target.checked;
                field.querySelector('textarea').disabled = !event.target.checked;
                field.querySelector('textarea').required = event.target.checked;
            });
            content.addEventListener('input', () => { exceptionDirty = true; });
            content.addEventListener('submit', event => {
                if (dirty) {
                    event.preventDefault();
                    event.target.querySelector('[data-sts-exception-message]').hidden = false;
                }
            });
            dialog.addEventListener('cancel', event => {
                event.preventDefault();
                closeGrades();
            });
            const changedRows = new Set();
            const sync = () => {
                all.checked = checks.length > 0 && checks.every(c => c.checked);
                all.indeterminate = checks.some(c => c.checked) && !all.checked;
                checks.forEach(check => {
                    const row = check.closest('[data-sts-row]');
                    const state = row.querySelector('[data-sts-review-state]');
                    const pending = changedRows.has(row) || check.checked !== (check.dataset.stsSaved === '1');
                    state.textContent = pending ? 'Belum disimpan' : (check.dataset.stsSourceChanged === '1' ? 'Rekap presensi berubah. Periksa ulang.' : (check.checked ? 'Tersimpan' : 'Belum diperiksa'));
                    state.classList.toggle('is-pending', pending);
                });
            };
            const changed = () => { dirty = true; document.getElementById('sts-unsaved').hidden = false; sync(); };
            form.addEventListener('input', event => {
                if (event.target === all) return;
                const row = event.target.closest('[data-sts-row]');
                if (row) changedRows.add(row);
                changed();
            });
            document.getElementById('sts-periode-form')?.addEventListener('input', changed);
            all.addEventListener('change', () => {
                checks.forEach(check => {
                    if (check.checked !== all.checked) changedRows.add(check.closest('[data-sts-row]'));
                    check.checked = all.checked;
                });
                changed();
            });
            form.querySelectorAll('[data-sts-reset]').forEach(button => button.addEventListener('click', () => {
                const row = button.closest('[data-sts-row]');
                row.querySelectorAll('[data-sts-original]').forEach(input => input.value = input.dataset.stsOriginal);
                row.querySelector('textarea').value = '';
                row.querySelector('[data-sts-reviewed]').checked = false;
                changedRows.add(row);
                changed();
            }));
            document.querySelectorAll('[data-sts-print]').forEach(link => link.addEventListener('click', event => {
                if (dirty) { event.preventDefault(); alert('Simpan perubahan periode dan pemeriksaan kehadiran sebelum mencetak rapor.'); }
            }));
            sync();
            if (dialog.dataset.stsReopen) {
                [...form.querySelectorAll('[data-sts-grades]')].find(button => button.dataset.stsGrades === 'sts-nilai-' + dialog.dataset.stsReopen)?.click();
            }
        })();
    </script>
@endsection
