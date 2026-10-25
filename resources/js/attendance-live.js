import { inspectNdef } from './nfc-reader.js';
import { createLocationAcquisition, CheckInFlow, locationCompatible } from './geographic-access.js';

export function credentialFromMessage(message) {
    if (inspectNdef(message) !== 'smartaulaues') return null;
    return new TextDecoder(message.records[0].encoding || 'utf-8', { fatal: true }).decode(message.records[0].data);
}

export function eventId() {
    if (globalThis.crypto.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, n => n.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
}

export class EventMemory {
    constructor(makeId = eventId) { this.makeId = makeId; this.clear(); }
    packet(values) {
        const clean = Object.fromEntries(Object.entries(values).filter(([key]) => key !== 'event_id').sort(([a], [b]) => a.localeCompare(b)));
        const fingerprint = JSON.stringify(clean);
        if (fingerprint !== this.fingerprint) { this.fingerprint = fingerprint; this.current = { ...clean, event_id: this.makeId() }; }
        return this.current;
    }
    clear() { this.fingerprint = null; this.current = null; }
}

export async function jsonRequest(url, body, method = 'POST') {
    let response;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
        response = await fetch(url, { method, signal: controller.signal, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body) });
    } catch {
        clearTimeout(timeout);
        throw new Error('Sin conexión o respuesta. Reintenta el mismo envío para recuperar su confirmación.');
    }
    const messages = {401: 'Inicia sesión nuevamente.', 403: 'Tu cuenta no tiene permiso para esta acción.', 419: 'La sesión expiró. Actualiza la página e inicia sesión nuevamente.', 422: 'Revisa los datos de la solicitud.', 429: 'Demasiadas solicitudes. Espera un minuto y reintenta el mismo envío.'};
    let result = null;
    try { result = await response.json(); } catch { /* Preserve HTTP status even for HTML proxy/auth failures. */ }
    finally { clearTimeout(timeout); }
    if (!response.ok || !result || typeof result !== 'object' || Array.isArray(result)) {
        const message = result?.errors ? Object.values(result.errors).flat().join(' ') : (result?.message || messages[response.status] || 'Sin confirmación válida del servidor. Reintenta el mismo envío.');
        const error = new Error(message); error.status = response.ok ? undefined : response.status; error.code = result?.code; error.geographic = Boolean(result?.errors?.location); throw error;
    }
    return result;
}

export function resourceExpired(expires, now = Date.now()) {
    return !expires || !Number.isFinite(Date.parse(expires)) || Date.parse(expires) <= now;
}

function status(element, message, state = '') { element.textContent = message; element.dataset.state = state; }

