<ol class="mitra-history">@forelse ($riwayat as $item)<li><strong>{{ $item->aksi }}</strong><p class="agenda-muted">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; {{ $item->created_at->format('d-m-Y H:i:s') }} WIB</p><details><summary>Rincian perubahan</summary>
    @php
        $labels = ['nama' => 'Nama mitra', 'jenis' => 'Jenis mitra', 'alamat' => 'Alamat', 'nama_kontak' => 'Nama kontak', 'jabatan_kontak' => 'Jabatan kontak', 'nomor_kontak' => 'Nomor kontak', 'email' => 'Email', 'website' => 'Website', 'catatan' => 'Catatan', 'judul' => 'Judul MoU', 'nomor' => 'Nomor MoU', 'bidang' => 'Bidang', 'ruang_lingkup' => 'Ruang lingkup', 'penanggung_jawab' => 'Penanggung jawab', 'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal berakhir', 'ingatkan_hari_sebelum' => 'Hari pengingat', 'status' => 'Status', 'alasan_diakhiri' => 'Alasan diakhiri', 'agenda' => 'Agenda'];
        if (auth()->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola'])) $labels['dokumen_humas_id'] = 'ID dokumen MoU';
        $teks = function ($key, $nilai) use ($item) {
            if ($nilai === null || $nilai === '') return '-';
            if ($key === 'jenis') return \App\Models\MitraHumas::JENIS[$nilai] ?? $nilai;
            if ($key === 'bidang') return \App\Models\KerjaSamaHumas::BIDANG[$nilai] ?? $nilai;
            if ($key === 'status') return ($item->kerja_sama_humas_id ? \App\Models\KerjaSamaHumas::STATUS : \App\Models\MitraHumas::STATUS)[$nilai] ?? $nilai;
            if (in_array($key, ['tanggal_mulai', 'tanggal_selesai'])) return \Illuminate\Support\Carbon::parse($nilai)->timezone(config('app.timezone'))->format('d-m-Y');
            return $nilai;
        };
    @endphp
    @foreach ($labels as $key => $label)
        @if (($item->data_sebelum[$key] ?? null) !== ($item->data_sesudah[$key] ?? null))
            <div class="mitra-diff"><strong>{{ $label }}</strong><div><span class="agenda-muted">Sebelum: </span>{{ $teks($key, $item->data_sebelum[$key] ?? null) }}</div><div><span class="agenda-muted">Sesudah: </span>{{ $teks($key, $item->data_sesudah[$key] ?? null) }}</div></div>
        @endif
    @endforeach
</details></li>@empty<li class="agenda-muted">Belum ada perubahan tercatat.</li>@endforelse</ol><div style="margin-top:18px">{{ $riwayat->links() }}</div>
