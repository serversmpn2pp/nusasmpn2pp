@include('agenda-humas._style')
<style>
    .publikasi-page { max-width:1200px; margin:0 auto; }
    .publikasi-page [hidden] { display:none !important; }
    .publikasi-page .page-header { flex-wrap:wrap; gap:16px; }
    .publikasi-page h1 { font-size:28px; overflow-wrap:anywhere; }
    .publikasi-page h2 { font-size:17px; }
    .publikasi-page .agenda-section { background:transparent; padding:22px 0; }
    .publikasi-page .button { white-space:normal; text-align:center; }
    .publikasi-page .field,.publikasi-page .actions { min-width:0; }
    .publikasi-page .agenda-facts dd { font-weight:500; }
    .publikasi-filter { display:grid; grid-template-columns:2fr repeat(3,minmax(0,1fr)); gap:14px; }
    .publikasi-status { display:inline-block; padding:5px 9px; border-radius:4px; font-size:12px; font-weight:700; color:#53636f; background:#e9edf0; }
    .publikasi-status--diajukan { color:#255577; background:#e5eff8; }
    .publikasi-status--revisi { color:#8b600a; background:#fff4d2; }
    .publikasi-status--disetujui { color:#22664d; background:#e5f3ea; }
    .publikasi-status--tayang { color:#3e5c68; background:#e3f1f3; }
    .publikasi-table { table-layout:fixed; background:white; }
    .publikasi-table th:first-child { width:40%; }
    .publikasi-table th:last-child { width:12%; }
    .publikasi-photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }
    .publikasi-photo { margin:0; min-width:0; }
    .publikasi-photo img { width:100%; aspect-ratio:4/3; object-fit:contain; background:#e9edf0; display:block; border:1px solid var(--line); border-radius:4px; }
    .publikasi-photo figcaption { font-size:12px; overflow-wrap:anywhere; padding:8px 0; }
    .publikasi-photo label { display:flex; gap:8px; align-items:center; font-size:13px; }
    .publikasi-photo input { width:18px; height:18px; accent-color:#9b3f35; }
    .publikasi-review { border-left:3px solid #a77b14; padding:12px 16px; background:#fff9e6; margin-bottom:20px; overflow-wrap:anywhere; }
    .publikasi-workflow { display:flex; flex-wrap:wrap; gap:8px; padding:0; list-style:none; margin:0 0 24px; font-size:12px; }
    .publikasi-workflow li { padding:8px 10px; border-bottom:2px solid #d3dce2; color:#627581; }
    .publikasi-workflow li[aria-current="step"] { color:#174f77; border-color:#174f77; font-weight:700; }
    .publikasi-history { padding:0; list-style:none; }
    .publikasi-history > li { padding:16px 0; border-bottom:1px solid var(--line); }
    .publikasi-history summary { cursor:pointer; margin-top:10px; color:var(--primary-dark); font-size:13px; font-weight:700; }
    .publikasi-snapshot { padding:14px 0; overflow-wrap:anywhere; }
    .publikasi-upload { margin:16px 0; padding:16px; border:1px solid #aac8d1; background:#eaf5f7; border-radius:5px; }
    .publikasi-upload progress { width:100%; display:block; margin-top:10px; accent-color:#22664d; }
    .publikasi-upload--error { color:#993d36; background:#fff0ed; border-color:#dfb2ac; }
    @media(max-width:900px) { .publikasi-filter { grid-template-columns:1fr 1fr; } }
    @media(max-width:600px) { .publikasi-filter { grid-template-columns:minmax(0,1fr); } .publikasi-page h1 { font-size:24px; } .publikasi-table { width:100%; } .publikasi-photos { grid-template-columns:minmax(0,1fr); } }
</style>
