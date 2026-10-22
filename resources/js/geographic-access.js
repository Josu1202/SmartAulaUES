export const alternativeRecommendation = 'No hemos podido validar tu ubicación. Intenta acercarte al docente o solicita registrar tu asistencia por NFC o de forma manual.';

export function locationCompatible(environment = globalThis) {
    return Boolean(environment.isSecureContext && environment.navigator?.geolocation?.watchPosition && environment.navigator?.geolocation?.clearWatch);
}

const geographicError = (message, code) => Object.assign(new Error(message), { geographic: true, code });
const cancelledError = () => Object.assign(new Error('Adquisición cancelada. Puedes reintentar.'), { name: 'AbortError', cancelled: true });

export function acquireLocation(options, environment = globalThis, { signal, onProgress = () => {} } = {}) {
    if (signal?.aborted || environment.document?.hidden) return Promise.reject(cancelledError());
    if (!locationCompatible(environment)) {
        return Promise.reject(Object.assign(new Error('La ubicación requiere HTTPS y un navegador compatible.'), { geographic: true, incompatible: true }));
    }
    return new Promise((resolve, reject) => {
        const duration = Math.min(15000, Math.max(1, Number(options.timeout_ms) || 15000));
        const started = environment.performance.now();
        const remaining = () => Math.max(0, duration - (environment.performance.now() - started));
        const wallNow = () => environment.Date.now();
        let watchId = null, timer = null, best = null, settled = false, readings = 0;
        let lastError = geographicError('No se obtuvo una ubicación reciente. Comprueba la señal y reintenta.', 'geo_unavailable');
        const recent = reading => wallNow() - reading.timestamp <= options.max_evidence_age_ms && reading.timestamp - wallNow() <= options.future_tolerance_ms;
        const notify = (state, message = '') => onProgress({ state, accuracy: best?.accuracy ?? null, readings, message });
        function cleanup() {
            if (watchId !== null) { environment.navigator.geolocation.clearWatch(watchId); watchId = null; }
            if (timer !== null) { environment.clearTimeout(timer); timer = null; }
            signal?.removeEventListener('abort', abort);
            environment.document?.removeEventListener('visibilitychange', hidden);
            environment.removeEventListener?.('pagehide', abort);
        }
        function finish(error = null) {
            if (settled) return;
            settled = true;
            cleanup();
            if (best && !recent(best)) best = null;
            if (!error && !best) error = lastError;
            if (!error && best.accuracy > options.max_accuracy_m) error = geographicError('La precisión obtenida no es suficiente. Busca mejor señal y reintenta, o solicita NFC o registro manual.', 'geo_accuracy');
            notify(error?.cancelled ? 'cancelled' : error ? 'error' : 'ready', error?.message || `Adquisición terminada. El servidor comprobará que la distancia más ambas incertidumbres sea ≤ ${options.radius_m} m respecto a la referencia del docente.`);
            const selected = best; best = null;
            if (error) reject(error); else resolve(selected);
        }
        function abort() { finish(cancelledError()); }
        function hidden() { if (environment.document.hidden) abort(); }
        signal?.addEventListener('abort', abort, { once: true });
        environment.document?.addEventListener('visibilitychange', hidden);
        environment.addEventListener?.('pagehide', abort, { once: true });
        notify('acquiring');
        // The outer deadline includes waiting for permission, which the API's timeout may exclude.
        timer = environment.setTimeout(() => finish(), duration);
        try {
            watchId = environment.navigator.geolocation.watchPosition(position => {
                if (settled) return;
                if (remaining() === 0) { finish(); return; }
                const { latitude, longitude, accuracy } = position.coords ?? {};
                const reading = { latitude, longitude, accuracy, timestamp: position.timestamp };
                if (![latitude, longitude, accuracy, reading.timestamp].every(Number.isFinite) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || accuracy <= 0 || reading.timestamp <= 0) {
                    lastError = geographicError('La ubicación recibida no es válida. Reintenta.', 'geo_invalid'); return;
                }
                // Keep one candidate that will still be recent at selection; no movement history.
                if (!recent(reading) || wallNow() + remaining() - reading.timestamp > options.max_evidence_age_ms) {
                    lastError = geographicError('La lectura no es suficientemente reciente para este intento. Reintenta.', 'geo_stale'); return;
                }
                readings++;
                if (!best || !recent(best) || accuracy < best.accuracy || (accuracy === best.accuracy && reading.timestamp > best.timestamp)) best = reading;
                notify('acquiring');
            }, error => {
                if (settled) return;
                const messages = { 1: 'Permiso de ubicación denegado. Permítelo en el navegador y reintenta.', 2: 'Ubicación no disponible. Comprueba la señal y reintenta.', 3: 'Se agotó el tiempo para obtener tu ubicación. Reintenta.' };
                lastError = geographicError(messages[error.code] || 'No se pudo obtener tu ubicación. Reintenta.', error.code === 1 ? 'geo_denied' : 'geo_unavailable');
                if (error.code === 1) finish(lastError);
            }, { enableHighAccuracy: true, maximumAge: options.maximum_age_ms, timeout: duration });
            // Some adapters may synchronously deny permission before returning the watch identifier.
            if (settled && watchId !== null) { environment.navigator.geolocation.clearWatch(watchId); watchId = null; }
        } catch {
            finish(geographicError('No fue posible iniciar la adquisición de ubicación. Reintenta.', 'geo_unavailable'));
        }
    });
}

