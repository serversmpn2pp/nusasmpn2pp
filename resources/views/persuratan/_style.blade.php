<style>
    .surat-stats { grid-template-columns:repeat(5,minmax(0,1fr)) }
    .surat-stats--pribadi { grid-template-columns:repeat(2,minmax(0,1fr)) }
    .surat-stats--tindak { grid-template-columns:repeat(4,minmax(0,1fr)) }
    .surat-stats .stat { display:flex; flex-direction:column; gap:4px }
    .surat-stats .stat-label { min-height:1.5em; overflow-wrap:anywhere }
    .surat-stats .stat-value { margin-top:auto; font-variant-numeric:tabular-nums }
    .surat-muted { color:var(--muted); font-size:.88rem }
    .surat-filter { display:grid; grid-template-columns:minmax(200px,2fr) repeat(4,minmax(100px,1fr)); gap:12px; align-items:end; margin-bottom:24px }
    .surat-filter--pribadi { grid-template-columns:minmax(0,2fr) minmax(160px,1fr) }
    .surat-filter--tindak { grid-template-columns:minmax(0,2fr) repeat(2,minmax(160px,1fr)) }
    .surat-filter > div,.surat-grid > div { min-width:0 }
    .surat-filter-actions,.surat-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; grid-column:1/-1 }
    .surat-filter label,.surat-grid label,.surat-decision label { display:block; color:#3f3f46; margin-bottom:6px; font-size:.9rem; font-weight:800 }
    .surat-filter-actions { padding-top:14px; border-top:1px solid var(--line) }
    .surat-filter-actions label { display:inline-flex; align-items:center; gap:8px; margin:0; font-weight:600 }
    .surat-filter-actions input[type="checkbox"] { width:17px; height:17px; margin:0; accent-color:var(--primary); flex-shrink:0 }
    .surat-filter-count { margin-left:auto }
    .surat-shell { overflow:auto; border:1px solid var(--line); border-radius:8px; background:var(--panel) }
    .surat-table { width:100%; border-collapse:collapse; font-size:.95rem; text-align:left }
    .surat-table th { background:#fafafa; color:#52525b; font-size:.76rem; font-weight:800; text-transform:uppercase; padding:14px 16px }
    .surat-table td { padding:14px 16px; border-top:1px solid var(--line); vertical-align:middle; overflow-wrap:anywhere }
    .surat-table tbody tr:hover { background:#fafafa }
    .surat-table td:first-child { min-width:200px }
    .surat-table small { display:block; margin-top:5px; color:var(--muted); font-size:.88rem; line-height:1.5 }
    .surat-table a:not(.button) { color:var(--primary-dark); font-weight:800; overflow-wrap:anywhere }
    .surat-table th:last-child,.surat-table td:last-child { text-align:right }
    .surat-table .button { width:auto; white-space:normal; min-width:64px }
    .surat-status { display:inline-flex; font-size:.78rem; font-weight:800; line-height:1.2; padding:4px 10px; border-radius:999px; color:#52525b; background:var(--soft) }
    .surat-status--diajukan,.surat-status--dikembalikan,.surat-status--menunggu_pemeriksaan,.surat-status--perlu_perbaikan { color:var(--accent-text); background:var(--accent-soft) }
    .surat-status--disahkan,.surat-status--tercatat,.surat-status--dikirim,.surat-status--selesai,.surat-status--diproses { color:var(--primary-dark); background:var(--primary-soft) }
    .surat-status--dibatalkan { color:var(--danger); background:var(--danger-soft) }
    .surat-late { color:#a23939!important; font-size:12px; display:block; margin-top:5px }
    .surat-details { margin-top:18px; border-top:1px solid var(--line); padding-top:16px }
    .surat-details summary { color:var(--primary-dark); cursor:pointer; font-size:.95rem; font-weight:800 }
    .surat-details[open] summary { margin-bottom:16px }
    .surat-file-list { list-style:none; padding:0; margin:18px 0 }
    .surat-file-list li { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid #e2e8ee }
    .surat-file-list span { min-width:0; flex:1; overflow-wrap:anywhere; font-size:14px }
    .surat-file-list .button { flex-shrink:0; width:auto!important; max-width:100% }
    .surat-file-list small { display:block; margin-top:4px }
    [data-penerima][hidden],[data-tindak-progress][hidden] { display:none!important }
    .surat-empty { text-align:center; padding:48px 20px; color:var(--muted) }
    .surat-empty p { margin:0 }
    .surat-document { overflow:hidden; min-width:0 }
    .surat-document > .surat-notice,.surat-document > .surat-muted { margin:20px 24px }
    .surat-section { padding:24px; border-bottom:1px solid var(--line) }
    .surat-section:last-of-type { border-bottom:0 }
    .surat-section h2 { font-size:1rem; font-weight:800; margin:0 0 20px; color:var(--text) }
    .surat-form-footer { padding:20px 24px; border-top:1px solid var(--line); background:#fafafa }
    .surat-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px 24px }
    .surat-full { grid-column:1/-1 }
    .surat-grid .input,.surat-grid .select,.surat-grid .textarea { width:100%; min-width:0; box-sizing:border-box }
    .surat-choice-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); max-height:280px; overflow-y:auto; border:1px solid #dce4eb; border-radius:4px; background:white; margin-top:10px }
    .surat-choice { display:flex!important; gap:12px; align-items:center; padding:12px; border-bottom:1px solid #edf1f4; margin:0!important; font-weight:400!important; overflow-wrap:anywhere }
    .surat-choice input { flex-shrink:0; width:17px; height:17px; accent-color:var(--primary) }
    .surat-choice[hidden], [data-surat-jenis][hidden] { display:none!important }
    .surat-choice strong { display:block; font-size:14px }
    .surat-choice small { display:block; margin-top:3px; font-size:12px; color:#607083 }
    .surat-detail-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:20px 24px; border-bottom:1px solid var(--line); border-left:4px solid var(--primary); background:var(--primary-soft) }
    .surat-detail-head > div { min-width:0; flex:1 1 240px }
    .surat-detail-head h2 { font-size:1.1rem; margin:0 0 5px; color:var(--primary-dark); overflow-wrap:anywhere }
    .surat-detail-head .surat-muted { display:block; overflow-wrap:anywhere }
    .surat-data { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:22px; margin:0 }
    .surat-data dt { font-size:.88rem; color:var(--muted); margin-bottom:6px }
    .surat-data dd { margin:0; font-size:.95rem; font-weight:800; overflow-wrap:anywhere }
    .surat-text { white-space:pre-wrap; overflow-wrap:anywhere; font-size:14px; line-height:1.7; margin:0 }
    .surat-decision { padding:20px 0; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:14px; border-top:1px solid var(--line); align-items:end }
    .surat-decision:first-of-type { border-top:0; padding-top:0 }
    .surat-decision:last-child { padding-bottom:0 }
    .surat-decision .textarea { width:100%; box-sizing:border-box }
    .surat-decision .surat-choice { grid-column:1/-1; border:0; padding:0 }
    .surat-danger { color:#a23939!important; border-color:#e7c4c4!important; background:white!important }
    .surat-timeline { list-style:none; padding:0; margin:0 }
    .surat-timeline li { padding:12px 0; border-top:1px solid #e2e8ee; line-height:1.6; font-size:14px }
    .surat-timeline p { margin:5px 0 0; white-space:pre-wrap; overflow-wrap:anywhere }
    .surat-notice { margin:16px 0; padding:12px 16px; background:var(--accent-soft); border-left:3px solid var(--accent); color:var(--accent-text); font-size:.92rem; line-height:1.6; overflow-wrap:anywhere }
    .surat-pagination { margin-top:20px }
    .surat-table--berkas td:first-child { min-width:0; overflow-wrap:anywhere; max-width:320px }
    .surat-mobile-meta { display:none!important }
    [data-surat-form] button[disabled] { cursor:wait; opacity:.65 }
    @media(max-width:1100px) { .surat-stats:not(.surat-stats--pribadi) { grid-template-columns:repeat(3,minmax(0,1fr)) } .surat-filter { grid-template-columns:repeat(2,minmax(0,1fr)) } .surat-filter > div:first-child { grid-column:1/-1 } .surat-filter--pribadi > div:first-child { grid-column:auto } .surat-decision { grid-template-columns:minmax(0,1fr) } .surat-decision .button { justify-self:start; white-space:normal } }
    @media(max-width:800px) { .surat-stats:not(.surat-stats--pribadi) { grid-template-columns:repeat(2,minmax(0,1fr)) } .surat-data { grid-template-columns:repeat(2,minmax(0,1fr)) } }
    @media(max-width:520px) { .surat-filter,.surat-grid,.surat-choice-list,.surat-data,.surat-decision { grid-template-columns:minmax(0,1fr) } .surat-stats,.surat-stats:not(.surat-stats--pribadi) { grid-template-columns:minmax(0,1fr) } .surat-filter-count { margin-left:0; width:100% } .surat-filter-actions label { width:100% } .surat-section,.surat-detail-head,.surat-form-footer { padding:20px 16px } .surat-document > .surat-notice,.surat-document > .surat-muted { margin:16px } .surat-actions .button,.surat-decision .button { width:100%; white-space:normal; justify-content:center } .surat-actions form { width:100% } }
    @media(max-width:520px) {
        .surat-table--berkas th:nth-child(2),.surat-table--berkas th:nth-child(3),.surat-table--berkas td:nth-child(2),.surat-table--berkas td:nth-child(3) { display:none }
        .surat-table--berkas { table-layout:fixed }
        .surat-table--berkas th:last-child { width:96px }
        .surat-table--berkas th,.surat-table--berkas td { padding:12px }
        .surat-mobile-meta { display:block!important }
        .surat-table--list thead { display:none }
        .surat-table--list tbody { display:block }
        .surat-table--list tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-bottom:1px solid #dce4eb; padding:8px }
        .surat-table--list tr:last-child { border-bottom:0 }
        .surat-table--list td { border:0; padding:8px; min-width:0!important; overflow-wrap:anywhere; text-align:left }
        .surat-table--list td:first-child,.surat-table--list td:last-child { grid-column:1/-1 }
        .surat-table--list td[data-label]::before { content:attr(data-label); display:block; font-size:12px; color:#607083; margin-bottom:6px }
        .surat-table--list td:last-child .button { width:100%; justify-content:center }
        .surat-table--list .surat-status { white-space:normal }
    }
</style>
