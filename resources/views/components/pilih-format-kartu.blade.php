@props([
    'jumlah' => 0,
    'jenis' => 'kartu',
])

<style>
    .card-export-dialog {
        width: min(92vw, 560px);
        max-height: min(88vh, 720px);
        overflow: auto;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 0;
        color: var(--text);
        box-shadow: 0 24px 60px rgba(4, 18, 35, .24);
    }

    .card-export-dialog::backdrop {
        background: rgba(4, 18, 35, .58);
    }

    .card-export-dialog-head,
    .card-export-dialog-body,
    .card-export-dialog-foot {
        padding: 18px 20px;
    }

    .card-export-dialog-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        border-bottom: 1px solid var(--line);
    }

    .card-export-dialog-head h2,
    .card-export-dialog-head p {
        margin: 0;
    }

    .card-export-dialog-head p {
        margin-top: 5px;
        color: var(--muted);
        font-size: .86rem;
        font-weight: 650;
    }

    .card-export-dialog-body {
        display: grid;
        gap: 16px;
    }

    .card-export-options {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .card-export-option {
        display: grid;
        min-height: 104px;
        align-content: center;
        gap: 5px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 14px;
        color: var(--text);
        text-align: left;
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease, transform .15s ease;
    }

    .card-export-option:hover,
    .card-export-option:focus-visible {
        border-color: var(--primary);
        background: #eef5fb;
        outline: none;
        transform: translateY(-1px);
    }

    .card-export-option:disabled {
        cursor: wait;
        opacity: .55;
        transform: none;
    }

    .card-export-option strong {
        color: var(--primary-dark);
        font-size: 1rem;
        font-weight: 950;
    }

    .card-export-option span {
        color: var(--muted);
        font-size: .78rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .card-export-option.is-primary {
        border-color: var(--primary);
        background: #eef5fb;
    }

    .card-export-progress {
        display: grid;
        gap: 8px;
        border: 1px solid #bcd2e8;
        border-radius: 8px;
        background: #f3f8fc;
        padding: 12px;
    }

    .card-export-progress[hidden] {
        display: none;
    }

    .card-export-progress p {
        margin: 0;
        color: var(--primary-dark);
        font-size: .84rem;
        font-weight: 800;
    }

    .card-export-progress progress {
        width: 100%;
        height: 10px;
        accent-color: var(--primary);
    }

    .card-export-progress.is-error {
        border-color: #fecaca;
        background: var(--danger-soft);
    }

    .card-export-progress.is-error p {
        color: #991b1b;
    }

    .card-export-dialog-foot {
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid var(--line);
    }

    @media (max-width: 620px) {
        .card-export-options {
            grid-template-columns: 1fr;
        }

        .card-export-option {
            min-height: 78px;
        }
    }
</style>

<dialog class="card-export-dialog" data-card-export-dialog aria-labelledby="card-export-title">
    <div class="card-export-dialog-head">
        <div>
            <h2 id="card-export-title" class="panel-title">Pilih format kartu</h2>
            <p>{{ $jumlah }} {{ $jenis }} siap diproses.</p>
        </div>
        <button type="button" class="button button-muted button-sm" data-card-export-close>Tutup</button>
    </div>

    <div class="card-export-dialog-body">
        <div class="card-export-options">
            <button type="button" class="card-export-option is-primary" data-card-export-pdf>
                <strong>PDF</strong>
                <span>Buka halaman cetak A4 dan pilih Simpan sebagai PDF.</span>
            </button>
            <button type="button" class="card-export-option" data-card-export-image="png">
                <strong>PNG</strong>
                <span>Gambar tajam untuk dicetak atau disimpan.</span>
            </button>
            <button type="button" class="card-export-option" data-card-export-image="jpeg">
                <strong>JPEG</strong>
                <span>Ukuran berkas lebih ringan untuk dibagikan.</span>
            </button>
        </div>

        <div class="card-export-progress" hidden data-card-export-progress>
            <p data-card-export-status>Menyiapkan kartu...</p>
            <progress value="0" max="100" data-card-export-progress-bar></progress>
        </div>
    </div>

    <div class="card-export-dialog-foot">
        <button type="button" class="button button-muted" data-card-export-close>Batal</button>
    </div>
</dialog>
