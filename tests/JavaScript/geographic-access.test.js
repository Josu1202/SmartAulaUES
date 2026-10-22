import test from 'node:test';
import assert from 'node:assert/strict';
import { acquireLocation, createLocationAcquisition, CheckInFlow, locationCompatible } from '../../resources/js/geographic-access.js';
import { EventMemory } from '../../resources/js/attendance-live.js';

const options = { timeout_ms: 15000, maximum_age_ms: 0, radius_m: 30, max_accuracy_m: 10, max_evidence_age_ms: 30000, future_tolerance_ms: 5000 };
const position = { coords: { latitude: 13.7, longitude: -89.2, accuracy: 1 }, timestamp: 10000 };
class Target extends EventTarget {
    listeners = new Set();
    addEventListener(type, callback, options) { this.listeners.add(callback); super.addEventListener(type, callback, options); }
    removeEventListener(type, callback) { this.listeners.delete(callback); super.removeEventListener(type, callback); }
}
function device() {
    let time = 0, id = 0, success, failure;
    const timers = new Map(), cleared = [], calls = [];
    const environment = Object.assign(new Target(), { isSecureContext: true, document: Object.assign(new Target(), { hidden: false }), Date: { now: () => 10000 + time }, performance: { now: () => time },
        setTimeout(callback, delay) { const key = ++id; timers.set(key, { at: time + delay, callback }); return key; },
        clearTimeout(key) { timers.delete(key); },
        navigator: { geolocation: {
            watchPosition(ok, fail, settings) { success = ok; failure = fail; calls.push(settings); return 0; },
            clearWatch(key) { cleared.push(key); },
        } },
    });
    return { environment, cleared, calls, timers,
        emit(changes = {}, timestamp = environment.Date.now()) { success({ coords: { ...position.coords, ...changes }, timestamp }); },
        error(code) { failure({ code }); },
        advance(milliseconds) { const end = time + milliseconds; while (true) { const first = [...timers.entries()].filter(([, value]) => value.at <= end).sort((a, b) => a[1].at - b[1].at)[0]; if (!first) break; time = first[1].at; timers.delete(first[0]); first[1].callback(); } time = end; },
        assertReleased() { assert.deepEqual(cleared, [0]); assert.equal(timers.size, 0); assert.equal(environment.listeners.size, 0); assert.equal(environment.document.listeners.size, 0); },
    };
}
const flow = overrides => {
    let id = 0;
    return new CheckInFlow({ memory: new EventMemory(() => `operation-${++id}`), required: true, portalId: 42, values: { method: 'qr', portal_token: 'private' }, acquire: async () => ({ ...position.coords, timestamp: position.timestamp }), send: async () => ({ status: 'recorded' }), ...overrides });
};

test('a 15 second window selects the most accurate recent reading, not the last or an average', async () => {
    const d = device(), progress = [];
    const request = acquireLocation(options, d.environment, { onProgress: value => progress.push(value) });
    d.emit({ accuracy: 12 }); d.advance(4000); d.emit({ accuracy: 1.5, latitude: 13.71 }); d.advance(4000); d.emit({ accuracy: 2 });
    assert.deepEqual(d.cleared, []); d.advance(7000);
    assert.deepEqual(await request, { ...position.coords, accuracy: 1.5, latitude: 13.71, timestamp: 14000 });
    assert.deepEqual(d.calls, [{ enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }]);
    assert.equal(progress.at(-1).readings, 3); assert.equal(progress.at(-1).accuracy, 1.5);
    assert.equal(JSON.stringify(progress).includes('latitude'), false); d.assertReleased();
});

test('equally accurate readings select the most recent timestamp', async () => {
    const d = device(); const request = acquireLocation(options, d.environment);
    d.emit(); d.advance(2000); d.emit({ latitude: 14 }); d.advance(13000);
    assert.equal((await request).latitude, 14); d.assertReleased();
});

