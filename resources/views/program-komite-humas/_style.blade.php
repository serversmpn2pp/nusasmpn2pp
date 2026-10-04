<style>
    .program-filter { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto auto; gap:14px; align-items:end; }
    .program-filter .check-label { display:flex; gap:8px; align-items:center; min-height:42px; cursor:pointer; font-size:13px; }
    .program-filter input[type=checkbox] { width:18px; height:18px; accent-color:#357154; }
    .program-list { list-style:none; margin:0; padding:0; }
    .program-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(150px,210px) auto; gap:20px; align-items:center; border-bottom:1px solid var(--line); padding:20px 0; }
    .program-row > * { min-width:0; overflow-wrap:anywhere; }
    .program-row h2,.program-row h3 { margin:8px 0; font-size:16px; }
    .program-row p { margin:7px 0; font-size:13px; }
    .program-row .agenda-actions { flex-wrap:wrap; }
    .program-badge { display:inline-block; padding:5px 9px; border-radius:4px; font-size:12px; font-weight:700; color:#486b82; background:#e8f1f6; }
    .program-badge--berjalan { color:#206c72; background:#e5f4f4; }
    .program-badge--selesai { color:#2c6b4c; background:#e7f3eb; }
    .program-badge--dibatalkan { color:#666; background:#efefef; }
    .program-badge--terlambat { color:#a34546; background:#fbecee; }
    .program-section-head { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
    .program-link-form { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) auto; gap:14px; align-items:end; margin-top:14px; }
    .program-unlink { border-top:1px solid var(--line); padding:14px 0; }
    .program-unlink summary { cursor:pointer; color:#973e3e; font-size:13px; }
    .program-rapat { padding:18px 0; border-bottom:1px solid var(--line); }
    .program-rapat h3 { font-size:15px; margin:0 0 8px; overflow-wrap:anywhere; }
    .program-rapat p { font-size:13px; }
    @media(max-width:900px) { .program-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } .program-link-form { grid-template-columns:minmax(0,1fr); } }
    @media(max-width:600px) { .program-filter,.program-row { grid-template-columns:minmax(0,1fr); } .program-row { gap:8px; } }
</style>
