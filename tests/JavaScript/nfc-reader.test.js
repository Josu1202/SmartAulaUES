import test from "node:test";
import assert from "node:assert/strict";
import { inspectNdef } from "../../resources/js/nfc-reader.js";
const text = (value) => ({
    recordType: "text",
    encoding: "utf-8",
    data: new TextEncoder().encode(value),
});
test("recognizes the proposed SIAA credential format without returning its token", () =>
    assert.equal(
        inspectNdef({ records: [text("SIAA:C1:" + "A".repeat(43))] }),
        "siaa",
    ));
test("rejects incomplete credentials", () =>
    assert.equal(inspectNdef({ records: [text("SIAA:C1:short")] }), "other"));
test("does not accept multiple text records", () =>
    assert.equal(
        inspectNdef({
            records: [text("SIAA:C1:" + "A".repeat(43)), text("other")],
        }),
        "other",
    ));
test("does not accept URL records", () =>
    assert.equal(
        inspectNdef({
            records: [{ recordType: "url", data: new Uint8Array() }],
        }),
        "other",
    ));
test("handles missing records", () => assert.equal(inspectNdef({}), "other"));
test("handles an unreadable encoding", () =>
    assert.equal(
        inspectNdef({
            records: [
                {
                    recordType: "text",
                    encoding: "invalid-encoding",
                    data: new Uint8Array(),
                },
            ],
        }),
        "unreadable",
    ));
test("accepts DataView data supplied by NDEFReader", () => {
    const data = new TextEncoder().encode("SIAA:C1:" + "A".repeat(43));
    assert.equal(
        inspectNdef({
            records: [
                {
                    recordType: "text",
                    encoding: "utf-8",
                    data: new DataView(data.buffer),
                },
            ],
        }),
        "siaa",
    );
});
test("rejects line breaks and extra whitespace in credentials", () =>
    assert.equal(
        inspectNdef({ records: [text(" SIAA:C1:" + "A".repeat(43))] }),
        "other",
    ));
