import jsQR from 'jsqr';

const root = document.getElementById('exam-attendance-app');

if (root) {
    const video = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    const cameraWrap = document.getElementById('camera-wrap');
    const startButton = document.getElementById('start-camera');
    const switchButton = document.getElementById('switch-camera');
    const stopButton = document.getElementById('stop-camera');
    const warning = document.getElementById('camera-warning');
    const statusText = document.getElementById('camera-status-text');
    const result = document.getElementById('scan-result');
    const resultPhoto = document.getElementById('result-photo');
    const resultKicker = document.getElementById('result-kicker');
    const resultName = document.getElementById('result-name');
    const resultMeta = document.getElementById('result-meta');
    const resultText = document.getElementById('result-text');
    const recentList = document.getElementById('recent-list');
    const manualForm = document.getElementById('manual-form');
    const manualNisn = document.getElementById('manual-nisn');
    const participantSearch = document.getElementById('participant-search');
    const participantForms = [...document.querySelectorAll('.participant-manual-form')];
    const clock = document.getElementById('server-clock');
    const queueCount = document.getElementById('queue-count');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const fallbackPhoto = root.dataset.fallbackPhoto;
    const serverStartedAt = new Date(root.dataset.serverTime);
    const localStartedAt = Date.now();
    const scanQueue = [];
    let stream = null;
    let devices = [];
    let activeDeviceIndex = 0;
    let animationFrame = null;
    let processing = false;
    let resumeAt = 0;
    let lastValue = '';
    let lastValueUntil = 0;
    let wakeLock = null;

    const updateClock = () => {
        const current = new Date(serverStartedAt.getTime() + (Date.now() - localStartedAt));
        clock.textContent = new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
        }).format(current).replaceAll('.', ':');
    };
    updateClock();
    window.setInterval(updateClock, 1000);

    const updateQueueCount = () => {
        queueCount.textContent = String(scanQueue.length + (processing ? 1 : 0));
    };

    const showWarning = (message) => {
        warning.textContent = message;
        warning.hidden = false;
    };

    const clearWarning = () => {
        warning.hidden = true;
        warning.textContent = '';
    };

    const requestWakeLock = async () => {
        try {
            wakeLock = await navigator.wakeLock?.request('screen');
        } catch (_) {
            wakeLock = null;
        }
    };

    const releaseWakeLock = async () => {
        try {
            await wakeLock?.release();
        } catch (_) {
            // Browser can release the lock when this page is hidden.
        }
        wakeLock = null;
    };

    const stopCamera = async () => {
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        cameraWrap.classList.remove('camera-on');
        startButton.disabled = false;
        switchButton.disabled = true;
        stopButton.disabled = true;
        statusText.textContent = 'Kamera dihentikan. Tekan Mulai kamera untuk melanjutkan.';
        await releaseWakeLock();
    };

    const readCameras = async () => {
        const allDevices = await navigator.mediaDevices.enumerateDevices();
        devices = allDevices.filter((device) => device.kind === 'videoinput');
        switchButton.disabled = devices.length < 2;
    };

    const startCamera = async (deviceId = null) => {
        if (!navigator.mediaDevices?.getUserMedia) {
            showWarning('Browser ini tidak mendukung kamera. Gunakan Chrome, Edge, atau Safari versi terbaru.');
            return;
        }

        startButton.disabled = true;
        clearWarning();
        statusText.textContent = 'Meminta izin kamera...';

        try {
            stream?.getTracks().forEach((track) => track.stop());
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: deviceId
                    ? { deviceId: { exact: deviceId } }
                    : { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            });
            video.srcObject = stream;
            await video.play();
            cameraWrap.classList.add('camera-on');
            stopButton.disabled = false;
            statusText.textContent = 'Kamera aktif. Arahkan QR kartu pelajar ke dalam kotak.';
            await readCameras();
            await requestWakeLock();
            scanFrame();
        } catch (error) {
            startButton.disabled = false;
            statusText.textContent = 'Kamera belum dapat digunakan.';
            const denied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
            showWarning(denied
                ? 'Izin kamera ditolak. Izinkan kamera pada pengaturan situs, lalu coba kembali.'
                : 'Kamera tidak dapat dibuka. Pastikan kamera tidak dipakai aplikasi lain dan NUSA diakses melalui HTTPS.');
        }
    };

    const scanFrame = () => {
        if (!stream) return;

        if (Date.now() >= resumeAt && video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
            const maximumWidth = 720;
            const scale = Math.min(1, maximumWidth / video.videoWidth);
            canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
            canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });

            if (code?.data && (code.data !== lastValue || Date.now() >= lastValueUntil)) {
                enqueueScan(code.data);
            }
        }

        animationFrame = requestAnimationFrame(scanFrame);
    };

    const createMeta = (values) => {
        resultMeta.replaceChildren();
        values.filter(Boolean).forEach((value) => {
            const span = document.createElement('span');
            span.textContent = value;
            resultMeta.append(span);
        });
    };

    const showResult = (payload) => {
        const known = payload.berhasil && !payload.baru;
        result.className = `result show ${payload.berhasil ? (known ? 'known' : 'success') : 'error'}`;
        resultKicker.textContent = payload.berhasil ? (known ? 'Sudah tercatat' : 'Presensi berhasil') : 'Presensi belum tercatat';
        resultName.textContent = payload.siswa?.nama_lengkap || 'QR tidak dikenali';
        resultPhoto.src = payload.siswa?.foto_url || fallbackPhoto;
        createMeta([
            payload.siswa?.kelas ? `Kelas ${payload.siswa.kelas}` : null,
            payload.siswa?.nisn ? `NISN ${payload.siswa.nisn}` : null,
            payload.siswa?.nomor_meja ? `Meja ${payload.siswa.nomor_meja}` : null,
            payload.waktu_server ? `Pukul ${payload.waktu_server.slice(0, 5)}` : null,
        ]);
        resultText.textContent = payload.pesan || 'Terjadi kesalahan ketika memproses kartu.';
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const updateSummary = (summary) => {
        if (!summary) return;
        document.getElementById('summary-participants').textContent = summary.peserta;
        document.getElementById('summary-present').textContent = summary.hadir;
        document.getElementById('summary-unrecorded').textContent = summary.belum_absen;
        document.getElementById('total-present').textContent = summary.hadir;
    };

    const updateParticipantRow = (participant) => {
        if (!participant) return;
        const form = document.querySelector(`.participant-manual-form[data-participant-id="${participant.id}"]`);
        if (!form) return;
        const select = form.querySelector('[name="status_kehadiran_ujian"]');
        if (select) select.value = participant.status;
    };

    const addRecent = (participant) => {
        if (!recentList || !participant) return;
        recentList.querySelector(`[data-participant-id="${participant.id}"]`)?.remove();
        recentList.querySelector('.participant-empty')?.remove();

        const item = document.createElement('article');
        item.className = 'recent-item';
        item.dataset.participantId = participant.id;
        const photo = document.createElement('img');
        photo.className = 'recent-photo';
        photo.src = participant.foto_url || fallbackPhoto;
        photo.alt = '';
        const identity = document.createElement('div');
        const name = document.createElement('p');
        name.className = 'recent-name';
        name.textContent = participant.nama_lengkap || '-';
        const meta = document.createElement('p');
        meta.className = 'recent-meta';
        meta.textContent = `Meja ${participant.nomor_meja || '-'} - ${participant.kelas || '-'}`;
        identity.append(name, meta);
        const time = document.createElement('time');
        time.className = 'recent-time';
        time.textContent = (participant.waktu_scan || clock.textContent).slice(0, 5);
        item.append(photo, identity, time);
        recentList.prepend(item);

        while (recentList.querySelectorAll('.recent-item').length > 8) {
            recentList.lastElementChild?.remove();
        }
    };

    const sound = (success) => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const audio = new AudioContext();
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            oscillator.frequency.value = success ? 880 : 220;
            gain.gain.setValueAtTime(.08, audio.currentTime);
            gain.gain.exponentialRampToValueAtTime(.001, audio.currentTime + .16);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start();
            oscillator.stop(audio.currentTime + .16);
        } catch (_) {
            // Visual feedback remains available if audio is blocked.
        }
        navigator.vibrate?.(success ? 80 : [80, 60, 80]);
    };

    const parseResponse = async (response) => {
        try {
            return await response.json();
        } catch (_) {
            return { berhasil: false, pesan: 'Server NUSA belum dapat memproses permintaan ini.' };
        }
    };

    const processQueue = async () => {
        if (processing || scanQueue.length === 0) return;
        processing = true;
        updateQueueCount();
        const value = scanQueue.shift();
        statusText.textContent = 'Memeriksa peserta dan ruang ujian...';

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ isi_scan: value }),
            });
            const payload = await parseResponse(response);
            if (!payload.pesan && payload.message) payload.pesan = payload.message;
            showResult(payload);
            sound(Boolean(payload.berhasil));
            updateSummary(payload.ringkasan);
            updateParticipantRow(payload.peserta);
            if (payload.berhasil) addRecent(payload.peserta);
            statusText.textContent = payload.berhasil
                ? 'Siap memindai peserta berikutnya.'
                : 'Periksa pesan hasil, lalu pindai kartu berikutnya.';
        } catch (_) {
            showResult({ berhasil: false, pesan: 'Tidak dapat terhubung ke server NUSA. Periksa jaringan lalu coba kembali.', waktu_server: clock.textContent });
            sound(false);
            statusText.textContent = 'Koneksi ke server terputus.';
        } finally {
            processing = false;
            resumeAt = Date.now() + 1600;
            updateQueueCount();
            processQueue();
        }
    };

    const enqueueScan = (rawValue) => {
        const value = String(rawValue || '').trim();
        if (!value) return;
        lastValue = value;
        lastValueUntil = Date.now() + 4500;
        if (!scanQueue.includes(value)) scanQueue.push(value);
        updateQueueCount();
        processQueue();
    };

    startButton?.addEventListener('click', () => startCamera());
    stopButton?.addEventListener('click', stopCamera);
    switchButton?.addEventListener('click', async () => {
        if (devices.length < 2) return;
        activeDeviceIndex = (activeDeviceIndex + 1) % devices.length;
        await startCamera(devices[activeDeviceIndex].deviceId);
    });
    manualForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        enqueueScan(manualNisn.value);
        manualNisn.value = '';
        manualNisn.focus();
    });
    participantSearch?.addEventListener('input', () => {
        const keyword = participantSearch.value.trim().toLocaleLowerCase('id-ID');
        participantForms.forEach((form) => {
            form.hidden = keyword !== '' && !form.dataset.search.includes(keyword);
        });
    });
    participantForms.forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.classList.contains('is-updating')) return;
        form.classList.add('is-updating');
        form.querySelector('button').disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'PUT',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ status_kehadiran_ujian: form.querySelector('select').value }),
            });
            const payload = await parseResponse(response);
            showResult({
                ...payload,
                baru: false,
                siswa: payload.peserta ? {
                    nama_lengkap: payload.peserta.nama_lengkap,
                    nisn: payload.peserta.nisn,
                    kelas: payload.peserta.kelas,
                    nomor_meja: payload.peserta.nomor_meja,
                    foto_url: payload.peserta.foto_url,
                } : null,
            });
            sound(Boolean(payload.berhasil));
            updateSummary(payload.ringkasan);
            updateParticipantRow(payload.peserta);
            if (payload.peserta && ['hadir', 'terlambat'].includes(payload.peserta.status)) addRecent(payload.peserta);
        } catch (_) {
            showResult({ berhasil: false, pesan: 'Perubahan manual tidak dapat dikirim ke server.' });
            sound(false);
        } finally {
            form.classList.remove('is-updating');
            form.querySelector('button').disabled = false;
        }
    }));
    window.addEventListener('pagehide', stopCamera);
}
