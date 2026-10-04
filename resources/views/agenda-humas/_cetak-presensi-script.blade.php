<script>
            // Measure the actual print layout, reserving space for both signatures on every sheet.
            window.paginateHumasAttendance = () => {
                if (!window.matchMedia('print').matches) return;
                const originals = Array.from(document.querySelectorAll('.sheet--attendance'));
                if (!originals.length) return;
                const template = originals[0].cloneNode(true);
                const rows = originals.flatMap(sheet => Array.from(sheet.querySelectorAll('.records tbody tr')));
                template.querySelector('.records tbody').replaceChildren();
                const staging = document.createElement('div');
                staging.style.cssText = 'position:absolute;left:-10000px;top:0;visibility:hidden;width:186mm;';
                document.body.appendChild(staging);
                const pages = [];
                const maxHeight = 273 * 96 / 25.4 - 4;
                let page;
                let body;
                const newPage = () => {
                    page = template.cloneNode(true);
                    staging.replaceChildren(page);
                    body = page.querySelector('.records tbody');
                    pages.push(page);
                };
                newPage();
                rows.forEach(row => {
                    if (body.children.length >= 24) newPage();
                    body.appendChild(row);
                    if (page.getBoundingClientRect().height > maxHeight && body.children.length > 1) {
                        row.remove();
                        newPage();
                        body.appendChild(row);
                    }
                });
                const fragment = document.createDocumentFragment();
                pages.forEach((sheet, index) => {
                    const foot = sheet.querySelector('.foot');
                    foot.textContent = foot.textContent.replace(/^Lembar \d+ dari \d+/, `Lembar ${index + 1} dari ${pages.length}`);
                    fragment.appendChild(sheet);
                });
                originals[0].before(fragment);
                originals.forEach(sheet => sheet.remove());
                staging.remove();
                window.agendaPrintReady = true;
            };
            window.addEventListener('beforeprint', window.paginateHumasAttendance);
            window.matchMedia('print').addEventListener('change', event => {
                if (event.matches) window.paginateHumasAttendance();
            });
            document.fonts.ready.then(window.paginateHumasAttendance);
        </script>