test('the ten metre accuracy limit is inclusive and the completion message uses the configured radius', async () => {
    const d = device(), progress = [];
    const request = acquireLocation(options, d.environment, { onProgress: value => progress.push(value) });
    d.emit({ accuracy: 10 }); d.advance(15000);
    assert.equal((await request).accuracy, 10);
    assert.match(progress.at(-1).message, /distancia más ambas incertidumbres sea ≤ 30 m/);
    d.assertReleased();
});

test('poor precision waits for improvements and reports the best obtained value without submitting it', async () => {
    const d = device(), progress = []; const request = acquireLocation(options, d.environment, { onProgress: value => progress.push(value) });
    const rejection = assert.rejects(request, error => error.code === 'geo_accuracy' && error.geographic);
    d.emit({ accuracy: 30 }); d.advance(5000); d.emit({ accuracy: 18 }); d.advance(10000);
    await rejection; assert.equal(progress.at(-1).accuracy, 18); assert.equal(progress.at(-1).state, 'error'); d.assertReleased();
});

test('stale, invalid and future readings cannot displace a recent candidate', async () => {
    const d = device(); const request = acquireLocation(options, d.environment);
    d.emit({ accuracy: 0.1 }, -10000); // Would be older than 30s when selection ends.
    d.emit({ accuracy: 0.1 }, 16000); // Beyond the existing 5s future tolerance.
    for (const values of [{ latitude: 91 }, { longitude: -181 }, { latitude: Infinity }, { accuracy: NaN }, { accuracy: 0 }]) d.emit(values);
    d.emit({ accuracy: 2 }); d.advance(15000);
    assert.equal((await request).accuracy, 2); d.assertReleased();
});

test('the deadline expires even if permission stays pending without an API callback', async () => {
    const d = device(); const request = acquireLocation(options, d.environment);
    const rejection = assert.rejects(request, /ubicación reciente/); d.advance(15000); await rejection; d.assertReleased();
});

test('transient unavailable and timeout callbacks allow a later valid reading', async () => {
    for (const code of [2, 3]) {
        const d = device(); const request = acquireLocation(options, d.environment);
        d.error(code); d.advance(2000); d.emit(); d.advance(13000);
        assert.equal((await request).accuracy, 1); d.assertReleased();
    }
});

test('without a valid reading unavailable and timeout errors retain their specific messages', async () => {
    for (const [code, expected] of [[2, /no disponible/], [3, /tiempo/]]) {
        const d = device(); const request = acquireLocation(options, d.environment);
        const rejection = assert.rejects(request, expected); d.error(code); d.advance(15000); await rejection; d.assertReleased();
    }
});

test('permission denial ends immediately and releases the watch and deadline', async () => {
    const d = device(); const request = acquireLocation(options, d.environment);
    const rejection = assert.rejects(request, /denegado/); d.error(1); await rejection; d.assertReleased();
});

test('cancel, hidden tab and pagehide discard readings and ignore late callbacks', async () => {
    for (const action of ['cancel', 'hidden', 'pagehide']) {
        const d = device(), controller = new AbortController(), progress = [];
        const request = acquireLocation(options, d.environment, { signal: controller.signal, onProgress: value => progress.push(value) });
        const rejection = assert.rejects(request, error => error.cancelled && !error.geographic);
        d.emit(); d.advance(500);
        if (action === 'cancel') controller.abort();
        else if (action === 'hidden') { d.environment.document.hidden = true; d.environment.document.dispatchEvent(new Event('visibilitychange')); }
        else d.environment.dispatchEvent(new Event('pagehide'));
        await rejection; const count = progress.length; d.emit({ accuracy: 0.5 }); d.error(1); d.advance(20000);
        assert.equal(progress.length, count); d.assertReleased();
    }
});

test('a previously cancelled or hidden attempt does not start acquisition', async () => {
    for (const hidden of [false, true]) {
        const d = device(), controller = new AbortController();
        if (hidden) d.environment.document.hidden = true; else controller.abort();
        await assert.rejects(acquireLocation(options, d.environment, { signal: controller.signal }), error => error.cancelled);
        assert.equal(d.calls.length, 0); assert.equal(d.timers.size, 0);
    }
});

