@include('publikasi-humas._style')
<style>
    .ak-page { max-width:1200px; margin:0 auto; }
    .ak-page h1 { font-size:28px; line-height:1.35; overflow-wrap:anywhere; }
    .ak-page h2 { font-size:18px; margin:0 0 16px; }
    .ak-page h3 { font-size:16px; margin:0 0 12px; }
    .ak-page p { overflow-wrap:anywhere; }
    .ak-head,.ak-toolbar { display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
    .ak-head { margin-bottom:24px; align-items:flex-start; }
    .ak-head > div { min-width:0; flex:1 1 340px; }
    .ak-head .actions { flex:0 1 auto; }
    .ak-section { padding:24px 0; border-top:1px solid var(--line); }
    .ak-filter { display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:12px; align-items:end; }
    .ak-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .ak-grid > *,.ak-filter > * { min-width:0; }
    .ak-wide { grid-column:1/-1; }
    .ak-page textarea { min-height:96px; resize:vertical; }
    .ak-facts { display:flex; gap:18px 32px; flex-wrap:wrap; margin:16px 0; }
    .ak-facts > div { min-width:0; max-width:100%; }
    .ak-facts dt { font-size:13px; color:var(--muted); margin-bottom:6px; }
    .ak-facts dd { margin:0; font-weight:700; overflow-wrap:anywhere; }
    .ak-badge { display:inline-block; padding:5px 9px; font-size:12px; font-weight:700; border-radius:4px; background:#edf0f3; color:#56616d; }
    .ak-badge--siap,.ak-badge--terpenuhi { background:#e6f3ed; color:#226a4e; }
    .ak-badge--draf,.ak-badge--belum_diperiksa { background:#e8eff8; color:#245981; }
    .ak-badge--perlu_perbaikan { background:#fff3dc; color:#815807; }
    .ak-note { border-left:3px solid #268368; padding:12px 16px; background:#f0f8f5; margin:16px 0; font-size:14px; }
    .ak-note--warning { border-color:#b98618; background:#fff9ed; color:#72540b; }
    .ak-row { padding:22px 0; border-bottom:1px solid var(--line); display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:center; }
    .ak-row > * { min-width:0; overflow-wrap:anywhere; }
    .ak-row h2 { font-size:17px; margin:8px 0; }
    .ak-row p { margin:8px 0; font-size:14px; }
    .ak-row a:not(.button) { color:var(--primary-dark); text-decoration:none; }
    .ak-row a:not(.button):hover { text-decoration:underline; }
    .ak-progress { width:100%; height:8px; accent-color:#268368; display:block; margin:12px 0; }
    .ak-disclosure { border-top:1px solid var(--line); padding:18px 0; }
    .ak-disclosure > summary { cursor:pointer; font-weight:700; color:var(--primary-dark); }
    .ak-disclosure[open] > summary { margin-bottom:20px; }
    .ak-doc { display:flex; gap:12px; padding:16px 12px; border-bottom:1px solid var(--line); align-items:flex-start; background:#fff; cursor:pointer; }
    .ak-doc:has(input:checked) { background:#eef6f3; box-shadow:inset 3px 0 #268368; }
    .ak-doc input { width:18px; height:18px; flex:0 0 18px; margin-top:2px; accent-color:#268368; }
    .ak-doc span { min-width:0; overflow-wrap:anywhere; }
    .ak-doc small { display:block; color:var(--muted); margin-top:7px; }
    .ak-review { background:#f5f8fa; padding:18px 0; border-block:1px solid var(--line); margin:18px 0; }
    .ak-review-form { display:grid; grid-template-columns:240px minmax(0,1fr); gap:16px; }
    .ak-review-form .actions { grid-column:1/-1; }
    .ak-history { list-style:none; padding:0; margin:0; }
    .ak-history li { padding:14px 0; border-bottom:1px solid var(--line); overflow-wrap:anywhere; }
    .ak-history p { margin:6px 0; font-size:13px; }
    .ak-page .button { white-space:normal; text-align:center; }
    .ak-page .input,.ak-page .select { width:100%; min-width:0; }
    .ak-page .field label { display:block; margin-bottom:7px; }
    .ak-page .actions form { margin:0; }
    .ak-danger { color:#a23d3d; }
    @media(max-width:1000px) { .ak-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:600px) {
        .ak-grid,.ak-filter,.ak-review-form,.ak-row { grid-template-columns:minmax(0,1fr); }
        .ak-head > div { flex-basis:100%; }
        .ak-page h1 { font-size:24px; }
        .ak-facts { gap:16px; }
        .ak-row { gap:10px; }
        .ak-row .button { justify-self:start; }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ak-page form[data-evidence]').forEach(form => {
        const update = () => { form.querySelector('[data-evidence-submit]').disabled = !form.querySelector('input[type="radio"]:checked:not(:disabled)'); };
        form.querySelectorAll('input[type="radio"]').forEach(input => input.addEventListener('change', update));
        update();
    });
    document.querySelectorAll('.ak-page form[data-save]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
            form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.textContent = 'Menyimpan...'; });
            form.setAttribute('aria-busy', 'true');
        });
    });
});
</script>
