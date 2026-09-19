import katex from 'katex';
import './inline-math.css';

// Store text and formula delimiters, never HTML supplied by the editor.
export const mathParts = (text) => {
    const pattern = /\\\(([\s\S]*?)\\\)|\\\[([\s\S]*?)\\\]/g;
    const parts = [];
    let start = 0;
    for (const match of text.matchAll(pattern)) {
        parts.push({ text: text.slice(start, match.index) });
        parts.push({ latex: match[1] ?? match[2], display: match[2] !== undefined });
        start = match.index + match[0].length;
    }
    parts.push({ text: text.slice(start) });
    return parts;
};

const mathNode = (part, editable = false) => {
    const node = document.createElement('span');
    node.className = part.display ? 'inline-equation is-display' : 'inline-equation';
    node.dataset.equation = part.latex;
    node.dataset.display = String(part.display);
    if (editable) {
        node.contentEditable = 'false';
        node.tabIndex = 0;
        node.setAttribute('role', 'button');
        node.title = 'Edit rumus';
        node.setAttribute('aria-label', `Edit rumus ${part.latex}`);
    }
    katex.render(part.latex, node, { displayMode: part.display, throwOnError: false, trust: false, strict: false });
    return node;
};

const fragmentFor = (text, editable = false) => {
    const fragment = document.createDocumentFragment();
    mathParts(text).forEach(part => fragment.append(part.latex === undefined
        ? document.createTextNode(part.text) : mathNode(part, editable)));
    return fragment;
};

export const renderInlineMath = (root) => {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: node => node.parentElement?.closest('textarea,input,select,script,style,.katex,[data-equation],[contenteditable]')
            ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT,
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(node => {
        if (mathParts(node.textContent).some(part => part.latex !== undefined)) node.replaceWith(fragmentFor(node.textContent));
    });
};

const serialize = (node) => {
    if (node.nodeType === Node.TEXT_NODE) return node.textContent;
    if (node.dataset?.equation !== undefined) return node.dataset.display === 'true'
        ? `\\[${node.dataset.equation}\\]` : `\\(${node.dataset.equation}\\)`;
    if (node.nodeName === 'BR') return '\n';
    return [...node.childNodes].map((child, index) => {
        const boundary = index > 0 && ['DIV', 'P'].includes(child.nodeName) ? '\n' : '';
        return boundary + serialize(child);
    }).join('');
};

let dialogReady;
const formulaDialog = () => dialogReady ??= (async () => {
    const { MathfieldElement } = await import('mathlive');
    await import('mathlive/fonts.css');
    MathfieldElement.fontsDirectory = null;
    MathfieldElement.soundsDirectory = null;
    const dialog = document.createElement('dialog');
    dialog.className = 'inline-equation-dialog';
    dialog.innerHTML = `<form method="dialog"><h2>Rumus matematika</h2>
        <fieldset class="inline-equation-modes"><legend>Posisi rumus</legend>
        <label><input type="radio" name="position" value="inline" checked> Dalam kalimat</label>
        <label><input type="radio" name="position" value="display"> Baris tersendiri</label></fieldset>
        <div data-math-host></div><div class="inline-equation-templates"></div>
        <p data-math-error role="alert" hidden></p>
        <div class="inline-equation-actions"><button type="button" class="button button-muted" data-cancel>Batal</button>
        <button type="submit" class="button button-primary">Sisipkan rumus</button></div></form>`;
    const field = new MathfieldElement();
    field.setAttribute('math-virtual-keyboard-policy', 'manual');
    field.setAttribute('aria-label', 'Isi rumus matematika');
    dialog.querySelector('[data-math-host]').append(field);
    for (const [label, latex] of [['Pecahan', '\\frac{#0}{#?}'], ['Akar', '\\sqrt{#0}'], ['Pangkat', '#0^{#?}'], ['Indeks', '#0_{#?}'], ['Kali', '\\times'], ['Bagi', '\\div']]) {
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'button button-muted'; button.title = label;
        katex.render(latex.replaceAll('#0', 'a').replaceAll('#?', 'b'), button, { throwOnError: false });
        button.setAttribute('aria-label', label);
        button.onclick = () => { field.focus(); field.executeCommand(['insert', latex]); };
        dialog.querySelector('.inline-equation-templates').append(button);
    }
    dialog.querySelector('[data-cancel]').onclick = () => dialog.close();
    document.body.append(dialog);
    return { dialog, field };
})();

