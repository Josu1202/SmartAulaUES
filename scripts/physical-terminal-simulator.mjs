import { randomUUID } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";
import { parseArgs } from "node:util";

const { values, positionals: [operation] } = parseArgs({
    allowPositionals: true,
    options: { assignment: { type: "string" }, replay: { type: "boolean" }, failure: { type: "string" }, "tag-file": { type: "string" } },
});
const configured = process.env.SMARTAULAUES_TERMINAL_URL;
const secret = process.env.SMARTAULAUES_TERMINAL_SECRET;
if (!configured || !/^[a-f0-9]{64}$/.test(secret ?? "")) {
    console.error("Configura SMARTAULAUES_TERMINAL_URL y SMARTAULAUES_TERMINAL_SECRET en el entorno privado.");
    process.exit(2);
}
const base = new URL(configured);
const local = ["localhost", "127.0.0.1", "[::1]"].includes(base.hostname);
if ((base.protocol !== "https:" && !(local && base.protocol === "http:")) || base.username || base.password) {
    console.error("Utiliza HTTPS o HTTP exclusivamente sobre loopback.");
    process.exit(2);
}

async function request(path, data) {
    const response = await fetch(new URL("/api/v1/terminal/" + path, base), {
        method: data === undefined ? "GET" : "POST", redirect: "error", signal: AbortSignal.timeout(7000),
        headers: { Authorization: "Bearer " + secret, Accept: "application/json", "Content-Type": "application/json" },
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok) throw new Error("La API respondió HTTP " + response.status + (result.code ? " (" + result.code + ")" : ""));
    return result;
}

async function assignment() {
    const id = values.assignment ?? (await request("state")).assignment?.id;
    if (!id) throw new Error("Vincula esta terminal a un portal desde la sesión.");
    return id;
}

async function run() {
    switch (operation) {
        case "heartbeat":
            return request("heartbeat", { firmware: "simulator-1" });
        case "state": {
            const result = await request("state");
            if (result.assignment?.challenge) delete result.assignment.challenge.token;
            return result;
        }
        case "qr":
            // This command deliberately presents the short token like the OLED would.
            return request("qr-challenges", { assignment_id: await assignment() });
        case "write": {
            const { command } = await request("commands/claim", {});
            if (!command) throw new Error("Prepara un trabajo de escritura desde Coordinación.");
            const path = "commands/" + command.id;
            if (values.failure) return request(path + "/result", { lease_id: command.lease_id, failure: values.failure });
            let readback = command.credential_token;
            if (values["tag-file"]) {
                await writeFile(values["tag-file"], readback, { flag: "wx", mode: 0o600 });
                readback = await readFile(values["tag-file"], "utf8");
            }
            const packet = { lease_id: command.lease_id, readback };
            if (values.replay) await request(path + "/confirm-write", packet);
            return request(path + "/confirm-write", packet);
        }
        case "nfc": {
            const credential = values["tag-file"] ? await readFile(values["tag-file"], "utf8") : process.env.SMARTAULAUES_TERMINAL_NDEF;
            if (!/^SIAA:C1:[A-Za-z0-9_-]{43}$/.test(credential ?? "")) throw new Error("Configura la lectura ficticia en SMARTAULAUES_TERMINAL_NDEF.");
            const packet = { event_id: randomUUID(), assignment_id: await assignment(), credential_token: credential };
            if (values.replay) await request("nfc-readings", packet);
            return request("nfc-readings", packet);
        }
        default:
            throw new Error("Usa heartbeat, state, qr, write [--replay | --failure timeout] o nfc [--replay].");
    }
}

try {
    console.log(JSON.stringify(await run(), null, 2));
} catch (error) {
    console.error(error.message);
    process.exitCode = 1;
}
