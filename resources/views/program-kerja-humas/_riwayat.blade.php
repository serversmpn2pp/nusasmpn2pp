<section class="agenda-section"><h2>Riwayat {{ $jenisRiwayat }}</h2><ol class="publikasi-history">
    @forelse($riwayat as $r)<li><strong>{{ $r->aksi }}</strong><p class="agenda-muted">{{ $r->created_at->format('d-m-Y H:i') }} &middot; {{ $r->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; Versi {{ $r->versi }}</p>@if($r->catatan_perubahan)<p class="agenda-text">{{ $r->catatan_perubahan }}</p>@endif
        <details><summary>Rincian versi {{ $r->versi }}</summary><div class="publikasi-snapshot">
            @foreach($jenisRiwayat === 'program' ? ['nama' => 'Nama program', 'tahun_pelajaran' => 'Tahun pelajaran', 'semester' => 'Periode', 'bidang' => 'Bidang', 'tujuan' => 'Tujuan', 'sasaran' => 'Sasaran', 'target_hasil' => 'Target hasil', 'target_kegiatan' => 'Target kegiatan', 'penanggung_jawab' => 'Penanggung jawab', 'tanggal_mulai' => 'Mulai', 'tanggal_selesai' => 'Selesai', 'status' => 'Status', 'evaluasi' => 'Evaluasi'] : ['judul' => 'Judul kegiatan', 'tanggal_mulai' => 'Mulai', 'tanggal_selesai' => 'Selesai', 'tempat' => 'Tempat', 'pelaksana' => 'Pelaksana', 'jumlah_peserta' => 'Peserta', 'uraian' => 'Pelaksanaan', 'hasil' => 'Hasil', 'kendala' => 'Kendala', 'tindak_lanjut' => 'Tindak lanjut', 'status' => 'Status'] as $key => $label)
                <h3>{{ $label }}</h3><div class="agenda-text">{{ $r->snapshot[$key] ?? '-' }}</div>
            @endforeach
            @if($jenisRiwayat === 'laporan' && $bolehAgenda && ($r->snapshot['agenda'] ?? null))<h3>Agenda terkait</h3><p>{{ $r->snapshot['agenda'] }}</p>@endif
            @if($jenisRiwayat === 'laporan' && $bolehDokumen)<h3>Bukti pada versi ini</h3>@forelse($r->snapshot['bukti'] ?? [] as $b)<p><a href="{{ route('program-kerja-humas.laporan.berkas', [$program, $laporan, $b['id']]) }}">{{ $b['judul'] }}</a> &middot; {{ $b['nama_file_asli'] }} &middot; Versi dokumen {{ $b['versi_dokumen'] }}</p>@empty<p>-</p>@endforelse @endif
        </div></details>
    </li>@empty<li class="agenda-muted">Belum ada riwayat.</li>@endforelse
</ol>{{ $riwayat->links() }}</section>
