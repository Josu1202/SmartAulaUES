import autoprefixer from "autoprefixer";
import prefixCustomProperties from "postcss-prefix-custom-properties";
export default {
    plugins: [
        prefixCustomProperties({
            prefix: "tblr-",
            ignore: [
                /^--tblr-/,
                /^--smartaulaues-/,
                /^--bs-/,
                /^--apx-/,
                /^--fc-/,
                /^--gl-/,
                /^--litepicker-/,
                /^--plyr-/,
                /^--ts-/,
                "--section-bg",
            ],
        }),
        autoprefixer(),
    ],
};
