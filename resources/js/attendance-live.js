import { inspectNdef } from './nfc-reader.js';

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

async function jsonRequest(url, body, method = 'POST') {
    const response = await fetch(url, { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body) });
    let result;
    try { result = await response.json(); } catch { throw new Error('Sin confirmación del servidor. Reintenta o actualiza tu sesión.'); }
    if (!response.ok) { const message = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'No se pudo completar la operación.'); const error = new Error(message); error.status = response.status; throw error; }
    return result;
}

function status(element, message, state = '') { element.textContent = message; element.dataset.state = state; }

export function initializeAcademicForms() {
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
    const select = document.querySelector('[data-format-select]'); const cycle = document.getElementById('cycle_id');
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
    let portalId = root.dataset.portal || null, readerId = null, controller = null, pending = null, busy = false;
    const memory = new EventMemory();
    const closePortal = root.querySelector('[data-live-close-portal]');
    const compatible = window.isSecureContext && 'NDEFReader' in window;
    if (!compatible) { start.disabled = true; status(panel, 'NFC requiere HTTPS y un Android compatible. Puedes usar los otros métodos habilitados.'); }
    root.querySelector('[data-open-portal]').addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return; const form = event.currentTarget; const button = form.querySelector('button'); button.disabled = true;
        try {
            await stopReading();
            const result = await jsonRequest(form.action, { minutes: Number(new FormData(form).get('minutes')), methods: new FormData(form).getAll('methods[]') });
            portalId = result.portal_id; root.dataset.portal = String(portalId); closePortal.disabled = false; document.querySelector('[data-session-portal-status]').textContent = 'Abierto';
            document.querySelectorAll('[data-manual-portal]').forEach(input => input.value = String(portalId));
            const share = root.querySelector('[data-portal-share]'); share.hidden = false;
            root.querySelector('[data-portal-expiry]').textContent = 'Registro abierto hasta ' + new Date(result.expires_at).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit', timeZone: 'America/El_Salvador' });
            const qr = root.querySelector('[data-portal-qr]'); qr.hidden = !result.qr; if (result.qr) qr.src = result.qr; else qr.removeAttribute('src');
            const copy = root.querySelector('[data-copy-link]'); copy.hidden = !result.url;
            copy.onclick = async () => { try { await navigator.clipboard.writeText(result.url); status(panel, 'Enlace copiado. Compártelo con los estudiantes de esta clase.'); } catch { status(panel, 'El navegador bloqueó copiar el enlace. Revisa los permisos del portapapeles.', 'error'); } };
            status(panel, 'Registro abierto. Ya puedes usar los métodos seleccionados.'); start.disabled = !compatible || !result.methods.includes('nfc');
        } catch (error) { status(panel, error.message, 'error'); } finally { button.disabled = false; }
    });
    closePortal.addEventListener('click', async () => {
        if (!portalId || busy) return;
        closePortal.disabled = true;
        try { await stopReading(); await jsonRequest(root.dataset.portalCloseBase + '/' + portalId + '/close', {}); portalId = null; root.dataset.portal = ''; root.querySelector('[data-portal-share]').hidden = true; document.querySelectorAll('[data-manual-portal]').forEach(input => input.value = ''); document.querySelector('[data-session-portal-status]').textContent = 'Cerrado'; status(panel, 'Registro cerrado.'); }
        catch(error) { status(panel, error.message, 'error'); closePortal.disabled = false; }
    });
    async function sendReading(packet) {
        if (busy) return; busy = true; retry.hidden = true; status(panel, 'Confirmando con el servidor…');
        try { const result = await jsonRequest(root.dataset.readingUrl, packet); if (!['recorded', 'already_recorded'].includes(result.status)) throw new Error('El servidor no confirmó la presencia.'); status(panel, `${result.student_name} (${result.student_code}): ${result.status === 'already_recorded' || result.replayed ? 'presencia ya registrada' : 'presencia confirmada'}.`, 'success'); const row = document.querySelector(`[data-student-row="${Number(result.student_id)}"] [data-attendance-state]`); if (row) row.textContent = 'Presente'; pending = null; memory.clear(); }
        catch (error) { status(panel, 'Sin confirmar. ' + error.message, 'error'); if (!error.status || error.status >= 500 || error.status === 429) { pending = packet; retry.hidden = false; } else { pending = null; memory.clear(); } }
        finally { busy = false; }
    }
    start.addEventListener('click', async () => {
        if (controller || busy) return; if (!portalId) { status(panel, 'Abre primero una ventana con NFC habilitado.', 'error'); return; }
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
        controller?.abort(); controller = null; pending = null; memory.clear(); retry.hidden = true; const closing = readerId; readerId = null; stop.disabled = true; start.disabled = !compatible;
        if (closing) { try { await jsonRequest(root.dataset.readerCloseBase + '/' + closing, {}, 'DELETE'); } catch {} }
        if (show) status(panel, 'Lector detenido.');
    }
    stop.addEventListener('click', () => { void stopReading(); }); retry.addEventListener('click', () => { if (pending) void sendReading(pending); });
    document.addEventListener('visibilitychange', () => { if (document.hidden && controller) void stopReading(); });
}

