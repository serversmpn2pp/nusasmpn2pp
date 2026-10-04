@include('publikasi-humas._style')
@include('program-komite-humas._style')
<style>
    .humas-program-filter { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; align-items:end; }
    .humas-program-row { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto; gap:22px; padding:22px 0; border-bottom:1px solid var(--line); }
    .humas-program-row > * { min-width:0; overflow-wrap:anywhere; }
    .humas-program-row h2 { margin:8px 0; font-size:17px; }
    .humas-program-row p { font-size:13px; margin:8px 0; }
    .humas-progress { display:block; width:100%; height:8px; margin-top:12px; accent-color:#2e806b; }
    .humas-program-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .humas-program-toolbar form { margin:0; }
    .humas-evidence { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:16px; border-bottom:1px solid var(--line); padding:16px 0; }
    .humas-evidence > * { min-width:0; overflow-wrap:anywhere; }
    .humas-evidence details { grid-column:1/-1; }
    .humas-evidence summary { cursor:pointer; font-size:13px; color:#a34546; }
    .humas-program-row .button { align-self:center; }
    @media(max-width:900px) { .humas-program-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:600px) { .humas-program-filter,.humas-program-row,.humas-evidence { grid-template-columns:minmax(0,1fr); } .humas-program-row { gap:8px; } }
</style>
<div class="page-header"><div style="min-width:0"><p class="eyebrow">Humas / Program Kerja</p><h1 class="page-title">{{ $judulHalaman }}</h1></div><div class="actions">@yield('program-actions')</div></div>
@include('agenda-humas._messages')
