@include('publikasi-humas._style')
<style>
    .kliping-filter { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; align-items:end; }
    .kliping-list { margin:0; padding:0; list-style:none; }
    .kliping-row { display:grid; grid-template-columns:112px minmax(0,1fr) auto; gap:20px; padding:22px 0; border-bottom:1px solid var(--line); align-items:center; }
    .kliping-row > * { min-width:0; }
    .kliping-thumb { width:100%; aspect-ratio:4/3; display:block; object-fit:contain; background:#eef2f4; border:1px solid #d3dce1; border-radius:4px; }
    .kliping-placeholder { display:flex; align-items:center; justify-content:center; text-align:center; color:#466573; font-size:12px; font-weight:700; }
    .kliping-row h2 { margin:8px 0; font-size:16px; overflow-wrap:anywhere; }
    .kliping-row h2 a { color:var(--text); text-decoration:none; }
    .kliping-row h2 a:hover { text-decoration:underline; }
    .kliping-row p { margin:6px 0; overflow-wrap:anywhere; }
    .kliping-tags { display:flex; gap:6px; flex-wrap:wrap; }
    .kliping-tag { display:inline-block; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:700; background:#e7eef7; color:#315d83; }
    .kliping-tag--prestasi { color:#327052; background:#e8f3ea; }
    .kliping-tag--kemitraan { color:#786019; background:#fff4d6; }
    .kliping-tag--layanan { color:#8a525b; background:#f8eaed; }
    .kliping-tag--arsip { color:#65717b; background:#eceff1; }
    .kliping-preview { display:block; max-width:680px; width:100%; max-height:480px; object-fit:contain; background:#eef2f4; border:1px solid #d3dce1; }
    .kliping-url { overflow-wrap:anywhere; color:#316757; }
    .kliping-source { border:0; padding:0; margin:12px 0 20px; display:flex; flex-wrap:wrap; gap:10px; }
    .kliping-source label { display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #cbd8de; border-radius:4px; background:#fff; cursor:pointer; }
    .kliping-source label:has(input:checked) { background:#edf5ef; border-color:#648773; }
    .kliping-source input { width:18px; height:18px; accent-color:#326647; flex:none; }
    @media(max-width:900px) { .kliping-filter { grid-template-columns:1fr 1fr; } .kliping-row { grid-template-columns:96px minmax(0,1fr); } .kliping-row .agenda-actions { grid-column:2; margin:0; } }
    @media(max-width:600px) { .kliping-filter { grid-template-columns:minmax(0,1fr); } .kliping-filter .span-2 { grid-column:auto; } .kliping-row { grid-template-columns:80px minmax(0,1fr); gap:14px; align-items:start; } .kliping-row .agenda-actions { grid-column:1/-1; } .kliping-source { flex-direction:column; } }
</style>
