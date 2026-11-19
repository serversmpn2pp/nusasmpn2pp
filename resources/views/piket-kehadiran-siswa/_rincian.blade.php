@if ($absensi)
    <div class="picket-evidence">
        <span>Masuk {{ $absensi->jam_masuk ? substr($absensi->jam_masuk, 0, 5) : '-' }} &middot; {{ $rincianPiket['sumber_masuk_label'] ?? '-' }}</span>
        <span>Pulang {{ $absensi->jam_pulang ? substr($absensi->jam_pulang, 0, 5) : '-' }} &middot; {{ $rincianPiket['sumber_pulang_label'] ?? '-' }}</span>
        @if ($pencatat = $rincianPiket['pencatat_terakhir'])
            <small>{{ $pencatat['jenis'] }}: {{ $pencatat['nama'] }}<br>{{ $pencatat['waktu_label'] }}</small>
        @endif
    </div>
@endif
