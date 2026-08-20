@extends('layouts.app')

@section('title', 'Akademik Anak - NUSA')

@section('content')
    <style>
        .child-academic{display:grid;gap:18px}
        .child-academic-hero{align-items:center;background:#15477a;border-radius:8px;color:#fff;display:flex;gap:18px;justify-content:space-between;padding:20px 22px}
        .child-academic-hero h1{color:#fff;font-size:1.65rem;letter-spacing:0;margin:0}
        .child-academic-hero p{color:#dbeafe;line-height:1.5;margin:7px 0 0}
        .child-academic-class{background:#fff;border-radius:7px;color:#15477a;flex:0 0 auto;font-size:.84rem;font-weight:900;padding:9px 13px;text-align:center}
        .child-academic-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));max-width:420px}
        .child-academic-tab{align-items:center;background:#fff;border:1px solid #cfd9e3;color:#526477;display:flex;font-size:.84rem;font-weight:900;justify-content:center;min-height:44px;padding:9px 14px;text-decoration:none}
        .child-academic-tab:first-child{border-radius:8px 0 0 8px}
        .child-academic-tab:last-child{border-left:0;border-radius:0 8px 8px 0}
        .child-academic-tab.active{background:#15477a;border-color:#15477a;color:#fff}
        .child-academic-summary{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
        .child-academic-stat{background:#fff;border:1px solid #dce4eb;border-top:4px solid #15477a;border-radius:8px;padding:15px}
        .child-academic-stat.yellow{border-top-color:#f1c40f}
        .child-academic-stat.green{border-top-color:#16a34a}
        .child-academic-stat span{color:#64748b;display:block;font-size:.76rem;font-weight:800}
        .child-academic-stat strong{color:#15477a;display:block;font-size:1.55rem;line-height:1;margin-top:8px}
        .child-academic-panel{background:#fff;border:1px solid #dce4eb;border-radius:8px;overflow:hidden}
        .child-academic-table-wrap{overflow-x:auto}
        .child-academic-table{border-collapse:collapse;min-width:1040px;table-layout:fixed;width:100%}
        .child-academic-table th,.child-academic-table td{border-bottom:1px solid #e5eaf0;padding:10px;text-align:left;vertical-align:top}
        .child-academic-table th{background:#f8fafc;color:#526477;font-size:.73rem;text-transform:uppercase}
        .child-academic-table th:first-child,.child-academic-table td:first-child{width:105px}
        .child-academic-table .today{background:#fff8d8;color:#6b5200}
        .child-slot{background:#fafafa;border:1px solid #e3e9ef;border-radius:7px;min-height:92px;padding:9px}
        .child-slot.lesson{background:#eef5fb;border-color:#b9cde2}
        .child-slot.special{background:#fffbea;border-color:#f1d76c}
        .child-slot.current{box-shadow:inset 0 0 0 2px rgba(241,196,15,.42)}
        .child-slot-time{color:#64748b;font-size:.7rem;font-weight:700}
        .child-slot strong{color:#172536;display:block;font-size:.84rem;line-height:1.35;margin-top:6px;overflow-wrap:anywhere}
        .child-slot small{color:#526477;display:block;font-size:.7rem;line-height:1.35;margin-top:5px}
        .child-mobile-schedule{display:none;padding:14px}
        .child-day-tabs{display:flex;gap:7px;overflow-x:auto;padding-bottom:4px}
        .child-day-tab{background:#fff;border:1px solid #d4dce5;border-radius:7px;color:#526477;cursor:pointer;font-size:.78rem;font-weight:900;min-height:38px;padding:7px 11px;white-space:nowrap}
        .child-day-tab.active{background:#15477a;border-color:#15477a;color:#fff}
        .child-day{display:none;gap:9px;margin-top:13px}
        .child-day.active{display:grid}
        .child-mobile-row{display:grid;gap:9px;grid-template-columns:68px minmax(0,1fr)}
        .child-mobile-hour{background:#f8fafc;border:1px solid #e3e9ef;border-radius:7px;color:#15477a;font-size:.75rem;font-weight:900;padding:9px 5px;text-align:center}
        .child-mobile-hour small{color:#64748b;display:block;font-size:.66rem;margin-top:4px}
        .child-grade-tools{align-items:end;display:flex;gap:12px;justify-content:space-between}
        .child-grade-tools .field{max-width:260px;width:100%}
        .child-grade-list{display:grid;gap:10px}
        .child-grade-card{background:#fff;border:1px solid #dce4eb;border-radius:8px;overflow:hidden}
        .child-grade-card.locked{border-color:#e5cf61}
        .child-grade-summary{align-items:center;display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto;padding:15px 16px}
        details .child-grade-summary{cursor:pointer;list-style:none}
        details .child-grade-summary::-webkit-details-marker{display:none}
        .child-grade-subject strong{color:#172536;display:block;font-size:.94rem;line-height:1.35;overflow-wrap:anywhere}
        .child-grade-subject small{color:#64748b;display:block;font-size:.72rem;line-height:1.4;margin-top:4px}
        .child-grade-value{color:#15477a;font-size:1.35rem;font-weight:900;text-align:right;white-space:nowrap}
        .child-grade-value small{color:#64748b;display:block;font-size:.66rem;margin-top:3px}
        .child-grade-lock{background:#fffbea;border-top:1px solid #f1dc79;color:#665000;font-size:.78rem;line-height:1.5;padding:12px 16px}
        .child-grade-components{border-top:1px solid #e5eaf0;padding:5px 16px 12px}
        .child-grade-component{align-items:center;border-bottom:1px solid #edf1f4;display:grid;gap:12px;grid-template-columns:100px minmax(0,1fr) auto;padding:10px 0}
        .child-grade-component:last-child{border-bottom:0}
        .child-grade-component span{color:#15477a;font-size:.71rem;font-weight:900}
        .child-grade-component strong{color:#172536;font-size:.8rem;overflow-wrap:anywhere}
        .child-grade-component b{color:#15477a;font-size:.9rem;white-space:nowrap}
        .child-academic-empty{background:#fff;border:1px dashed #b9c8d6;border-radius:8px;color:#64748b;padding:30px 18px;text-align:center}
        .child-academic-empty strong{color:#15477a;display:block;margin-bottom:5px}
        @media(max-width:760px){.child-academic-hero{align-items:flex-start;flex-direction:column;padding:17px}.child-academic-class{width:100%}.child-academic-tabs{max-width:none}.child-academic-summary{gap:7px}.child-academic-stat{padding:12px 9px}.child-academic-stat span{font-size:.67rem}.child-academic-stat strong{font-size:1.35rem}.child-academic-table-wrap{display:none}.child-mobile-schedule{display:block}.child-grade-tools{align-items:stretch;flex-direction:column}.child-grade-tools .field{max-width:none}.child-grade-component{align-items:start;grid-template-columns:1fr auto}.child-grade-component span{grid-column:1/-1}.child-grade-component strong{grid-column:1}.child-grade-component b{grid-column:2;grid-row:2}}
    </style>

    @php
        $labelPredikat = fn (?string $predikat) => match ($predikat) {
            'SB' => 'Sangat Baik',
            'B' => 'Baik',
            'C' => 'Cukup',
            'K' => 'Kurang',
            default => '-',
        };
    @endphp

    <div class="child-academic">
        <section class="child-academic-hero">
            <div>
                <h1>Akademik Anak</h1>
                <p>{{ $siswa?->nama_lengkap ?: 'Akun belum terhubung ke data siswa' }} · {{ $tahunPelajaran?->nama ?: 'Tahun pelajaran belum tersedia' }}</p>
            </div>
            <div class="child-academic-class">{{ $anggotaKelas?->kelas?->nama ?: 'Belum ditempatkan di kelas' }}</div>
        </section>

        <nav class="child-academic-tabs" aria-label="Bagian akademik anak">
            <a href="{{ route('akademik-anak.index', ['tab' => 'jadwal', 'semester' => $semester]) }}" class="child-academic-tab {{ $tab === 'jadwal' ? 'active' : '' }}">Jadwal Pelajaran</a>
            <a href="{{ route('akademik-anak.index', ['tab' => 'nilai', 'semester' => $semester]) }}" class="child-academic-tab {{ $tab === 'nilai' ? 'active' : '' }}">Nilai Anak</a>
        </nav>

        @if (! $siswa)
            <div class="child-academic-empty">
                <strong>Akun belum terhubung ke data siswa</strong>
                Hubungi administrator sekolah agar akun orang tua dihubungkan dengan data anak yang benar.
            </div>
        @elseif (! $anggotaKelas)
            <div class="child-academic-empty">
                <strong>Kelas anak belum tersedia</strong>
                Penempatan kelas pada tahun pelajaran aktif belum ditemukan.
            </div>
        @elseif ($tab === 'jadwal')
            <section class="child-academic-summary" aria-label="Ringkasan jadwal">
                <article class="child-academic-stat"><span>Kelas</span><strong>{{ $anggotaKelas->kelas?->nama }}</strong></article>
                <article class="child-academic-stat green"><span>Jam terjadwal</span><strong>{{ $jumlahJadwal }}</strong></article>
                <article class="child-academic-stat yellow"><span>Mata pelajaran</span><strong>{{ $jumlahMataPelajaran }}</strong></article>
            </section>

            <section class="child-academic-panel">
                <div class="child-academic-table-wrap">
                    <table class="child-academic-table">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                @foreach ($daftarHari as $kodeHari => $labelHari)
                                    <th class="{{ $kodeHari === $hariHariIni ? 'today' : '' }}">{{ $labelHari }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($nomorJam as $nomor)
                                <tr>
                                    <td><strong>Jam {{ $nomor }}</strong></td>
                                    @foreach ($daftarHari as $kodeHari => $labelHari)
                                        @php
                                            $jam = $jamPerHari->get($kodeHari)?->get($nomor);
                                            $jadwal = $jam ? $jadwalKelas->get($jam->id) : null;
                                            $sedangBerlangsung = $jam && $jam->id === $jamAktifId;
                                        @endphp
                                        <td>
                                            @if (! $jam)
                                                <div class="child-slot"><strong>-</strong></div>
                                            @elseif ($jam->jenis !== 'pelajaran')
                                                <div class="child-slot special {{ $sedangBerlangsung ? 'current' : '' }}">
                                                    <span class="child-slot-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</span>
                                                    <strong>{{ $jam->label ?: $jam->labelJenis() }}</strong>
                                                </div>
                                            @elseif ($jadwal)
                                                <div class="child-slot lesson {{ $sedangBerlangsung ? 'current' : '' }}">
                                                    <span class="child-slot-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</span>
                                                    <strong>{{ $jadwal->mataPelajaranTerjadwal()?->nama ?: '-' }}</strong>
                                                    <small>{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?: 'Guru belum ditentukan' }}</small>
                                                </div>
                                            @else
                                                <div class="child-slot {{ $sedangBerlangsung ? 'current' : '' }}">
                                                    <span class="child-slot-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</span>
                                                    <strong>Kosong</strong>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($daftarHari) + 1 }}">Jam pelajaran belum diatur.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="child-mobile-schedule">
                    <div class="child-day-tabs" role="tablist" aria-label="Pilih hari">
                        @foreach ($daftarHari as $kodeHari => $labelHari)
                            @php $hariAktif = $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first); @endphp
                            <button type="button" class="child-day-tab {{ $hariAktif ? 'active' : '' }}" data-child-day-tab="{{ $kodeHari }}" aria-selected="{{ $hariAktif ? 'true' : 'false' }}">{{ $labelHari }}</button>
                        @endforeach
                    </div>

                    @foreach ($daftarHari as $kodeHari => $labelHari)
                        @php $hariAktif = $kodeHari === $hariHariIni || ($hariHariIni === 'minggu' && $loop->first); @endphp
                        <div class="child-day {{ $hariAktif ? 'active' : '' }}" data-child-day="{{ $kodeHari }}">
                            @forelse ($jamPerHari->get($kodeHari, collect()) as $jam)
                                @php
                                    $jadwal = $jadwalKelas->get($jam->id);
                                    $sedangBerlangsung = $jam->id === $jamAktifId;
                                @endphp
                                <div class="child-mobile-row">
                                    <div class="child-mobile-hour">Jam {{ $jam->nomor_jam }}<small>{{ $jam->formatJam($jam->jam_mulai) }}</small></div>
                                    @if ($jam->jenis !== 'pelajaran')
                                        <div class="child-slot special {{ $sedangBerlangsung ? 'current' : '' }}"><span class="child-slot-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</span><strong>{{ $jam->label ?: $jam->labelJenis() }}</strong></div>
                                    @elseif ($jadwal)
                                        <div class="child-slot lesson {{ $sedangBerlangsung ? 'current' : '' }}"><span class="child-slot-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</span><strong>{{ $jadwal->mataPelajaranTerjadwal()?->nama ?: '-' }}</strong><small>{{ $jadwal->guruMataPelajaran?->pegawai?->nama_lengkap ?: 'Guru belum ditentukan' }}</small></div>
                                    @else
                                        <div class="child-slot {{ $sedangBerlangsung ? 'current' : '' }}"><span class="child-slot-time">{{ $jam->formatJam($jam->jam_mulai) }} - {{ $jam->formatJam($jam->jam_selesai) }}</span><strong>Kosong</strong></div>
                                    @endif
                                </div>
                            @empty
                                <div class="child-academic-empty">Jam pelajaran {{ $labelHari }} belum diatur.</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <form method="GET" class="child-grade-tools panel panel-pad" data-auto-submit-filter>
                <input type="hidden" name="tab" value="nilai">
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="select" data-auto-submit-control>
                        <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($semester === 'genap')>Genap</option>
                    </select>
                </div>
                <span class="badge badge-active">{{ $tahunPelajaran?->nama }}</span>
            </form>

            <section class="child-academic-summary" aria-label="Ringkasan nilai">
                <article class="child-academic-stat"><span>Dipublikasikan</span><strong>{{ $ringkasan['mata_pelajaran'] }}</strong></article>
                <article class="child-academic-stat green"><span>Sudah terbuka</span><strong>{{ $ringkasan['nilai_terbuka'] }}</strong></article>
                <article class="child-academic-stat yellow"><span>Menunggu survei anak</span><strong>{{ $ringkasan['survei_belum_diisi'] }}</strong></article>
            </section>

            <section class="child-grade-list" aria-label="Daftar nilai anak">
                @forelse ($daftarNilai as $item)
                    @if (! $item['survei_diisi'])
                        <article class="child-grade-card locked" id="mapel-{{ $item['guru_mata_pelajaran']->id }}">
                            <div class="child-grade-summary">
                                <div class="child-grade-subject">
                                    <strong>{{ $item['mata_pelajaran']?->nama ?: 'Mata pelajaran' }}</strong>
                                    <small>{{ $item['guru_mata_pelajaran']?->pegawai?->nama_lengkap ?: 'Guru belum dicantumkan' }}</small>
                                </div>
                                <span class="badge badge-warning">Terkunci</span>
                            </div>
                            <div class="child-grade-lock">Nilai akan terbuka setelah anak mengisi survei pembelajaran melalui akun siswa.</div>
                        </article>
                    @else
                        <details class="child-grade-card" id="mapel-{{ $item['guru_mata_pelajaran']->id }}">
                            <summary class="child-grade-summary">
                                <div class="child-grade-subject">
                                    <strong>{{ $item['mata_pelajaran']?->nama ?: 'Mata pelajaran' }}</strong>
                                    <small>{{ $item['guru_mata_pelajaran']?->pegawai?->nama_lengkap ?: 'Guru belum dicantumkan' }}</small>
                                </div>
                                <div class="child-grade-value">
                                    @if ($item['menggunakan_predikat'])
                                        Predikat<small>Lihat rincian</small>
                                    @else
                                        {{ $item['nilai_akhir'] === null ? '-' : number_format($item['nilai_akhir'], 2, ',', '.') }}
                                        <small>{{ $item['nilai_akhir'] === null ? 'Belum lengkap' : ($item['tuntas'] ? 'Tuntas' : 'Belum tuntas') }}</small>
                                    @endif
                                </div>
                            </summary>
                            <div class="child-grade-components">
                                @forelse ($item['komponen'] as $komponen)
                                    <div class="child-grade-component">
                                        <span>{{ $komponen['label_jenis'] }}</span>
                                        <strong>{{ $komponen['nama'] }}</strong>
                                        <b>{{ $item['menggunakan_predikat'] ? $labelPredikat($komponen['predikat']) : ($komponen['nilai'] === null ? '-' : number_format($komponen['nilai'], 2, ',', '.')) }}</b>
                                    </div>
                                @empty
                                    <div class="child-grade-lock">Rincian nilai belum tersedia.</div>
                                @endforelse
                            </div>
                        </details>
                    @endif
                @empty
                    <div class="child-academic-empty">
                        <strong>Belum ada nilai yang dipublikasikan</strong>
                        Nilai akan tampil setelah guru mata pelajaran mempublikasikannya.
                    </div>
                @endforelse
            </section>
        @endif
    </div>

    <script>
        document.querySelectorAll('[data-child-day-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-child-day-tab]').forEach(function (item) {
                    item.classList.toggle('active', item === button);
                    item.setAttribute('aria-selected', item === button ? 'true' : 'false');
                });
                document.querySelectorAll('[data-child-day]').forEach(function (day) {
                    day.classList.toggle('active', day.dataset.childDay === button.dataset.childDayTab);
                });
            });
        });

        document.querySelectorAll('[data-auto-submit-control]').forEach(function (control) {
            control.addEventListener('change', function () {
                control.closest('form').submit();
            });
        });
    </script>
@endsection
