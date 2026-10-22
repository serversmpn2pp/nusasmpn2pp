@if(isset($dokumenTerkait) && $dokumenTerkait->isNotEmpty())
<section data-document-backlinks style="margin:22px 0;padding:18px;background:#e7f4f0;border-top:3px solid #0f766e;color:#15477a;min-width:0" aria-label="Arsip Humas terkait">
    <h2 style="font-size:18px;color:#15477a;margin:0 0 12px;display:flex;align-items:center;gap:10px"><i data-lucide="files" aria-hidden="true" style="width:20px;height:20px;flex-shrink:0"></i>Arsip Humas terkait</h2>
    <ul style="list-style:none;padding:0;margin:0">@foreach($dokumenTerkait as $arsip)
        <li style="padding:10px 0;overflow-wrap:anywhere;display:flex;align-items:center;gap:10px;flex-wrap:wrap"><a style="min-width:0;flex:1 1 200px;font-weight:700" href="{{ route('dokumen-humas.show', $arsip) }}">{{ $arsip->judul }}</a> <span class="badge badge-muted">{{ \App\Models\DokumenHumas::JENIS_DOKUMEN[$arsip->jenis_dokumen] ?? 'Belum diklasifikasi' }}</span></li>
    @endforeach</ul>
</section>
@endif
