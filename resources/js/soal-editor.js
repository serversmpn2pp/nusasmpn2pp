import katex from 'katex';
import 'katex/dist/katex.min.css';

const mathLiveReady = document.querySelector('[data-formula-field]')
    ? Promise.all([import('mathlive'), import('mathlive/fonts.css')]).then(([mathlive]) => {
        mathlive.MathfieldElement.fontsDirectory = null;
        mathlive.MathfieldElement.soundsDirectory = null;

        return mathlive;
    })
    : Promise.resolve(null);

const renderFormula = (element, latex) => {
    const value = (latex ?? element.dataset.rumusLatex ?? '').trim();

    if (!value) {
        element.textContent = 'Pratinjau rumus akan muncul di sini.';
        return;
    }

    try {
        katex.render(value, element, {
            displayMode: true,
            strict: false,
            throwOnError: true,
            trust: false,
        });
    } catch {
        element.textContent = 'Rumus belum dapat dibaca. Periksa kembali tanda kurung dan penulisannya.';
        element.classList.add('is-invalid-formula');
        return;
    }

    element.classList.remove('is-invalid-formula');
};

window.renderRumusSoal = (root = document) => {
    root.querySelectorAll('[data-rumus-latex]').forEach((element) => renderFormula(element));
};

window.renderRumusSoal();

const initializeQuestionMediaEditor = (editor) => {
    if (editor.dataset.mediaEditorReady === '1') return;
    editor.dataset.mediaEditorReady = '1';

    const toggles = [...editor.querySelectorAll('[data-media-toggle]')];
    const panels = [...editor.querySelectorAll('[data-media-panel]')];
    const imageInput = editor.querySelector('[data-image-input]');
    const imagePreview = editor.querySelector('[data-image-preview]');
    const imageError = editor.querySelector('[data-image-error]');
    const removeImageInput = editor.querySelector('[data-remove-image]');
    const clearImageButton = editor.querySelector('[data-clear-image]');
    const currentImage = editor.dataset.currentImage || '';
    const tableRowsSelect = editor.querySelector('[data-table-rows]');
    const tableColumnsSelect = editor.querySelector('[data-table-columns]');
    const tableEditor = editor.querySelector('[data-table-editor]');
    const tableValueInput = editor.querySelector('[data-table-value]');
    const clearTableButton = editor.querySelector('[data-clear-table]');
    const formulaInput = editor.querySelector('[data-formula-input]');
    const formulaHost = editor.querySelector('[data-formula-field]');
    const mediaStatus = editor.querySelector('[data-media-status]');
    let objectImageUrl = null;
    let tableData = [];
    let formulaInitialized = false;

    const initializeFormula = () => {
        if (formulaInitialized) return;
        formulaInitialized = true;

        mathLiveReady.then((mathlive) => {
            if (!formulaHost || !formulaInput || !mathlive) return;

            const formulaField = new mathlive.MathfieldElement();
            formulaField.className = formulaHost.className;
            formulaField.setAttribute('math-virtual-keyboard-policy', 'auto');
            formulaField.setAttribute('smart-fence', '');
            formulaField.setAttribute('aria-label', 'Isi rumus matematika');
            formulaField.dataset.formulaField = '';
            formulaHost.replaceWith(formulaField);

            formulaField.value = formulaInput.value || '';
            formulaField.addEventListener('input', () => {
                formulaInput.value = formulaField.value;
                syncMediaStatus();
            });

            editor.querySelectorAll('[data-formula-template]').forEach((button) => {
                button.addEventListener('click', () => {
                    formulaField.focus();
                    formulaField.executeCommand(['insert', button.dataset.formulaTemplate || '']);
                    formulaInput.value = formulaField.value;
                    syncMediaStatus();
                });
            });
        });
    };

    const syncMediaStatus = () => {
        if (!mediaStatus) return;
        const hasImage = Boolean(imageInput?.files?.length)
            || (Boolean(currentImage) && removeImageInput?.value !== '1');
        const hasTable = Boolean(tableValueInput?.value.trim());
        const hasFormula = Boolean(formulaInput?.value.trim());
        mediaStatus.hidden = !(hasImage || hasTable || hasFormula);
    };

    const setOpenPanel = (name) => {
        panels.forEach((panel) => {
            const active = panel.dataset.mediaPanel === name;
            panel.hidden = !active;
        });
        toggles.forEach((button) => button.classList.toggle('is-active', button.dataset.mediaToggle === name));
        if (name === 'rumus') initializeFormula();
    };

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.mediaToggle;
            const panel = editor.querySelector(`[data-media-panel="${target}"]`);
            setOpenPanel(panel?.hidden ? target : null);
        });
    });

    const showImage = (url, alt = 'Gambar pendukung soal') => {
        imagePreview.replaceChildren();
        if (!url) {
            const empty = document.createElement('span');
            empty.textContent = 'Belum ada gambar';
            imagePreview.append(empty);
            return;
        }

        const image = document.createElement('img');
        image.src = url;
        image.alt = alt;
        imagePreview.append(image);
    };

    imageInput?.addEventListener('change', () => {
        imageError.hidden = true;
        imageError.textContent = '';
        imageInput.setCustomValidity('');
        const file = imageInput.files?.[0];

        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            imageInput.value = '';
            imageInput.setCustomValidity('Ukuran gambar melebihi 5 MB.');
            imageError.textContent = 'Ukuran gambar melebihi 5 MB. Pilih gambar yang lebih kecil.';
            imageError.hidden = false;
            showImage(currentImage);
            return;
        }

        if (objectImageUrl) URL.revokeObjectURL(objectImageUrl);
        objectImageUrl = URL.createObjectURL(file);
        removeImageInput.value = '0';
        showImage(objectImageUrl, file.name);
        syncMediaStatus();
    });

    clearImageButton?.addEventListener('click', () => {
        if (objectImageUrl) URL.revokeObjectURL(objectImageUrl);
        objectImageUrl = null;
        imageInput.value = '';
        removeImageInput.value = '1';
        showImage('');
        syncMediaStatus();
    });

    try {
        const parsed = JSON.parse(tableValueInput?.value || '[]');
        tableData = Array.isArray(parsed) ? parsed : [];
    } catch {
        tableData = [];
    }

    const resizeTableData = () => {
        const rows = Number(tableRowsSelect?.value || 3);
        const columns = Number(tableColumnsSelect?.value || 3);
        tableData = Array.from({ length: rows }, (_, rowIndex) => (
            Array.from({ length: columns }, (_, columnIndex) => tableData[rowIndex]?.[columnIndex] ?? '')
        ));
    };

    const syncTableValue = () => {
        const hasValue = tableData.some((row) => row.some((cell) => String(cell).trim() !== ''));
        tableValueInput.value = hasValue ? JSON.stringify(tableData) : '';
        syncMediaStatus();
    };

    const renderTableEditor = () => {
        resizeTableData();
        const table = document.createElement('table');
        table.className = 'question-table-grid';
        const body = document.createElement('tbody');

        tableData.forEach((row, rowIndex) => {
            const tr = document.createElement('tr');
            row.forEach((cell, columnIndex) => {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = 'text';
                input.maxLength = 500;
                input.value = cell;
                input.placeholder = rowIndex === 0 ? `Judul ${columnIndex + 1}` : `Isi baris ${rowIndex}`;
                input.setAttribute('aria-label', `Baris ${rowIndex + 1}, kolom ${columnIndex + 1}`);
                input.addEventListener('input', () => {
                    tableData[rowIndex][columnIndex] = input.value;
                    syncTableValue();
                });
                td.append(input);
                tr.append(td);
            });
            body.append(tr);
        });

        table.append(body);
        tableEditor.replaceChildren(table);
    };

    tableRowsSelect?.addEventListener('change', () => {
        renderTableEditor();
        syncTableValue();
    });
    tableColumnsSelect?.addEventListener('change', () => {
        renderTableEditor();
        syncTableValue();
    });
    clearTableButton?.addEventListener('click', () => {
        tableData = [];
        tableValueInput.value = '';
        renderTableEditor();
        syncMediaStatus();
    });
    renderTableEditor();

    syncMediaStatus();
};

