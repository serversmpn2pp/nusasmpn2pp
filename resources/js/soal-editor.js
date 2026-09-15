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

const appendPreviewMedia = (container, editor) => {
    if (!editor) return;
    const media = document.createElement('div');
    media.className = 'question-preview-media';
    const previewImage = editor.querySelector('[data-image-preview] img');
    const field = (rootName, nestedName = rootName) => editor.querySelector(
        `[name="${rootName}"], [name$="[${nestedName}]"]`,
    );

    if (previewImage) {
        const image = document.createElement('img');
        image.src = previewImage.src;
        image.alt = field('gambar_alt')?.value || previewImage.alt;
        media.append(image);
        const caption = field('gambar_keterangan')?.value.trim();
        if (caption) media.append(createText('small', caption));
    }

    const tableValue = editor.querySelector('[data-table-value]')?.value;
    if (tableValue) {
        try {
            const rows = JSON.parse(tableValue);
            const title = field('tabel_judul')?.value.trim();
            if (title) media.append(createText('strong', title));
            const table = document.createElement('table');
            const thead = document.createElement('thead');
            const tbody = document.createElement('tbody');
            rows.forEach((row, rowIndex) => {
                const tr = document.createElement('tr');
                row.forEach((cell) => tr.append(createText(rowIndex === 0 ? 'th' : 'td', cell)));
                (rowIndex === 0 ? thead : tbody).append(tr);
            });
            table.append(thead, tbody);
            media.append(table);
        } catch {
            // Validasi server akan menangani nilai tabel yang tidak dapat dibaca.
        }
    }

    const latex = editor.querySelector('[data-formula-input]')?.value.trim();
    if (latex) {
        const formula = document.createElement('div');
        formula.className = 'question-formula-preview';
        renderFormula(formula, latex);
        media.append(formula);
        const caption = field('rumus_keterangan')?.value.trim();
        if (caption) media.append(createText('small', caption));
    }

    if (media.childElementCount) container.append(media);
};

const appendAnswerPreview = (container, type) => {
    const options = document.createElement('div');
    options.className = 'question-preview-options';

    if (['pilihan_ganda', 'pilihan_ganda_kompleks'].includes(type)) {
        document.querySelectorAll('[name^="opsi["]').forEach((input) => {
            const code = input.name.match(/\[([^\]]+)\]/)?.[1] || '';
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'question-preview-option';
            const content = document.createElement('div');
            content.append(createText('span', input.value));
            appendPreviewMedia(content, input.closest('.soal-option-row')?.querySelector('[data-question-media-editor]'));
            row.append(createText('b', code), content);
            options.append(row);
        });
    } else if (type === 'benar_salah') {
        document.querySelectorAll('[name="pernyataan[]"]').forEach((input, index) => {
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'question-preview-option';
            const content = document.createElement('div');
            content.append(createText('span', input.value));
            appendPreviewMedia(content, input.closest('.soal-option-row')?.querySelector('[data-question-media-editor]'));
            row.append(createText('b', String(index + 1)), content);
            options.append(row);
        });
    } else if (type === 'menjodohkan') {
        const leftInputs = [...document.querySelectorAll('[name="pasangan_kiri[]"]')];
        const rightInputs = [...document.querySelectorAll('[name="pasangan_kanan[]"]')];
        leftInputs.forEach((input, index) => {
            const right = rightInputs[index]?.value.trim() || '';
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'question-preview-option';
            const content = document.createElement('div');
            content.append(createText('span', input.value.trim()));
            appendPreviewMedia(content, input.closest('[data-matching-pair]')?.querySelector('[data-question-media-editor]'));
            row.append(
                createText('b', String(index + 1)),
                content,
            );
            options.append(row);
        });

        const pilihanJawaban = [
            ...rightInputs.map((input) => input.value.trim()),
            ...[...document.querySelectorAll('[name="pengecoh_menjodohkan[]"]')].map((input) => input.value.trim()),
        ].filter((value, index, values) => (
            value && values.findIndex((candidate) => candidate.toLocaleLowerCase() === value.toLocaleLowerCase()) === index
        ));

        if (pilihanJawaban.length) {
            options.append(createText('strong', 'Pilihan jawaban siswa'));
            pilihanJawaban.forEach((value, index) => {
                const row = document.createElement('div');
                row.className = 'question-preview-option';
                const source = [...rightInputs, ...document.querySelectorAll('[name="pengecoh_menjodohkan[]"]')]
                    .find((input) => input.value.trim().toLocaleLowerCase() === value.toLocaleLowerCase());
                const content = document.createElement('div');
                content.append(createText('span', value));
                const sourceEditors = source?.closest('.soal-option-row')?.querySelectorAll('[data-question-media-editor]') || [];
                const sourceEditor = source?.name === 'pasangan_kanan[]' ? sourceEditors[1] : sourceEditors[0];
                appendPreviewMedia(content, sourceEditor);
                row.append(createText('b', String.fromCharCode(65 + index)), content);
                options.append(row);
            });
        }
    } else {
        options.append(createText('div', type === 'upload_file' ? 'Siswa akan mengunggah berkas jawaban.' : 'Kolom jawaban siswa akan tampil di sini.', 'question-preview-option'));
    }

    if (options.childElementCount) container.append(options);
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
        const topic = document.querySelector('[name="topik"]')?.value.trim();
        body.append(createText('div', topic ? `${typeLabel} · ${topic}` : typeLabel, 'question-preview-meta'));

        const stimulus = document.querySelector('[name="stimulus"]')?.value.trim();
        const stimulusEditor = document.querySelector('[data-question-media-editor][data-media-key="stimulus"]');
        if (stimulus || stimulusEditor) {
            const stimulusBox = document.createElement('div');
            stimulusBox.className = 'question-preview-stimulus';
            if (stimulus) stimulusBox.append(createText('div', stimulus));
            appendPreviewMedia(stimulusBox, stimulusEditor);
            if (stimulusBox.childElementCount) body.append(stimulusBox);
        }

        appendPreviewMedia(body, editor);
        body.append(createText('div', document.querySelector('[name="pertanyaan"]')?.value.trim() || 'Isi soal belum ditulis.', 'question-preview-text'));
        appendAnswerPreview(body, typeInput?.value || 'pilihan_ganda');

        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    });
});

document.querySelectorAll('[data-close-question-preview]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
