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
    .js("resources/js/print-layout.js", "public/js")
    .js("resources/js/app-layout.js", "public/js")
    .js("resources/js/process-map-layout.js", "public/js")
    .js("resources/js/processes/modeler/index.js", "public/js/processes/modeler")
    .js("resources/js/processes/modeler/process-map.js", "public/js/processes/modeler")
    .js("resources/js/processes/modeler/initialLoad.js", "public/js/processes/modeler")
    .js("resources/js/admin/auth/passwords/change.js", "public/js/admin/auth/passwords/change.js")
    .js("resources/js/admin/settings/index.js", "public/js/admin/settings")
    .js("resources/js/admin/settings/ldaplogs.js", "public/js/admin/settings")
    .js("resources/js/admin/users/index.js", "public/js/admin/users")
    .js("resources/js/admin/users/edit.js", "public/js/admin/users/edit.js")
    .js("resources/js/admin/groups/index.js", "public/js/admin/groups")
    .js("resources/js/admin/groups/edit.js", "public/js/admin/groups/edit.js")
    .js("resources/js/admin/auth-clients/index.js", "public/js/admin/auth-clients/index.js")
    .js("resources/js/admin/profile/edit.js", "public/js/admin/profile/edit.js")
    .js("resources/js/admin/cssOverride/edit.js", "public/js/admin/cssOverride/edit.js")
    .js("resources/js/admin/script-executors/index.js", "public/js/admin/script-executors/index.js")
    .js("resources/js/admin/tenant-queues/index.js", "public/js/admin/tenant-queues/index.js");

mix.vue({ version: 2 });
