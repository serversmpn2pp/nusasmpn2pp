<dialog class="package-bank" data-bank-dialog data-bank-url="{{ route('paket-soal-terpusat.bank-soal', $jadwal) }}" aria-labelledby="package-bank-title">
    <div class="package-heading package-section-head">
        <div>
            <h2 id="package-bank-title">Tambah dari Bank Soal</h2>
            <p>{{ $jadwal->mataPelajaran?->nama }} &middot; Tingkat {{ $jadwal->tingkat }}</p>
        </div>
        <button type="button" class="button button-muted" title="Tutup Bank Soal" aria-label="Tutup Bank Soal" data-bank-close><i data-lucide="x" aria-hidden="true"></i></button>
    </div>
    <div class="package-bank-filters">
        <div class="field">
            <label for="bank-soal-cari">Cari soal</label>
            <input id="bank-soal-cari" type="search" class="input" placeholder="Kode atau isi" maxlength="120" name="cari" data-bank-filter autofocus>
        </div>
        <div class="field">
            <label for="bank-soal-folder">Folder</label>
            <select id="bank-soal-folder" name="folder" class="select" data-bank-filter>
                <option value="">Semua folder</option>
                <option value="belum">Belum dikelompokkan</option>
                @foreach ($folder as $itemFolder)
                    <option value="{{ $itemFolder->id }}">{{ $itemFolder->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="bank-soal-jenis">Jenis soal</label>
            <select id="bank-soal-jenis" name="jenis" class="select" data-bank-filter>
                <option value="">Semua</option>
                @foreach ($daftarJenisSoal as $kode => $label)
                    <option value="{{ $kode }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="bank-soal-kesulitan">Kesulitan</label>
            <select id="bank-soal-kesulitan" name="kesulitan" class="select" data-bank-filter>
                <option value="">Semua</option>
                @foreach ($daftarKesulitan as $kode => $label)
                    <option value="{{ $kode }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="package-bank-scroll" data-bank-scroll>
        <div class="package-empty" role="status" data-bank-loading hidden>Memuat Bank Soal...</div>
        <div class="package-empty" data-bank-error hidden>
            <p role="alert">Bank Soal tidak dapat dimuat.</p>
            <button type="button" class="button button-muted" data-bank-retry>Coba lagi</button>
        </div>
        <div data-bank-results></div>
    </div>
    <div class="package-bank-footer">
        <span role="status" data-bank-count>0 dipilih</span>
        <div class="actions">
            <button type="button" class="button button-muted" data-bank-select-page disabled>Pilih halaman ini</button>
            <button type="button" class="button button-primary" data-bank-add disabled><i data-lucide="plus" aria-hidden="true"></i> Tambahkan</button>
        </div>
    </div>
</dialog>
