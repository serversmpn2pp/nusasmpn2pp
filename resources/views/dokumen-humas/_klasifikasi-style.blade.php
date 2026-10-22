<style>
    .humas-summary { grid-template-columns:repeat(4,minmax(0,1fr)); }
    .humas-filter { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .humas-filter .actions { align-self:end; }
    .dh-classification { background:#edf4fc; padding:18px; border-radius:6px; margin-bottom:20px; }
    .dh-classification h2 { margin:0 0 16px; font-size:18px; color:#15477a; }
    .dh-classification .field { min-width:0; }
    .dh-classification small { display:block; margin-top:6px; color:#627486; }
    .dh-type { display:block; margin:5px 0; color:#29465b; font-size:14px; }
    .dh-bulk { padding:18px; margin:0 0 18px; background:#fff8dc; border:1px solid #e6d48c; border-radius:6px; }
    .dh-bulk[hidden] { display:none; }
    .dh-bulk header { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .dh-bulk h2 { font-size:17px; color:#735700; margin:0; }
    .dh-bulk-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
    .dh-bulk-grid .dh-bulk-reason { grid-column:1/-1; }
    .dh-bulk .actions { margin-top:14px; justify-content:flex-end; }
    .dh-check { width:18px; height:18px; accent-color:#0f766e; cursor:pointer; }
    .dh-button { display:inline-flex; align-items:center; gap:8px; }
    .dh-button svg { width:18px; height:18px; flex-shrink:0; }
    .dh-relations { padding:18px 0; border-top:1px solid #dce5eb; }
    .dh-relations h2 { margin:0 0 14px; font-size:18px; color:#15477a; }
    .dh-relations ul { list-style:none; margin:0; padding:0; }
    .dh-relations li { display:flex; gap:12px; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #e5ebef; }
    .dh-relations li > div { min-width:0; overflow-wrap:anywhere; }
    .dh-relations li small { display:block; color:#627486; margin-bottom:4px; }
    .dh-link-form { margin-top:18px; padding:18px; background:#eaf6f3; border-radius:6px; }
    .dh-link-form .field { min-width:0; }
    .dh-link-form form + form { border-top:1px solid #bfd9d3; margin-top:16px; padding-top:16px; }
    .dh-link-form .actions { margin-top:12px; }
    .dh-link-form .input,.dh-link-form .select { width:100%; min-width:0; }
    .dh-unlink { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .dh-unlink input { width:180px; max-width:100%; }
    .dh-audit-list { padding:0; margin:0; list-style:none; }
    .dh-audit-list li { padding:12px 0; border-bottom:1px solid #e5ebef; overflow-wrap:anywhere; }
    .dh-audit-list small { color:#627486; display:block; margin-top:4px; }
    .humas-table td { overflow-wrap:anywhere; }
    @media(max-width:700px) {
        .humas-table thead { display:none; }
        .humas-table tr { display:flex; flex-direction:column; padding:16px 0; border-bottom:1px solid #dce5eb; }
        .humas-table td { padding:7px 0; border:0; }
        .humas-table td[data-label]::before { content:attr(data-label); display:block; color:#627486; font-size:13px; margin-bottom:5px; }
        .humas-row-actions { justify-content:flex-start; }
    }
    @media(max-width:1000px) { .humas-filter,.humas-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } .dh-relations li { align-items:flex-start; flex-direction:column; } }
    @media(max-width:560px) { .humas-filter,.dh-bulk-grid { grid-template-columns:minmax(0,1fr); } .dh-bulk { padding:14px; } .dh-unlink input { width:100%; } .dh-unlink { width:100%; } }
</style>
