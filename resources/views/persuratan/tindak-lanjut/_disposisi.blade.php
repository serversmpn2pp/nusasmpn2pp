<section class="surat-section"><h2>Disposisi &amp; tindak lanjut</h2>
    @if($surat->tindakLanjut->isNotEmpty())
        <div class="surat-shell"><table class="surat-table surat-table--list"><thead><tr><th>Penerima</th><th>Tenggat</th><th>Status</th><th>Pemeriksa</th><th></th></tr></thead><tbody>@foreach($surat->tindakLanjut as $t)<tr><td>{{ $t->nama_pegawai }}</td><td data-label="Tenggat">{{ $t->batas_tanggal->format('d-m-Y') }}</td><td data-label="Status"><span class="surat-status surat-status--{{ $t->status }}">{{ \App\Models\TindakLanjutSuratTu::STATUS[$t->status] }}</span></td><td data-label="Pemeriksa">{{ $t->pemeriksa?->nama ?? '-' }}</td><td><a class="button button-muted button-sm" href="{{ route('tindak-lanjut-surat.show', $t) }}">Lihat tindak lanjut</a></td></tr>@endforeach</tbody></table></div>
    @else<p class="surat-muted">Belum ada disposisi.</p>@endif
    @if($surat->status === 'tercatat' && $penerimaDisposisi->isNotEmpty()) @izin('surat.disposisi')
        <details class="surat-details" @if($errors->any()) open @endif><summary>Berikan disposisi</summary>
            <form method="POST" action="{{ route('persuratan.disposisi', $surat) }}" class="surat-grid" data-tindak-upload>@csrf<input type="hidden" name="versi" value="{{ $surat->versi }}">
                <div class="surat-full"><label for="cari_disposisi">Penerima disposisi</label><input class="input" id="cari_disposisi" type="search" placeholder="Cari guru / pegawai" data-cari-penerima><div class="surat-choice-list">@foreach($penerimaDisposisi as $p)<label class="surat-choice" data-penerima><input type="checkbox" name="pegawai_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, old('pegawai_ids', [])))><span><strong>{{ $p->nama_lengkap }}</strong><small>{{ $p->jabatan_utama }}</small></span></label>@endforeach</div></div>
                <div class="surat-full"><label for="instruksi">Instruksi disposisi</label><textarea class="textarea" id="instruksi" name="instruksi" rows="3" maxlength="5000" required>{{ old('instruksi') }}</textarea></div>
                <div><label for="batas_tanggal">Tenggat tindak lanjut</label><input class="input" type="date" id="batas_tanggal" name="batas_tanggal" value="{{ old('batas_tanggal') }}" min="{{ today()->toDateString() }}" required></div><div><label>Pemeriksa laporan</label><strong>{{ auth()->user()->nama }}</strong></div>
                <div class="surat-actions"><button class="button button-primary" type="submit">Kirim disposisi</button><span class="surat-muted" data-tindak-progress hidden>Sedang menyimpan disposisi...</span></div>
            </form>
        </details>
    @endizin @endif
</section>
