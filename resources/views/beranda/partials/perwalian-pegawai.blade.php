@if ($memilikiPerwalian)
    <section class="ph-section" aria-labelledby="ph-wali-title">
        <header class="ph-section-head"><h2 id="ph-wali-title"><i data-lucide="users-round" aria-hidden="true"></i> Perwalian Siswa</h2><span class="ph-badge blue">{{ $labelBulan }}</span></header>
        <div class="ph-wali">
            <div>
                <h3>Kelas Wali</h3>
                <div class="ph-piket">@foreach ($kelasWali as $kelas)<span class="ph-badge teal">{{ $kelas->nama }} &middot; {{ $angka($kelas->jumlah_siswa_aktif) }} siswa</span>@endforeach @if ($jumlahSiswaGuruWali > 0)<span class="ph-badge blue">{{ $angka($jumlahSiswaGuruWali) }} siswa dampingan</span>@endif</div>
                @if ($kelasWali->isNotEmpty())
                    <h3>Kehadiran Siswa Wali</h3>
                    <dl class="ph-facts">@foreach (['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alfa' => 'Alfa', 'terlambat' => 'Terlambat', 'pulang_cepat' => 'Pulang cepat'] as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $angka($ringkasanAbsensiSiswaWali[$key]) }}</dd></div>@endforeach</dl>
                    @izin('absensi.lihat', 'absensi.koreksi')<a class="ph-link" href="{{ route('rekap-absensi-harian.index') }}">Rekap kelas <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endizin
                @endif
            </div>
            <div>
                <h3>Pembinaan &amp; Poin Siswa Wali</h3>
                <dl class="ph-facts">@foreach (['total_laporan' => 'Laporan', 'siswa_terlapor' => 'Siswa terlapor', 'menunggu_bk' => 'Menunggu keputusan BK', 'menunggu_wakil' => 'Menunggu pengesahan Wakil', 'poin_aktif' => 'Poin aktif'] as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ $angka($ringkasanPembinaanWali[$key]) }}</dd></div>@endforeach</dl>
                @foreach ($laporanPembinaanWali as $laporan)<div class="ph-ward-report"><strong>{{ $laporan->siswa?->nama_lengkap ?? 'Siswa tidak ditemukan' }}</strong><small>{{ $laporan->kelas?->nama }} &middot; {{ $laporan->kategoriPembinaanSiswa?->nama }} &middot; {{ $laporan->labelStatus() }}</small></div>@endforeach
                @izin('poin_siswa.lihat', 'poin_siswa.lapor')<a class="ph-link" href="{{ route('laporan-pembinaan-siswa.index') }}">Pembinaan &amp; Poin <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endizin
            </div>
        </div>
    </section>
@endif
