(() => {
    'use strict';
    let csrf = document.querySelector('meta[name="scanner-csrf"]').content;
    let active = true;
    let syncing = null;
    let onStatus = () => {};
    const input = document.getElementById('scanInput');
    const banner = document.createElement('div');
    banner.setAttribute('role', 'alert');
    banner.hidden = true;
    banner.className = 'scanner-access-alert';
    input.closest('.scanner-card, .office-main')?.prepend(banner);
    if (!banner.isConnected) input.parentElement.before(banner);

    function block(payload) {
        active = false;
        input.disabled = true;
        banner.textContent = payload.pesan || 'Browser scanner tidak aktif. Hubungi administrator.';
        banner.hidden = false;
        window.prosesAntrean?.();
    }

    async function sync() {
        if (syncing) return syncing;
        syncing = (async () => {
            try {
                const response = await fetch(location.pathname, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                const payload = await response.json();
                if ([401, 403].includes(response.status)) {
                    block(payload);
                    return false;
                }
                if (!response.ok || !payload.scannerCsrf) return false;
                csrf = payload.scannerCsrf;
                active = true;
                input.disabled = false;
                banner.hidden = true;
                onStatus(payload);
                return true;
            } catch (_) {
                // A lost heartbeat must not claim that a scan has been recorded.
                return false;
            } finally {
                syncing = null;
            }
        })();
        return syncing;
    }

    window.NusaScanner = {
        get active() { return active; },
        start(callback) {
            onStatus = callback;
            setInterval(sync, 60000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
        },
        async submit(url, data) {
            if (!active) {
                return new Response(JSON.stringify({berhasil: false, status: 'perangkat_tidak_aktif', pesan: banner.textContent}), {status: 401, headers: {'Content-Type': 'application/json'}});
            }
            const send = () => fetch(url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-Scanner-CSRF': csrf},
                body: JSON.stringify(data),
            });
            let response = await send();
            // Only retry an authorization rejection, never retry an ambiguous write.
            if (response.status === 419 && await sync()) response = await send();
            if ([401, 403].includes(response.status)) {
                const payload = await response.clone().json();
                block(payload);
            }
            return response;
        },
    };
})();
