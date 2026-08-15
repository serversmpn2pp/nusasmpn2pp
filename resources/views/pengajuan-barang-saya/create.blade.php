@extends('layouts.app')

@section('title', 'Buat Pengajuan Barang - NUSA')

@section('content')
    @php
        $peminjaman = $barang->jenis_barang !== 'habis_pakai';
        $satuan = $barang->tipe_pengelolaan === 'aset_individual' ? 'unit' : $barang->satuanBarang->nama;
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Layanan Sarana Prasarana</p>
            <h1 class="page-title">{{ $peminjaman ? 'Ajukan peminjaman aset' : 'Minta barang habis pakai' }}</h1>
        </div>
        <a href="{{ route('katalog-barang.index') }}" class="button button-muted">Kembali ke katalog</a>
    </div>

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <p class="eyebrow">Barang dipilih</p>
            <h2 class="panel-title" style="margin-top: 7px;">{{ $barang->nama }}</h2>
            <p class="person-meta" style="margin-top: 5px;">{{ $barang->kode }} &middot; {{ $barang->kategoriBarang->nama }}</p>
            <dl class="quick-facts" style="margin-top: 18px;">
                <div><dt>Jenis layanan</dt><dd>{{ $peminjaman ? 'Peminjaman, wajib kembali' : 'Permintaan, tidak dikembalikan' }}</dd></div>
                <div><dt>Tersedia saat ini</dt><dd>{{ number_format((float) $ketersediaan, 2, ',', '.') }} {{ $satuan }}</dd></div>
            </dl>
        </aside>

        <form action="{{ route('pengajuan-barang-saya.store') }}" method="POST" class="panel panel-pad">
            @csrf
            <input type="hidden" name="barang_id" value="{{ $barang->id }}">
            <h2 class="panel-title">Rincian pengajuan</h2>
            <p class="help-text" style="margin-top: 5px;">Petugas akan memeriksa ketersediaan akhir sebelum barang diserahkan.</p>

            <div class="form-grid" style="margin-top: 20px;">
                <div class="field">
                    <label for="jumlah">Jumlah <span class="required">*</span></label>
                    <input id="jumlah" name="jumlah" type="number" min="{{ $peminjaman ? 1 : 0.01 }}" max="{{ $ketersediaan }}" step="{{ $peminjaman ? 1 : 0.01 }}" value="{{ old('jumlah', 1) }}" class="input" required>
                    @error('jumlah')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="tanggal_dibutuhkan">Tanggal dibutuhkan <span class="required">*</span></label>
                    <input id="tanggal_dibutuhkan" name="tanggal_dibutuhkan" type="date" min="{{ now()->toDateString() }}" value="{{ old('tanggal_dibutuhkan', now()->toDateString()) }}" class="input" required>
                    @error('tanggal_dibutuhkan')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                @if ($peminjaman)
                    <div class="field">
                        <label for="rencana_kembali">Rencana kembali <span class="required">*</span></label>
                        <input id="rencana_kembali" name="rencana_kembali" type="date" min="{{ now()->toDateString() }}" value="{{ old('rencana_kembali', now()->addDays(7)->toDateString()) }}" class="input" required>
                        @error('rencana_kembali')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                @endif
                <div class="field span-2">
                    <label for="tujuan">Tujuan penggunaan <span class="required">*</span></label>
                    <textarea id="tujuan" name="tujuan" class="textarea" rows="4" maxlength="1000" placeholder="Contoh: Digunakan untuk pembelajaran Informatika kelas VIII.A" required>{{ old('tujuan') }}</textarea>
                    @error('tujuan')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="actions" style="justify-content: flex-end; margin-top: 22px;">
                <a href="{{ route('katalog-barang.index') }}" class="button button-muted">Batal</a>
                <button type="submit" class="button button-primary" @disabled($ketersediaan <= 0)>Kirim pengajuan</button>
            </div>
        </form>
    </div>
@endsection