export const initializeInlineEditors = (root = document) => {
    const names = ['stimulus', 'pertanyaan', 'pernyataan[]', 'pasangan_kiri[]', 'pasangan_kanan[]', 'pengecoh_menjodohkan[]', 'pembahasan', 'rubrik_teks', 'kunci_teks'];
    root.querySelectorAll('textarea').forEach(input => {
        if ((!names.includes(input.name) && !input.name.startsWith('opsi[')) || input.dataset.inlineReady) return;
        input.dataset.inlineReady = '1';
        const editor = document.createElement('div');
        editor.className = 'textarea inline-equation-editor';
        editor.contentEditable = 'true';
        editor.setAttribute('role', 'textbox');
        editor.setAttribute('aria-multiline', 'true');
        editor.setAttribute('aria-label', input.labels?.[0]?.textContent || input.placeholder || 'Isi teks soal');
        editor.dataset.placeholder = input.placeholder;
        editor.append(fragmentFor(input.value, true));
        const required = input.required;
        input.required = false;
        input.hidden = true;
        input.after(editor);
        const sync = () => {
            if (editor.hidden) return;
            input.value = serialize(editor);
            editor.setAttribute('aria-invalid', String(required && !input.value.trim()));
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        let range;
        const remember = () => {
            const selection = window.getSelection();
            if (selection.rangeCount && editor.contains(selection.anchorNode) && editor.contains(selection.focusNode)) range = selection.getRangeAt(0).cloneRange();
        };
        editor.addEventListener('input', sync);
        editor.addEventListener('keyup', remember);
        editor.addEventListener('mouseup', remember);
        editor.addEventListener('blur', remember);
        const insert = (fragment) => {
            editor.focus();
            if (!range || !editor.contains(range.commonAncestorContainer)) {
                range = document.createRange(); range.selectNodeContents(editor); range.collapse(false);
            }
            range.deleteContents();
            const last = fragment.lastChild;
            range.insertNode(fragment);
            if (last) range.setStartAfter(last);
            range.collapse(true);
            const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
            sync();
        };
        editor.addEventListener('paste', event => {
            event.preventDefault(); remember();
            insert(fragmentFor(event.clipboardData.getData('text/plain'), true));
        });
        editor.addEventListener('drop', event => event.preventDefault());
        for (const action of ['copy', 'cut']) editor.addEventListener(action, event => {
            remember();
            if (!range || range.collapsed) return;
            event.preventDefault();
            event.clipboardData.setData('text/plain', serialize(range.cloneContents()));
            if (action === 'cut') { range.deleteContents(); sync(); }
        });
        const open = async (existing) => {
            remember();
            let tools;
            try { tools = await formulaDialog(); }
            catch {
                dialogReady = undefined;
                window.alert('Editor rumus belum dapat dimuat. Muat ulang halaman lalu coba kembali.');
                return;
            }
            const { dialog, field } = tools;
            field.value = existing?.dataset.equation || '';
            dialog.querySelector(`[value="${existing?.dataset.display === 'true' ? 'display' : 'inline'}"]`).checked = true;
            const error = dialog.querySelector('[data-math-error]'); error.hidden = true;
            dialog.querySelector('[type=submit]').textContent = existing ? 'Simpan rumus' : 'Sisipkan rumus';
            dialog.querySelector('form').onsubmit = event => {
                event.preventDefault();
                const latex = field.value.trim();
                try {
                    if (!latex) throw new Error();
                    katex.renderToString(latex, { throwOnError: true, trust: false });
                } catch {
                    error.textContent = 'Lengkapi rumus terlebih dahulu.'; error.hidden = false; return;
                }
                const node = mathNode({ latex, display: dialog.querySelector('[name=position]:checked').value === 'display' }, true);
                dialog.close();
                if (existing?.isConnected) { existing.replaceWith(node); sync(); }
                else { const fragment = document.createDocumentFragment(); fragment.append(node, document.createTextNode(' ')); insert(fragment); }
            };
            dialog.showModal(); field.focus();
        };
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'button button-muted inline-equation-insert';
        button.textContent = 'Sisipkan rumus';
        button.addEventListener('mousedown', event => { remember(); event.preventDefault(); });
        button.onclick = () => open();
        editor.before(button);
        if (input.name === 'kunci_teks') {
            const toggleManual = () => {
                const manual = ['uraian', 'upload_file'].includes(input.form?.querySelector('[data-soal-kind]:checked')?.value);
                if (manual && editor.hidden) editor.replaceChildren(fragmentFor(input.value, true));
                editor.hidden = !manual;
                button.hidden = !manual;
                input.hidden = manual;
            };
            input.form?.querySelectorAll('[data-soal-kind]').forEach(control => control.addEventListener('change', toggleManual));
            toggleManual();
        }
        editor.addEventListener('click', event => {
            const existing = event.target.closest('[data-equation]');
            if (existing) open(existing);
        });
        editor.addEventListener('keydown', event => {
            if (event.target.matches('[data-equation]') && ['Enter', ' '].includes(event.key)) { event.preventDefault(); open(event.target); }
        });
        input.form?.addEventListener('submit', event => {
            sync();
            if ((required && !input.value.trim()) || (input.maxLength > 0 && input.value.length > input.maxLength)) {
                event.preventDefault(); editor.focus(); editor.setAttribute('aria-invalid', 'true');
            }
        });
    });
};
