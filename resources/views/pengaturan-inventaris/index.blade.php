@extends('layouts.app')

@section('title', 'Pengaturan Inventaris - NUSA')

@section('content')
    <style>
        .inventory-setting-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr);
            gap: 20px;
            align-items: start;
        }

        .asset-number-preview {
            display: grid;
            gap: 8px;
            margin-top: 18px;
            padding: 18px;
            border: 1px solid #d6e1ec;
            border-left: 5px solid #f1c40f;
            border-radius: 6px;
            background: #f7fafc;
        }

        .asset-number-preview strong {
            color: #15477a;
            font-size: 1.15rem;
            overflow-wrap: anywhere;
        }

        .inventory-rule-list {
            display: grid;
            gap: 12px;
            margin: 16px 0 0;
            padding: 0;
            list-style: none;
        }

        .inventory-rule-list li {
            display: grid;
            grid-template-columns: 32px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
        }

        .inventory-rule-list span {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: #15477a;
            color: #f1c40f;
            font-weight: 900;
        }

        @media (max-width: 880px) {
            .inventory-setting-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Pengaturan inventaris</h1>
            <p class="help-text" style="margin-top: 6px;">Identitas baku yang digunakan pada nomor aset dan label barang sekolah.</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="inventory-setting-shell">
        <form action="{{ route('pengaturan-inventaris.update') }}" method="POST" class="panel panel-pad">
            @csrf
            @method('PUT')

            <h2 class="panel-title">Identitas aset sekolah</h2>
            <p class="help-text" style="margin-top: 6px;">Tahun perolehan akan ditempatkan otomatis di antara awalan dan akhiran nomor aset.</p>

            <div class="form-grid" style="margin-top: 20px;">
                <div class="field">
                    <label for="awalan_nomor_aset">Awalan nomor aset</label>
                    <input id="awalan_nomor_aset" name="awalan_nomor_aset" type="text" value="{{ old('awalan_nomor_aset', $pengaturan->awalan_nomor_aset) }}" class="input" inputmode="numeric" required>
                    <p class="help-text">Format kelompok dua angka yang dipisahkan titik.</p>
                </div>

                <div class="field">
                    <label for="akhiran_nomor_aset">Akhiran tetap</label>
                    <input id="akhiran_nomor_aset" name="akhiran_nomor_aset" type="text" value="{{ old('akhiran_nomor_aset', $pengaturan->akhiran_nomor_aset) }}" class="input" inputmode="numeric" maxlength="2" required>
                    <p class="help-text">Nilai tetap sesuai format sekolah.</p>
                </div>

                <div class="field span-2">
                    <label for="nama_pemilik">Nama pemilik</label>
                    <input id="nama_pemilik" name="nama_pemilik" type="text" value="{{ old('nama_pemilik', $pengaturan->nama_pemilik) }}" class="input" required>
                </div>

                <div class="field">
                    <label for="jumlah_digit_id_internal">Jumlah digit ID internal</label>
                    <input id="jumlah_digit_id_internal" name="jumlah_digit_id_internal" type="number" min="4" max="10" value="{{ old('jumlah_digit_id_internal', $pengaturan->jumlah_digit_id_internal) }}" class="input" required>
                    <p class="help-text">Contoh 6 digit menghasilkan 000001.</p>
                </div>
            </div>

            <div class="asset-number-preview" aria-live="polite">
                <span class="help-text">Contoh nomor aset tahun {{ now()->format('Y') }}</span>
                <strong id="asset-number-preview">{{ $pengaturan->contohNomorAset() }}</strong>
            </div>

            <div class="form-actions" style="margin-top: 22px;">
                <button type="submit" class="button button-primary">Simpan pengaturan</button>
            </div>
        </form>

        <aside class="panel panel-pad">
            <h2 class="panel-title">Aturan identitas</h2>
            <ul class="inventory-rule-list">
                <li><span>1</span><p class="help-text">Nomor aset resmi mengikuti format sekolah dan hanya bagian tahun yang berubah.</p></li>
                <li><span>2</span><p class="help-text">ID internal NUSA tetap unik untuk setiap barang dan digunakan pada barcode.</p></li>
                <li><span>3</span><p class="help-text">Barang habis pakai memperoleh kode otomatis dengan format BHP-000001.</p></li>
                <li><span>4</span><p class="help-text">Unit aset memperoleh ID internal seperti AST-2024-000001.</p></li>
            </ul>

            @if ($pengaturan->diperbaruiOleh)
                <p class="help-text" style="margin-top: 20px;">Terakhir diperbarui oleh {{ $pengaturan->diperbaruiOleh->nama }}.</p>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const awalan = document.getElementById('awalan_nomor_aset');
            const akhiran = document.getElementById('akhiran_nomor_aset');
            const preview = document.getElementById('asset-number-preview');
            const tahun = @json(now()->format('Y'));

            const perbaruiPreview = () => {
                const bagianAwal = awalan.value.replace(/^\.+|\.+$/g, '') || '-';
                const bagianAkhir = akhiran.value.replace(/^\.+|\.+$/g, '') || '-';
                preview.textContent = `${bagianAwal}.${tahun}.${bagianAkhir}`;
            };

            awalan.addEventListener('input', perbaruiPreview);
            akhiran.addEventListener('input', perbaruiPreview);
        });
    </script>
@endpush
