import test from 'node:test';
import assert from 'node:assert/strict';
import { tourIndex, tourProgressLabel } from '../../resources/js/guide-tour.js';

test('B04: tour navigation never leaves the available steps', () => {
    assert.equal(tourIndex(0, -1, 5), 0);
    assert.equal(tourIndex(4, 1, 5), 4);
    assert.equal(tourIndex(0, 1, 5), 1);
    assert.equal(tourIndex(1, -1, 5), 0);
});

test('B04: progress text is legible and works with an empty list', () => {
    assert.equal(tourProgressLabel(2, 5), 'Paso 3 de 5');
    assert.equal(tourIndex(0, 1, 0), 0);
});
