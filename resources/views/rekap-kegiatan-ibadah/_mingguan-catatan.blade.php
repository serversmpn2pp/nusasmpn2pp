<p>Belum tercatat bukan bukti siswa tidak salat. Konfirmasi kepada siswa sebelum menindaklanjuti.</p>
@if ($hariTanpaCatatan->isNotEmpty())
    <p class="iw-warning"><strong>Tanggal tanpa catatan ibadah pada cakupan ini:</strong> {{ $hariTanpaCatatan->map(fn ($hari) => $hari->locale('id')->translatedFormat('D, d M'))->implode('; ') }}. Periksa pelaksanaan kegiatan dan scanner pada tanggal tersebut.</p>
@endif
