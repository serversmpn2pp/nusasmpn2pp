@include('publikasi-humas._style')
<style>
    .uf-page { max-width:1200px; margin:0 auto; }
    .uf-page h1 { font-size:28px; line-height:1.35; overflow-wrap:anywhere; }
    .uf-page h2 { font-size:18px; margin:0 0 16px; }
    .uf-page h3 { font-size:16px; line-height:1.5; margin:0 0 12px; overflow-wrap:anywhere; }
    .uf-page p,.uf-page dd,.uf-page li { overflow-wrap:anywhere; }
    .uf-head,.uf-toolbar { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .uf-head { margin-bottom:24px; }
    .uf-head > div { min-width:0; flex:1 1 330px; }
    .uf-head .actions { flex:0 1 auto; }
    .uf-section { border-top:1px solid var(--line); padding:24px 0; }
    .uf-filter { display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:12px; align-items:end; }
    .uf-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .uf-wide { grid-column:1/-1; }
    .uf-grid > *,.uf-filter > * { min-width:0; }
    .uf-page .input,.uf-page .select { min-width:0; width:100%; }
    .uf-page textarea { min-height:100px; resize:vertical; }
    .uf-page .field label { display:block; margin-bottom:7px; }
    .uf-page .button { white-space:normal; text-align:center; }
    .uf-badge { display:inline-block; padding:5px 9px; font-size:12px; border-radius:4px; background:#eef0f3; color:#56616d; font-weight:700; }
    .uf-badge--aktif { background:#e6f3ee; color:#23684e; }
    .uf-badge--draf { background:#e8f0f9; color:#28597d; }
    .uf-badge--diproses { background:#fff3dc; color:#7e5708; }
    .uf-badge--selesai { background:#e6f3ee; color:#23684e; }
    .uf-row { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:18px; padding:22px 0; border-bottom:1px solid var(--line); }
    .uf-row > * { min-width:0; overflow-wrap:anywhere; }
    .uf-row h2 { font-size:17px; margin:8px 0; }
    .uf-row p { font-size:14px; margin:8px 0; }
    .uf-facts { display:flex; gap:18px 32px; flex-wrap:wrap; margin:18px 0 24px; }
    .uf-facts dt { font-size:13px; color:var(--muted); margin-bottom:6px; }
    .uf-facts dd { margin:0; font-weight:700; }
    .uf-facts > div { max-width:100%; }
    .uf-note { margin:16px 0; padding:12px 16px; border-left:3px solid #2e8068; background:#eef8f3; font-size:14px; line-height:1.6; }
    .uf-note--warning { border-color:#bd8c20; background:#fff8e9; color:#76540b; }
    .uf-question { border:0; border-top:1px solid var(--line); margin:20px 0 0; padding:20px 0; min-width:0; }
    .uf-question legend { font-weight:700; font-size:16px; max-width:100%; overflow-wrap:anywhere; padding:0 12px 0 0; }
    .uf-question-tools { display:flex; gap:8px; align-items:center; justify-content:flex-end; margin-bottom:12px; }
    .uf-tool { width:36px; height:36px; flex:0 0 36px; background:#fff; border:1px solid var(--line); border-radius:4px; color:var(--primary-dark); cursor:pointer; font-size:20px; }
    .uf-tool:disabled { opacity:.4; cursor:default; }
    .uf-check { display:flex; gap:8px; align-items:center; font-size:14px; }
    .uf-check input { width:18px; height:18px; accent-color:var(--primary); }
    .uf-classes { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; }
    .uf-results { padding:22px 0; border-bottom:1px solid var(--line); }
    .uf-stats { display:flex; gap:12px 32px; flex-wrap:wrap; padding:12px 0; }
    .uf-stats strong { font-size:20px; display:block; }
    .uf-stats span { color:var(--muted); font-size:13px; }
    .uf-bars { display:grid; gap:10px; margin:14px 0; }
    .uf-bar { display:grid; grid-template-columns:160px minmax(0,1fr) 36px; gap:12px; align-items:center; font-size:13px; }
    .uf-bar progress { width:100%; height:10px; accent-color:#2c7e66; }
    .uf-bar:nth-child(-n+2) progress { accent-color:#b37c15; }
    .uf-comments { padding-left:24px; }
    .uf-comments li { padding:12px 0; border-bottom:1px solid var(--line); white-space:pre-wrap; }
    .uf-disclosure { padding:18px 0; border-top:1px solid var(--line); }
    .uf-disclosure > summary { cursor:pointer; color:var(--primary-dark); font-weight:700; }
    .uf-disclosure[open] > summary { margin-bottom:20px; }
    .uf-history { list-style:none; padding:0; }
    .uf-history li { padding:14px 0; border-bottom:1px solid var(--line); }
    .uf-history p { font-size:13px; margin:6px 0; }
    .uf-options { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
    .uf-option { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid var(--line); border-radius:4px; padding:14px 10px; font-size:14px; cursor:pointer; min-width:0; }
    .uf-option span { overflow-wrap:anywhere; }
    .uf-option input { flex:0 0 18px; width:18px; height:18px; accent-color:#2e8068; }
    .uf-option:has(input:checked) { background:#ecf7f0; border-color:#2e8068; }
    .uf-parent { max-width:960px; }
    .uf-page [hidden] { display:none !important; }
    @media(max-width:1000px) { .uf-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } .uf-options { grid-template-columns:repeat(2,minmax(0,1fr)); } .uf-option:last-child { grid-column:1/-1; } }
    @media(max-width:600px) {
        .uf-grid,.uf-filter,.uf-row { grid-template-columns:minmax(0,1fr); }
        .uf-head > div { flex-basis:100%; } .uf-page h1 { font-size:24px; }
        .uf-row .button { justify-self:start; } .uf-bar { grid-template-columns:120px minmax(0,1fr) 28px; gap:8px; }
        .uf-option { padding:12px 8px; font-size:13px; }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.uf-page form[data-save]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.textContent = 'Memproses...'; });
        form.setAttribute('aria-busy','true');
    }));
});
</script>
