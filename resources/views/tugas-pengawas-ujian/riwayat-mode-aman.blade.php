@extends('layouts.app')

@section('title', 'Riwayat Mode Aman - NUSA')

@push('styles')
<style>
    .security-context { padding:18px 0 20px; border-bottom:1px solid var(--line); }
    .security-context h2 { margin:0 0 5px; font-size:1.2rem; overflow-wrap:anywhere; }
    .security-context p { margin:0; color:var(--muted); }
    .security-facts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:0; margin:18px 0 24px; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
    .security-facts div { padding:15px 17px; border-right:1px solid var(--line); background:#fff; }
    .security-facts div:last-child { border-right:0; }
    .security-facts span,.security-facts strong { display:block; }
    .security-facts span { color:var(--muted); font-size:.78rem; }
    .security-facts strong { margin-top:6px; font-size:1.15rem; font-variant-numeric:tabular-nums; }
    .security-review { padding:18px 20px; margin:24px 0 26px; border-left:4px solid #cb8b00; }
    .security-review h2,.security-history h2 { margin:0 0 8px; font-size:1.08rem; }
    .security-review p,.security-history-intro { margin:0 0 14px; color:var(--muted); }
    .security-review .field { max-width:680px; }
    .security-review textarea { min-height:96px; }
    .security-review .button { margin-top:12px; }
    .security-history { margin-top:24px; }
    .security-entry { display:grid; grid-template-columns:170px minmax(0,1fr); gap:16px; padding:16px 0; border-top:1px solid var(--line); }
    .security-entry time { font-variant-numeric:tabular-nums; color:var(--muted); font-size:.82rem; }
    .security-entry h3 { margin:0 0 5px; font-size:.96rem; }
    .security-entry p { margin:3px 0; color:var(--muted); overflow-wrap:anywhere; }
    .security-entry-note { color:var(--text) !important; }
    @media(max-width:680px) {
        .security-facts { grid-template-columns:1fr; }
        .security-facts div { border-right:0; border-bottom:1px solid var(--line); }
        .security-facts div:last-child { border-bottom:0; }
        .security-entry { grid-template-columns:1fr; gap:5px; }
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian & Asesmen</p>
            <h1 class="page-title">Riwayat Mode Aman</h1>
        </div>
        <a class="button button-muted" href="{{ route('tugas-pengawas-ujian.show', [$ruang, 'tahap' => 'pantau']) }}">Kembali ke pantau siswa</a>
    </div>

    @if(session('berhasil')) <div class="alert">{{ session('berhasil') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><strong>Periksa alasan pembukaan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <section class="security-context">
        <h2>{{ $peserta->anggotaKelas?->siswa?->nama_lengkap ?: 'Peserta ujian' }}</h2>
        <p>NISN {{ $peserta->anggotaKelas?->siswa?->nisn ?: '-' }} · Meja {{ $peserta->nomor_meja ?: '-' }} · {{ $peserta->kelasUjianCbt?->kelas?->nama ?: '-' }}</p>
        <p>{{ $ruang->jadwalUjianCbt?->mataPelajaran?->nama ?: $ruang->ujianCbt?->nama }} · {{ $ruang->kode }} - {{ $ruang->nama }}</p>
    </section>

    <div class="security-facts" aria-label="Ringkasan Mode Aman">
        <div><span>Status saat ini</span><strong>{{ $peserta->status === 'terblokir' ? 'Ditahan Mode Aman' : $peserta->labelStatusPelaksanaan() }}</strong></div>
        <div><span>Kejadian dihitung</span><strong>{{ $peserta->jumlah_pindah_aplikasi }} kali</strong></div>
        <div><span>Total di luar halaman</span><strong>{{ $peserta->durasi_di_luar_aplikasi_detik }} detik</strong></div>
    </div>

    <section class="security-history">
        <h2>Riwayat aktivitas</h2>
        <p class="security-history-intro">Catatan perpindahan halaman adalah indikasi yang perlu diperiksa, bukan bukti otomatis kecurangan.</p>
        @forelse($aktivitas as $item)
            <article class="security-entry">
                <time datetime="{{ $item->mulai_pada?->toIso8601String() }}">{{ $item->mulai_pada?->format('d M Y, H:i:s') }}</time>
                <div>
                    @if($item->jenis === 'buka_mode_aman')
                        <h3>Akses ujian dibuka</h3>
                        <p>Oleh {{ $item->dibukaOleh?->nama ?: data_get($item->metadata, 'nama_petugas', 'Petugas') }}</p>
                        <p class="security-entry-note">{{ $item->catatan ?: 'Alasan tidak dicatat saat akses dibuka.' }}</p>
                    @else
                        <h3>{{ $item->selesai_pada === null ? 'Siswa keluar dari halaman ujian' : 'Siswa kembali ke halaman ujian' }}</h3>
                        <p>
                            @if($item->selesai_pada === null)
                                Belum ada waktu kembali yang tercatat.
                            @elseif($item->dihitung)
                                Dihitung sebagai kejadian · {{ $item->durasi_detik }} detik di luar halaman.
                            @else
                                Di bawah batas toleransi · {{ $item->durasi_detik }} detik di luar halaman.
                            @endif
                        </p>
                    @endif
                </div>
            </article>
        @empty
            <p class="empty-state">Belum ada aktivitas Mode Aman yang tercatat untuk peserta ini.</p>
        @endforelse
        {{ $aktivitas->links() }}
    </section>

    @if($dapatMembuka)
        <section class="panel security-review">
            <h2>Buka kembali ujian</h2>
            <p>Konfirmasi keadaan siswa dan catat alasan sebelum membuka akses.</p>
            <form method="POST" action="{{ route('tugas-pengawas-ujian.mode-aman.buka', [$ruang, $peserta]) }}">
                @csrf
                <div class="field">
                    <label for="alasan_pembukaan">Alasan pembukaan</label>
                    <textarea class="textarea" id="alasan_pembukaan" name="alasan_pembukaan" minlength="10" maxlength="500" required placeholder="Contoh: Pengawas memeriksa HP siswa; aplikasi tertutup saat ada panggilan masuk.">{{ old('alasan_pembukaan') }}</textarea>
                </div>
                <button class="button button-primary" type="submit" onclick="return confirm('Buka kembali ujian peserta ini?')">Buka Mode Aman</button>
            </form>
        </section>
    @endif
@endsection
