@extends('layouts.app')

@section('title', 'Atur Poin Keterlambatan - NUSA')

@section('content')
    @php
        $rentangAwal = old('rentang', $pengaturan->rentangPoinKeterlambatan->map(fn ($item) => [
            'menit_mulai' => $item->menit_mulai,
            'menit_selesai' => $item->menit_selesai,
            'poin' => $item->poin,
        ])->all());
    @endphp

    <style>
        .late-settings-head { align-items: center; display: flex; gap: 14px; justify-content: space-between; }
        .late-toggle { align-items: start; border: 1px solid var(--line); border-radius: 8px; display: grid; gap: 11px; grid-template-columns: 20px minmax(0, 1fr); padding: 15px; }
        .late-toggle input { margin-top: 3px; }
        .late-row { align-items: end; border-bottom: 1px solid var(--line); display: grid; gap: 12px; grid-template-columns: 52px repeat(3, minmax(0, 1fr)) auto; padding: 14px 0; }
        .late-row:first-child { padding-top: 0; }
        .late-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .late-row-number { align-items: center; background: #15477a; border-radius: 6px; color: #fff; display: inline-flex; font-size: .82rem; font-weight: 900; height: 36px; justify-content: center; width: 36px; }
        .late-rule-actions { display: flex; justify-content: flex-end; margin-top: 16px; }
        @media (max-width: 760px) {
            .late-settings-head { align-items: stretch; flex-direction: column; }
            .late-row { align-items: stretch; grid-template-columns: 42px minmax(0, 1fr); }
            .late-row .field { grid-column: 2; }
            .late-row .late-remove { grid-column: 2; justify-self: start; }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Atur poin keterlambatan</h1>
            <p class="page-subtitle">Tahun pelajaran {{ $tahunPelajaran->nama }}</p>
        </div>
        <a href="{{ route('pengaturan-poin-keterlambatan.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada data yang perlu diperbaiki.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pengaturan-poin-keterlambatan.update', $tahunPelajaran) }}">
        @csrf
        @method('PUT')

        <section class="panel panel-pad">
            <div class="late-settings-head">
                <div>
                    <h2 class="panel-title">Otomatisasi laporan</h2>
                    <p class="help-text">Laporan keterlambatan tetap menunggu keputusan BK sebelum poin ditetapkan.</p>
                </div>
                <label class="late-toggle">
                    <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $pengaturan->aktif))>
                    <span><strong>Aktif</strong><span class="help-text">Gunakan aturan ini pada rekap presensi.</span></span>
                </label>
            </div>
        </section>

        <section class="panel panel-pad" style="margin-top: 18px;">
            <h2 class="panel-title">Rentang menit dan poin</h2>
            <p class="help-text">Gunakan 0 poin sebagai toleransi. Rentang terakhir tidak memiliki batas akhir.</p>

            <div id="late-rules" style="margin-top: 18px;">
                @foreach ($rentangAwal as $index => $rentang)
                    <div class="late-row" data-late-row>
                        <span class="late-row-number" data-row-number>{{ $index + 1 }}</span>
                        <div class="field">
                            <label>Mulai menit</label>
                            <input type="number" min="1" max="1440" class="input" name="rentang[{{ $index }}][menit_mulai]" value="{{ $rentang['menit_mulai'] }}" required data-field="menit_mulai">
                        </div>
                        <div class="field">
                            <label>Sampai menit</label>
                            <input type="number" min="1" max="1440" class="input" name="rentang[{{ $index }}][menit_selesai]" value="{{ $rentang['menit_selesai'] }}" placeholder="Tanpa batas" data-field="menit_selesai">
                        </div>
                        <div class="field">
                            <label>Poin</label>
                            <input type="number" min="0" max="500" class="input" name="rentang[{{ $index }}][poin]" value="{{ $rentang['poin'] }}" required data-field="poin">
                        </div>
                        <button type="button" class="button button-muted button-sm late-remove" data-remove-row>Hapus</button>
                    </div>
                @endforeach
            </div>

            <div class="late-rule-actions">
                <button type="button" class="button button-dark" id="add-late-rule">Tambah rentang</button>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('pengaturan-poin-keterlambatan.index') }}" class="button button-muted">Batal</a>
            <button class="button button-primary">Simpan pengaturan</button>
        </div>
    </form>

    <template id="late-rule-template">
        <div class="late-row" data-late-row>
            <span class="late-row-number" data-row-number></span>
            <div class="field"><label>Mulai menit</label><input type="number" min="1" max="1440" class="input" required data-field="menit_mulai"></div>
            <div class="field"><label>Sampai menit</label><input type="number" min="1" max="1440" class="input" placeholder="Tanpa batas" data-field="menit_selesai"></div>
            <div class="field"><label>Poin</label><input type="number" min="0" max="500" class="input" value="0" required data-field="poin"></div>
            <button type="button" class="button button-muted button-sm late-remove" data-remove-row>Hapus</button>
        </div>
    </template>

    <script>
        (() => {
            const container = document.getElementById('late-rules');
            const template = document.getElementById('late-rule-template');
            const addButton = document.getElementById('add-late-rule');

            const reindex = () => {
                const rows = [...container.querySelectorAll('[data-late-row]')];
                rows.forEach((row, index) => {
                    row.querySelector('[data-row-number]').textContent = index + 1;
                    row.querySelectorAll('[data-field]').forEach((field) => {
                        field.name = `rentang[${index}][${field.dataset.field}]`;
                    });
                });

                rows.forEach((row) => {
                    row.querySelector('[data-remove-row]').disabled = rows.length === 1;
                });
            };

            const tambahRentang = () => {
                const rows = [...container.querySelectorAll('[data-late-row]')];
                const last = rows.at(-1);
                const lastStart = Number(last?.querySelector('[data-field="menit_mulai"]')?.value || 0);
                const lastEnd = Number(last?.querySelector('[data-field="menit_selesai"]')?.value || 0);
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('[data-late-row]');
                row.querySelector('[data-field="menit_mulai"]').value = lastEnd > 0 ? lastEnd + 1 : lastStart + 1;
                container.appendChild(fragment);
                reindex();
                row.querySelector('[data-field="menit_mulai"]').focus();
            };

            addButton.addEventListener('click', tambahRentang);
            container.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-row]');
                if (!button || container.querySelectorAll('[data-late-row]').length === 1) return;
                button.closest('[data-late-row]').remove();
                reindex();
            });

            reindex();
        })();
    </script>
@endsection
