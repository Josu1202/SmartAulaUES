import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
export default defineConfig({
    plugins: [laravel({ input: ["resources/js/app.js"], refresh: true })],
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: [
                    "color-functions",
                    "global-builtin",
                    "import",
                    "if-function",
                ],
            },
        },
    },
    server: { host: "127.0.0.1" },
});
