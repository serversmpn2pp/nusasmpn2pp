import jsQR from 'jsqr';

const root = document.getElementById('scan-worship-app');

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
    const totalToday = document.getElementById('total-today');
    const recentList = document.getElementById('recent-list');
    const manualForm = document.getElementById('manual-form');
    const manualNisn = document.getElementById('manual-nisn');
    const clock = document.getElementById('server-clock');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const fallbackPhoto = root.dataset.fallbackPhoto;
    const serverStartedAt = new Date(root.dataset.serverTime);
    const localStartedAt = Date.now();
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
            // The browser may have released it when the page became hidden.
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
        startButton.disabled = root.dataset.scanActive !== '1';
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
                ? 'Izin kamera ditolak. Buka pengaturan situs pada browser, izinkan kamera, lalu coba kembali.'
                : 'Kamera tidak dapat dibuka. Pastikan tidak sedang dipakai aplikasi lain dan halaman diakses melalui HTTPS.');
        }
    };

    const scanFrame = () => {
        if (!stream) return;

        if (!processing && Date.now() >= resumeAt && video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
            const maximumWidth = 720;
            const scale = Math.min(1, maximumWidth / video.videoWidth);
            canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
            canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });

            if (code?.data && (code.data !== lastValue || Date.now() >= lastValueUntil)) {
                submitScan(code.data);
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
        result.className = `result show ${payload.berhasil ? (payload.baru ? 'success' : 'known') : 'error'}`;
        resultKicker.textContent = payload.berhasil ? (payload.baru ? 'Presensi berhasil' : 'Sudah tercatat') : 'Scan belum dapat dicatat';
        resultName.textContent = payload.siswa?.nama_lengkap || 'QR tidak dikenali';
        resultPhoto.src = payload.siswa?.foto_url || fallbackPhoto;
        createMeta([
            payload.siswa?.kelas ? `Kelas ${payload.siswa.kelas}` : null,
            payload.siswa?.nisn ? `NISN ${payload.siswa.nisn}` : null,
            payload.waktu_server ? `Pukul ${payload.waktu_server.slice(0, 5)}` : null,
        ]);
        resultText.textContent = payload.pesan || 'Terjadi kesalahan saat memproses QR.';
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const addRecent = (presence) => {
        if (!presence || recentList.querySelector(`[data-presence-id="${presence.id}"]`)) return;

        recentList.querySelector('.recent-empty')?.remove();
        const item = document.createElement('article');
        item.className = 'recent-item';
        item.dataset.presenceId = presence.id;
        const photo = document.createElement('img');
        photo.className = 'recent-photo';
        photo.src = presence.foto_url || fallbackPhoto;
        photo.alt = '';
        const identity = document.createElement('div');
        const name = document.createElement('p');
        name.className = 'recent-name';
        name.textContent = presence.nama_lengkap || '-';
        const meta = document.createElement('p');
        meta.className = 'recent-meta';
        meta.textContent = `${presence.kelas || '-'} - NISN ${presence.nisn || '-'}`;
        identity.append(name, meta);
        const time = document.createElement('time');
        time.className = 'recent-time';
        time.textContent = (presence.waktu_scan || '').slice(0, 5);
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
            // Visual feedback remains available when audio is blocked.
        }
        navigator.vibrate?.(success ? 80 : [80, 60, 80]);
    };

    const submitScan = async (value) => {
        if (processing || !String(value).trim()) return;
        processing = true;
        lastValue = String(value).trim();
        lastValueUntil = Date.now() + 5000;
        statusText.textContent = 'Memeriksa data siswa...';

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    jadwal_kegiatan_ibadah_id: root.dataset.scheduleId,
                    isi_scan: String(value).trim(),
                }),
            });
            const payload = await response.json();

            if (!payload.pesan && payload.message) payload.pesan = payload.message;
            showResult(payload);
            sound(Boolean(payload.berhasil));
            totalToday.textContent = payload.jumlah_hari_ini ?? totalToday.textContent;
            if (payload.berhasil && payload.baru) addRecent(payload.presensi);
            statusText.textContent = payload.berhasil
                ? 'Siap memindai siswa berikutnya.'
                : 'Periksa pesan hasil, lalu arahkan kembali kamera ke QR.';
        } catch (_) {
            const payload = {
                berhasil: false,
                pesan: 'Tidak dapat terhubung ke server NUSA. Periksa jaringan lalu coba kembali.',
                waktu_server: clock.textContent,
            };
            showResult(payload);
            sound(false);
            statusText.textContent = 'Koneksi ke server terputus.';
        } finally {
            resumeAt = Date.now() + 1800;
            processing = false;
        }
    };

    startButton?.addEventListener('click', () => startCamera());
    stopButton?.addEventListener('click', stopCamera);
    switchButton?.addEventListener('click', async () => {
        if (devices.length < 2) return;
        activeDeviceIndex = (activeDeviceIndex + 1) % devices.length;
        await startCamera(devices[activeDeviceIndex].deviceId);
    });
    manualForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const value = manualNisn.value.trim();
        if (!value) return;
        await submitScan(value);
        manualNisn.value = '';
        manualNisn.focus();
    });
    window.addEventListener('pagehide', stopCamera);
}
