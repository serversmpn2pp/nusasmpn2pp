@include('publikasi-humas._style')
<style>
    .aset-filter { display:grid; grid-template-columns:2fr repeat(3,minmax(0,1fr)) auto; gap:12px; align-items:end; }
    .aset-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; padding:22px 0; }
    .aset-item { min-width:0; background:#fff; border:1px solid #d5dfe3; border-radius:6px; overflow:hidden; display:flex; flex-direction:column; }
    .aset-cover { width:100%; aspect-ratio:16/10; object-fit:contain; background:#f2f5f6; display:block; border-bottom:1px solid #d5dfe3; }
    .aset-placeholder { display:flex; align-items:center; justify-content:center; color:#507369; font-size:20px; font-weight:700; }
    .aset-body { padding:16px; flex:1; }
    .aset-body h2 { font-size:16px; margin:10px 0; overflow-wrap:anywhere; }
    .aset-body p { margin:8px 0; overflow-wrap:anywhere; }
    .aset-body .agenda-actions { margin-top:16px; }
    .aset-tags { display:flex; flex-wrap:wrap; gap:6px; font-size:12px; }
    .aset-tags span { background:#eaf3ee; color:#315e48; border-radius:4px; padding:4px 8px; }
    .aset-tags .arsip { background:#fff3d9; color:#885b14; }
    .aset-source { display:flex; flex-wrap:wrap; gap:12px; padding:0; border:0; margin:14px 0 20px; }
    .aset-source label { display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #cbd8de; border-radius:4px; background:#fff; cursor:pointer; }
    .aset-source label:has(input:checked) { background:#edf5ef; border-color:#648773; }
    .aset-source input,.aset-pick input { width:18px; height:18px; flex:none; accent-color:#326647; }
    .aset-preview { max-width:640px; width:100%; max-height:420px; object-fit:contain; background:#f2f5f6; border:1px solid #d5dfe3; }
    .aset-picks { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:16px 0; max-height:420px; overflow:auto; padding:2px; }
    .aset-pick { padding:12px; border:1px solid #d5dfe3; background:#fff; border-radius:4px; min-width:0; }
    .aset-pick label { display:flex; gap:9px; align-items:start; overflow-wrap:anywhere; font-size:13px; }
    .aset-pick p { margin:8px 0 0 27px; font-size:12px; color:#61727c; }
    .aset-pick:has(input:checked) { border-color:#648773; background:#f4f9f5; }
    @media(max-width:1000px) { .aset-filter { grid-template-columns:1fr 1fr; } .aset-grid { grid-template-columns:1fr 1fr; } }
    @media(max-width:600px) { .aset-filter,.aset-grid,.aset-picks { grid-template-columns:minmax(0,1fr); } .aset-source { flex-direction:column; } }
</style>