export function initializeManualAttendance() {
    document.querySelectorAll('[data-manual-attendance]').forEach(form => {
        const memory = new EventMemory(); let busy = false;
        form.addEventListener('submit', async event => { event.preventDefault(); if (busy) return; const values = new FormData(form); const packet = memory.packet({ student_id: Number(values.get('student_id')), portal_id: Number(values.get('portal_id')), reason: values.get('reason') }); const button = form.querySelector('button'); let panel = form.querySelector('[data-manual-status]'); if (!panel) { panel = document.createElement('p'); panel.dataset.manualStatus = ''; panel.setAttribute('role', 'status'); form.append(panel); } busy = true; button.disabled = true;
            try { const result = await jsonRequest(form.action, packet); if (!['recorded', 'already_recorded'].includes(result.status)) throw new Error('No se confirmó el registro.'); form.closest('tr').querySelector('[data-attendance-state]').textContent = 'Presente'; panel.textContent = 'Presencia confirmada.'; memory.clear(); }
            catch (error) { panel.textContent = 'Sin confirmar. ' + error.message; if (error.status && error.status < 500 && error.status !== 429) memory.clear(); }
            finally { busy = false; button.disabled = false; }
        });
    });
}

export function initializeCredentials(root) {
    if (!root) return;
    const panel = root.querySelector('[data-credential-status]'); const write = root.querySelector('[data-write-credential]'); const confirm = root.querySelector('[data-confirm-credential]'); let token = null, id = null, controller = null, busy = false;
    const compatible = window.isSecureContext && 'NDEFReader' in window;
    root.querySelector('[data-issue-credential]').addEventListener('submit', async event => { event.preventDefault(); if (busy) return; controller?.abort(); const form = event.currentTarget; const button = form.querySelector('button'); button.disabled = true; write.disabled = true; confirm.disabled = true; token = null; id = null;
        try { const result = await jsonRequest(form.action, { student_id: Number(new FormData(form).get('student_id')) }); token = result.token; id = result.credential_id; write.disabled = !compatible; status(panel, compatible ? 'Alta pendiente. Escribe la etiqueta y luego lee la misma etiqueta para activarla.' : 'Alta pendiente. Abre esta página desde HTTPS en un Android compatible para escribir y comprobar la etiqueta.'); }
        catch(error) { status(panel, error.message, 'error'); } finally { button.disabled = false; }
    });
    write.addEventListener('click', async () => { if (!token || busy) return; busy = true; write.disabled = true; try { await new NDEFReader().write({ records: [{ recordType: 'text', data: token }] }); confirm.disabled = false; status(panel, 'Escritura terminada. Pulsa Leer y activar etiqueta para comprobarla.'); } catch (error) { status(panel, error.name === 'NotAllowedError' ? 'Permiso NFC denegado.' : 'No se pudo escribir la etiqueta. Comprueba que sea NDEF regrabable.', 'error'); } finally { busy = false; write.disabled = false; } });
    confirm.addEventListener('click', async () => { if (!token || controller || busy) return; controller = new AbortController(); const current = controller; confirm.disabled = true; const reader = new NDEFReader(); reader.addEventListener('reading', async event => { if (current.signal.aborted || busy) return; const read = credentialFromMessage(event.message); if (read !== token) { status(panel, 'La etiqueta no coincide con esta alta pendiente.', 'error'); return; } busy = true;
        try { await jsonRequest(root.dataset.confirmBase + '/' + id + '/confirm', { credential_token: read }); current.abort(); controller = null; token = null; id = null; write.disabled = true; confirm.disabled = true; status(panel, 'Etiqueta comprobada y activada. La credencial anterior quedó revocada.', 'success'); }
        catch(error) { status(panel, error.message, 'error'); current.abort(); controller = null; confirm.disabled = false; }
        finally { busy = false; }
    }, { signal: current.signal }); reader.addEventListener('readingerror', () => status(panel, 'No se pudo leer la etiqueta. Intenta acercarla nuevamente.', 'error'), { signal: current.signal });
        try { await reader.scan({ signal: current.signal }); status(panel, 'Acerca la misma etiqueta que acabas de escribir.'); } catch(error) { current.abort(); controller = null; confirm.disabled = false; status(panel, error.name === 'NotAllowedError' ? 'Permiso NFC denegado.' : 'No fue posible iniciar la lectura.', 'error'); }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) { controller?.abort(); controller = null; if (token) confirm.disabled = !compatible; } });
}