window.initializeQuestionMediaEditors = (root = document) => {
    root.querySelectorAll('[data-question-media-editor]').forEach(initializeQuestionMediaEditor);
};

window.initializeQuestionMediaEditors();

const createText = (tag, text, className = '') => {
    const element = document.createElement(tag);
    element.textContent = text;
    if (className) element.className = className;
    return element;
};

const appendPreviewMedia = (container, editor, compact = false) => {
    if (!editor) return;
    const media = document.createElement('div');
    media.className = `question-media-content${compact ? ' is-compact' : ''}`;
    const previewImage = editor.querySelector('[data-image-preview] img');
    const field = (rootName, nestedName = rootName) => editor.querySelector(
        `[name="${rootName}"], [name$="[${nestedName}]"]`,
    );

    if (previewImage) {
        const figure = document.createElement('figure');
        figure.className = 'question-media-figure';
        const image = document.createElement('img');
        const alt = field('gambar_alt')?.value.trim() || previewImage.alt || 'Gambar pendukung soal';
        const caption = field('gambar_keterangan')?.value.trim()
            || (alt !== 'Gambar pendukung soal' ? alt : '');
        image.src = previewImage.src;
        image.alt = alt;
        figure.append(image);
        if (caption) figure.append(createText('figcaption', caption));
        media.append(figure);
    }

    const tableValue = editor.querySelector('[data-table-value]')?.value;
    if (tableValue) {
        try {
            const rows = JSON.parse(tableValue);
            const figure = document.createElement('figure');
            figure.className = 'question-media-table-wrap';
            const title = field('tabel_judul')?.value.trim();
            if (title) figure.append(createText('figcaption', title));
            const scroll = document.createElement('div');
            scroll.className = 'question-media-table-scroll';
            const table = document.createElement('table');
            table.className = 'question-media-table';
            const thead = document.createElement('thead');
            const tbody = document.createElement('tbody');
            rows.forEach((row, rowIndex) => {
                const tr = document.createElement('tr');
                row.forEach((cell) => tr.append(createText(rowIndex === 0 ? 'th' : 'td', cell)));
                (rowIndex === 0 ? thead : tbody).append(tr);
            });
            table.append(thead, tbody);
            scroll.append(table);
            figure.append(scroll);
            media.append(figure);
        } catch {
            // Validasi server akan menangani nilai tabel yang tidak dapat dibaca.
        }
    }

    const latex = editor.querySelector('[data-formula-input]')?.value.trim();
    if (latex) {
        const figure = document.createElement('figure');
        figure.className = 'question-media-formula';
        const formula = document.createElement('div');
        formula.dataset.rumusLatex = latex;
        renderFormula(formula, latex);
        figure.append(formula);
        const caption = field('rumus_keterangan')?.value.trim();
        if (caption) figure.append(createText('figcaption', caption));
        media.append(figure);
    }

    if (media.childElementCount) container.append(media);
};

