@extends('layouts.app')
@section('admin-module', 'kehadiran')
@section('title', 'Pengecualian Presensi - NUSA')
@section('content')
    @include('pengecualian-presensi._style')
    @php
        $tahunId = old('tahun_pelajaran_id', request('tahun_pelajaran_id', $tahun->first()?->id));
        $nilai = fn ($kunci, $default = '') => old($kunci, request($kunci, $default));
    @endphp
    <div class="page-header">
        <div><p class="eyebrow">Presensi siswa</p><h1 class="page-title">Pengecualian presensi</h1></div>
        <a class="button button-muted" href="{{ route('pengaturan-absensi.index') }}">Pengaturan presensi</a>
    </div>
    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="exception-section">
        <h2>Tetapkan pengecualian atau penugasan siswa</h2>
        <form action="{{ route('pengecualian-presensi.pratinjau') }}" method="POST" data-exception-form>
            @csrf
            <div class="exception-grid">
                <div class="field"><label for="exception-year">Tahun pelajaran</label><select class="select" id="exception-year" name="tahun_pelajaran_id" required>
                    @foreach ($tahun as $t)<option value="{{ $t->id }}" data-start="{{ $t->tanggal_mulai?->toDateString() }}" data-end="{{ $t->tanggal_selesai?->toDateString() }}" @selected((int) $tahunId === $t->id)>{{ $t->nama }}</option>@endforeach
                </select></div>
                <div class="field"><label for="exception-scope">Cakupan</label><select class="select" id="exception-scope" name="cakupan">
                    @foreach (['sekolah' => 'Seluruh sekolah', 'kelas' => 'Satu kelas', 'siswa' => 'Siswa tertentu — tugas sekolah'] as $kode => $label)<option value="{{ $kode }}" @selected($nilai('cakupan', $nilai('kelas_id') ? 'kelas' : 'sekolah') === $kode)>{{ $label }}</option>@endforeach
                </select></div>
                <div class="field wide" id="exception-class-field"><label for="exception-class">Kelas</label><select class="select" id="exception-class" name="kelas_id">
                    <option value="">Pilih kelas</option>
                    @foreach ($kelas as $k)<option value="{{ $k->id }}" data-year="{{ $k->tahun_pelajaran_id }}" @selected((int) $nilai('kelas_id') === $k->id)>{{ $k->nama }}</option>@endforeach
                </select></div>
                <div class="field"><label for="exception-start">Tanggal mulai</label><input class="input" id="exception-start" type="date" name="tanggal_mulai" value="{{ $nilai('tanggal_mulai', now()->toDateString()) }}" required></div>
                <div class="field"><label for="exception-end">Tanggal selesai</label><input class="input" id="exception-end" type="date" name="tanggal_selesai" value="{{ $nilai('tanggal_selesai', now()->toDateString()) }}" required></div>
                <div class="field wide"><label for="exception-kind">Jenis kejadian</label><select class="select" id="exception-kind" name="jenis" required>
                    @foreach (\App\Models\PengecualianPresensiSiswa::jenisWeb() as $kode => $label)<option value="{{ $kode }}" @selected($nilai('jenis', 'pjj') === $kode)>{{ $label }}</option>@endforeach
                </select></div>
                @include('pengecualian-presensi._siswa')
                <div class="field wide"><label for="exception-reason">Alasan / dasar penetapan</label><textarea class="textarea" id="exception-reason" name="alasan" rows="3" minlength="10" maxlength="1000" required placeholder="Contoh: PJJ akibat bencana asap sesuai keputusan sekolah.">{{ $nilai('alasan') }}</textarea></div>
            </div>
            <p class="exception-note" id="exception-legacy-note">Hanya alfa otomatis karena tidak scan yang dikecualikan. Catatan scan dan presensi manual tetap berlaku. Siswa tidak otomatis dianggap hadir.</p>
            <p class="exception-note" id="exception-duty-note" hidden>Peserta dicatat Hadir — Tugas sekolah pada hari presensi aktif, tanpa mengisi jam scan fiktif dan tanpa sanksi keterlambatan/tidak membawa kartu. Catatan Sakit/Izin perlu diperiksa terlebih dahulu. Maksimal 200 siswa dan 31 hari per penetapan.</p>
            <div class="exception-actions"><button class="button button-primary" type="submit" @disabled($tahun->isEmpty())><img src="{{ asset('images/icons/eye.svg') }}" width="18" height="18" alt="" aria-hidden="true"> Pratinjau dampak</button></div>
        </form>
    </section>

    <section class="exception-section">
        <h2>Penetapan &amp; riwayat pembatalan</h2>
        <div class="exception-history">
            @forelse ($riwayat as $p)
                <article class="exception-item {{ $p->aktif ? '' : 'is-cancelled' }}" data-exception-item>
                    <div class="exception-head"><div><h3>{{ \App\Models\PengecualianPresensiSiswa::jenisWeb()[$p->jenis] }}</h3><p class="exception-note">{{ $p->tanggal_mulai->locale('id')->translatedFormat('d F Y') }} s.d. {{ $p->tanggal_selesai->locale('id')->translatedFormat('d F Y') }}</p></div><span class="badge {{ $p->aktif ? 'badge-active' : 'badge-muted' }}">{{ $p->aktif ? 'Aktif' : 'Dibatalkan' }}</span></div>
                    <p class="exception-note"><strong>{{ $p->labelCakupan() }}</strong> &middot; {{ $p->tahunPelajaran?->nama }}</p>
                    @if ($p->cakupan === 'siswa')<details class="exception-students-history"><summary>Lihat siswa terpilih</summary><ul>@foreach ($p->anggotaKelas as $a)<li>{{ $a->siswa?->nama_lengkap }} · {{ $a->kelas?->nama }}</li>@endforeach</ul></details>@endif
                    @if (!$p->aktif && $p->cakupan === 'siswa')
                        <details><summary>Arsip catatan penugasan yang dibatalkan</summary>
                            @foreach ($p->anggotaKelas as $a)
                                @foreach (json_decode($a->pivot->riwayat_pembatalan ?? '[]', true) as $tanggalArsip => $arsip)
                                    <p class="exception-note"><strong>{{ $a->siswa?->nama_lengkap }} · {{ $tanggalArsip }}</strong>: penugasan tanpa bukti scan dibatalkan, kembali mengikuti presensi biasa.</p>
                                    @foreach ($arsip['riwayat'] as $r)<p class="exception-note">{{ $r['catatan'] }}</p>@endforeach
                                @endforeach
                            @endforeach
                        </details>
                    @endif
                    <p class="exception-reason">{{ $p->alasan }}</p>
                    <p class="exception-note">Ditetapkan {{ $p->created_at->format('d-m-Y H:i') }} oleh {{ $p->pembuat?->nama ?: 'Akun tidak tersedia' }}. Saat pratinjau: {{ $p->dampak_pratinjau['siswa_terdampak'] }} siswa{{ $p->jenis === 'tugas_sekolah' ? ' terpilih.' : ', '.$p->dampak_pratinjau['alfa_otomatis_dibatalkan'].' alfa otomatis.' }}</p>
                    @if ($p->jenis === 'tugas_sekolah')<p class="exception-note">{{ $p->dampak_pratinjau['hadir_penugasan'] ?? 0 }} catatan hadir penugasan; {{ $p->dampak_pratinjau['poin_dibatalkan'] ?? 0 }} poin presensi dikecualikan saat penetapan.</p>@endif
                    @if (! $p->aktif)
                        <p class="exception-note">Dibatalkan {{ $p->dibatalkan_pada?->format('d-m-Y H:i') }} oleh {{ $p->pembatal?->nama ?: 'Akun tidak tersedia' }}.</p><p class="exception-reason">Alasan pembatalan: {{ $p->alasan_pembatalan }}</p>
                    @else
                        <details><summary>Batalkan pengecualian</summary>
                            <form action="{{ route('pengecualian-presensi.batalkan', $p) }}" method="POST">
                                @csrf
                                <div class="field"><label for="cancel-reason-{{ $p->id }}">Alasan pembatalan</label><textarea class="textarea" id="cancel-reason-{{ $p->id }}" name="alasan_pembatalan" rows="2" minlength="10" maxlength="1000" required></textarea></div>
                                <label class="exception-check"><input type="checkbox" name="konfirmasi" value="1" required><span>Saya memahami alfa otomatis dapat kembali muncul dan rekap rapor perlu diperiksa ulang.</span></label>
                                <div class="exception-actions"><button class="button button-danger" type="submit">Batalkan pengecualian ini</button></div>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <p class="exception-note">Belum ada pengecualian presensi.</p>
            @endforelse
        </div>
        <div style="margin-top: 18px;">{{ $riwayat->links() }}</div>
    </section>
    <script>
        (() => {
            const year = document.querySelector('#exception-year'), classes = document.querySelector('#exception-class');
            const scope = document.querySelector('#exception-scope'), kind = document.querySelector('#exception-kind');
            const rows = [...document.querySelectorAll('[data-student-row]')];
            const search = document.querySelector('#exception-student-search'), filter = document.querySelector('#exception-student-class');
            const updateSelection = () => {
                const checked = rows.filter(row => row.querySelector('input').checked);
                document.querySelector('#exception-selected-count').textContent = `${checked.length} siswa dipilih`;
                document.querySelector('#exception-selected-names').textContent = checked.map(row => row.dataset.label).join(' · ');
            };
            const filterStudents = () => {
                const needle = search.value.trim().toLocaleLowerCase('id');
                for (const row of rows) {
                    const input = row.querySelector('input');
                    input.disabled = scope.value !== 'siswa' || row.dataset.year !== year.value;
                    if (row.dataset.year !== year.value) input.checked = false;
                    row.hidden = row.dataset.year !== year.value || (filter.value && row.dataset.class !== filter.value) || !row.dataset.search.includes(needle);
                }
                updateSelection();
            };
            const update = () => {
                const selectedYear = year.selectedOptions[0];
                for (const input of document.querySelectorAll('#exception-start, #exception-end')) {
                    input.min = selectedYear?.dataset.start || ''; input.max = selectedYear?.dataset.end || '';
                }
                for (const option of classes.options) {
                    const unavailable = Boolean(option.dataset.year && option.dataset.year !== year.value);
                    option.hidden = unavailable; option.disabled = unavailable;
                    if (option.selected && unavailable) classes.value = '';
                }
                document.querySelector('#exception-class-field').hidden = scope.value !== 'kelas';
                classes.disabled = scope.value !== 'kelas'; classes.required = scope.value === 'kelas';
                document.querySelector('#exception-students').hidden = scope.value !== 'siswa';
                document.querySelector('#exception-duty-note').hidden = scope.value !== 'siswa';
                document.querySelector('#exception-legacy-note').hidden = scope.value === 'siswa';
                document.querySelector('#exception-reason').placeholder = scope.value === 'siswa' ? 'Contoh: Mewakili sekolah mengikuti lomba, upacara, atau kegiatan resmi di luar sekolah.' : 'Contoh: PJJ akibat bencana asap sesuai keputusan sekolah.';
                for (const option of filter.options) {
                    option.hidden = Boolean(option.dataset.year && option.dataset.year !== year.value);
                    option.disabled = option.hidden;
                    if (option.selected && option.hidden) filter.value = '';
                }
                filterStudents();
            };
            scope.addEventListener('change', () => { if (scope.value === 'siswa') kind.value = 'tugas_sekolah'; else if (kind.value === 'tugas_sekolah') kind.value = 'pjj'; update(); });
            kind.addEventListener('change', () => { if (kind.value === 'tugas_sekolah') scope.value = 'siswa'; else if (scope.value === 'siswa') scope.value = 'sekolah'; update(); });
            rows.forEach(row => row.querySelector('input').addEventListener('change', updateSelection));
            search.addEventListener('input', filterStudents); filter.addEventListener('change', filterStudents);
            document.querySelector('#exception-select-visible').addEventListener('click', () => { for (const row of rows) if (!row.hidden && !row.querySelector('input').disabled) row.querySelector('input').checked = true; updateSelection(); });
            document.querySelector('#exception-clear-students').addEventListener('click', () => { rows.forEach(row => row.querySelector('input').checked = false); updateSelection(); });
            year.addEventListener('change', update);
            if (kind.value === 'tugas_sekolah') scope.value = 'siswa'; update();
        })();
    </script>
@endsection
