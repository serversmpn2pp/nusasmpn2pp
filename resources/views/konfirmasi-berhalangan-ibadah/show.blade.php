@extends('layouts.app')

@section('title', 'Konfirmasi Privat Siswi - NUSA')

@section('content')
    <style>
        .private-layout { display:grid; grid-template-columns:minmax(250px,.68fr) minmax(0,1.32fr); gap:18px; align-items:start; }
        .private-person { display:flex; align-items:center; gap:14px; padding-bottom:17px; border-bottom:1px solid var(--line); }
        .private-person img { width:78px; height:96px; object-fit:cover; border:2px solid #fff; border-radius:7px; background:#eef2f6; box-shadow:0 0 0 1px var(--line); }
        .private-decision { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .private-decision label { display:flex; align-items:flex-start; gap:10px; min-height:98px; padding:15px; border:1px solid var(--line); border-radius:7px; background:#fff; cursor:pointer; }
        .private-decision label:has(input:checked) { border-color:var(--primary); background:#eef5fb; box-shadow:inset 0 0 0 1px var(--primary); }
        .private-decision strong { display:block; color:var(--primary-dark); }
        .private-decision small { display:block; margin-top:5px; color:var(--muted); line-height:1.45; }
        .privacy-guidance { padding:14px; border-left:4px solid var(--warning); border-radius:6px; background:#fff9df; color:#614b00; line-height:1.55; }
        .history-item { padding:14px 0; border-bottom:1px solid var(--line); }
        .history-item:last-child { border-bottom:0; }
        .history-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
        .history-note { margin:8px 0 0; padding:10px 12px; border-radius:6px; background:#f7f9fc; color:#435166; line-height:1.5; }
        @media (max-width:820px) { .private-layout { grid-template-columns:1fr; } }
        @media (max-width:620px) { .private-decision { grid-template-columns:1fr; } .private-decision label { min-height:0; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pendamping Ibadah Siswi</p>
            <h1 class="page-title">Konfirmasi Privat</h1>
            <p class="page-subtitle">Catat hasil percakapan secara singkat dan hormati privasi siswi.</p>
        </div>
        <a href="{{ route('konfirmasi-berhalangan-ibadah.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><strong>Konfirmasi belum dapat disimpan.</strong><ul style="margin:7px 0 0;padding-left:19px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="private-layout">
        <div style="display:grid;gap:18px;">
            <section class="panel panel-pad">
                <div class="private-person">
                    <img src="{{ $periode->siswa?->foto ? asset('storage/'.$periode->siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}" alt="">
                    <div><p class="eyebrow">{{ $periode->kelas?->nama ?? '-' }}</p><h2 class="panel-title" style="margin-top:3px;">{{ $periode->siswa?->nama_lengkap ?? '-' }}</h2><p class="person-meta">NISN {{ $periode->siswa?->nisn ?? '-' }}</p></div>
                </div>
                <div class="detail-grid" style="margin-top:16px;">
                    <div><span>Mulai periode</span><strong>{{ $periode->tanggal_mulai->format('d/m/Y') }}</strong></div>
                    <div><span>Durasi</span><strong>Hari ke-{{ $hariKe }}</strong></div>
                    <div><span>Presensi tercatat</span><strong>{{ $periode->presensiHarian->count() }} hari</strong></div>
                    <div><span>Batas awal</span><strong>{{ $periode->batas_hari_konfirmasi }} hari</strong></div>
                </div>
            </section>

            <div class="privacy-guidance">
                <strong>Percakapan privat, bukan pemeriksaan.</strong><br>
                Tanyakan dengan bahasa yang sopan. Jangan meminta bukti fisik, foto, atau rincian medis. Catatan cukup berisi informasi administratif yang diperlukan.
            </div>
        </div>

        <div style="display:grid;gap:18px;">
            <section class="panel panel-pad">
                <h2 class="panel-title">Hasil konfirmasi</h2>
                <p class="help-text">Pilih hasil sesuai keterangan siswi.</p>

                @if ($periode->status === \App\Models\PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)
                    <form method="POST" action="{{ route('konfirmasi-berhalangan-ibadah.update', $periode) }}" style="margin-top:18px;" data-confirmation-form>
                        @csrf
                        @method('PUT')
                        <div class="private-decision">
                            <label>
                                <input type="radio" name="hasil" value="masih_berhalangan" @checked(old('hasil', 'masih_berhalangan') === 'masih_berhalangan')>
                                <span><strong>Masih berhalangan</strong><small>Periode tetap aktif dan NUSA akan mengingatkan kembali sesuai jeda yang dipilih.</small></span>
                            </label>
                            <label>
                                <input type="radio" name="hasil" value="selesai" @checked(old('hasil') === 'selesai')>
                                <span><strong>Sudah selesai</strong><small>Periode ditutup. Scan ibadah biasa berikutnya juga akan memulai status normal.</small></span>
                            </label>
                        </div>

                        <div class="field" style="margin-top:17px;" data-reminder-field>
                            <label for="jeda_konfirmasi_hari">Ingatkan kembali</label>
                            <select id="jeda_konfirmasi_hari" name="jeda_konfirmasi_hari" class="select @error('jeda_konfirmasi_hari') is-invalid @enderror">
                                @foreach ([1, 2, 3, 5, 7, 10, 14] as $hari)
                                    <option value="{{ $hari }}" @selected((int) old('jeda_konfirmasi_hari', $jedaAwal) === $hari)>{{ $hari }} hari lagi</option>
                                @endforeach
                            </select>
                            <p class="help-text">Jika siswi masih berhalangan sampai waktu ini, status kembali masuk daftar konfirmasi.</p>
                        </div>

                        <div class="field" style="margin-top:17px;">
                            <label for="catatan_privat">Catatan privat <span class="label-optional">Opsional</span></label>
                            <textarea id="catatan_privat" name="catatan_privat" class="textarea @error('catatan_privat') is-invalid @enderror" rows="4" maxlength="500" placeholder="Contoh: Sudah dikonfirmasi secara pribadi.">{{ old('catatan_privat') }}</textarea>
                            <p class="help-text">Hindari menulis rincian kesehatan yang tidak diperlukan.</p>
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('konfirmasi-berhalangan-ibadah.index') }}" class="button button-muted">Batal</a>
                            <button type="submit" class="button button-primary">Simpan konfirmasi</button>
                        </div>
                    </form>
                @else
                    <div class="alert" style="margin-top:16px;">Periode ini sudah ditangani dan tidak memerlukan konfirmasi baru.</div>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Riwayat privat</h2>
                <p class="help-text">Riwayat hanya tersedia bagi petugas yang berwenang.</p>
                <div style="margin-top:10px;">
                    @forelse ($periode->riwayatKonfirmasi as $riwayat)
                        <article class="history-item">
                            <div class="history-head">
                                <div><strong>{{ $riwayat->labelHasil() }}</strong><p class="person-meta">{{ $riwayat->dikonfirmasiOlehPengguna?->nama ?? 'Petugas tidak aktif' }}</p></div>
                                <span class="person-meta">{{ $riwayat->dikonfirmasi_pada->format('d/m/Y H:i') }}</span>
                            </div>
                            @if ($riwayat->konfirmasi_berikutnya_pada)<p class="person-meta" style="margin-top:7px;">Pengingat berikutnya: {{ $riwayat->konfirmasi_berikutnya_pada->format('d/m/Y') }}</p>@endif
                            @if ($riwayat->catatan_privat)<p class="history-note">{{ $riwayat->catatan_privat }}</p>@endif
                        </article>
                    @empty
                        <div class="empty-state">Belum ada riwayat konfirmasi.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-confirmation-form]');
            const pengingat = form?.querySelector('[data-reminder-field]');
            if (!form || !pengingat) return;

            const perbarui = () => {
                pengingat.hidden = form.querySelector('input[name="hasil"]:checked')?.value !== 'masih_berhalangan';
            };

            form.querySelectorAll('input[name="hasil"]').forEach((input) => input.addEventListener('change', perbarui));
            perbarui();
        })();
    </script>
@endpush