export function initializeAcademicForms() {
    document.querySelectorAll('[data-location-measurement]').forEach(root => createLocationAcquisition(root, JSON.parse(root.dataset.geoOptions)));
    document.querySelectorAll('[data-event-location]').forEach(container => {
        const modality = container.closest('form').querySelector('[name="modality"]');
        const mode = container.querySelector('select');
        const update = () => { container.hidden = modality.value !== 'event'; mode.required = !container.hidden; };
        modality.addEventListener('change', update); update();
    });
    document.querySelectorAll('[data-repeater]').forEach(root => {
        const rows = root.querySelector('[data-rows]');
        let index = Math.max(-1, ...Array.from(rows.querySelectorAll('[name]')).map(el => Number(el.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
        root.querySelector('[data-add-row]').addEventListener('click', () => {
            if (rows.children.length >= Number(root.dataset.max)) return;
            const number = Math.max(0, ...Array.from(rows.querySelectorAll('[name$="[number]"]')).map(el => Number(el.value))) + 1;
            const template = root.querySelector('[data-row-template]').innerHTML.replaceAll('__INDEX__', String(index++)).replaceAll('__NUMBER__', String(number));
            rows.insertAdjacentHTML('beforeend', template);
            rows.lastElementChild.querySelector('input')?.focus();
        });
        rows.addEventListener('click', event => { if (event.target.closest('[data-remove-row]') && rows.children.length > 1) event.target.closest('[data-row]').remove(); });
    });
    const select = document.querySelector('[data-format-select]'); const cycle = document.querySelector('[name="cycle_id"]');
    if (select && cycle) { const update = () => { for (const option of select.options) { if (!option.value) continue; option.hidden = option.dataset.cycle !== cycle.value; option.disabled = option.hidden; } if (select.selectedOptions[0]?.disabled) select.value = ''; }; cycle.addEventListener('change', update); update(); }
    document.querySelectorAll('[data-selection-form]').forEach(form => {
        const inputs = [...form.querySelectorAll('[data-select-student]')]; const all = form.querySelector('[data-select-all]');
        const update = () => { const count = inputs.filter(input => input.checked).length; form.querySelector('[data-selection-count]').textContent = `${count} seleccionados`; all.checked = inputs.length > 0 && count === inputs.length; all.indeterminate = count > 0 && count < inputs.length; };
        all.addEventListener('change', () => { inputs.forEach(input => input.checked = all.checked); update(); }); inputs.forEach(input => input.addEventListener('change', update)); update();
    });
}

export function initializeAttendanceConsole(root) {
    if (!root) return;
    const panel = root.querySelector('[data-live-status]'); const start = root.querySelector('[data-live-start]'); const stop = root.querySelector('[data-live-stop]'); const retry = root.querySelector('[data-live-retry]');
    let portalId = root.dataset.portal || null, readerId = null, controller = null, pending = null, busy = false, opening = false;
    let portalMethods = JSON.parse(root.dataset.portalMethods || '[]');
    const memory = new EventMemory();
    const closePortal = root.querySelector('[data-live-close-portal]');
    const compatible = window.isSecureContext && 'NDEFReader' in window;
    const location = createLocationAcquisition(root, JSON.parse(root.dataset.geoOptions));
    let geographicFailures = 0;
    let sharedTagUrl = null;
    const tagButton = root.querySelector('[data-write-shared-tag]');
    tagButton?.addEventListener('click', async () => {
        if (!sharedTagUrl || resourceExpired(root.dataset.portalExpires)) return;
        tagButton.disabled = true;
        try { await new NDEFReader().write({records: [{recordType: 'url', data: sharedTagUrl}]}); status(panel, 'Etiqueta compartida escrita. Los estudiantes pueden abrirla y validar su propia asistencia.'); }
        catch { status(panel, 'No se pudo escribir. Reintenta o comparte QR/enlace.', 'error'); }
        finally { tagButton.disabled = !compatible; }
    });
    const expire = () => {
        if (!portalId || !resourceExpired(root.dataset.portalExpires)) return;
        controller?.abort(); controller = null; portalMethods = [];
        start.disabled = true; stop.disabled = true;
        if (tagButton) tagButton.hidden = true;
        root.querySelector('[data-portal-share]').hidden = true;
        document.querySelector('[data-session-portal-status]').textContent = 'Vencido';
        document.querySelectorAll('[data-manual-attendance]').forEach(form => form.querySelector('button').disabled = !form.dataset.pendingEvent);
    };
    setInterval(expire, 1000); window.addEventListener('focus', expire); document.addEventListener('visibilitychange', expire); expire();
    start.disabled = !compatible || !portalMethods.includes('nfc');
    document.querySelectorAll('[data-manual-attendance] button').forEach(button => {
        button.disabled = !portalId || resourceExpired(root.dataset.portalExpires) || !portalMethods.includes('manual');
    });
    if (!compatible) { start.disabled = true; status(panel, 'NFC requiere HTTPS y un Android compatible. Puedes usar los otros métodos habilitados.'); }
    root.querySelector('[data-open-portal]').addEventListener('submit', async event => {
        event.preventDefault(); if (busy || opening || location.busy) return; opening = true; const form = event.currentTarget; const button = form.querySelector('[data-open-portal-submit]'); button.disabled = true; if (location.measureButton) location.measureButton.disabled = true; closePortal.disabled = true; start.disabled = true;
        try {
            await stopReading(); start.disabled = true;
            const values = new FormData(form);
            const body = { minutes: Number(values.get('minutes')), methods: values.getAll('methods[]') };
            if (root.dataset.geoRequired === 'true' && body.methods.some(method => ['qr', 'link', 'nfc_tag'].includes(method))) {
                status(panel, 'Obteniendo la ubicación que se usará como referencia fija del registro…');
                body.location = await location.acquire();
            }
            const result = await jsonRequest(form.action, body);
            geographicFailures = 0;
            const alternative = root.querySelector('[data-geo-alternative]'); if (alternative) alternative.hidden = true;
            button.textContent = 'Abrir otra ventana';
            root.dataset.portalExpires = result.expires_at; root.dataset.portalMethods = JSON.stringify(result.methods); sharedTagUrl = result.nfc_tag_url; if (tagButton) { tagButton.hidden = !sharedTagUrl; tagButton.disabled = !compatible; } document.querySelectorAll('[data-manual-attendance] button').forEach(button => button.disabled = !result.methods.includes('manual')); portalId = result.portal_id; portalMethods = result.methods; root.dataset.portal = String(portalId); closePortal.disabled = false; document.querySelector('[data-session-portal-status]').textContent = 'Abierto';
            document.querySelectorAll('[data-manual-portal]').forEach(input => input.value = String(portalId));
            const share = root.querySelector('[data-portal-share]'); share.hidden = false;
            root.querySelector('[data-portal-expiry]').textContent = 'Registro abierto hasta ' + new Date(result.expires_at).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit', timeZone: 'America/El_Salvador' });
            const qr = root.querySelector('[data-portal-qr]'); qr.hidden = !result.qr; if (result.qr) qr.src = result.qr; else qr.removeAttribute('src');
            const copy = root.querySelector('[data-copy-link]'); copy.hidden = !result.url;
            copy.onclick = async () => { try { await navigator.clipboard.writeText(result.url); status(panel, 'Enlace copiado. Compártelo con los estudiantes de esta clase.'); } catch { status(panel, 'El navegador bloqueó copiar el enlace. Revisa los permisos del portapapeles.', 'error'); } };
            status(panel, 'Registro abierto. Ya puedes usar los métodos seleccionados.'); start.disabled = !compatible || !result.methods.includes('nfc');
        } catch (error) {
            if (error.geographic || error.code?.startsWith('geo_')) geographicFailures++;
            const alternative = root.querySelector('[data-geo-alternative]');
            if (alternative && (error.incompatible || geographicFailures >= 3)) alternative.hidden = false;
            status(panel, error.message + (error.cancelled ? '' : ' Puedes reintentar o abrir solo NFC y manual.'), error.cancelled ? '' : 'error');
        } finally { opening = false; button.disabled = false; if (location.measureButton) location.measureButton.disabled = false; closePortal.disabled = !portalId; start.disabled = !compatible || !portalMethods.includes('nfc'); }
    });
    closePortal.addEventListener('click', async () => {
        if (!portalId || busy || opening) return;
        closePortal.disabled = true;
        try { await stopReading(); await jsonRequest(root.dataset.portalCloseBase + '/' + portalId + '/close', {}); portalId = null; portalMethods = []; start.disabled = true; root.dataset.portal = ''; root.dataset.portalMethods = '[]'; root.querySelector('[data-portal-share]').hidden = true; document.querySelectorAll('[data-manual-portal]').forEach(input => input.value = ''); document.querySelectorAll('[data-manual-attendance]').forEach(form => form.querySelector('button').disabled = !form.dataset.pendingEvent); document.querySelector('[data-session-portal-status]').textContent = 'Cerrado'; status(panel, 'Registro cerrado.'); }
        catch(error) { status(panel, error.message, 'error'); closePortal.disabled = false; }
    });
    async function sendReading(packet) {
        if (busy) return; busy = true; retry.hidden = true; status(panel, 'Confirmando con el servidor…');
        try { const result = await jsonRequest(root.dataset.readingUrl, packet); if (!['recorded', 'already_recorded'].includes(result.status)) throw new Error('El servidor no confirmó la presencia.'); status(panel, `${result.student_name} (${result.student_code}): ${result.status === 'already_recorded' || result.replayed ? 'presencia ya registrada' : 'presencia confirmada'}.`, 'success'); const row = document.querySelector(`[data-student-row="${Number(result.student_id)}"] [data-attendance-state]`); if (row) row.textContent = 'Presente'; pending = null; memory.clear(); }
        catch (error) { status(panel, 'Sin confirmar. ' + error.message, 'error'); if (!error.status || error.status >= 500 || error.status === 429) { pending = packet; retry.hidden = false; } else { pending = null; memory.clear(); } }
        finally { busy = false; }
    }
    start.addEventListener('click', async () => {
        if (controller || busy || opening) return; if (!portalId) { status(panel, 'Abre primero una ventana con NFC habilitado.', 'error'); return; }
        start.disabled = true; busy = true;
        try {
            const result = await jsonRequest(root.dataset.readerUrl, { portal_id: Number(portalId), label: 'Terminal móvil de clase' }); readerId = result.id;
            controller = new AbortController(); const current = controller; const reader = new NDEFReader(); stop.disabled = false;
            reader.addEventListener('reading', event => { if (current.signal.aborted || busy) return; if (pending) { status(panel, 'Hay una lectura sin confirmar. Pulsa Reintentar envío o detén el lector.', 'error'); return; } const token = credentialFromMessage(event.message); if (!token) { status(panel, 'La etiqueta no contiene una credencial SmartAulaUES válida.', 'error'); return; } const packet = memory.packet({ reader_session_id: readerId, credential_token: token }); void sendReading(packet); }, { signal: current.signal });
            reader.addEventListener('readingerror', () => status(panel, 'No fue posible leer la etiqueta. Acércala nuevamente.', 'error'), { signal: current.signal });
            await reader.scan({ signal: current.signal }); if (!current.signal.aborted) status(panel, 'Lector activo. Acerca una etiqueta.');
        } catch (error) { status(panel, error.name === 'NotAllowedError' ? 'Permiso NFC denegado.' : error.message, 'error'); await stopReading(false); } finally { busy = false; }
    });
    async function stopReading(show = true) {
        controller?.abort(); controller = null; pending = null; memory.clear(); retry.hidden = true; const closing = readerId; readerId = null; stop.disabled = true; start.disabled = !compatible || !portalMethods.includes('nfc');
        if (closing) { try { await jsonRequest(root.dataset.readerCloseBase + '/' + closing, {}, 'DELETE'); } catch {} }
        if (show) status(panel, 'Lector detenido.');
    }
    stop.addEventListener('click', () => { void stopReading(); }); retry.addEventListener('click', () => { if (pending) void sendReading(pending); });
    document.addEventListener('visibilitychange', () => { if (document.hidden && controller) void stopReading(); });
}

export function initializeManualAttendance() {
    document.querySelectorAll('[data-manual-attendance]').forEach(form => {
        const memory = new EventMemory(); let busy = false, pendingPacket = null;
        form.addEventListener('submit', async event => { event.preventDefault(); if (busy) return; const values = new FormData(form); const packet = pendingPacket || memory.packet({ student_id: Number(values.get('student_id')), portal_id: Number(values.get('portal_id')), reason: values.get('reason') }); const button = form.querySelector('button'); let panel = form.querySelector('[data-manual-status]'); if (!panel) { panel = document.createElement('p'); panel.dataset.manualStatus = ''; panel.setAttribute('role', 'status'); form.append(panel); } busy = true; button.disabled = true;
            try { const result = await jsonRequest(form.action, packet); if (!['recorded', 'already_recorded'].includes(result.status)) throw new Error('No se confirmó el registro.'); form.closest('tr').querySelector('[data-attendance-state]').textContent = 'Presente'; panel.textContent = 'Presencia confirmada.'; pendingPacket = null; delete form.dataset.pendingEvent; memory.clear(); }
            catch (error) { panel.textContent = 'Sin confirmar. ' + error.message; if (error.status && error.status < 500 && error.status !== 429) { pendingPacket = null; delete form.dataset.pendingEvent; memory.clear(); } else { pendingPacket = packet; form.dataset.pendingEvent = 'true'; button.textContent = 'Reintentar confirmacion'; } }
            finally { busy = false; const console = document.querySelector('[data-attendance-console]'); const blocked = console && (!console.dataset.portal || resourceExpired(console.dataset.portalExpires) || !JSON.parse(console.dataset.portalMethods || '[]').includes('manual')); button.disabled = Boolean(blocked && !pendingPacket); }
        });
    });
}

export { initializeCredentials } from './credentials.js';

export function initializeCheckIn(root) {
    if (!root) return;
    const panel = root.querySelector('[data-checkin-status]'); const token = new URLSearchParams(window.location.hash.slice(1)).get('t');
    history.replaceState(null, '', window.location.pathname + window.location.search);
    if (!token || !/^[A-Za-z0-9_-]{43}$/.test(token)) { status(panel, 'Falta el enlace completo de la clase. Ábrelo nuevamente desde el QR o enlace compartido.', 'error'); root.querySelectorAll('button').forEach(button => button.disabled = true); return; }
    const login = root.querySelector('[data-checkin-login]');
    login?.addEventListener('submit', async event => { event.preventDefault(); const values = new FormData(login); const button = login.querySelector('button'); button.disabled = true; try { await jsonRequest(root.dataset.loginUrl, { email: values.get('email'), password: values.get('password') }); history.replaceState(null, '', window.location.pathname + window.location.search + '#t=' + encodeURIComponent(token)); window.location.reload(); } catch(error) { status(panel, error.message, 'error'); button.disabled = false; } });
    const confirm = root.querySelector('[data-confirm-checkin]');
    if (!confirm) return;
    const fallback = root.querySelector('[data-geo-alternative]');
    const required = root.dataset.geoRequired === 'true';
    const location = createLocationAcquisition(root, JSON.parse(root.dataset.geoOptions));
    const flow = new CheckInFlow({ memory: new EventMemory(), acquire: () => location.acquire(), send: packet => jsonRequest(root.dataset.submitUrl, packet), values: { portal_token: token, method: root.dataset.method }, portalId: Number(root.dataset.portal), required });
    let completed = false;
    const refresh = () => {
        if (!completed && !flow.busy && !flow.pending && resourceExpired(root.dataset.checkinExpires)) {
            confirm.disabled = true;
            status(panel, 'La ventana de registro venció o fue cerrada. Solicita un acceso vigente al docente.');
        }
    };
    setInterval(refresh, 1000); window.addEventListener('focus', refresh); refresh();
    if (required && !locationCompatible()) fallback.hidden = false;
    confirm.addEventListener('click', async () => {
        if (flow.busy || location.busy) return;
        if (resourceExpired(root.dataset.checkinExpires) && !flow.pending) { refresh(); return; }
        confirm.disabled = true;
        if (location.measureButton) location.measureButton.disabled = true;
        try {
            const result = await flow.run(message => status(panel, message));
            if (!['recorded', 'already_recorded'].includes(result.status)) throw new Error('No se confirmó la presencia.');
            status(panel, result.status === 'already_recorded' || result.replayed ? 'Tu asistencia ya estaba registrada.' : (required ? 'Proximidad aproximada validada. Tu asistencia quedó confirmada.' : 'Tu asistencia quedó confirmada.'), 'success');
            confirm.textContent = 'Asistencia registrada';
            completed = true;
            fallback.hidden = true;
        } catch (error) {
            status(panel, error.cancelled ? error.message : 'Sin confirmar. ' + error.message, error.cancelled ? '' : 'error');
            confirm.disabled = false;
            confirm.textContent = flow.pending ? 'Reintentar envío' : 'Reintentar registro';
            fallback.hidden = !(flow.recommend || (required && !locationCompatible()));
        } finally { if (location.measureButton) location.measureButton.disabled = false; refresh(); }
    });
}
