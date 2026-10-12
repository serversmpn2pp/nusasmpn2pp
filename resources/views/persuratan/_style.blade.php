<style>
    .surat-summary { display:flex; flex-wrap:wrap; gap:24px 48px; padding:16px 0 22px; border-bottom:1px solid #dce4eb; margin-bottom:20px }
    .surat-summary span,.surat-muted { color:#607083; font-size:13px }
    .surat-summary strong { display:block; font-size:23px; color:#172c40; margin-top:5px }
    .surat-filter { display:grid; grid-template-columns:minmax(200px,2fr) repeat(4,minmax(100px,1fr)); gap:14px; align-items:end; padding:0 0 20px }
    .surat-filter-actions,.surat-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; grid-column:1/-1 }
    .surat-filter label,.surat-grid label { display:block; margin-bottom:6px; font-size:13px; font-weight:700 }
    .surat-shell { overflow:auto; border:1px solid #dce4eb; border-radius:6px; background:white }
    .surat-table { width:100%; border-collapse:collapse; font-size:14px; text-align:left }
    .surat-table th { background:#edf3f6; color:#415266; font-size:12px; padding:13px 16px; white-space:nowrap }
    .surat-table td { padding:15px 16px; border-top:1px solid #e2e8ee; vertical-align:top }
    .surat-table tbody tr:hover { background:#f5f8fa }
    .surat-table td:first-child { min-width:200px }
    .surat-table small { display:block; margin-top:5px; color:#607083; line-height:1.5 }
    .surat-table a:not(.button) { color:#164c75; font-weight:700; overflow-wrap:anywhere }
    .surat-table .surat-status { white-space:nowrap }
    .surat-status { display:inline-flex; font-size:12px; font-weight:700; line-height:1.5; padding:3px 9px; border-radius:4px; color:#4b5c6d; background:#eef1f4 }
    .surat-status--diajukan,.surat-status--dikembalikan { color:#865000; background:#fff2cf }
    .surat-status--disahkan,.surat-status--tercatat { color:#126347; background:#e7f5ec }
    .surat-status--dikirim { color:#16567e; background:#e6f1f8 }
    .surat-status--dibatalkan { color:#9c3535; background:#fceeee }
    .surat-status--menunggu_pemeriksaan,.surat-status--perlu_perbaikan { color:#865000; background:#fff2cf }
    .surat-status--selesai { color:#126347; background:#e7f5ec }
    .surat-status--diproses { color:#16567e; background:#e6f1f8 }
    .surat-late { color:#a23939!important; font-size:12px; display:block; margin-top:5px }
    .surat-details { margin-top:18px; border-top:1px solid #e2e8ee; padding-top:16px }
    .surat-details summary { color:#164c75; cursor:pointer; font-size:14px; font-weight:600; margin-bottom:16px }
    .surat-file-list { list-style:none; padding:0; margin:18px 0 }
    .surat-file-list li { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid #e2e8ee }
    .surat-file-list span { min-width:0; flex:1; overflow-wrap:anywhere; font-size:14px }
    .surat-file-list .button { flex-shrink:0; width:auto!important; max-width:100% }
    .surat-file-list small { display:block; margin-top:4px }
    [data-penerima][hidden],[data-tindak-progress][hidden] { display:none!important }
    .surat-empty { text-align:center; padding:48px 20px; color:#607083 }
    .surat-section { padding:24px 0; border-bottom:1px solid #dce4eb }
    .surat-section:first-of-type { padding-top:10px }
    .surat-section h2 { font-size:17px; margin:0 0 20px; color:#243d50 }
    .surat-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px 24px }
    .surat-full { grid-column:1/-1 }
    .surat-grid .input,.surat-grid .select,.surat-grid .textarea { width:100%; min-width:0; box-sizing:border-box }
    .surat-choice-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); max-height:280px; overflow-y:auto; border:1px solid #dce4eb; border-radius:4px; background:white; margin-top:10px }
    .surat-choice { display:flex!important; gap:12px; align-items:center; padding:12px; border-bottom:1px solid #edf1f4; margin:0!important; font-weight:400!important; overflow-wrap:anywhere }
    .surat-choice input { flex-shrink:0; width:17px; height:17px; accent-color:#176459 }
    .surat-choice[hidden], [data-surat-jenis][hidden] { display:none!important }
    .surat-choice strong { display:block; font-size:14px }
    .surat-choice small { display:block; margin-top:3px; font-size:12px; color:#607083 }
    .surat-detail-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:18px 20px; border:1px solid #dce4eb; border-left:4px solid #176459; border-radius:6px; background:white; margin-bottom:20px }
    .surat-detail-head h2 { font-size:18px; margin:0 0 5px; overflow-wrap:anywhere }
    .surat-data { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:22px; margin:0 }
    .surat-data dt { font-size:13px; color:#607083; margin-bottom:6px }
    .surat-data dd { margin:0; font-size:14px; font-weight:600; overflow-wrap:anywhere }
    .surat-text { white-space:pre-wrap; overflow-wrap:anywhere; font-size:14px; line-height:1.7; margin:0 }
    .surat-decision { padding:20px 0; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:14px; border-top:1px solid #dce4eb; align-items:end }
    .surat-decision .textarea { width:100%; box-sizing:border-box }
    .surat-decision .surat-choice { grid-column:1/-1; border:0; padding:0 }
    .surat-danger { color:#a23939!important; border-color:#e7c4c4!important; background:white!important }
    .surat-timeline { list-style:none; padding:0; margin:0 }
    .surat-timeline li { padding:12px 0; border-top:1px solid #e2e8ee; line-height:1.6; font-size:14px }
    .surat-timeline p { margin:5px 0 0; white-space:pre-wrap; overflow-wrap:anywhere }
    .surat-notice { margin:16px 0; padding:12px 16px; background:#fff8e6; border-left:3px solid #d1a43e; font-size:14px; line-height:1.6 }
    .surat-pagination { margin-top:20px }
    .surat-table--berkas td:first-child { min-width:0; overflow-wrap:anywhere; max-width:320px }
    .surat-mobile-meta { display:none!important }
    [data-surat-form] button[disabled] { cursor:wait; opacity:.65 }
    @media(max-width:800px) { .surat-filter { grid-template-columns:repeat(2,minmax(0,1fr)) } .surat-data { grid-template-columns:repeat(2,minmax(0,1fr)) } }
    @media(max-width:520px) { .surat-filter,.surat-grid,.surat-choice-list,.surat-data,.surat-decision { grid-template-columns:minmax(0,1fr) } .surat-summary { gap:16px 24px } .surat-summary strong { font-size:20px } .surat-decision .button { width:100%; justify-content:center } }
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
        .surat-table--list td { border:0; padding:8px; min-width:0!important; overflow-wrap:anywhere }
        .surat-table--list td:first-child,.surat-table--list td:last-child { grid-column:1/-1 }
        .surat-table--list td[data-label]::before { content:attr(data-label); display:block; font-size:12px; color:#607083; margin-bottom:6px }
        .surat-table--list td:last-child .button { width:100%; justify-content:center }
        .surat-table--list .surat-status { white-space:normal }
    }
</style>
