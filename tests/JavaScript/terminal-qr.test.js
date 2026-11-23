import assert from "node:assert/strict";
import test from "node:test";
import jsQR from "jsqr";
import { isTerminalToken } from "../../resources/js/terminal-qr.js";

test("terminal tokens accept exactly 20 uppercase Base32 characters", () => {
    assert.ok(isTerminalToken("ABCDEFGHIJKLMNOPQRST"));
    assert.ok(isTerminalToken("23456723456723456723"));
    for (const invalid of ["ABCDEFGHIJKLMNOPQRS", "abcdefghijklmnopqrst", "0BCDEFGHIJKLMNOPQRST", "ABCDEFGHIJKLMNOPQRS\n", "ABCDEFGHIJKLMNOPQRST\n", "https://example.test/t", null]) {
        assert.equal(isTerminalToken(invalid), false);
    }
});

test("decodes an independently encoded Version 1-M alphanumeric QR at 58 pixels", () => {
    // Generated with node-qrcode 1.5.4 and an explicit alphanumeric segment.
    const modules = [
        "111111100001001111111", "100000100110001000001", "101110101111101011101",
        "101110101101001011101", "101110101010101011101", "100000101010101000001",
        "111111101010101111111", "000000001100000000000", "101111100010101111100",
        "000111000111100011110", "010100101100111000111", "111110000100000001100",
        "001101101000001011111", "000000001011111010110", "111111100101011100000",
        "100000101100011010001", "101110101010010110001", "101110101000110100100",
        "101110101101001101100", "100000100110100011101", "111111101110001001100",
    ];
    const width = 58;
    const image = new Uint8ClampedArray(width * width * 4).fill(255);
    modules.forEach((row, y) => Array.from(row).forEach((module, x) => {
        if (module === "0") return;
        for (let dy = 0; dy < 2; dy++) {
            for (let dx = 0; dx < 2; dx++) {
                const offset = ((8 + y * 2 + dy) * width + 8 + x * 2 + dx) * 4;
                image.fill(0, offset, offset + 3);
            }
        }
    }));
    assert.equal(jsQR(image, width, width)?.data, "ABCDEFGHIJKLMNOPQRST");
});