test('synchronous permission denial still clears the returned watch identifier', async () => {
    const d = device(); d.environment.navigator.geolocation.watchPosition = (success, failure) => { failure({ code: 1 }); return 0; };
    await assert.rejects(acquireLocation(options, d.environment), /denegado/); d.assertReleased();
});

test('a background-delayed deadline cannot select a now expired candidate', async () => {
    const d = device(); const request = acquireLocation(options, d.environment); d.emit();
    d.environment.Date.now = () => 100000;
    const rejection = assert.rejects(request, /ubicación reciente/); d.advance(15000); await rejection; d.assertReleased();
});

test('configuration cannot extend an attempt beyond the requested maximum of 15 seconds', async () => {
    const d = device(); const request = acquireLocation({ ...options, timeout_ms: 90000 }, d.environment); d.emit(); d.advance(15000);
    await request; assert.equal(d.calls[0].timeout, 15000); d.assertReleased();
});

function diagnosticRoot() {
    const button = () => Object.assign(new EventTarget(), { disabled: false, hidden: false });
    const diagnostic = { textContent: '' }, cancel = button(), measure = button(), submit = button(), alternative = { hidden: true };
    const selectors = { '[data-geo-diagnostic]': diagnostic, '[data-cancel-location]': cancel, '[data-measure-location]': measure, '[data-geo-alternative]': alternative };
    return { diagnostic, cancel, measure, submit, alternative, querySelector: selector => selectors[selector], querySelectorAll: () => [measure, submit] };
}

test('diagnostics display only accuracy and requirement, preserve decimals above the limit, and restore controls', async () => {
    const d = device(), root = diagnosticRoot(), acquisition = createLocationAcquisition(root, options, d.environment);
    const request = acquisition.acquire(); const rejection = assert.rejects(request, /no es suficiente/);
    assert.equal(root.cancel.hidden, false); assert.equal(root.submit.disabled, true);
    d.emit({ accuracy: 10.001 }); assert.match(root.diagnostic.textContent, /10,01 m/); assert.match(root.diagnostic.textContent, /10,00 m/);
    assert.equal(root.diagnostic.textContent.includes('13.7'), false);
    d.advance(15000); await rejection;
    assert.match(root.diagnostic.textContent, /no es suficiente/); assert.equal(root.cancel.hidden, true); assert.equal(root.submit.disabled, false); d.assertReleased();
});

test('the cancel control releases acquisition, restores controls and does not turn cancellation into failure', async () => {
    const d = device(), root = diagnosticRoot(), acquisition = createLocationAcquisition(root, options, d.environment);
    const request = acquisition.acquire(); const rejection = assert.rejects(request, error => error.cancelled);
    d.emit(); root.cancel.dispatchEvent(new Event('click')); await rejection;
    assert.match(root.diagnostic.textContent, /cancelada/); assert.equal(acquisition.busy, false); assert.equal(root.submit.disabled, false); d.assertReleased();
});

test('concurrent diagnostic calls cannot start a second watch', async () => {
    const d = device(), root = diagnosticRoot(), acquisition = createLocationAcquisition(root, options, d.environment);
    const first = acquisition.acquire(); await assert.rejects(acquisition.acquire(), /Ya se está/);
    d.emit(); d.advance(15000); await first; assert.equal(d.calls.length, 1); d.assertReleased();
});

test('three failed diagnostic measurements show alternatives and a fourth measurement is still allowed', async () => {
    const d = device(), root = diagnosticRoot(); createLocationAcquisition(root, options, d.environment);
    for (let index = 1; index <= 3; index++) {
        root.measure.dispatchEvent(new Event('click')); d.emit({ accuracy: 18 }); d.advance(15000);
        await Promise.resolve(); await Promise.resolve();
        assert.equal(root.alternative.hidden, index < 3); assert.equal(root.measure.disabled, false);
    }
    root.measure.dispatchEvent(new Event('click')); d.emit(); d.advance(15000);
    await Promise.resolve(); await Promise.resolve();
    assert.equal(d.calls.length, 4); assert.equal(root.measure.disabled, false); assert.equal(d.timers.size, 0); assert.equal(root.alternative.hidden, true);
});

