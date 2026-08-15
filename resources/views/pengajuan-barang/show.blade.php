@extends('layouts.app')

@section('title', 'Periksa Pengajuan Barang - NUSA')

@section('content')
    <style>
        .unit-choice-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
        .unit-choice { display: flex; align-items: flex-start; gap: 10px; border: 1px solid var(--line); border-radius: 7px; padding: 12px; cursor: pointer; }
        .unit-choice:has(input:checked) { border-color: var(--primary); background: #edf5fd; }
        .unit-choice input { flex: none; margin-top: 3px; }
        @media (max-width: 720px) { .unit-choice-list { grid-template-columns: 1fr; } }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Sarana Prasarana</p><h1 class="page-title">Periksa pengajuan barang</h1></div>
        <a href="{{ route('pengajuan-barang.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <p class="eyebrow">{{ $pengajuanBarang->nomor_pengajuan }}</p>
            <h2 class="panel-title" style="margin-top: 7px;">{{ $pengajuanBarang->pegawai->nama_lengkap }}</h2>
            <p class="person-meta" style="margin-top: 4px;">{{ $pengajuanBarang->pegawai->jenis_pegawai ?: 'Pegawai' }}</p>
            <div class="actions" style="margin-top: 15px;"><span class="badge {{ $pengajuanBarang->status === 'dipenuhi' ? 'badge-active' : ($pengajuanBarang->status === 'menunggu' ? 'badge-warning' : 'badge-muted') }}">{{ $pengajuanBarang->labelStatus() }}</span></div>
        </aside>

        <section class="panel panel-pad">
            <h2 class="panel-title">Rincian pengajuan</h2>
            <dl class="detail-grid" style="margin-top: 16px;">
                <div class="detail-item"><dt>Barang</dt><dd>{{ $pengajuanBarang->barang->nama }}</dd></div>
                <div class="detail-item"><dt>Jenis</dt><dd>{{ $pengajuanBarang->labelJenis() }}</dd></div>
                <div class="detail-item"><dt>Jumlah</dt><dd>{{ number_format((float) $pengajuanBarang->jumlah, 2, ',', '.') }} {{ $pengajuanBarang->barang->satuanBarang->nama }}</dd></div>
                <div class="detail-item"><dt>Tanggal dibutuhkan</dt><dd>{{ $pengajuanBarang->tanggal_dibutuhkan->locale('id')->translatedFormat('d F Y') }}</dd></div>
                @if ($pengajuanBarang->jenis_pengajuan === 'peminjaman')<div class="detail-item"><dt>Rencana kembali</dt><dd>{{ $pengajuanBarang->rencana_kembali?->locale('id')->translatedFormat('d F Y') ?: '-' }}</dd></div>@endif
                <div class="detail-item span-2"><dt>Tujuan penggunaan</dt><dd style="white-space: pre-line;">{{ $pengajuanBarang->tujuan }}</dd></div>
                @if (! $pengajuanBarang->masihMenunggu())
                    <div class="detail-item"><dt>Diproses oleh</dt><dd>{{ $pengajuanBarang->diprosesOleh?->nama ?: 'Sistem' }}</dd></div>
                    <div class="detail-item"><dt>Waktu proses</dt><dd>{{ $pengajuanBarang->diproses_pada?->locale('id')->translatedFormat('d F Y, H:i') ?: '-' }}</dd></div>
                    <div class="detail-item span-2"><dt>Catatan petugas</dt><dd style="white-space: pre-line;">{{ $pengajuanBarang->catatan_petugas ?: '-' }}</dd></div>
                @endif
            </dl>
            @if ($pengajuanBarang->peminjamanBarang)
                <div class="actions" style="margin-top: 18px;"><a href="{{ route('peminjaman-barang.show', $pengajuanBarang->peminjamanBarang) }}" class="button button-muted">Lihat transaksi {{ $pengajuanBarang->peminjamanBarang->nomor_peminjaman }}</a></div>
            @endif
        </section>
    </div>

    @if ($pengajuanBarang->masihMenunggu())
        <div class="section-stack" style="margin-top: 24px;">
            <form action="{{ route('pengajuan-barang.penuhi', $pengajuanBarang) }}" method="POST" class="panel panel-pad">
                @csrf
                @method('PATCH')
                <h2 class="panel-title">Penuhi dan serahkan barang</h2>
                <p class="help-text" style="margin-top: 5px;">Tindakan ini langsung mencatat transaksi dan memperbarui ketersediaan barang.</p>

                @if ($pengajuanBarang->barang->tipe_pengelolaan === 'aset_individual')
                    <div class="field" style="margin-top: 20px;">
                        <label>Pilih {{ number_format((float) $pengajuanBarang->jumlah, 0, ',', '.') }} unit aset <span class="required">*</span></label>
                        <div class="unit-choice-list">
                            @forelse ($daftarUnit as $unit)
                                <label class="unit-choice">
                                    <input type="checkbox" name="unit_barang_ids[]" value="{{ $unit->id }}" @checked(in_array($unit->id, old('unit_barang_ids', [])))>
                                    <span><strong>{{ $unit->kode_inventaris }}</strong><span class="person-meta">{{ $unit->nomor_aset_resmi ?: 'Nomor aset belum diisi' }} &middot; {{ $unit->lokasiBarang?->nama ?: 'Tanpa lokasi' }}</span></span>
                                </label>
                            @empty
                                <p class="help-text">Tidak ada unit aset yang tersedia.</p>
                            @endforelse
                        </div>
                        @error('unit_barang_ids')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                @else
                    <div class="field" style="margin-top: 20px; max-width: 560px;">
                        <label for="lokasi_barang_id">Lokasi asal stok <span class="required">*</span></label>
                        <select id="lokasi_barang_id" name="lokasi_barang_id" class="select" required>
                            <option value="">Pilih lokasi</option>
                            @foreach ($daftarSaldo as $saldo)
                                <option value="{{ $saldo->lokasi_barang_id }}" @selected((string) old('lokasi_barang_id') === (string) $saldo->lokasi_barang_id)>{{ $saldo->lokasiBarang->nama }} - tersedia {{ number_format((float) $saldo->jumlah, 2, ',', '.') }} {{ $pengajuanBarang->barang->satuanBarang->nama }}</option>
                            @endforeach
                        </select>
                        @error('lokasi_barang_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                @endif

                <div class="field" style="margin-top: 18px;">
                    <label for="catatan_petugas">Catatan petugas</label>
                    <textarea id="catatan_petugas" name="catatan_petugas" class="textarea" rows="3" maxlength="1000" placeholder="Opsional">{{ old('catatan_petugas') }}</textarea>
                </div>
                <div class="actions" style="justify-content: flex-end; margin-top: 20px;"><button type="submit" class="button button-primary" onclick="return confirm('Serahkan barang dan catat transaksi sekarang?')">Penuhi dan serahkan</button></div>
            </form>

            <form action="{{ route('pengajuan-barang.tolak', $pengajuanBarang) }}" method="POST" class="panel panel-pad">
                @csrf
                @method('PATCH')
                <h2 class="panel-title">Tolak pengajuan</h2>
                <div class="field" style="margin-top: 16px;">
                    <label for="catatan_penolakan">Alasan penolakan <span class="required">*</span></label>
                    <textarea id="catatan_penolakan" name="catatan_petugas" class="textarea" rows="3" minlength="5" maxlength="1000" required>{{ old('catatan_petugas') }}</textarea>
                </div>
                <div class="actions" style="justify-content: flex-end; margin-top: 18px;"><button type="submit" class="button button-danger" onclick="return confirm('Tolak pengajuan ini?')">Tolak pengajuan</button></div>
            </form>
        </div>
    @endif
@endsection
