<nav class="agenda-tabs" aria-label="Bagian agenda">
    @foreach (['ringkasan' => 'Ringkasan', 'peserta' => 'Peserta & Kehadiran', 'qr' => 'E-Presensi QR', 'notulen' => 'Notulen', 'tindak-lanjut' => 'Tindak Lanjut', 'dokumen' => 'Dokumen', 'bundel' => 'Bundel Pertemuan'] as $kode => $nama)
        <a href="{{ $kode === 'bundel' ? route('agenda-humas.bundel', $agendaHumas) : route('agenda-humas.show', [$agendaHumas, 'tab' => $kode]) }}" @if ($tab === $kode) aria-current="page" @endif>{{ $nama }}@if ($kode === 'peserta')<span class="agenda-count">{{ array_sum($rekapPresensi) }}</span>@elseif ($kode === 'tindak-lanjut')<span class="agenda-count">{{ $agendaHumas->tindakLanjut->where('status', '!=', 'selesai')->count() }}</span>@endif</a>
    @endforeach
</nav>