const appendAnswerPreview = (container, type) => {
    if (['pilihan_ganda', 'pilihan_ganda_kompleks'].includes(type)) {
        const options = document.createElement('div');
        options.className = 'option-list';
        document.querySelectorAll('[name^="opsi["]').forEach((input) => {
            if (!input.value.trim()) return;
            const code = input.name.match(/\[([^\]]+)\]/)?.[1] || '';
            const row = document.createElement('label');
            row.className = 'option-card';
            const control = document.createElement('input');
            control.type = type === 'pilihan_ganda' ? 'radio' : 'checkbox';
            control.tabIndex = -1;
            const content = document.createElement('div');
            content.className = 'option-card-content';
            content.append(
                createText('span', code, 'option-code'),
                createText('span', input.value, 'option-text'),
            );
            appendPreviewMedia(
                content,
                input.closest('.soal-option-row')?.querySelector('[data-question-media-editor]'),
                true,
            );
            row.append(control, content);
            options.append(row);
        });
        container.append(options);
    } else if (type === 'benar_salah') {
        const options = document.createElement('div');
        options.className = 'option-list';
        document.querySelectorAll('[name="pernyataan[]"]').forEach((input, index) => {
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'statement-row';
            const content = document.createElement('div');
            content.append(
                createText('span', String(index + 1), 'option-code'),
                createText('span', input.value, 'option-text'),
            );
            appendPreviewMedia(
                content,
                input.closest('.soal-option-row')?.querySelector('[data-question-media-editor]'),
                true,
            );
            const controls = document.createElement('div');
            controls.className = 'statement-options';
            ['Benar', 'Salah'].forEach((label) => {
                const choice = document.createElement('label');
                choice.className = 'pill-option';
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = `preview-benar-salah-${index}`;
                radio.tabIndex = -1;
                choice.append(radio, label);
                controls.append(choice);
            });
            row.append(content, controls);
            options.append(row);
        });
        container.append(options);
    } else if (type === 'menjodohkan') {
        const leftInputs = [...document.querySelectorAll('[name="pasangan_kiri[]"]')];
        const rightInputs = [...document.querySelectorAll('[name="pasangan_kanan[]"]')];
        const distractorInputs = [...document.querySelectorAll('[name="pengecoh_menjodohkan[]"]')];
        const answerInputs = [...rightInputs, ...distractorInputs];
        const answers = [
            ...rightInputs.map((input) => input.value.trim()),
            ...distractorInputs.map((input) => input.value.trim()),
        ].filter((value, index, values) => (
            value && values.findIndex((candidate) => candidate.toLocaleLowerCase() === value.toLocaleLowerCase()) === index
        ));

        if (answers.length) {
            const bank = document.createElement('div');
            bank.className = 'matching-answer-bank';
            bank.append(createText('strong', 'Pilihan pasangan'));
            const answerGrid = document.createElement('div');
            answers.forEach((value, index) => {
                const row = document.createElement('div');
                row.className = 'matching-answer-option';
                const source = answerInputs
                    .find((input) => input.value.trim().toLocaleLowerCase() === value.toLocaleLowerCase());
                const content = document.createElement('div');
                content.append(createText('span', value));
                const sourceEditors = source?.closest('.soal-option-row')?.querySelectorAll('[data-question-media-editor]') || [];
                const sourceEditor = source?.name === 'pasangan_kanan[]' ? sourceEditors[1] : sourceEditors[0];
                appendPreviewMedia(content, sourceEditor, true);
                row.append(createText('b', String.fromCharCode(65 + index)), content);
                answerGrid.append(row);
            });
            bank.append(answerGrid);
            container.append(bank);
        }

        const options = document.createElement('div');
        options.className = 'option-list';
        leftInputs.forEach((input, index) => {
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'matching-row';
            const content = document.createElement('div');
            content.append(
                createText('span', String(index + 1), 'option-code'),
                createText('span', input.value.trim(), 'option-text'),
            );
            appendPreviewMedia(
                content,
                input.closest('[data-matching-pair]')?.querySelector('[data-question-media-editor]'),
                true,
            );
            const select = document.createElement('select');
            select.className = 'select matching-select';
            select.tabIndex = -1;
            select.append(new Option('Pilih pasangan', ''));
            answers.forEach((answer, answerIndex) => {
                select.append(new Option(`${String.fromCharCode(65 + answerIndex)}. ${answer}`, answer));
            });
            row.append(content, select);
            options.append(row);
        });
        container.append(options);
    } else if (['isian_singkat', 'numerik'].includes(type)) {
        const field = document.createElement('div');
        field.className = 'field';
        field.append(createText('label', 'Jawaban'));
        const input = document.createElement('input');
        input.className = 'input';
        input.type = 'text';
        input.inputMode = type === 'numerik' ? 'decimal' : 'text';
        input.tabIndex = -1;
        field.append(input);
        container.append(field);
    } else if (type === 'upload_file') {
        const box = document.createElement('div');
        box.className = 'file-answer-box';
        const status = document.createElement('div');
        status.className = 'file-answer-status';
        status.append(
            createText('strong', 'Belum ada berkas jawaban'),
            createText('span', 'PDF, gambar, Word, Excel, atau PowerPoint · Maksimal 10 MB'),
        );
        const action = document.createElement('div');
        action.className = 'file-answer-action';
        const button = createText('span', 'Pilih dan unggah berkas', 'button button-primary');
        action.append(button, createText('span', 'Unggahan tersimpan otomatis setelah berkas dipilih.', 'help-text'));
        box.append(status, action);
        container.append(box);
    } else {
        const field = document.createElement('div');
        field.className = 'field';
        field.append(createText('label', 'Jawaban'));
        const textarea = document.createElement('textarea');
        textarea.className = 'textarea';
        textarea.placeholder = 'Tulis jawaban di sini.';
        textarea.tabIndex = -1;
        field.append(textarea);
        container.append(field);
    }
};

