<style>
    .question-preview-dialog { width: min(860px, calc(100% - 28px)); max-height: calc(100vh - 32px); border: 0; border-radius: 8px; box-shadow: 0 24px 70px rgba(15, 53, 92, .25); padding: 0; }
    .question-preview-dialog::backdrop { background: rgba(15, 35, 55, .58); }
    .question-preview-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--line); padding: 15px 18px; }
    .question-preview-head h2 { margin: 0; font-size: 1rem; }
    .question-preview-body { max-height: calc(100vh - 78px); overflow-y: auto; background: #f6f8fb; padding: 18px; }
    .question-preview-exam { min-height: 410px; padding: 22px; scroll-margin-top: 96px; }
    .question-preview-exam .question-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .question-preview-exam .question-number { display: inline-flex; min-width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 8px; background: var(--primary); color: #fff; font-weight: 950; }
    .question-preview-exam .badge { min-height: 30px; align-items: center; padding: 6px 10px; font-size: .78rem; font-weight: 900; white-space: nowrap; }
    .question-preview-exam .question-title { margin: 0; font-size: 1.05rem; line-height: 1.45; white-space: pre-line; }
    .question-preview-exam .stimulus { margin: 12px 0 16px; border-left: 4px solid var(--accent); border-radius: 8px; background: #fffaf0; padding: 12px 14px; color: #334155; white-space: pre-line; }
    .question-preview-exam .option-list { display: grid; gap: 10px; margin-top: 14px; }
    .question-preview-exam .option-card { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: start; border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 12px; cursor: pointer; }
    .question-preview-exam .option-card:hover { border-color: rgba(21, 71, 122, .45); background: #fbfdff; }
    .question-preview-exam .option-card input { width: 19px; height: 19px; margin-top: 2px; accent-color: var(--primary); }
    .question-preview-exam .option-card-content { min-width: 0; }
    .question-preview-exam .option-code { display: inline-flex; min-width: 28px; height: 28px; align-items: center; justify-content: center; border-radius: 8px; background: var(--primary-soft); color: var(--primary-dark); font-weight: 950; }
    .question-preview-exam .option-text { color: #344054; font-weight: 760; white-space: pre-line; }
    .question-preview-exam .statement-row, .question-preview-exam .matching-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
    .question-preview-exam .statement-options { display: flex; flex-wrap: wrap; gap: 8px; }
    .question-preview-exam .pill-option { display: inline-flex; align-items: center; gap: 7px; border: 1px solid var(--line); border-radius: 999px; padding: 7px 10px; font-size: .88rem; font-weight: 900; }
    .question-preview-exam .pill-option input { accent-color: var(--primary); }
    .question-preview-exam .matching-answer-bank { margin-top: 14px; border: 1px solid #b9cde2; border-radius: 8px; background: var(--primary-soft); padding: 12px; }
    .question-preview-exam .matching-answer-bank > strong { display: block; margin-bottom: 9px; color: var(--primary-dark); }
    .question-preview-exam .matching-answer-bank > div { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .question-preview-exam .matching-answer-option { display: grid; grid-template-columns: 26px minmax(0, 1fr); gap: 7px; align-items: start; border: 1px solid rgba(21, 71, 122, .14); border-radius: 7px; background: #fff; padding: 8px 9px; color: #344054; font-size: .88rem; font-weight: 700; }
    .question-preview-exam .matching-answer-bank b { color: var(--primary-dark); }
    .question-preview-exam .matching-select { min-width: 250px; }
    .question-preview-exam .check-row { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: .9rem; font-weight: 900; }
    .question-preview-exam .check-row input { width: 18px; height: 18px; accent-color: var(--accent); }
    .question-preview-exam .field { display: grid; gap: 7px; margin-top: 14px; }
    .question-preview-exam .field label { margin: 0; color: #344054; font-size: .9rem; font-weight: 900; }
    .question-preview-exam .input, .question-preview-exam .textarea, .question-preview-exam .select { width: 100%; border: 1px solid #cfd8e3; border-radius: 8px; background: #fff; color: var(--text); outline: none; }
    .question-preview-exam .input, .question-preview-exam .select { min-height: 46px; padding: 10px 12px; }
    .question-preview-exam .textarea { min-height: 116px; resize: vertical; padding: 11px 12px; }
    .question-preview-exam .file-answer-box { display: grid; gap: 12px; margin-top: 14px; border: 1px solid #b9cde2; border-radius: 8px; background: var(--primary-soft); padding: 16px; }
    .question-preview-exam .file-answer-status { display: grid; gap: 3px; min-width: 0; }
    .question-preview-exam .file-answer-status span { color: var(--muted); font-size: .82rem; font-weight: 750; }
    .question-preview-exam .file-answer-action { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .question-preview-exam .question-media-content { display: grid; gap: 16px; margin: 16px 0; }
    .question-preview-exam .question-media-content figure { margin: 0; }
    .question-preview-exam .question-media-figure { text-align: center; }
    .question-preview-exam .question-media-figure img { display: block; width: auto; max-width: 100%; max-height: 430px; margin: 0 auto; border: 1px solid #dfe7f0; border-radius: 7px; object-fit: contain; }
    .question-preview-exam .question-media-content figcaption { margin-top: 7px; color: #71717a; font-size: .78rem; text-align: center; }
    .question-preview-exam .question-media-table-wrap > figcaption { margin: 0 0 7px; color: #18181b; font-size: .84rem; font-weight: 800; text-align: left; }
    .question-preview-exam .question-media-table-scroll { overflow-x: auto; }
    .question-preview-exam .question-media-table { width: 100%; min-width: 420px; border-collapse: collapse; }
    .question-preview-exam .question-media-table th, .question-preview-exam .question-media-table td { border: 1px solid #dfe7f0; padding: 9px 10px; text-align: left; vertical-align: top; }
    .question-preview-exam .question-media-table th { background: var(--primary-soft); color: var(--primary-dark); font-weight: 800; }
    .question-preview-exam .question-media-formula { overflow-x: auto; border: 1px solid #dfe7f0; border-radius: 7px; background: #fff; padding: 14px; text-align: center; }
    .question-preview-exam .question-media-formula [data-rumus-latex] { min-width: max-content; font-size: 1.08rem; }
    .question-preview-exam .question-media-content.is-compact { gap: 9px; margin: 9px 0 2px; }
    .question-preview-exam .question-media-content.is-compact .question-media-figure { text-align: left; }
    .question-preview-exam .question-media-content.is-compact .question-media-figure img { max-height: 230px; margin-left: 0; }
    .question-preview-exam .question-media-content.is-compact .question-media-table { min-width: 320px; }
    .question-preview-exam .question-media-content.is-compact .question-media-table th, .question-preview-exam .question-media-content.is-compact .question-media-table td { padding: 7px 8px; }
    .question-preview-exam .question-media-content.is-compact .question-media-formula { padding: 10px; text-align: left; }
    .question-preview-exam .question-media-content.is-compact figcaption { text-align: left; }
    .question-preview-footer { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:12px; padding:14px 18px; border-top:1px solid var(--line); }
    .question-preview-body { max-height:calc(100vh - 210px); }
    @media(max-width:680px) {
        .question-preview-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; }
        .question-preview-head .button { width: auto; min-width: 74px; }
        .question-preview-exam { min-height: 0; padding: 17px; }
        .question-preview-exam .statement-row, .question-preview-exam .matching-row { grid-template-columns: 1fr; }
        .question-preview-exam .matching-answer-bank > div { grid-template-columns: 1fr; }
        .question-preview-exam .matching-select { min-width: 0; }
    }
</style>
