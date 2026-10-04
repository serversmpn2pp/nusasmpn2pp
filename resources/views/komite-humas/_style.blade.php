@include('publikasi-humas._style')
<style>
    .komite-filter { display:grid; grid-template-columns:2fr 1fr 1.2fr; gap:14px; align-items:end; }
    .komite-list { list-style:none; padding:0; margin:0; }
    .komite-row { display:grid; grid-template-columns:minmax(0,1fr) 210px 180px; gap:20px; padding:22px 0; border-bottom:1px solid var(--line); align-items:center; }
    .komite-row > * { min-width:0; }
    .komite-row h2 { font-size:16px; margin:10px 0; overflow-wrap:anywhere; }
    .komite-row h2 a { color:var(--text); text-decoration:none; }
    .komite-row h2 a:hover { text-decoration:underline; }
    .komite-row p { margin:6px 0; overflow-wrap:anywhere; }
    .komite-tags { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .komite-badge { display:inline-block; border-radius:4px; padding:5px 9px; font-size:12px; font-weight:700; color:#5b6571; background:#edf0f3; }
    .komite-badge--aktif,.komite-badge--berjalan { color:#2c6b4c; background:#e7f3eb; }
    .komite-badge--draf,.komite-badge--belum_mulai { color:#356684; background:#e8f1f6; }
    .komite-badge--berakhir { color:#a34546; background:#fbecee; }
    .komite-badge--segera_berakhir { color:#805d18; background:#fff3d6; }
    .komite-member { padding:18px 0; border-bottom:1px solid var(--line); }
    .komite-member-head { display:flex; align-items:center; gap:12px; justify-content:space-between; margin-bottom:14px; }
    .komite-member-head h3 { font-size:14px; margin:0; }
    .komite-member-fields { display:grid; grid-template-columns:2fr 1.3fr 1.4fr 1fr; gap:14px; }
    .komite-member-fields > * { min-width:0; }
    .komite-member--nonaktif { border-left:3px solid #c3c9ce; padding-left:14px; }
    .komite-source { display:flex; flex-wrap:wrap; gap:14px 24px; padding:0; margin:0 0 18px; border:0; }
    .komite-source label { display:flex; align-items:center; gap:8px; cursor:pointer; }
    .komite-source input { width:18px; height:18px; accent-color:#357154; }
    .komite-table { width:100%; border-collapse:collapse; font-size:14px; }
    .komite-table td,.komite-table th { padding:13px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; overflow-wrap:anywhere; }
    .komite-table th { background:#f3f6f8; font-size:12px; color:#576674; }
    .komite-preview { max-width:100%; max-height:240px; object-fit:contain; display:block; }
    .komite-file { display:flex; gap:14px; flex-wrap:wrap; align-items:center; overflow-wrap:anywhere; }
    .komite-file strong { min-width:0; flex:1 1 220px; }
    .komite-table-shell { overflow-x:auto; }
    .komite-table--snapshot { table-layout:fixed; }
    @media(max-width:1000px) { .komite-member-fields { grid-template-columns:repeat(2,minmax(0,1fr)); } .komite-row { grid-template-columns:minmax(0,1fr) 170px; } .komite-row > :last-child { grid-column:1/-1; } }
    @media(max-width:600px) { .komite-filter,.komite-row,.komite-member-fields { grid-template-columns:minmax(0,1fr); } .komite-row { gap:10px; } .komite-table--responsive thead { display:none; } .komite-table--responsive tr { display:block; border-bottom:1px solid var(--line); padding:10px 0; } .komite-table--responsive td { display:block; padding:7px 0; border:0; } .komite-table--responsive td::before { content:attr(data-label) ": "; font-size:12px; font-weight:700; color:var(--muted); } }
</style>
