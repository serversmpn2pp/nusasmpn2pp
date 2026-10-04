@include('publikasi-humas._style')
<style>
    .alumni-page [hidden] { display:none !important; }
    .alumni-page .page-title { overflow-wrap:anywhere; font-size:1.65rem; }
    .alumni-page .agenda-facts { grid-template-columns:130px minmax(0,1fr); gap:14px 18px; }
    .alumni-filter { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; align-items:end; }
    .alumni-row { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1.5fr) auto; gap:24px; padding:20px 0; border-bottom:1px solid var(--line); align-items:center; }
    .alumni-row > * { min-width:0; overflow-wrap:anywhere; }
    .alumni-row h2 { font-size:16px; margin:0 0 8px; }
    .alumni-row p { font-size:13px; margin:6px 0; }
    .alumni-tag { display:inline-block; border-radius:4px; padding:5px 9px; font-weight:700; font-size:12px; background:#eef0f2; color:#53616a; }
    .alumni-tag--melanjutkan { background:#e6f3ed; color:#286949; }
    .alumni-tag--tidak_melanjutkan { background:#fff3df; color:#866020; }
    .alumni-columns { display:grid; grid-template-columns:1fr 1fr; gap:28px; }
    .alumni-chart-row { display:grid; grid-template-columns:95px minmax(0,1fr) 45px; gap:12px; align-items:center; margin:18px 0; font-size:13px; }
    .alumni-chart-row progress { width:100%; height:12px; accent-color:#337c72; }
    .alumni-chart-row:nth-child(3) progress { accent-color:#315f83; }
    .alumni-chart-row:nth-child(4) progress { accent-color:#ac8031; }
    .alumni-chart-row:nth-child(5) progress { accent-color:#ab615f; }
    .alumni-chart-row:nth-child(6) progress { accent-color:#617a4c; }
    .alumni-mode { display:flex; flex-wrap:wrap; gap:0; margin:0 0 20px; border:0; padding:0; }
    .alumni-mode legend { font-weight:700; font-size:14px; margin-bottom:12px; }
    .alumni-mode label { display:flex; gap:8px; align-items:center; padding:11px 15px; border:1px solid #bfccd6; background:white; cursor:pointer; font-size:13px; }
    .alumni-mode label:has(input:checked) { background:#e6f3ed; border-color:#37796d; color:#245b50; }
    .alumni-mode input,.alumni-check input { width:18px; height:18px; flex-shrink:0; accent-color:#32776a; }
    .alumni-check { display:flex; gap:10px; align-items:center; font-size:13px; margin:16px 0; }
    .alumni-source-results { list-style:none; margin:12px 0; padding:0; max-height:330px; overflow:auto; }
    .alumni-source-results button { display:block; width:100%; padding:12px; background:white; border:0; border-bottom:1px solid var(--line); text-align:left; cursor:pointer; font:inherit; font-size:13px; overflow-wrap:anywhere; }
    .alumni-source-results button:hover,.alumni-source-results button:focus-visible { background:#eaf4ef; }
    .alumni-source-results small { display:block; color:var(--muted); margin-top:5px; }
    .alumni-source-selected { padding:12px 0; color:#286949; font-size:13px; font-weight:700; overflow-wrap:anywhere; }
    .alumni-search { display:flex; gap:10px; align-items:end; }
    .alumni-search .field { flex:1; }
    .alumni-export { border-top:1px solid var(--line); padding-top:18px; margin-top:22px; }
    .alumni-table { table-layout:fixed; }
    .alumni-table th:first-child { width:24%; }
    @media(max-width:900px) { .alumni-filter { grid-template-columns:1fr 1fr; } .alumni-columns { grid-template-columns:minmax(0,1fr); gap:0; } }
    @media(max-width:600px) { .alumni-filter,.alumni-row { grid-template-columns:minmax(0,1fr); } .alumni-row { gap:8px; } .alumni-search { flex-wrap:wrap; } .alumni-search .field { flex-basis:100%; } .alumni-chart-row { grid-template-columns:75px minmax(0,1fr) 35px; } .alumni-page .agenda-facts { grid-template-columns:minmax(0,1fr); gap:5px; } .alumni-page .agenda-facts dd { margin-bottom:12px; } .alumni-table th:first-child { width:auto; } }
</style>