document.querySelectorAll('[data-question-preview]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.querySelector('[data-question-preview-dialog]');
        const body = dialog?.querySelector('[data-question-preview-body]');
        const editor = document.querySelector('[data-question-media-editor][data-media-key="utama"]');
        if (!dialog || !body || !editor) return;

        body.replaceChildren();
        const typeInput = document.querySelector('[data-soal-kind]:checked');
        const typeLabel = typeInput?.closest('label')?.querySelector('strong')?.textContent || 'Soal';
        const question = document.createElement('article');
        question.className = 'panel question-card question-preview-exam';
        const head = document.createElement('div');
        head.className = 'question-head';
        const identity = document.createElement('div');
        identity.style.cssText = 'display:flex;gap:12px;align-items:flex-start';
        const type = document.createElement('div');
        type.append(createText('span', typeLabel, 'badge badge-muted'));
        identity.append(createText('span', '1', 'question-number'), type);
        const doubt = document.createElement('label');
        doubt.className = 'check-row';
        const doubtInput = document.createElement('input');
        doubtInput.type = 'checkbox';
        doubtInput.tabIndex = -1;
        doubt.append(doubtInput, 'Ragu-ragu');
        head.append(identity, doubt);
        question.append(head);

        const stimulus = document.querySelector('[name="stimulus"]')?.value.trim();
        const stimulusEditor = document.querySelector('[data-question-media-editor][data-media-key="stimulus"]');
        if (stimulus || stimulusEditor) {
            const stimulusBox = document.createElement('div');
            stimulusBox.className = 'stimulus';
            if (stimulus) stimulusBox.append(createText('div', stimulus));
            appendPreviewMedia(stimulusBox, stimulusEditor, true);
            if (stimulusBox.childElementCount) question.append(stimulusBox);
        }

        appendPreviewMedia(question, editor);
        question.append(createText('h2', document.querySelector('[name="pertanyaan"]')?.value.trim() || 'Isi soal belum ditulis.', 'question-title'));
        appendAnswerPreview(question, typeInput?.value || 'pilihan_ganda');
        body.append(question);

        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    });
});

document.querySelectorAll('[data-close-question-preview]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
