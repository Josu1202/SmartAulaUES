import "../scss/tabler.scss";
import "../css/app.css";
import Dropdown from "bootstrap/js/dist/dropdown";
import { initializeNfcDiagnostic } from "./nfc-reader";

for (const element of document.querySelectorAll('[data-bs-toggle="dropdown"]'))
    Dropdown.getOrCreateInstance(element);
const menuToggle = document.querySelector("[data-menu-toggle]");
const sidebar = document.getElementById("sidebar");
function setMenu(open) {
    document.body.classList.toggle("menu-open", open);
    menuToggle?.setAttribute("aria-expanded", String(open));
    menuToggle?.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
    if (open) sidebar?.querySelector("a")?.focus();
    else menuToggle?.focus();
}
menuToggle?.addEventListener("click", () =>
    setMenu(!document.body.classList.contains("menu-open")),
);
document
    .querySelector("[data-close-menu]")
    ?.addEventListener("click", () => setMenu(false));
document.addEventListener("keydown", (event) => {
    if (!document.body.classList.contains("menu-open")) return;
    if (event.key === "Escape") setMenu(false);
    if (event.key === "Tab" && sidebar) {
        const links = [...sidebar.querySelectorAll("a,button")];
        if (event.shiftKey && document.activeElement === links[0]) {
            event.preventDefault();
            links.at(-1)?.focus();
        } else if (!event.shiftKey && document.activeElement === links.at(-1)) {
            event.preventDefault();
            links[0]?.focus();
        }
    }
});
for (const button of document.querySelectorAll("[data-password-toggle]")) {
    button.addEventListener("click", () => {
        const field = document.getElementById(
            button.getAttribute("aria-controls"),
        );
        const reveal = field.type === "password";
        field.type = reveal ? "text" : "password";
        button.setAttribute(
            "aria-label",
            reveal ? "Ocultar contraseña" : "Mostrar contraseña",
        );
        button.setAttribute("aria-pressed", String(reveal));
    });
}
initializeNfcDiagnostic(document.querySelector("[data-nfc-diagnostic]"));

import { initializeAcademicForms, initializeAttendanceConsole, initializeManualAttendance, initializeCredentials, initializeCheckIn } from './attendance-live.js';
initializeAcademicForms();
initializeAttendanceConsole(document.querySelector('[data-attendance-console]'));
initializeManualAttendance();
initializeCredentials(document.querySelector('[data-credential-manager]'));
initializeCheckIn(document.querySelector('[data-checkin]'));
