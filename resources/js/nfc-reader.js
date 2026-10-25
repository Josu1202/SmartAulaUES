/** Inspect NDEF without exposing a credential, UID or personal data. */
export function inspectNdef(message) {
    if (
        !message ||
        !Array.isArray(message.records) ||
        message.records.length !== 1
    )
        return "other";
    const record = message.records[0];
    if (record.recordType !== "text") return "other";
    try {
        const value = new TextDecoder(record.encoding || "utf-8", {
            fatal: true,
        }).decode(record.data);
        return /^SIAA:C1:[A-Za-z0-9_-]{43}$/.test(value) ? "smartaulaues" : "other";
    } catch {
        return "unreadable";
    }
}

export function initializeNfcDiagnostic(root) {
    if (!root) return;
    const start = root.querySelector("[data-nfc-start]");
    const stop = root.querySelector("[data-nfc-stop]");
    const status = root.querySelector("[data-nfc-status]");
    let controller = null;
    let count = 0;
    const show = (text, state = "idle") => {
        status.textContent = text;
        status.dataset.state = state;
    };
    if (!window.isSecureContext) {
        start.disabled = true;
        show(
            "Abre esta página desde una dirección HTTPS para acceder a NFC.",
            "error",
        );
        return;
    }
    if (!("NDEFReader" in window)) {
        start.disabled = true;
        show(
            "Este navegador no permite leer etiquetas. Usa Chrome en un Android con NFC habilitado.",
            "error",
        );
        return;
    }
    show(
        "Este navegador permite leer etiquetas. Inicia la lectura para comprobar el dispositivo.",
    );
    start.addEventListener("click", async () => {
        if (controller) return;
        controller = new AbortController();
        const scanController = controller;
        start.disabled = true;
        stop.disabled = false;
        show("Solicitando permiso de lectura…");
        try {
            const reader = new window.NDEFReader();
            reader.addEventListener(
                "reading",
                (event) => {
                    if (scanController.signal.aborted) return;
                    const kind = inspectNdef(event.message);
                    count++;
                    show(
                        kind === "smartaulaues"
                            ? `Etiqueta SmartAulaUES reconocida. Lectura ${count}. Esta lectura no registra asistencias.`
                            : kind === "unreadable"
                              ? "El contenido no pudo interpretarse. Acerca otra etiqueta."
                              : "Etiqueta NDEF leída. Su contenido no corresponde al formato SmartAulaUES.",
                        kind === "unreadable" ? "error" : "reading",
                    );
                },
                { signal: scanController.signal },
            );
            reader.addEventListener(
                "readingerror",
                () =>
                    show(
                        "No fue posible leer la etiqueta. Acércala nuevamente.",
                        "error",
                    ),
                { signal: scanController.signal },
            );
            await reader.scan({ signal: scanController.signal });
            if (!scanController.signal.aborted)
                show("Lector activo. Acerca una etiqueta NDEF.", "reading");
        } catch (error) {
            if (!scanController.signal.aborted) {
                show(
                    error.name === "NotAllowedError"
                        ? "El permiso NFC fue denegado. Revisa los permisos del navegador."
                        : "No se pudo iniciar NFC. Comprueba que tu dispositivo tiene NFC y que está encendido.",
                    "error",
                );
                controller = null;
                start.disabled = false;
                stop.disabled = true;
            }
        }
    });
    const stopReading = () => {
        controller?.abort();
        controller = null;
        start.disabled = false;
        stop.disabled = true;
        show("Lector detenido. Puedes iniciar otra lectura.");
    };
    stop.addEventListener("click", stopReading);
    document.addEventListener("visibilitychange", () => {
        if (document.hidden && controller) stopReading();
    });
}