test('cancelling registration acquisition never sends a packet or counts a geographic failure', async () => {
    const d = device(), controller = new AbortController(); let sent = 0;
    const current = flow({ acquire: () => acquireLocation(options, d.environment, { signal: controller.signal }), send: () => { sent++; } });
    const request = current.run(); const rejection = assert.rejects(request, error => error.cancelled); controller.abort(); await rejection;
    assert.equal(current.pending, null); assert.equal(current.failures, 0); assert.equal(sent, 0); d.assertReleased();
});

test('no request occurs on construction and non-presential flow never requests location', async () => {
    let acquired = 0, sent = 0;
    const current = flow({ required: false, acquire: () => { acquired++; }, send: async packet => { sent++; assert.equal('location' in packet, false); return { status: 'recorded' }; } });
    assert.equal(sent, 0); await current.run(); assert.equal(acquired, 0); assert.equal(sent, 1);
});

test('three geographic failures show recommendation and allow a fourth attempt', async () => {
    let acquired = 0, sent = 0;
    const current = flow({ acquire: async () => { acquired++; if (acquired <= 3) throw Object.assign(new Error('Ubicación denegada'), { geographic: true }); return position.coords; }, send: async () => { sent++; return { status: 'recorded' }; } });
    for (let i = 1; i <= 3; i++) { await assert.rejects(current.run()); assert.equal(current.failures, i); assert.equal(current.recommend, i >= 3); assert.equal(current.busy, false); }
    assert.equal(sent, 0); assert.equal((await current.run()).status, 'recorded'); assert.equal(sent, 1); assert.equal(current.recommend, false);
});

test('incompatibility shows alternatives immediately without disabling retry', async () => {
    assert.equal(locationCompatible({ isSecureContext: false }), false);
    const current = flow({ acquire: () => acquireLocation(options, { isSecureContext: false }) });
    await assert.rejects(current.run(), /HTTPS/); assert.equal(current.recommend, true); assert.equal(current.busy, false);
});

test('concurrent clicks do not acquire or submit another reading', async () => {
    let finish, acquisitions = 0, submissions = 0;
    const current = flow({ acquire: () => { acquisitions++; return new Promise(resolve => { finish = resolve; }); }, send: async () => { submissions++; return { status: 'recorded' }; } });
    const first = current.run(); assert.equal(current.busy, true); assert.equal(await current.run(), null);
    finish(position.coords); await first; assert.equal(acquisitions, 1); assert.equal(submissions, 1); assert.equal(current.busy, false);
});

test('network loss reuses exactly the original packet and UUID without reacquisition', async () => {
    let acquired = 0; const packets = [];
    const current = flow({ acquire: async () => { acquired++; return position.coords; }, send: async packet => { packets.push(packet); if (packets.length === 1) throw new Error('Network lost'); return { status: 'recorded', replayed: true }; } });
    await assert.rejects(current.run()); assert.ok(current.pending); await current.run();
    assert.equal(acquired, 1); assert.equal(packets[0], packets[1]); assert.equal(current.pending, null);
});

test('server geographic rejection discards evidence and the next attempt has a new UUID', async () => {
    const packets = [];
    const current = flow({ send: async packet => { packets.push(packet); if (packets.length <= 3) throw Object.assign(new Error('Outside'), { status: 422, code: 'geo_outside' }); return { status: 'recorded' }; } });
    for (let i = 0; i < 3; i++) await assert.rejects(current.run());
    assert.equal(current.recommend, true); assert.equal(current.pending, null); await current.run();
    assert.equal(new Set(packets.map(packet => packet.event_id)).size, 4);
    assert.equal(packets[0].location.portal_id, 42);
});
