export function sharedTagUrl(message, origin) {
    if (message?.records?.length !== 1 || message.records[0].recordType !== 'url') return null;
    try {
        const value = new TextDecoder('utf-8', {fatal: true}).decode(message.records[0].data);
        const url = new URL(value);
        if (url.origin !== origin || !/^\/check-in\/[1-9]\d*$/.test(url.pathname)
            || url.searchParams.get('method') !== 'nfc_tag' || !/^#t=[A-Za-z0-9_-]{43}$/.test(url.hash)) return null;
        return url.href;
    } catch { return null; }
}

export function initializeSharedTag(root) {
    if (!root) return;
    const button = root.querySelector('[data-read-shared-tag]');
    const panel = root.querySelector('[data-shared-tag-status]');
    let controller = null;
    if (!window.isSecureContext || !('NDEFReader' in window)) {
        button.disabled = true; panel.textContent = 'La lectura NFC no está disponible en este navegador. Abre la etiqueta con el sistema del teléfono o solicita QR/enlace al docente.'; return;
    }
    button.addEventListener('click', async () => {
        if (controller) return;
        controller = new AbortController(); const current = controller; button.disabled = true;
        const reader = new NDEFReader();
        reader.addEventListener('reading', event => {
            if (current.signal.aborted) return;
            const url = sharedTagUrl(event.message, window.location.origin);
            if (!url) { panel.textContent = 'La etiqueta no contiene un registro compartido de esta instalación.'; return; }
            current.abort(); window.location.assign(url);
        }, {signal: current.signal});
        reader.addEventListener('readingerror', () => { panel.textContent = 'No se pudo leer. Acerca la etiqueta otra vez.'; }, {signal: current.signal});
        try { await reader.scan({signal: current.signal}); panel.textContent = 'Acerca la etiqueta compartida del docente.'; }
        catch { current.abort(); controller = null; button.disabled = false; panel.textContent = 'No fue posible iniciar NFC. Revisa permisos o utiliza QR/enlace.'; }
    });
    const stop = () => { controller?.abort(); controller = null; button.disabled = false; };
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    window.addEventListener('pagehide', stop);
}
