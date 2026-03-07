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
    .js("resources/js/leave-warning.js", "public/js")
    .js("resources/js/requests/index.js", "public/js/requests")
    .js("resources/js/requests/mobile.js", "public/js/requests/mobile.js")
    .js("resources/js/requests/show.js", "public/js/requests")
    .js("resources/js/requests/preview.js", "public/js/requests")
    .js("resources/jscomposition/cases/casesMain/main.js", "public/js/composition/cases/casesMain/main.js")
    .js("resources/jscomposition/cases/casesDetail/edit.js", "public/js/composition/cases/casesDetail/edit.js")
    .js("resources/js/processes/translations/import.js", "public/js/processes/translations")
    .js("resources/js/processes-catalogue/index.js", "public/js/processes-catalogue/index.js")
    .js("resources/js/tasks/index.js", "public/js/tasks/index.js")
    .js("resources/js/tasks/mobile.js", "public/js/tasks/mobile.js")
    .js("resources/js/tasks/show.js", "public/js/tasks/show.js")
    .js("resources/js/tasks/router.js", "public/js/tasks/router.js")
    .js("resources/js/notifications/index.js", "public/js/notifications/index.js")
    .js("resources/js/inbox-rules/index.js", "public/js/inbox-rules")
    .js("resources/js/inbox-rules/show.js", "public/js/inbox-rules")
    .js("resources/js/admin/devlink/index.js", "public/js/admin/devlink")
    .js("resources/jscomposition/cases/casesMain/loader.js", "public/js/composition/cases/casesMain")
    .js("resources/jscomposition/cases/casesDetail/loader.js", "public/js/composition/cases/casesDetail")
    .js("resources/js/initialLoad.js", "public/js")
    .js("resources/js/tasks/loaderMain.js", "public/js/tasks")
    .js("resources/js/tasks/loaderPreview.js", "public/js/tasks")
    .js("resources/js/tasks/loaderEdit.js", "public/js/tasks")
    .js("resources/js/tasks/edit.js", "public/js/tasks/edit.js")
    .js("resources/js/tasks/preview.js", "public/js/tasks/preview.js")
    .js("resources/js/app.js", "public/js");

mix.vue({ version: 2 });