export function initializeCheckIn(root) {
    if (!root) return;
    const panel = root.querySelector('[data-checkin-status]'); const token = new URLSearchParams(window.location.hash.slice(1)).get('t');
    history.replaceState(null, '', window.location.pathname + window.location.search);
    if (!token || !/^[A-Za-z0-9_-]{43}$/.test(token)) { status(panel, 'Falta el enlace completo de la clase. Ábrelo nuevamente desde el QR o enlace compartido.', 'error'); root.querySelectorAll('button').forEach(button => button.disabled = true); return; }
    const login = root.querySelector('[data-checkin-login]');
    login?.addEventListener('submit', async event => { event.preventDefault(); const values = new FormData(login); const button = login.querySelector('button'); button.disabled = true; try { await jsonRequest(root.dataset.loginUrl, { email: values.get('email'), password: values.get('password') }); history.replaceState(null, '', window.location.pathname + window.location.search + '#t=' + encodeURIComponent(token)); window.location.reload(); } catch(error) { status(panel, error.message, 'error'); button.disabled = false; } });
    const confirm = root.querySelector('[data-confirm-checkin]'); const memory = new EventMemory(); let busy = false;
    confirm?.addEventListener('click', async () => { if (busy) return; busy = true; confirm.disabled = true; status(panel, 'Confirmando con el servidor…'); const packet = memory.packet({ portal_token: token, method: root.dataset.method }); try { const result = await jsonRequest(root.dataset.submitUrl, packet); if (!['recorded', 'already_recorded'].includes(result.status)) throw new Error('No se confirmó la presencia.'); status(panel, result.status === 'already_recorded' || result.replayed ? 'Tu presencia ya estaba registrada.' : 'Tu presencia quedó confirmada.', 'success'); memory.clear(); } catch(error) { status(panel, 'Sin confirmar. ' + error.message, 'error'); confirm.disabled = false; if (error.status && error.status < 500 && error.status !== 429) memory.clear(); } finally { busy = false; } });
}
