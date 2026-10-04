@include('agenda-humas._style')
<style>
    .bp-page { max-width:1200px; margin:0 auto; }
    .bp-page h1 { font-size:28px; line-height:1.35; }
    .bp-page h2 { font-size:18px; margin:0 0 14px; }
    .bp-page h3 { font-size:16px; margin:0 0 12px; }
    .bp-page h1,.bp-page p,.bp-page label,.bp-page li { overflow-wrap:anywhere; }
    .bp-section { border-top:1px solid var(--line); padding:24px 0; }
    .bp-head { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px; }
    .bp-head > div { min-width:0; flex:1 1 350px; }
    .bp-head .button { flex:0 1 auto; }
    .bp-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:28px; }
    .bp-grid > * { min-width:0; }
    .bp-ready { margin:0; padding:0; list-style:none; }
    .bp-ready li { display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid var(--line); }
    .bp-mark { width:24px; height:24px; flex:0 0 24px; border-radius:4px; background:#e8f5ed; color:#276846; text-align:center; font-weight:700; }
    .bp-mark--wait { background:#fff2d8; color:#815914; }
    .bp-ready strong { display:block; font-size:14px; }
    .bp-ready small { display:block; color:var(--muted); font-size:13px; margin-top:4px; }
    .bp-check { display:flex; align-items:flex-start; gap:12px; padding:15px 0; border-bottom:1px solid var(--line); cursor:pointer; }
    .bp-check > input { width:18px; height:18px; flex:0 0 18px; margin-top:2px; accent-color:var(--primary); }
    .bp-check > span { min-width:0; }
    .bp-check strong { font-size:14px; display:block; line-height:1.5; }
    .bp-check small { display:block; font-size:13px; color:var(--muted); margin-top:5px; }
    .bp-check--all { font-weight:700; font-size:14px; padding:0 0 16px; }
    .bp-document-list { max-height:460px; overflow:auto; scrollbar-gutter:stable; }
    .bp-note { font-size:14px; line-height:1.6; margin:16px 0; border-left:3px solid #bd8a20; padding:10px 14px; background:#fff8e9; }
    .bp-note--ok { background:#eef8f2; border-color:#2c8066; }
    .bp-submit { padding:20px 0; display:flex; flex-wrap:wrap; align-items:center; gap:12px; border-top:1px solid var(--line); }
    .bp-submit .button { white-space:normal; text-align:center; }
    .bp-processing { display:flex; align-items:center; gap:10px; font-size:14px; }
    .bp-spinner { width:18px; height:18px; flex:0 0 18px; border:2px solid #c4d7de; border-top-color:#2c8066; border-radius:50%; animation:bp-spin 1s linear infinite; }
    @keyframes bp-spin { to { transform:rotate(360deg); } }
    .bp-status { padding:12px 14px; font-size:14px; border-left:3px solid #2c8066; background:#eef8f2; }
    .bp-status--error { border-color:#ae3838; background:#fff0f0; }
    .bp-history { padding:0; list-style:none; }
    .bp-history li { padding:14px 0; border-bottom:1px solid var(--line); }
    .bp-page [hidden] { display:none !important; }
    @media(max-width:760px) { .bp-grid { grid-template-columns:minmax(0,1fr); gap:24px; } .bp-head > div { flex-basis:100%; } .bp-page h1 { font-size:24px; } }
    @media(prefers-reduced-motion:reduce) { .bp-spinner { animation:none; } }
</style>
