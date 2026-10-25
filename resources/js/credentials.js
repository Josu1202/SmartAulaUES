import { credentialFromMessage, jsonRequest } from './attendance-live.js';

export function initializeCredentials(root) {
    if (!root) return;
    const form = root.querySelector('[data-issue-credential]');
    const select = form.querySelector('[name="student_id"]');
    const prepare = form.querySelector('button');
    const write = root.querySelector('[data-write-credential]');
    const confirm = root.querySelector('[data-confirm-credential]');
    const panel = root.querySelector('[data-credential-status]');
    const batchPanel = root.querySelector('[data-batch-status]');
    const compatible = window.isSecureContext && 'NDEFReader' in window;
    let pending = null, controller = null, busy = false, generation = 0, queue = [];
    const show = (text, state = '') => { panel.textContent = text; panel.dataset.state = state; };
    const progress = text => {
        const row = root.querySelector(`[data-batch-result="${pending?.studentId}"]`);
        if (row) row.textContent = text;
    };
    const controls = () => {
        prepare.disabled = busy;
        select.disabled = busy || queue.length > 0;
        write.disabled = busy || !compatible || !pending || Date.parse(pending.expires) <= Date.now();
        confirm.disabled = busy || !compatible || !pending?.written || Boolean(controller);
    };
    const stop = () => { controller?.abort(); controller = null; controls(); };
    root.querySelector('[data-cancel-credential]')?.addEventListener('click', () => {
        generation++; stop(); progress('Cancelada; alta pendiente sin activar'); pending = null; queue = []; controls();
        show('Proceso cancelado. Se conserva la credencial activa anterior. El alta pendiente vencerá.');
        batchPanel.textContent = 'Lote cancelado. Las credenciales ya activadas se conservan.';
    });
    root.querySelector('[data-start-batch]')?.addEventListener('click', () => {
        if (busy || pending) { show('Termina o cancela la credencial actual antes de iniciar otro lote.'); return; }
        queue = [...root.querySelectorAll('[data-batch-student]:checked')].map(el => ({id: Number(el.value), label: el.dataset.label}));
        if (!queue.length) { batchPanel.textContent = 'Selecciona al menos un estudiante.'; return; }
        select.value = String(queue[0].id); controls();
        batchPanel.textContent = `${queue.length} pendientes. Prepara ${queue[0].label}.`;
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        stop(); const attempt = ++generation; busy = true;
        const studentId = queue[0]?.id || Number(select.value);
        pending = null; controls();
        try {
            const result = await jsonRequest(form.action, {student_id: studentId});
            if (attempt !== generation) return;
            pending = {id: result.credential_id, token: result.token, expires: result.expires_at, studentId, written: false};
            progress('Preparada');
            show(compatible ? 'Alta preparada. Escribe y después lee la misma etiqueta para confirmarla.' : 'Alta preparada. Necesitas HTTPS y un Android compatible para escribirla y verificarla.');
        } catch (error) { if (attempt === generation) show(error.message, 'error'); }
        finally { busy = false; controls(); }
    });
    write.addEventListener('click', async () => {
        if (!pending || busy || Date.parse(pending.expires) <= Date.now()) return;
        busy = true; const attempt = generation; controller = new AbortController(); controls();
        try {
            await new NDEFReader().write({records: [{recordType: 'text', data: pending.token}]}, {signal: controller.signal});
            if (attempt !== generation) return;
            pending.written = true; progress('Escrita; falta verificar'); show('Escritura terminada. Lee la misma etiqueta para activarla.');
        } catch (error) { if (attempt === generation) { progress('Error de escritura; reintentar'); show(error.name === 'NotAllowedError' ? 'Permiso NFC denegado.' : 'No se pudo escribir. Reintenta con una etiqueta NDEF regrabable.', 'error'); } }
        finally { controller = null; busy = false; controls(); }
    });
    confirm.addEventListener('click', async () => {
        if (!pending?.written || busy || controller) return;
        controller = new AbortController(); const current = controller; const attempt = generation; controls();
        const reader = new NDEFReader();
        reader.addEventListener('reading', async event => {
            if (current.signal.aborted || busy || attempt !== generation) return;
            const token = credentialFromMessage(event.message);
            if (token !== pending.token) { show('La etiqueta no coincide con esta alta.', 'error'); return; }
            current.abort(); controller = null; busy = true; controls(); progress('Verificada; confirmando servidor');
            try {
                await jsonRequest(`${root.dataset.confirmBase}/${pending.id}/confirm`, {credential_token: token});
                if (attempt !== generation) return;
                progress('Activada'); pending = null;
                show('Etiqueta verificada y activada. Su reemplazo se confirmó en el servidor.', 'success');
                if (queue.length) {
                    queue.shift(); if (queue.length) select.value = String(queue[0].id);
                    batchPanel.textContent = queue.length ? `${queue.length} pendientes. Prepara ${queue[0].label}.` : 'Lote completado. Cada credencial fue verificada y confirmada individualmente.';
                }
            } catch (error) { if (attempt === generation) { progress('Sin confirmar; reintentar lectura'); show('Sin confirmar. '+error.message+' Puedes repetir la lectura para recuperar una confirmación perdida.', 'error'); } }
            finally { busy = false; controls(); }
        }, {signal: current.signal});
        reader.addEventListener('readingerror', () => show('No se pudo leer. Acerca la etiqueta otra vez.', 'error'), {signal: current.signal});
        try { await reader.scan({signal: current.signal}); if (!current.signal.aborted) show('Acerca la misma etiqueta escrita.'); }
        catch { if (attempt === generation) { stop(); show('No se pudo iniciar la lectura. Revisa los permisos NFC.', 'error'); } }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    window.addEventListener('pagehide', stop);
    setInterval(() => {
        controls();
        if (pending && Date.parse(pending.expires) <= Date.now() && !busy && !controller) {
            show('El plazo del alta venció. Puedes repetir la lectura si faltó la respuesta de activación; si no se activó, prepara otra alta.', 'error');
        }
    }, 1000);
    controls();
}
