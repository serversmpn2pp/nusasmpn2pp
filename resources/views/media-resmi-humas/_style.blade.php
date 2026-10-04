@include('publikasi-humas._style')
<style>
    .media-filter { display:grid; grid-template-columns:2fr repeat(3,minmax(0,1fr)) auto; gap:12px; align-items:end; }
    .media-list { margin:0; padding:0; list-style:none; }
    .media-row { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1.2fr) minmax(0,.8fr) auto; gap:20px; align-items:center; padding:22px 0; border-bottom:1px solid var(--line); }
    .media-row > * { min-width:0; }
    .media-row h2 { margin:10px 0 6px; font-size:16px; overflow-wrap:anywhere; }
    .media-row h2 a { text-decoration:none; color:var(--text); }
    .media-row h2 a:hover { text-decoration:underline; }
    .media-url { display:block; color:#316757; font-size:13px; overflow-wrap:anywhere; }
    .media-type { display:inline-block; padding:4px 8px; border-radius:4px; background:#e6eef7; color:#245984; font-size:12px; font-weight:700; }
    .media-type--instagram { background:#faeeee; color:#984d58; }
    .media-type--youtube { background:#f9e8e5; color:#a24131; }
    .media-type--tiktok { background:#eaf3f1; color:#34675b; }
    .media-type--lainnya { background:#eeedf1; color:#645b72; }
    .media-status { display:inline-block; padding:5px 9px; border-radius:4px; font-size:12px; font-weight:700; color:#276447; background:#e8f3eb; }
    .media-status--nonaktif { background:#fff4d9; color:#876217; }
    .media-status--arsip { background:#eceff1; color:#5e6b76; }
    .media-pic strong { display:block; overflow-wrap:anywhere; font-size:14px; }
    .media-pic p,.media-row p { margin:6px 0; overflow-wrap:anywhere; }
    .media-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .media-page [data-publikasi-submit][aria-busy="true"] { opacity:.7; }
    @media(max-width:1000px) { .media-filter { grid-template-columns:1fr 1fr; } .media-row { grid-template-columns:2fr 1fr; gap:14px; } }
    @media(max-width:600px) { .media-filter,.media-row { grid-template-columns:minmax(0,1fr); } .media-row { gap:12px; } .media-actions .button { flex:1; } }
</style>
