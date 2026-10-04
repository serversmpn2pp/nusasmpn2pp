@php($lembar = $jenisCetak === 'daftar-hadir' ? ($agendaHumas->peserta->isEmpty() ? collect([collect()]) : $agendaHumas->peserta->chunk(24)) : collect([collect()]))
    @foreach ($lembar as $halaman => $pesertaLembar)
        <article class="sheet {{ $jenisCetak === 'daftar-hadir' ? 'sheet--attendance' : '' }}">
            <header class="header"><img src="{{ $logoKota ?? asset('images/logo-padang-panjang.png') }}" alt="Logo Padang Panjang"><div><h1>SMP NEGERI 2 PADANG PANJANG</h1><p>WAKIL PIMPINAN BIDANG HUMAS</p></div><img src="{{ $logoSekolah ?? asset('images/kartu-pelajar/logo-smpn2pp.png') }}" alt="Logo sekolah"></header>
            <h2 class="heading">{{ $jenisCetak === 'daftar-hadir' ? 'DAFTAR HADIR PERTEMUAN' : 'NOTULEN PERTEMUAN' }}</h2>
            @if ($agendaHumas->status === 'dibatalkan')<p class="draft">AGENDA DIBATALKAN: {{ $agendaHumas->alasan_pembatalan }}</p>@elseif ($jenisCetak === 'notulen' && $agendaHumas->status !== 'selesai')<p class="draft">DRAF NOTULEN</p>@endif
            <table class="facts">
                <tr><td>Agenda</td><td>: {{ $agendaHumas->judul }}</td></tr>
                <tr><td>Hari / tanggal</td><td>: {{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('l, d F Y') }}</td></tr>
                <tr><td>Waktu</td><td>: {{ $agendaHumas->waktu_mulai->format('H:i') }} - {{ $agendaHumas->waktu_selesai->format($agendaHumas->waktu_mulai->isSameDay($agendaHumas->waktu_selesai) ? 'H:i' : 'd-m-Y H:i') }} WIB</td></tr>
                <tr><td>Tempat</td><td>: {{ $agendaHumas->tempat }}</td></tr>
                @if ($jenisCetak === 'notulen')<tr><td>Pemimpin</td><td>: {{ $agendaHumas->pemimpin ?: '-' }}</td></tr><tr><td>Notulis</td><td>: {{ $agendaHumas->notulis ?: '-' }}</td></tr>@endif
            </table>
            @if ($jenisCetak === 'daftar-hadir')
                <table class="records"><colgroup><col style="width:7%"><col style="width:30%"><col style="width:28%"><col style="width:15%"><col style="width:20%"></colgroup><thead><tr><th>No</th><th>Nama peserta</th><th>Instansi / kelas / peran</th><th>Kehadiran</th><th>Tanda tangan</th></tr></thead><tbody>
                    @forelse ($pesertaLembar as $peserta)<tr><td class="center">{{ $halaman * 24 + $loop->iteration }}</td><td>{{ $peserta->nama }}</td><td>{{ collect([$peserta->instansi, $peserta->peran])->filter()->join(' / ') }}</td><td class="center">{{ $peserta->status_kehadiran === 'belum_dicatat' ? '' : \App\Models\PesertaPertemuanHumas::KEHADIRAN[$peserta->status_kehadiran] }}</td><td></td></tr>@empty
                        @for ($baris = 1; $baris <= 12; $baris++)<tr><td class="center">{{ $baris }}</td><td></td><td></td><td></td><td></td></tr>@endfor
                    @endforelse
                </tbody></table>
                <p class="foot">Lembar {{ $halaman + 1 }} dari {{ $lembar->count() }} &middot; Total {{ $agendaHumas->peserta->count() }} peserta terdaftar.</p>
            @else
                <p style="font-size:9pt">Kehadiran: {{ $agendaHumas->peserta->where('status_kehadiran', 'hadir')->count() }} hadir, {{ $agendaHumas->peserta->where('status_kehadiran', 'izin')->count() }} izin, {{ $agendaHumas->peserta->where('status_kehadiran', 'tidak_hadir')->count() }} tidak hadir, {{ $agendaHumas->peserta->where('status_kehadiran', 'belum_dicatat')->count() }} belum dicatat.</p>
                <h3>Pokok agenda</h3><div class="text">{{ $agendaHumas->topik }}</div>
                <h3>Pembahasan</h3><div class="text">{{ $agendaHumas->pembahasan ?: 'Belum dicatat.' }}</div>
                <h3>Keputusan / kesepakatan</h3><div class="text">{{ $agendaHumas->keputusan ?: 'Belum dicatat.' }}</div>
                @if ($agendaHumas->tindakLanjut->isNotEmpty())
                    <h3>Tindak lanjut</h3><table class="records"><colgroup><col style="width:7%"><col style="width:43%"><col style="width:25%"><col style="width:25%"></colgroup><thead><tr><th>No</th><th>Tindak lanjut</th><th>Penanggung jawab</th><th>Batas / status</th></tr></thead><tbody>
                        @foreach ($agendaHumas->tindakLanjut as $tugas)<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $tugas->uraian }}@if ($tugas->catatan)<br>{{ $tugas->catatan }}@endif</td><td>{{ $tugas->penanggung_jawab }}</td><td>{{ $tugas->batas_tanggal?->format('d-m-Y') ?: '-' }}<br>{{ \App\Models\TindakLanjutAgendaHumas::STATUS[$tugas->status] }}</td></tr>@endforeach
                    </tbody></table>
                @endif
            @endif
            <p class="signature-date">Padang Panjang, {{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('d F Y') }}</p>
            <div class="signatures"><div><p>Pemimpin pertemuan</p><div class="signature-space"></div><p>{{ $agendaHumas->pemimpin ?: '(................................)' }}</p></div><div><p>Notulis</p><div class="signature-space"></div><p>{{ $agendaHumas->notulis ?: '(................................)' }}</p></div></div>
        </article>
    @endforeach