export function createLocationAcquisition(root, options, environment = globalThis) {
    const diagnostic = root.querySelector('[data-geo-diagnostic]');
    const cancel = root.querySelector('[data-cancel-location]');
    const measure = root.querySelector('[data-measure-location]');
    let controller = null;
    const format = value => (Math.ceil(value * 100) / 100).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function render({ state, accuracy, readings, message }) {
        if (!diagnostic) return;
        const obtained = accuracy === null ? 'sin lectura reciente' : `±${format(accuracy)} m`;
        const acquiring = state === 'acquiring' ? 'Buscando la mejor lectura durante un máximo de 15 segundos. ' : '';
        diagnostic.textContent = `${acquiring}Mejor precisión reciente obtenida: ${obtained}. Requisito por dispositivo: ≤ ${format(options.max_accuracy_m)} m. Lecturas válidas: ${readings}. ${message}`;
    }
    const acquisition = {
        get busy() { return controller !== null; },
        measureButton: measure,
        cancel() { controller?.abort(); },
        async acquire() {
            if (controller) throw new Error('Ya se está obteniendo la ubicación. Espera o cancela el intento actual.');
            controller = new AbortController();
            const controls = [...root.querySelectorAll('[data-measure-location], [data-confirm-checkin], [data-open-portal] button[type="submit"], [data-open-portal] button:not([type])')];
            const previous = controls.map(button => button.disabled);
            controls.forEach(button => button.disabled = true);
            if (cancel) cancel.hidden = false;
            render({ state: 'acquiring', accuracy: null, readings: 0, message: '' });
            let reported = false;
            try {
                return await acquireLocation(options, environment, { signal: controller.signal, onProgress: state => { reported = true; render(state); } });
            } catch (error) {
                if (!reported) render({ state: error.cancelled ? 'cancelled' : 'error', accuracy: null, readings: 0, message: error.message });
                throw error;
            } finally {
                controller = null;
                if (cancel) cancel.hidden = true;
                controls.forEach((button, index) => button.disabled = previous[index]);
            }
        },
    };
    cancel?.addEventListener('click', () => acquisition.cancel());
    let failures = 0;
    measure?.addEventListener('click', async () => {
        if (acquisition.busy) return;
        try {
            await acquisition.acquire(); failures = 0;
            const alternative = root.querySelector('[data-geo-alternative]'); if (alternative) alternative.hidden = true;
        }
        catch (error) {
            if (error.geographic) failures++;
            const alternative = root.querySelector('[data-geo-alternative]');
            if (alternative && (error.incompatible || failures >= 3)) alternative.hidden = false;
        }
    });
    return acquisition;
}

// A transport retry keeps its entire packet, even if the response was lost and its reading has aged.
// A definitive rejection discards it; the next acquisition gets a new operation identity.
export class CheckInFlow {
    constructor({ memory, acquire, send, values, portalId, required }) {
        Object.assign(this, { memory, acquire, send, values, portalId, required });
        this.pending = null; this.failures = 0; this.busy = false; this.recommend = false;
    }
    async run(notify = () => {}) {
        if (this.busy) return null;
        this.busy = true;
        try {
            if (!this.pending) {
                const values = { ...this.values };
                if (this.required) {
                    notify('Obteniendo tu ubicación…');
                    values.location = { ...await this.acquire(), portal_id: this.portalId };
                }
                this.pending = this.memory.packet(values);
            }
            notify('Validando y confirmando con el servidor…');
            const result = await this.send(this.pending);
            this.pending = null; this.memory.clear(); this.failures = 0; this.recommend = false;
            return result;
        } catch (error) {
            if (error.geographic || error.code?.startsWith('geo_')) {
                this.failures += 1;
                this.recommend ||= error.incompatible || this.failures >= 3;
            }
            if (error.status && error.status < 500 && ![408, 429].includes(error.status)) {
                this.pending = null; this.memory.clear();
            }
            throw error;
        } finally { this.busy = false; }
    }
}
