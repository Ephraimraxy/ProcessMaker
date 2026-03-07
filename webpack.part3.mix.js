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
    .js("resources/js/processes/index.js", "public/js/processes")
    .js("resources/js/processes/edit.js", "public/js/processes")
    .js("resources/js/processes/archived.js", "public/js/processes")
    .js("resources/js/processes/newDesigner.js", "public/js/processes")
    .js("resources/js/templates/index.js", "public/js/templates")
    .js("resources/js/templates/import/index.js", "public/js/templates/import")
    .js("resources/js/templates/configure.js", "public/js/templates")
    .js("resources/js/templates/assets.js", "public/js/templates")
    .js("resources/js/processes/categories/index.js", "public/js/processes/categories")
    .js("resources/js/processes/scripts/index.js", "public/js/processes/scripts")
    .js("resources/js/processes/scripts/edit.js", "public/js/processes/scripts")
    .js("resources/js/processes/scripts/editConfig.js", "public/js/processes/scripts")
    .js("resources/js/processes/scripts/preview.js", "public/js/processes/scripts")
    .js("resources/js/processes/export/index.js", "public/js/processes/export")
    .js("resources/js/processes/environment-variables/index.js", "public/js/processes/environment-variables")
    .js("resources/js/processes/import/index.js", "public/js/processes/import")
    .js("resources/js/processes/screens/index.js", "public/js/processes/screens")
    .js("resources/js/processes/screens/edit.js", "public/js/processes/screens")
    .js("resources/js/processes/screens/preview.js", "public/js/processes/screens")
    .js("resources/js/processes/screen-templates/myTemplates.js", "public/js/processes/screen-templates")
    .js("resources/js/processes/screen-templates/publicTemplates.js", "public/js/processes/screen-templates")
    .js("resources/js/processes/signals/index.js", "public/js/processes/signals")
    .js("resources/js/processes/signals/edit.js", "public/js/processes/signals")
    .js("resources/js/processes/screen-builder/main.js", "public/js/processes/screen-builder")
    .js("resources/js/processes/screen-builder/typeForm.js", "public/js/processes/screen-builder")
    .js("resources/js/processes/screen-builder/typeDisplay.js", "public/js/processes/screen-builder");

mix.vue({ version: 2 });
