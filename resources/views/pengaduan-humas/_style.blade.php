@include('publikasi-humas._style')
<style>
    .pengaduan-filter { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; align-items:end; }
    .pengaduan-list { padding:0; margin:0; list-style:none; }
    .pengaduan-row { display:grid; grid-template-columns:minmax(0,1fr) 190px 170px; gap:20px; padding:20px 0; border-bottom:1px solid var(--line); align-items:center; }
    .pengaduan-row > * { min-width:0; }
    .pengaduan-row h2 { font-size:16px; margin:8px 0; overflow-wrap:anywhere; }
    .pengaduan-row h2 a { color:var(--text); text-decoration:none; }
    .pengaduan-row h2 a:hover { text-decoration:underline; }
    .pengaduan-row p { margin:6px 0; overflow-wrap:anywhere; }
    .pengaduan-tags { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .pengaduan-status { display:inline-block; padding:5px 9px; border-radius:4px; font-weight:700; font-size:12px; background:#eceff1; color:#56616a; }
    .pengaduan-status--baru,.pengaduan-status--ditugaskan { background:#e7eef8; color:#305c86; }
    .pengaduan-status--diproses { background:#e5f2f3; color:#2b6d72; }
    .pengaduan-status--menunggu,.pengaduan-status--verifikasi { background:#fff3d3; color:#805c0d; }
    .pengaduan-status--selesai { background:#e7f3e9; color:#2d6c48; }
    .pengaduan-priority { font-size:12px; font-weight:700; color:#a13e42; }
    .pengaduan-check { display:flex; align-items:center; gap:10px; cursor:pointer; }
    .pengaduan-check input { width:18px; height:18px; accent-color:#386a52; flex:none; }
    .pengaduan-details { border-block:1px solid var(--line); padding:18px 0; margin-bottom:22px; }
    .pengaduan-details summary { cursor:pointer; font-weight:700; padding:4px 0; }
    .pengaduan-details[open] summary { margin-bottom:16px; }
    .pengaduan-details .agenda-field-grid { margin-top:16px; }
    .pengaduan-attachment { margin:12px 0; display:flex; flex-wrap:wrap; align-items:center; gap:10px 18px; overflow-wrap:anywhere; }
    .pengaduan-attachment strong { flex:1 1 200px; min-width:0; }
    .pengaduan-form { max-width:950px; }
    .pengaduan-conversation { list-style:none; padding:0; margin:0; }
    .pengaduan-message { border-left:3px solid #77919e; padding:14px 18px; border-bottom:1px solid var(--line); margin-bottom:14px; overflow-wrap:anywhere; }
    .pengaduan-message--humas { border-left-color:#357154; background:#f5f9f6; }
    .pengaduan-message p { margin:12px 0 0; }
    .pengaduan-privacy { font-size:14px; line-height:1.6; max-width:760px; color:var(--muted); }
    @media(max-width:900px) { .pengaduan-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } .pengaduan-row { grid-template-columns:minmax(0,1fr) 170px; } .pengaduan-row > :last-child { grid-column:1/-1; } }
    @media(max-width:600px) { .pengaduan-filter,.pengaduan-row { grid-template-columns:minmax(0,1fr); } .pengaduan-filter .span-2 { grid-column:auto; } .pengaduan-row { gap:8px; } }
</style>
