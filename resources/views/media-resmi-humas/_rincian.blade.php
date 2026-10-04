<dl class="agenda-facts">
    <div><dt>Jenis media</dt><dd>{{ \App\Models\MediaResmiHumas::JENIS[$dataMedia['jenis']] }}</dd></div><div><dt>Status</dt><dd><span class="media-status media-status--{{ $dataMedia['status'] }}">{{ \App\Models\MediaResmiHumas::STATUS[$dataMedia['status']] }}</span></dd></div>
    <div><dt>Nama akun publik / handle</dt><dd>{{ $dataMedia['identitas_akun'] ?: '-' }}</dd></div><div><dt>Terakhir diperiksa</dt><dd>{{ $dataMedia['tanggal_diperiksa'] ?: 'Belum dicatat' }}</dd></div>
    <div><dt>Penanggung jawab</dt><dd>{{ $dataMedia['penanggung_jawab'] }}</dd></div><div><dt>Jabatan / tugas</dt><dd>{{ $dataMedia['jabatan_penanggung_jawab'] ?: '-' }}</dd></div>
    <div class="span-2"><dt>Alamat website / profil media</dt><dd><a class="media-url" href="{{ $dataMedia['tautan'] }}" target="_blank" rel="noopener noreferrer">{{ $dataMedia['tautan'] }}</a></dd></div>
</dl>
@if ($dataMedia['catatan'])<p style="margin-top:20px"><strong>Catatan pengelolaan</strong></p><div class="agenda-text">{{ $dataMedia['catatan'] }}</div>@endif
