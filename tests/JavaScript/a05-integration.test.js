import test from 'node:test';
import assert from 'node:assert/strict';
import {jsonRequest, resourceExpired} from '../../resources/js/attendance-live.js';
import {sharedTagUrl} from '../../resources/js/shared-tag.js';

test('HTML errors retain status and actionable messages for login, CSRF and throttling', async () => {
    const originalFetch = globalThis.fetch, originalDocument = globalThis.document;
    globalThis.document = {querySelector: () => ({content: 'synthetic-csrf'})};
    try {
        for (const status of [401, 403, 419, 422, 429, 503]) {
            globalThis.fetch = async () => ({ok: false, status, json: async () => { throw new SyntaxError(); }});
            await assert.rejects(jsonRequest('/synthetic', {}), error => error.status === status && error.message.length > 10);
        }
    } finally { globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('JSON validation details and password-change code survive HTTP failures', async () => {
    const originalFetch = globalThis.fetch, originalDocument = globalThis.document;
    globalThis.document = {querySelector: () => ({content: 'synthetic-csrf'})};
    try {
        globalThis.fetch = async () => ({ok: false, status: 403, json: async () => ({code: 'password_change_required', message: 'Cambia la contraseña'})});
        await assert.rejects(jsonRequest('/synthetic', {}), error => error.status === 403 && error.code === 'password_change_required');
        globalThis.fetch = async () => ({ok: false, status: 422, json: async () => ({errors: {location: ['Precisión insuficiente']}})});
        await assert.rejects(jsonRequest('/synthetic', {}), error => error.geographic && error.message === 'Precisión insuficiente');
    } finally { globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('expiry is exclusive at the exact millisecond and fails closed for invalid dates', () => {
    const at = Date.parse('2026-10-24T17:00:00Z');
    assert.equal(resourceExpired('2026-10-24T17:00:00Z', at - 1), false);
    assert.equal(resourceExpired('2026-10-24T17:00:00Z', at), true);
    assert.equal(resourceExpired('2026-10-24T17:00:00Z', at + 1), true);
    assert.equal(resourceExpired('invalid', at), true);
});

test('an unexpected successful HTML response remains retryable without losing confirmation identity', async () => {
    const originalFetch = globalThis.fetch, originalDocument = globalThis.document;
    globalThis.document = {querySelector: () => ({content: 'synthetic-csrf'})};
    try {
        globalThis.fetch = async () => ({ok: true, status: 200, json: async () => { throw new SyntaxError('HTML'); }});
        await assert.rejects(jsonRequest('/synthetic', {}), error => error.status === undefined && error.message.includes('Reintenta'));
    } finally { globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('shared tag accepts only one contextual URL of this installation', () => {
    const origin = 'https://campus.example.test';
    const valid = origin+'/check-in/12?method=nfc_tag#t='+'A'.repeat(43);
    const message = value => ({records: [{recordType: 'url', data: new TextEncoder().encode(value)}]});
    assert.equal(sharedTagUrl(message(valid), origin), valid);
    assert.equal(sharedTagUrl(message(valid.replace(origin, 'https://other.example.test')), origin), null);
    assert.equal(sharedTagUrl(message(valid.replace('nfc_tag', 'nfc')), origin), null);
    assert.equal(sharedTagUrl(message(valid.replace('/12', '/0')), origin), null);
    assert.equal(sharedTagUrl(message(valid.slice(0, -1)), origin), null);
    assert.equal(sharedTagUrl({records: [...message(valid).records, ...message(valid).records]}, origin), null);
    assert.equal(sharedTagUrl({records: [{recordType: 'text', data: new TextEncoder().encode(valid)}]}, origin), null);
});
