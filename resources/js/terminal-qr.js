import { acquireLocation } from "./geographic-access.js";

export const isTerminalToken = (value) => typeof value === "string" && value.length === 20 && !/[^A-Z2-7]/.test(value);

export function initializeTerminalQr(root) {
    const video = root.querySelector("video");
    const camera = root.querySelector("[data-camera]");
    const confirm = root.querySelector("[data-confirm]");
    const status = root.querySelector("[data-status]");
    const manual = root.querySelector("[data-manual]");
    const input = manual.querySelector("input");
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let stream, timer, context, packet, operation;

    function stopCamera() {
        clearTimeout(timer);
        stream?.getTracks().forEach((track) => track.stop());
        stream = undefined;
        video.srcObject = null;
        video.hidden = true;
    }

    function reset() {
        operation?.abort();
        operation = undefined;
        stopCamera();
        context = packet = undefined;
        input.value = "";
        confirm.hidden = true;
        confirm.disabled = false;
        confirm.textContent = "Confirmar asistencia";
        camera.disabled = false;
    }

    function startOperation() {
        operation?.abort();
        operation = new AbortController();
        camera.disabled = true;
        return operation.signal;
    }

    async function send(url, body, signal) {
        const requestSignal = typeof AbortSignal.any === "function"
            ? AbortSignal.any([signal, AbortSignal.timeout(12000)])
            : signal;
        const response = await fetch(url, {
            method: "POST", credentials: "same-origin", cache: "no-store", signal: requestSignal,
            headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify(body),
        });
        const data = await response.json();
        if (!response.ok) {
            throw Object.assign(new Error(data.message || "No se pudo completar la solicitud."), { status: response.status });
        }
        return data;
    }

    async function resolve(token) {
        if (!isTerminalToken(token)) return;
        reset();
        const signal = startOperation();
        status.textContent = "Consultando el código…";
        try {
            const data = await send(root.dataset.resolve, { short_token: token }, signal);
            if (signal.aborted) return;
            context = { ...data, short_token: token };
            status.textContent = data.subject + (data.requires_location ? " · Al confirmar se solicitará tu ubicación." : " · Confirma tu asistencia.");
            confirm.hidden = false;
        } catch (error) {
            if (signal.aborted) return;
            status.textContent = error.message;
        } finally {
            if (!signal.aborted) camera.disabled = false;
        }
    }

    async function decoder() {
        if ("BarcodeDetector" in window) {
            try {
                if ((await window.BarcodeDetector.getSupportedFormats()).includes("qr_code")) {
                    const detector = new window.BarcodeDetector({ formats: ["qr_code"] });
                    return async () => (await detector.detect(video)).map(({ rawValue }) => rawValue);
                }
            } catch {
                // Fall back to local pixel decoding.
            }
        }
        const { default: jsQR } = await import("jsqr");
        const canvas = document.createElement("canvas");
        const canvasContext = canvas.getContext("2d", { willReadFrequently: true });
        return () => {
            const scale = Math.min(1, 800 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = canvasContext.getImageData(0, 0, canvas.width, canvas.height);
            const result = jsQR(image.data, image.width, image.height);
            return result ? [result.data] : [];
        };
    }

    camera.addEventListener("click", async () => {
        reset();
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = "La cámara requiere HTTPS y un navegador compatible.";
            return;
        }
        const signal = startOperation();
        try {
            const receivedStream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: "environment" } } });
            if (signal.aborted) {
                receivedStream.getTracks().forEach((track) => track.stop());
                return;
            }
            stream = receivedStream;
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            const decode = await decoder();
            if (signal.aborted) return;
            status.textContent = "Busca el QR corto en la pantalla de la terminal.";
            async function frame() {
                if (signal.aborted) return;
                try {
                    const values = video.readyState >= 2 ? await decode() : [];
                    if (signal.aborted) return;
                    const token = values.find(isTerminalToken);
                    if (token) {
                        void resolve(token);
                        return;
                    }
                    if (values.length) status.textContent = "El código leído no es un QR de terminal.";
                    timer = setTimeout(frame, 180);
                } catch {
                    if (signal.aborted) return;
                    reset();
                    status.textContent = "No se pudo leer el vídeo. Reintenta o introduce el token.";
                }
            }
            void frame();
        } catch {
            if (signal.aborted) return;
            reset();
            status.textContent = "No se pudo usar la cámara. Comprueba el permiso y que esté disponible.";
        }
    });

    manual.addEventListener("submit", (event) => {
        event.preventDefault();
        void resolve(input.value);
    });
    confirm.addEventListener("click", async () => {
        if (!context) return;
        const signal = startOperation();
        confirm.disabled = true;
        try {
            if (!packet) {
                const { portal_id, challenge_id, short_token, requires_location } = context;
                let location;
                if (requires_location) {
                    status.textContent = "Obteniendo ubicación reciente…";
                    location = { ...(await acquireLocation(JSON.parse(root.dataset.geoOptions), globalThis, { signal })), portal_id };
                }
                if (signal.aborted) return;
                packet = { event_id: crypto.randomUUID(), portal_id, challenge_id, short_token, ...(location && { location }) };
            }
            const result = await send(root.dataset.store, packet, signal);
            if (signal.aborted) return;
            if (!["recorded", "already_recorded"].includes(result.status)) {
                throw new Error("Respuesta de confirmación inesperada.");
            }
            reset();
            status.textContent = result.status === "already_recorded" ? "La asistencia ya estaba registrada." : "Asistencia confirmada.";
        } catch (error) {
            if (signal.aborted) return;
            if (error.status && error.status < 500 && error.status !== 429) {
                reset();
                status.textContent = error.message + " Escanea el código vigente para volver a intentar.";
                return;
            }
            if (packet) confirm.textContent = "Reintentar confirmación";
            status.textContent = packet ? "Sin confirmar. Reintenta el mismo envío." : error.message;
        } finally {
            if (!signal.aborted) {
                confirm.disabled = false;
                camera.disabled = false;
            }
        }
    });

    root.querySelector("[data-reset]").addEventListener("click", () => {
        reset();
        status.textContent = "Escaneo cancelado.";
    });
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) reset();
    });
    window.addEventListener("pagehide", reset);
}
