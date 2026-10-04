@include('kliping-berita-humas._style')
<style>
    .prestasi-page [hidden] { display:none !important; }
    .prestasi-page .page-title { font-size:1.65rem; overflow-wrap:anywhere; }
    .prestasi-filter { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; align-items:end; }
    .prestasi-row { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr) auto; gap:22px; padding:22px 0; border-bottom:1px solid var(--line); align-items:center; }
    .prestasi-row > * { min-width:0; overflow-wrap:anywhere; }
    .prestasi-row h2 { font-size:16px; margin:8px 0; }
    .prestasi-row p { font-size:13px; margin:6px 0; }
    .prestasi-badge { display:inline-block; font-size:12px; font-weight:700; padding:5px 9px; border-radius:4px; color:#53616a; background:#eceff1; }
    .prestasi-badge--terverifikasi { color:#286949; background:#e6f3ed; }
    .prestasi-badge--draf { color:#866020; background:#fff3df; }
    .prestasi-columns { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:24px; }
    .prestasi-chart { display:grid; grid-template-columns:125px minmax(0,1fr) 35px; gap:12px; margin:18px 0; align-items:center; font-size:13px; }
    .prestasi-chart progress { width:100%; height:12px; accent-color:#347d70; }
    .prestasi-chart:nth-child(even) progress { accent-color:#426685; }
    .prestasi-page .agenda-facts { grid-template-columns:135px minmax(0,1fr); gap:14px 20px; }
    .prestasi-selected,.prestasi-results { list-style:none; margin:12px 0; padding:0; }
    .prestasi-selected li { display:flex; gap:12px; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--line); overflow-wrap:anywhere; }
    .prestasi-results { max-height:280px; overflow:auto; }
    .prestasi-results button { font:inherit; text-align:left; width:100%; background:white; padding:12px; border:0; border-bottom:1px solid var(--line); cursor:pointer; overflow-wrap:anywhere; }
    .prestasi-results button:hover { background:#eaf4ef; }
    .prestasi-remove { width:34px; height:34px; flex:none; border:1px solid #c9d4dc; border-radius:4px; background:white; color:#94504c; font-size:22px; cursor:pointer; }
    .prestasi-check { display:flex; align-items:center; gap:10px; margin:16px 0; font-size:13px; }
    .prestasi-check input { width:18px; height:18px; accent-color:#32776a; flex:none; }
    .prestasi-search { display:flex; gap:10px; align-items:end; }
    .prestasi-search .field { flex:1; }
    .prestasi-peserta-names { margin:0; padding-left:20px; overflow-wrap:anywhere; }
    .prestasi-peserta-names li { margin:8px 0; }
    @media(max-width:900px) { .prestasi-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } .prestasi-columns { grid-template-columns:minmax(0,1fr); gap:0; } }
    @media(max-width:600px) { .prestasi-filter,.prestasi-row { grid-template-columns:minmax(0,1fr); } .prestasi-row { gap:8px; } .prestasi-page .agenda-facts { grid-template-columns:minmax(0,1fr); gap:5px; } .prestasi-page .agenda-facts dd { margin-bottom:12px; } .prestasi-chart { grid-template-columns:95px minmax(0,1fr) 25px; } .prestasi-search { flex-wrap:wrap; } .prestasi-search .field { flex-basis:100%; } }
</style>
