@extends('layouts.app')
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
        <h2>Tetapkan periode tanpa kewajiban scan</h2>
        <form action="{{ route('pengecualian-presensi.pratinjau') }}" method="POST" data-exception-form>
            @csrf
            <div class="exception-grid">
                <div class="field"><label for="exception-year">Tahun pelajaran</label><select class="select" id="exception-year" name="tahun_pelajaran_id" required>
                    @foreach ($tahun as $t)<option value="{{ $t->id }}" data-start="{{ $t->tanggal_mulai?->toDateString() }}" data-end="{{ $t->tanggal_selesai?->toDateString() }}" @selected((int) $tahunId === $t->id)>{{ $t->nama }}</option>@endforeach
                </select></div>
                <div class="field"><label for="exception-class">Cakupan</label><select class="select" id="exception-class" name="kelas_id">
                    <option value="">Seluruh sekolah</option>
                    @foreach ($kelas as $k)<option value="{{ $k->id }}" data-year="{{ $k->tahun_pelajaran_id }}" @selected((int) $nilai('kelas_id') === $k->id)>{{ $k->nama }}</option>@endforeach
                </select></div>
                <div class="field"><label for="exception-start">Tanggal mulai</label><input class="input" id="exception-start" type="date" name="tanggal_mulai" value="{{ $nilai('tanggal_mulai', now()->toDateString()) }}" required></div>
                <div class="field"><label for="exception-end">Tanggal selesai</label><input class="input" id="exception-end" type="date" name="tanggal_selesai" value="{{ $nilai('tanggal_selesai', now()->toDateString()) }}" required></div>
                <div class="field wide"><label for="exception-kind">Jenis kejadian</label><select class="select" id="exception-kind" name="jenis" required>
                    @foreach (\App\Models\PengecualianPresensiSiswa::JENIS as $kode => $label)<option value="{{ $kode }}" @selected($nilai('jenis', 'pjj') === $kode)>{{ $label }}</option>@endforeach
                </select></div>
                <div class="field wide"><label for="exception-reason">Alasan / dasar penetapan</label><textarea class="textarea" id="exception-reason" name="alasan" rows="3" minlength="10" maxlength="1000" required placeholder="Contoh: PJJ akibat bencana asap sesuai keputusan sekolah.">{{ $nilai('alasan') }}</textarea></div>
            </div>
            <p class="exception-note">Hanya alfa otomatis karena tidak scan yang dikecualikan. Catatan scan dan presensi manual tetap berlaku. Siswa tidak otomatis dianggap hadir.</p>
            <div class="exception-actions"><button class="button button-primary" type="submit" @disabled($tahun->isEmpty())><img src="{{ asset('images/icons/eye.svg') }}" width="18" height="18" alt="" aria-hidden="true"> Pratinjau dampak</button></div>
        </form>
    </section>

    <section class="exception-section">
        <h2>Penetapan &amp; riwayat pembatalan</h2>
        <div class="exception-history">
            @forelse ($riwayat as $p)
                <article class="exception-item {{ $p->aktif ? '' : 'is-cancelled' }}" data-exception-item>
                    <div class="exception-head"><div><h3>{{ \App\Models\PengecualianPresensiSiswa::JENIS[$p->jenis] }}</h3><p class="exception-note">{{ $p->tanggal_mulai->locale('id')->translatedFormat('d F Y') }} s.d. {{ $p->tanggal_selesai->locale('id')->translatedFormat('d F Y') }}</p></div><span class="badge {{ $p->aktif ? 'badge-active' : 'badge-muted' }}">{{ $p->aktif ? 'Aktif' : 'Dibatalkan' }}</span></div>
                    <p class="exception-note"><strong>{{ $p->kelas?->nama ?: 'Seluruh sekolah' }}</strong> &middot; {{ $p->tahunPelajaran?->nama }}</p>
                    <p class="exception-reason">{{ $p->alasan }}</p>
                    <p class="exception-note">Ditetapkan {{ $p->created_at->format('d-m-Y H:i') }} oleh {{ $p->pembuat?->nama ?: 'Akun tidak tersedia' }}. Saat pratinjau: {{ $p->dampak_pratinjau['siswa_terdampak'] }} siswa, {{ $p->dampak_pratinjau['alfa_otomatis_dibatalkan'] }} alfa otomatis.</p>
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
            };
            year.addEventListener('change', update); update();
        })();
    </script>
@endsection
