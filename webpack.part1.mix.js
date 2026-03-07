const mix = require("laravel-mix");
const path = require("path");
const fs = require("fs");
const manifestPath = path.resolve(__dirname, "public/mix-manifest.json");
let existingContent = {};

mix.webpackConfig({
    externals: ["SharedComponents", "ModelerInspector"],
    resolve: {
        extensions: [".*", ".js", ".ts", ".mjs", ".vue", ".json"],
        symlinks: false,
        alias: {
            "vue-monaco": path.resolve(__dirname, "resources/js/vue-monaco-amd.js"),
            styles: path.resolve(__dirname, "resources/sass"),
        },
    },
});

mix.options({
    legacyNodePolyfills: false,
    terser: {
        parallel: false,
    },
});

mix.before(() => {
    if (fs.existsSync(manifestPath)) {
        try {
            existingContent = JSON.parse(fs.readFileSync(manifestPath, "utf8"));
        } catch (e) {
            existingContent = {};
        }
    }
});

mix.then(() => {
    if (fs.existsSync(manifestPath)) {
        const newContent = JSON.parse(fs.readFileSync(manifestPath, "utf8"));
        const mergedContent = { ...existingContent, ...newContent };
        fs.writeFileSync(manifestPath, JSON.stringify(mergedContent, null, 4));
    }
});

mix
    .js("resources/js/timeout.js", "public/js") // Anchors the extraction
    .extract([
        "jquery",
        "bootstrap-vue",
        "popper.js",
        "bootstrap",
    ], "public/js/bootstrap-vendor.js")
    .extract([
        "@fortawesome/fontawesome-free",
        "@fortawesome/fontawesome-svg-core",
        "@fortawesome/free-brands-svg-icons",
        "@fortawesome/free-solid-svg-icons",
        "@fortawesome/vue-fontawesome",
    ], "public/js/fortawesome-vendor.js")
    .extract([
        "jointjs",
        "luxon",
        "bpmn-moddle",
        "@processmaker/modeler",
    ], "public/js/modeler-vendor.js")
    .extract([
        "vue",
        "vue-router",
        "axios",
        "lodash",
    ], "public/js/vue-vendor.js")
    .copy("resources/img/*", "public/img")
    .copy("resources/img/launchpad-images/*", "public/img/launchpad-images")
    .copy("resources/img/launchpad-images/icons/*", "public/img/launchpad-images/icons")
    .copy("resources/img/connected-account-images/*", "public/img/connected-account-images")
    .copy("resources/img/smartinbox-images/*", "public/img/smartinbox-images")
    .copy("resources/img/pagination-images/*", "public/img/pagination-images")
    .copy("resources/img/script_lang/*", "public/img/script_lang")
    .copy("node_modules/snapsvg/dist/snap.svg.js", "public/js")
    .copy("resources/js/components/CustomActions.vue", "public/js")
    .copy("resources/js/components/DetailRow.vue", "public/js")
    .copy("resources/js/components/FilterBar.vue", "public/js")
    .copy("node_modules/@processmaker/modeler/dist/img", "public/js/img")
    .copy("node_modules/bpmn-font/dist", "public/css/bpmn-symbols");

mix.vue({ version: 2 });
