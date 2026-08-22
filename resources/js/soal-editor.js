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

document.querySelectorAll('[data-question-media-editor]').forEach((editor) => {
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
    let objectImageUrl = null;
    let tableData = [];

    const setOpenPanel = (name) => {
        panels.forEach((panel) => {
            const active = panel.dataset.mediaPanel === name;
            panel.hidden = !active;
        });
        toggles.forEach((button) => button.classList.toggle('is-active', button.dataset.mediaToggle === name));
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
    });

    clearImageButton?.addEventListener('click', () => {
        if (objectImageUrl) URL.revokeObjectURL(objectImageUrl);
        objectImageUrl = null;
        imageInput.value = '';
        removeImageInput.value = '1';
        showImage('');
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

    mathLiveReady.then((mathlive) => {
        if (!formulaHost || !formulaInput || !mathlive) return;

        const formulaField = new mathlive.MathfieldElement();
        formulaField.id = 'rumus_visual';
        formulaField.className = formulaHost.className;
        formulaField.setAttribute('math-virtual-keyboard-policy', 'auto');
        formulaField.setAttribute('smart-fence', '');
        formulaField.setAttribute('aria-label', 'Isi rumus matematika');
        formulaField.dataset.formulaField = '';
        formulaHost.replaceWith(formulaField);

        formulaField.value = formulaInput.value || '';
        formulaField.addEventListener('input', () => {
            formulaInput.value = formulaField.value;
        });

        editor.querySelectorAll('[data-formula-template]').forEach((button) => {
            button.addEventListener('click', () => {
                formulaField.focus();
                formulaField.executeCommand(['insert', button.dataset.formulaTemplate || '']);
                formulaInput.value = formulaField.value;
            });
        });
    });
});

const createText = (tag, text, className = '') => {
    const element = document.createElement(tag);
    element.textContent = text;
    if (className) element.className = className;
    return element;
};

const appendPreviewMedia = (container, editor) => {
    const media = document.createElement('div');
    media.className = 'question-preview-media';
    const previewImage = editor.querySelector('[data-image-preview] img');

    if (previewImage) {
        const image = document.createElement('img');
        image.src = previewImage.src;
        image.alt = document.querySelector('[name="gambar_alt"]')?.value || previewImage.alt;
        media.append(image);
        const caption = document.querySelector('[name="gambar_keterangan"]')?.value.trim();
        if (caption) media.append(createText('small', caption));
    }

    const tableValue = editor.querySelector('[data-table-value]')?.value;
    if (tableValue) {
        try {
            const rows = JSON.parse(tableValue);
            const title = document.querySelector('[name="tabel_judul"]')?.value.trim();
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
        const caption = document.querySelector('[name="rumus_keterangan"]')?.value.trim();
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
            row.append(createText('b', code), createText('span', input.value));
            options.append(row);
        });
    } else if (type === 'benar_salah') {
        document.querySelectorAll('[name="pernyataan[]"]').forEach((input, index) => {
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'question-preview-option';
            row.append(createText('b', String(index + 1)), createText('span', input.value));
            options.append(row);
        });
    } else if (type === 'menjodohkan') {
        document.querySelectorAll('[name="pasangan_kiri[]"]').forEach((input, index) => {
            if (!input.value.trim()) return;
            const row = document.createElement('div');
            row.className = 'question-preview-option';
            row.append(createText('b', String(index + 1)), createText('span', input.value));
            options.append(row);
        });
    } else {
        options.append(createText('div', type === 'upload_file' ? 'Siswa akan mengunggah berkas jawaban.' : 'Kolom jawaban siswa akan tampil di sini.', 'question-preview-option'));
    }

    if (options.childElementCount) container.append(options);
};

document.querySelectorAll('[data-question-preview]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.querySelector('[data-question-preview-dialog]');
        const body = dialog?.querySelector('[data-question-preview-body]');
        const editor = document.querySelector('[data-question-media-editor]');
        if (!dialog || !body || !editor) return;

        body.replaceChildren();
        const typeInput = document.querySelector('[data-soal-kind]:checked');
        const typeLabel = typeInput?.closest('label')?.querySelector('strong')?.textContent || 'Soal';
        const topic = document.querySelector('[name="topik"]')?.value.trim();
        body.append(createText('div', topic ? `${typeLabel} · ${topic}` : typeLabel, 'question-preview-meta'));

        const stimulus = document.querySelector('[name="stimulus"]')?.value.trim();
        if (stimulus) body.append(createText('div', stimulus, 'question-preview-stimulus'));

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
