@if ($baris->isEmpty())
    <p class="empty-state iw-empty">{{ $jumlahHari > 0 ? 'Tidak ada siswa yang perlu dikonfirmasi pada pilihan ini.' : 'Belum ada sesi terjadwal yang selesai pada minggu ini untuk pilihan tersebut.' }}</p>
@else
    <table class="iw-table" aria-label="Siswa hadir yang belum tercatat ibadah">
        <colgroup><col class="iw-col-siswa"><col><col class="iw-col-jumlah">@if ($cetak)<col class="iw-col-catatan">@endif</colgroup>
        <thead><tr><th scope="col">Siswa</th><th scope="col">Tanggal belum tercatat</th><th scope="col" class="iw-count">Kali</th>@if ($cetak)<th scope="col">Hasil konfirmasi</th>@endif</tr></thead>
        <tbody>
            @foreach ($baris as $item)
                <tr>
                    <td><strong>{{ $item['nama'] }}</strong><span class="iw-class">{{ $item['kelas']->nama }}@if ($item['absen']) &middot; Absen {{ $item['absen'] }}@endif</span></td>
                    <td><ul class="iw-dates">@foreach ($item['tanggal'] as $hari)<li>{{ $hari->locale('id')->translatedFormat('D, d M') }}</li>@endforeach</ul></td>
                    <td class="iw-count"><strong>{{ $item['tanggal']->count() }}</strong></td>
                    @if ($cetak)<td></td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
